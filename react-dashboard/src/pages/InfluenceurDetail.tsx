import React, { useEffect, useState, useContext, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import api from '../api/client';
import { AuthContext } from '../hooks/useAuth';
import type { Contact, ContactType, Influenceur, TeamMember } from '../types/influenceur';
import ContactTimeline from '../components/ContactTimeline';
import ContactForm from '../components/ContactForm';
import ContactTypeBadge, { CONTACT_TYPE_OPTIONS } from '../components/ContactTypeBadge';
import StatusBadge from '../components/StatusBadge';
import PlatformBadge from '../components/PlatformBadge';
import { getLanguageLabel, getCountryFlag } from '../lib/constants';

const STATUS_OPTIONS = [
  { value: 'prospect', label: 'Prospect' },
  { value: 'contacted', label: 'Contacté' },
  { value: 'negotiating', label: 'En négociation' },
  { value: 'active', label: 'Actif' },
  { value: 'refused', label: 'Refusé' },
  { value: 'inactive', label: 'Inactif' },
];

// ============================================================
// Email role classification
// ============================================================
type EmailRole = 'Contact général' | 'Direction' | 'Admissions' | 'Accueil' | 'Autre';

const EMAIL_ROLE_PREFIXES: { prefixes: string[]; role: EmailRole }[] = [
  { prefixes: ['contact@', 'info@', 'admin@'], role: 'Contact général' },
  { prefixes: ['principal@', 'direction@', 'directeur@'], role: 'Direction' },
  { prefixes: ['admissions@', 'inscription@'], role: 'Admissions' },
  { prefixes: ['enquiries@', 'office@'], role: 'Accueil' },
];

function classifyEmail(email: string): EmailRole {
  const lower = email.toLowerCase();
  for (const { prefixes, role } of EMAIL_ROLE_PREFIXES) {
    if (prefixes.some(prefix => lower.startsWith(prefix))) {
      return role;
    }
  }
  return 'Autre';
}

function groupEmailsByRole(rawEmails: string[]): { role: EmailRole; emails: string[] }[] {
  // 1. Clean: remove u003e prefix, trim, lowercase, deduplicate
  const seen = new Set<string>();
  const cleanEmails: string[] = [];
  for (const raw of rawEmails) {
    const email = raw.replace(/u003[ce]/gi, '').toLowerCase().trim();
    if (!email.includes('@') || seen.has(email)) continue;
    seen.add(email);
    cleanEmails.push(email);
  }

  // 2. Group by role
  const map = new Map<EmailRole, string[]>();
  for (const email of cleanEmails) {
    const role = classifyEmail(email);
    if (!map.has(role)) map.set(role, []);
    map.get(role)!.push(email);
  }
  const order: EmailRole[] = ['Contact général', 'Direction', 'Admissions', 'Accueil', 'Autre'];
  return order
    .filter(role => map.has(role))
    .map(role => ({ role, emails: map.get(role)! }));
}

// ============================================================
// Copy to clipboard helper
// ============================================================
function CopyButton({ text }: { text: string }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // fallback
    }
  };

  return (
    <button
      onClick={handleCopy}
      className="ml-1.5 px-1.5 py-0.5 text-xs bg-surface2 border border-border rounded hover:bg-white/10 text-muted hover:text-white transition-colors"
      title="Copier"
    >
      {copied ? '✓' : '📋 Copier'}
    </button>
  );
}

export default function InfluenceurDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useContext(AuthContext);
  const [influenceur, setInfluenceur] = useState<Influenceur | null>(null);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [loading, setLoading] = useState(true);
  const [team, setTeam] = useState<TeamMember[]>([]);
  const [editing, setEditing] = useState(false);
  const [formData, setFormData] = useState<Partial<Influenceur>>({});
  const [showContactForm, setShowContactForm] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [rescraping, setRescraping] = useState(false);

  const loadData = useCallback(() => {
    if (!id) return;
    setLoading(true);
    Promise.all([
      api.get<Influenceur>(`/influenceurs/${id}`),
      api.get<Contact[]>(`/influenceurs/${id}/contacts`),
      api.get<TeamMember[]>('/team').catch(() => ({ data: [] as TeamMember[] })),
    ]).then(([infRes, contactsRes, teamRes]) => {
      setInfluenceur(infRes.data);
      setFormData(infRes.data);
      setContacts(contactsRes.data);
      setTeam(teamRes.data);
    }).finally(() => setLoading(false));
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleSave = async () => {
    if (!id || !influenceur) return;
    setSaveError('');
    try {
      const { data } = await api.put<Influenceur>(`/influenceurs/${id}`, formData);
      setInfluenceur(data);
      setEditing(false);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setSaveError(e.response?.data?.message ?? 'Erreur lors de la sauvegarde.');
    }
  };

  const handleDelete = async () => {
    if (!id || !confirm('Supprimer cet influenceur ? Cette action est irréversible.')) return;
    try {
      await api.delete(`/influenceurs/${id}`);
      navigate('/influenceurs');
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setSaveError(e.response?.data?.message ?? 'Erreur lors de la suppression.');
    }
  };

  const handleRescrape = async () => {
    if (!id || rescraping) return;
    setRescraping(true);
    setSaveError('');
    try {
      const { data } = await api.post<Influenceur>(`/influenceurs/${id}/rescrape`);
      setInfluenceur(data);
      setFormData(data);
    } catch (err: unknown) {
      const e = err as { response?: { data?: { message?: string } } };
      setSaveError(e.response?.data?.message ?? 'Erreur lors du re-scraping.');
    } finally {
      setRescraping(false);
    }
  };

  const refreshContacts = async () => {
    if (!id) return;
    const { data } = await api.get<Contact[]>(`/influenceurs/${id}/contacts`);
    setContacts(data);
    setShowContactForm(false);
  };

  if (loading) return (
    <div className="flex items-center justify-center h-64">
      <div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  if (!influenceur) return (
    <div className="p-4 md:p-6 text-center text-muted">Influenceur introuvable.</div>
  );

  // Extract contact_persons from scraped_social if present
  const contactPersons = (influenceur.scraped_social as Record<string, unknown> | null)?.contact_persons as
    | { name: string; role?: string; email?: string; phone?: string }[]
    | undefined;

  return (
    <div className="p-4 md:p-6 max-w-4xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4 flex-wrap">
        <button onClick={() => navigate(-1)} className="text-muted hover:text-white text-sm transition-colors">
          ← Retour
        </button>
        <div className="flex flex-wrap gap-2">
          {editing ? (
            <>
              <button onClick={handleSave} className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
                Sauvegarder
              </button>
              <button onClick={() => { setEditing(false); setFormData(influenceur); setSaveError(''); }} className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                Annuler
              </button>
            </>
          ) : (
            <>
              <button onClick={() => setEditing(true)} className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
                Modifier
              </button>
              {(user?.role === 'admin' || user?.role === 'researcher') && (
                <button onClick={handleDelete} className="px-4 py-2 bg-red-500/10 text-red-400 hover:bg-red-500/20 text-sm rounded-lg border border-red-500/30 transition-colors">
                  Supprimer
                </button>
              )}
            </>
          )}
        </div>
      </div>

      {/* Error */}
      {saveError && (
        <div className="bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3 rounded-lg">{saveError}</div>
      )}

      {/* Fiche principale */}
      <div className="bg-surface border border-border rounded-2xl p-4 md:p-6">
        <div className="flex items-start gap-4">
          {influenceur.avatar_url ? (
            <img src={influenceur.avatar_url} alt={influenceur.name} className="w-16 h-16 rounded-full object-cover flex-shrink-0" />
          ) : (
            <div className="w-16 h-16 rounded-full bg-violet/20 flex items-center justify-center text-2xl font-bold text-violet-light flex-shrink-0">
              {influenceur.name[0]}
            </div>
          )}
          <div className="flex-1 min-w-0">
            {editing ? (
              <input
                value={formData.name ?? ''}
                onChange={e => setFormData(p => ({ ...p, name: e.target.value }))}
                className="text-2xl font-title font-bold bg-surface2 border border-border rounded-lg px-3 py-1 text-white w-full focus:outline-none focus:border-violet"
              />
            ) : (
              <h1 className="text-2xl font-title font-bold text-white">{influenceur.name}</h1>
            )}
            <div className="flex items-center gap-2 mt-2 flex-wrap">
              {editing ? (
                <select
                  value={formData.contact_type ?? influenceur.contact_type}
                  onChange={e => setFormData(p => ({ ...p, contact_type: e.target.value as ContactType }))}
                  className="bg-surface2 border border-border rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-violet"
                >
                  {CONTACT_TYPE_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              ) : (
                <ContactTypeBadge type={influenceur.contact_type} size="md" />
              )}
              {influenceur.handle && <span className="font-mono text-sm text-cyan">@{influenceur.handle}</span>}
              {influenceur.primary_platform && <PlatformBadge platform={influenceur.primary_platform} />}
              {editing ? (
                <select
                  value={formData.status ?? influenceur.status}
                  onChange={e => setFormData(p => ({ ...p, status: e.target.value as Influenceur['status'] }))}
                  className="bg-surface2 border border-border rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-violet"
                >
                  {STATUS_OPTIONS.map(o => <option key={o.value} value={o.value}>{o.label}</option>)}
                </select>
              ) : (
                <StatusBadge status={influenceur.status} />
              )}
              {influenceur.pending_reminder && (
                <span className="px-2 py-0.5 bg-amber/20 text-amber text-xs rounded-full font-mono">RELANCER</span>
              )}
            </div>
            {influenceur.followers && (
              <p className="text-muted text-sm mt-1">{influenceur.followers.toLocaleString('fr-FR')} followers</p>
            )}
          </div>
        </div>

        {/* Infos détaillées */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6 pt-6 border-t border-border text-sm">
          {[
            { label: 'Email', value: influenceur.email, field: 'email', href: influenceur.email ? `mailto:${influenceur.email}` : null },
            { label: 'Téléphone', value: influenceur.phone, field: 'phone', href: influenceur.phone ? `tel:${influenceur.phone}` : null },
            { label: 'Profil URL', value: influenceur.profile_url, field: 'profile_url', href: influenceur.profile_url, external: true },
            { label: 'Pays', value: influenceur.country ? `${getCountryFlag(influenceur.country)} ${influenceur.country}` : null, field: 'country', href: null, rawEdit: influenceur.country },
            { label: 'Langue', value: influenceur.language ? getLanguageLabel(influenceur.language) : null, field: 'language', href: null, rawEdit: influenceur.language },
            { label: 'Niche', value: influenceur.niche, field: 'niche', href: null },
          ].map(({ label, value, field, href, external, rawEdit }: { label: string; value: string | null | undefined; field: string; href?: string | null; external?: boolean; rawEdit?: string | null }) => (
            <div key={field}>
              <p className="text-muted text-xs mb-1">{label}</p>
              {editing ? (
                <input
                  value={(formData as Record<string, unknown>)[field] as string ?? ''}
                  onChange={e => setFormData(p => ({ ...p, [field]: e.target.value }))}
                  className="bg-surface2 border border-border rounded px-2 py-1 text-white text-sm w-full focus:outline-none focus:border-violet"
                />
              ) : href && (rawEdit ?? value) ? (
                <a href={href} {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})} className="text-cyan hover:underline break-all">{value}</a>
              ) : (
                <p className="text-white">{value ?? '—'}</p>
              )}
            </div>
          ))}

          {/* Assignation */}
          <div>
            <p className="text-muted text-xs mb-1">Assigné à</p>
            {editing ? (
              <select
                value={formData.assigned_to ?? ''}
                onChange={e => setFormData(p => ({ ...p, assigned_to: e.target.value ? Number(e.target.value) : null }))}
                className="bg-surface2 border border-border rounded px-2 py-1 text-sm text-white w-full focus:outline-none focus:border-violet"
              >
                <option value="">Non assigné</option>
                {team.map(m => <option key={m.id} value={m.id}>{m.name}</option>)}
              </select>
            ) : (
              <p className="text-white">{influenceur.assigned_to_user?.name ?? '—'}</p>
            )}
          </div>
        </div>

        {/* Rappels */}
        <div className="mt-4 pt-4 border-t border-border">
          <p className="text-muted text-xs mb-2">Rappel automatique</p>
          <div className="flex items-center gap-4 flex-wrap">
            <label className="flex items-center gap-2 text-sm text-white cursor-pointer">
              <input
                type="checkbox"
                checked={editing ? (formData.reminder_active ?? influenceur.reminder_active) : influenceur.reminder_active}
                onChange={e => editing && setFormData(p => ({ ...p, reminder_active: e.target.checked }))}
                disabled={!editing}
                className="accent-violet"
              />
              Actif
            </label>
            <span className="text-sm text-muted">après</span>
            {editing ? (
              <input
                type="number"
                min={1} max={365}
                value={formData.reminder_days ?? influenceur.reminder_days}
                onChange={e => setFormData(p => ({ ...p, reminder_days: Number(e.target.value) }))}
                className="w-16 bg-surface2 border border-border rounded px-2 py-1 text-sm text-white focus:outline-none focus:border-violet"
              />
            ) : (
              <span className="text-white font-mono text-sm">{influenceur.reminder_days}</span>
            )}
            <span className="text-sm text-muted">jours</span>
          </div>
        </div>

        {/* Notes */}
        <div className="mt-4 pt-4 border-t border-border">
          <p className="text-muted text-xs mb-2">Notes</p>
          {editing ? (
            <textarea
              value={formData.notes ?? ''}
              onChange={e => setFormData(p => ({ ...p, notes: e.target.value }))}
              rows={3}
              className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-violet resize-none"
            />
          ) : (
            <p className="text-white text-sm whitespace-pre-wrap">{influenceur.notes ?? '—'}</p>
          )}
        </div>
      </div>

      {/* Scraped contact data */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="font-title font-semibold text-white mb-4 flex items-center gap-2">
          {'📋'} Informations de contact scrapées
          {influenceur.scraper_status && (
            <span className={`ml-2 px-2 py-0.5 text-xs rounded-full font-mono ${
              influenceur.scraper_status === 'completed' ? 'bg-green-500/20 text-green-400' :
              influenceur.scraper_status === 'failed' ? 'bg-red-500/20 text-red-400' :
              influenceur.scraper_status === 'pending' ? 'bg-amber/20 text-amber' :
              'bg-white/10 text-muted'
            }`}>
              {influenceur.scraper_status}
            </span>
          )}
          {!influenceur.scraper_status && (
            <span className="ml-2 text-xs text-muted">Non scrapé</span>
          )}
          <button
            onClick={handleRescrape}
            disabled={rescraping}
            className="ml-auto px-3 py-1.5 bg-violet/20 hover:bg-violet/30 text-violet-light text-sm rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
          >
            {rescraping ? (
              <>
                <span className="w-3.5 h-3.5 border-2 border-violet-light border-t-transparent rounded-full animate-spin inline-block" />
                Scraping...
              </>
            ) : (
              <>{'🔄'} Re-scraper</>
            )}
          </button>
        </h3>
        {influenceur.scraped_at && (
          <p className="text-muted text-xs mb-4">
            Scrapé le {new Date(influenceur.scraped_at).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
          </p>
        )}

        <div className="space-y-4">
          {/* Emails — grouped by role */}
          <div>
            <p className="text-muted text-xs mb-1.5 uppercase tracking-wider">Emails</p>
            {influenceur.scraped_emails && influenceur.scraped_emails.length > 0 ? (
              <div className="space-y-3">
                {groupEmailsByRole(influenceur.scraped_emails).map(({ role, emails }) => (
                  <div key={role}>
                    <p className="text-muted text-xs font-medium mb-1">{role}</p>
                    <div className="flex flex-wrap gap-2">
                      {emails.map((em) => (
                        <span key={em} className="flex items-center gap-1 text-sm">
                          {em === influenceur.email && <span title="Email principal">{'✅'}</span>}
                          <a href={`mailto:${em}`} className="text-cyan hover:underline">{em}</a>
                          <CopyButton text={em} />
                        </span>
                      ))}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <p className="text-muted text-sm">Aucun email trouvé</p>
            )}
          </div>

          {/* Phones */}
          <div>
            <p className="text-muted text-xs mb-1.5 uppercase tracking-wider">Téléphones</p>
            {influenceur.scraped_phones && influenceur.scraped_phones.length > 0 ? (
              <div className="flex flex-wrap gap-3">
                {[...new Set(influenceur.scraped_phones)].map((ph) => (
                  <span key={ph} className="text-sm flex items-center gap-1.5">
                    {ph === influenceur.phone && <span title="Téléphone principal">{'✅'}</span>}
                    <a href={`tel:${ph}`} className="text-cyan hover:underline">{ph}</a>
                    {ph.startsWith('+') && (
                      <a href={`https://wa.me/${ph.replace(/[^0-9]/g, '')}`} target="_blank" rel="noopener noreferrer" className="text-green-400 hover:text-green-300 text-xs" title="WhatsApp">
                        {'💬'}
                      </a>
                    )}
                    <CopyButton text={ph} />
                  </span>
                ))}
              </div>
            ) : (
              <p className="text-muted text-sm">Aucun téléphone trouvé</p>
            )}
          </div>

          {/* Contact persons */}
          {contactPersons && contactPersons.length > 0 && (
            <div>
              <p className="text-muted text-xs mb-1.5 uppercase tracking-wider">Personnes de contact</p>
              <div className="space-y-2">
                {contactPersons.map((person, i) => (
                  <div key={i} className="flex items-center gap-3 px-3 py-2 bg-surface2 border border-border rounded-lg text-sm flex-wrap">
                    <span className="text-white font-medium">{person.name}</span>
                    {person.role && <span className="text-muted text-xs">({person.role})</span>}
                    {person.email && (
                      <a href={`mailto:${person.email}`} className="text-cyan hover:underline text-xs">{person.email}</a>
                    )}
                    {person.phone && (
                      <a href={`tel:${person.phone}`} className="text-cyan hover:underline text-xs">{person.phone}</a>
                    )}
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Social links */}
          <div>
            <p className="text-muted text-xs mb-1.5 uppercase tracking-wider">Réseaux sociaux</p>
            {influenceur.scraped_social && Object.keys(influenceur.scraped_social).filter(k => k !== 'contact_persons').length > 0 ? (
              <div className="flex flex-wrap gap-3">
                {Object.entries(influenceur.scraped_social)
                  .filter(([platform]) => platform !== 'contact_persons')
                  .map(([platform, url]) => {
                  const icons: Record<string, string> = {
                    facebook: '🔵', linkedin: '🔗', twitter: '𝕏', x: '𝕏',
                    instagram: '📸', whatsapp: '💬', telegram: '✈️',
                    youtube: '🎬', tiktok: '🎵',
                  };
                  return (
                    <a
                      key={platform}
                      href={url as string}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center gap-1.5 px-2.5 py-1 bg-surface2 border border-border rounded-lg text-cyan hover:text-white hover:border-cyan/50 text-sm transition-colors"
                    >
                      <span>{icons[platform.toLowerCase()] ?? '🌐'}</span>
                      <span className="capitalize">{platform}</span>
                    </a>
                  );
                })}
              </div>
            ) : (
              <p className="text-muted text-sm">Aucun réseau social trouvé</p>
            )}
          </div>

          {/* Addresses */}
          {influenceur.scraped_addresses && influenceur.scraped_addresses.length > 0 && (
            <div>
              <p className="text-muted text-xs mb-1.5 uppercase tracking-wider">Adresses</p>
              <div className="space-y-1">
                {influenceur.scraped_addresses.map((addr, i) => (
                  <p key={i} className="text-white text-sm">{addr}</p>
                ))}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Timeline contacts */}
      <div className="bg-surface border border-border rounded-2xl p-4 md:p-6">
        <div className="flex items-center justify-between mb-4 flex-wrap gap-2">
          <h3 className="font-title font-semibold text-white">Timeline des contacts</h3>
          <button
            onClick={() => setShowContactForm(!showContactForm)}
            className="px-3 py-1.5 bg-violet/20 hover:bg-violet/30 text-violet-light text-sm rounded-lg transition-colors"
          >
            + Ajouter un contact
          </button>
        </div>

        {showContactForm && (
          <div className="mb-6 pb-6 border-b border-border">
            <ContactForm influenceurId={influenceur.id} onSaved={refreshContacts} onCancel={() => setShowContactForm(false)} />
          </div>
        )}

        <ContactTimeline contacts={contacts} />
      </div>
    </div>
  );
}
