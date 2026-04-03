import React, { useState, useEffect, useCallback } from 'react';
import { useAuth } from '../../hooks/useAuth';
import { toast as _toast } from '../../components/Toast';

// ── Types ────────────────────────────────────────────────────────────────────

type CommissionType = 'percentage' | 'fixed_per_lead' | 'fixed_per_sale' | 'recurring' | 'hybrid' | 'cpc' | 'unknown';
type ProgramStatus = 'active' | 'pending_approval' | 'applied' | 'inactive' | 'not_applied';
type ProgramCategory = 'insurance' | 'finance' | 'travel' | 'vpn' | 'housing' | 'employment' | 'education' | 'shopping' | 'telecom' | 'community' | 'legal' | 'other';
type EarningType = 'commission' | 'payout' | 'adjustment';

interface AffiliateProgram {
  id: number;
  name: string;
  slug: string;
  category: ProgramCategory;
  description: string | null;
  website_url: string;
  affiliate_dashboard_url: string | null;
  affiliate_signup_url: string | null;
  my_affiliate_link: string | null;
  commission_type: CommissionType;
  commission_info: string | null;
  cookie_duration_days: number | null;
  payout_threshold: string | null;
  payout_method: string | null;
  payout_frequency: string | null;
  current_balance: string;
  total_earned: string;
  last_payout_amount: string | null;
  last_payout_at: string | null;
  status: ProgramStatus;
  network: string | null;
  logo_url: string | null;
  notes: string | null;
  sort_order: number;
}

interface AffiliateEarning {
  id: number;
  affiliate_program_id: number;
  amount: string;
  currency: string;
  type: EarningType;
  description: string | null;
  reference: string | null;
  earned_at: string;
}

interface GlobalStats {
  total: number;
  active: number;
  not_applied: number;
  needs_payout: number;
  total_balance: number;
  total_earned: number;
}

// ── Constantes ────────────────────────────────────────────────────────────────

const CATEGORY_LABELS: Record<ProgramCategory, string> = {
  insurance: 'Assurance',
  finance: 'Finance',
  travel: 'Voyage',
  vpn: 'VPN',
  housing: 'Logement',
  employment: 'Emploi',
  education: 'Formation',
  shopping: 'Shopping',
  telecom: 'Télécom',
  community: 'Communauté',
  legal: 'Juridique',
  other: 'Autre',
};

const CATEGORY_COLORS: Record<ProgramCategory, string> = {
  insurance: 'bg-blue-500/20 text-blue-300',
  finance: 'bg-green-500/20 text-green-300',
  travel: 'bg-orange-500/20 text-orange-300',
  vpn: 'bg-purple-500/20 text-purple-300',
  housing: 'bg-yellow-500/20 text-yellow-300',
  employment: 'bg-cyan-500/20 text-cyan-300',
  education: 'bg-pink-500/20 text-pink-300',
  shopping: 'bg-red-500/20 text-red-300',
  telecom: 'bg-indigo-500/20 text-indigo-300',
  community: 'bg-teal-500/20 text-teal-300',
  legal: 'bg-gray-500/20 text-gray-300',
  other: 'bg-zinc-500/20 text-zinc-400',
};

const CATEGORY_ICONS: Record<ProgramCategory, string> = {
  insurance: '🛡️',
  finance: '💳',
  travel: '✈️',
  vpn: '🔒',
  housing: '🏠',
  employment: '💼',
  education: '🎓',
  shopping: '🛒',
  telecom: '📱',
  community: '🌍',
  legal: '⚖️',
  other: '📦',
};

const STATUS_LABELS: Record<ProgramStatus, string> = {
  active: 'Actif',
  pending_approval: 'En attente',
  applied: 'Candidature envoyée',
  inactive: 'Inactif',
  not_applied: 'Non inscrit',
};

const STATUS_COLORS: Record<ProgramStatus, string> = {
  active: 'bg-green-500/20 text-green-300 border-green-500/30',
  pending_approval: 'bg-yellow-500/20 text-yellow-300 border-yellow-500/30',
  applied: 'bg-blue-500/20 text-blue-300 border-blue-500/30',
  inactive: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
  not_applied: 'bg-zinc-500/20 text-zinc-500 border-zinc-500/30',
};

// ── API ───────────────────────────────────────────────────────────────────────

const API = import.meta.env.VITE_API_URL || '';

async function apiFetch<T>(path: string, options?: RequestInit, token?: string): Promise<T> {
  const res = await fetch(`${API}/api${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...(options?.headers || {}),
    },
  });
  if (!res.ok) {
    const err = await res.json().catch(() => ({}));
    throw new Error((err as any).message || `Erreur ${res.status}`);
  }
  return res.json();
}

// ── Composant principal ───────────────────────────────────────────────────────

export default function AffiliateDashboard() {
  const { user } = useAuth();
  const showToast = (msg: string, type: 'success' | 'error') => _toast(type, msg);
  const token = (user as any)?.token;

  const [programs, setPrograms] = useState<AffiliateProgram[]>([]);
  const [stats, setStats] = useState<GlobalStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [filterCategory, setFilterCategory] = useState<ProgramCategory | 'all'>('all');
  const [filterStatus, setFilterStatus] = useState<ProgramStatus | 'all'>('all');
  const [search, setSearch] = useState('');
  const [view, setView] = useState<'cards' | 'table'>('cards');

  // Modales
  const [editProgram, setEditProgram] = useState<AffiliateProgram | null>(null);
  const [addOpen, setAddOpen] = useState(false);
  const [earningProgram, setEarningProgram] = useState<AffiliateProgram | null>(null);
  const [detailProgram, setDetailProgram] = useState<AffiliateProgram | null>(null);
  const [earnings, setEarnings] = useState<AffiliateEarning[]>([]);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = new URLSearchParams();
      if (filterCategory !== 'all') params.set('category', filterCategory);
      if (filterStatus !== 'all') params.set('status', filterStatus);
      if (search) params.set('search', search);

      const data = await apiFetch<{ data: AffiliateProgram[]; stats: GlobalStats }>(
        `/affiliates?${params}`, undefined, token
      );
      setPrograms(data.data);
      setStats(data.stats);
    } catch (e: any) {
      showToast(e.message || 'Erreur chargement', 'error');
    } finally {
      setLoading(false);
    }
  }, [filterCategory, filterStatus, search, token]);

  useEffect(() => { load(); }, [load]);

  async function loadEarnings(program: AffiliateProgram) {
    setDetailProgram(program);
    try {
      const data = await apiFetch<{ data: AffiliateEarning[] }>(`/affiliates/${program.id}/earnings`, undefined, token);
      setEarnings(data.data);
    } catch {
      setEarnings([]);
    }
  }

  async function handleUpdateField(program: AffiliateProgram, field: string, value: any) {
    try {
      await apiFetch(`/affiliates/${program.id}`, {
        method: 'PUT',
        body: JSON.stringify({ [field]: value }),
      }, token);
      showToast('Mis à jour', 'success');
      load();
    } catch (e: any) {
      showToast(e.message, 'error');
    }
  }

  const copyLink = (link: string) => {
    navigator.clipboard.writeText(link);
    showToast('Lien copié !', 'success');
  };

  // Grouper par catégorie pour le mode cards
  const grouped = programs.reduce<Record<string, AffiliateProgram[]>>((acc, p) => {
    if (!acc[p.category]) acc[p.category] = [];
    acc[p.category].push(p);
    return acc;
  }, {});

  const needsPayoutList = programs.filter(p =>
    p.payout_threshold && parseFloat(p.current_balance) >= parseFloat(p.payout_threshold)
  );

  return (
    <div className="p-6 max-w-[1400px] mx-auto">
      {/* ── Header ── */}
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-white">Programmes Affiliés</h1>
          <p className="text-sm text-gray-400 mt-1">
            Cartographie en temps réel de tes revenus d'affiliation
          </p>
        </div>
        <button
          onClick={() => setAddOpen(true)}
          className="px-4 py-2 bg-violet text-white rounded-lg text-sm font-medium hover:bg-violet/80 transition-colors"
        >
          + Ajouter un programme
        </button>
      </div>

      {/* ── KPI Cards ── */}
      {stats && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
          <KpiCard label="Programmes" value={stats.total} />
          <KpiCard label="Actifs" value={stats.active} color="text-green-400" />
          <KpiCard label="Non inscrits" value={stats.not_applied} color="text-gray-400" />
          <KpiCard label="À encaisser" value={stats.needs_payout} color="text-amber-400" />
          <KpiCard label="Solde total" value={`${Number(stats.total_balance).toFixed(2)} €`} color="text-violet" />
          <KpiCard label="Total gagné" value={`${Number(stats.total_earned).toFixed(2)} €`} color="text-green-300" />
        </div>
      )}

      {/* ── Alerte paiements à encaisser ── */}
      {needsPayoutList.length > 0 && (
        <div className="bg-amber-500/10 border border-amber-500/30 rounded-lg p-3 mb-4 flex items-center gap-3">
          <span className="text-amber-400 text-lg">⚠️</span>
          <div className="flex-1 text-sm">
            <span className="font-medium text-amber-300">
              {needsPayoutList.length} programme{needsPayoutList.length > 1 ? 's' : ''} à encaisser :
            </span>{' '}
            <span className="text-amber-400">
              {needsPayoutList.map(p => `${p.name} (${Number(p.current_balance).toFixed(2)} €)`).join(', ')}
            </span>
          </div>
        </div>
      )}

      {/* ── Filtres ── */}
      <div className="flex flex-wrap gap-2 mb-4">
        <input
          type="text"
          placeholder="Rechercher..."
          value={search}
          onChange={e => setSearch(e.target.value)}
          className="px-3 py-1.5 bg-surface2 border border-border rounded-lg text-sm text-white placeholder-gray-500 focus:outline-none focus:border-violet w-40"
        />
        <select
          value={filterCategory}
          onChange={e => setFilterCategory(e.target.value as any)}
          className="px-3 py-1.5 bg-surface2 border border-border rounded-lg text-sm text-gray-300 focus:outline-none"
        >
          <option value="all">Toutes catégories</option>
          {(Object.keys(CATEGORY_LABELS) as ProgramCategory[]).map(c => (
            <option key={c} value={c}>{CATEGORY_ICONS[c]} {CATEGORY_LABELS[c]}</option>
          ))}
        </select>
        <select
          value={filterStatus}
          onChange={e => setFilterStatus(e.target.value as any)}
          className="px-3 py-1.5 bg-surface2 border border-border rounded-lg text-sm text-gray-300 focus:outline-none"
        >
          <option value="all">Tous statuts</option>
          {(Object.keys(STATUS_LABELS) as ProgramStatus[]).map(s => (
            <option key={s} value={s}>{STATUS_LABELS[s]}</option>
          ))}
        </select>
        <div className="ml-auto flex gap-1">
          <button onClick={() => setView('cards')} className={`px-3 py-1.5 rounded-lg text-xs ${view === 'cards' ? 'bg-violet text-white' : 'bg-surface2 text-gray-400'}`}>
            Cartes
          </button>
          <button onClick={() => setView('table')} className={`px-3 py-1.5 rounded-lg text-xs ${view === 'table' ? 'bg-violet text-white' : 'bg-surface2 text-gray-400'}`}>
            Tableau
          </button>
        </div>
      </div>

      {/* ── Contenu ── */}
      {loading ? (
        <div className="flex items-center justify-center h-40">
          <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
        </div>
      ) : view === 'cards' ? (
        // Mode cartes — groupé par catégorie
        <div className="space-y-8">
          {(Object.keys(grouped) as ProgramCategory[]).map(cat => (
            <div key={cat}>
              <div className="flex items-center gap-2 mb-3">
                <span className="text-lg">{CATEGORY_ICONS[cat]}</span>
                <h2 className="text-sm font-semibold text-gray-300 uppercase tracking-wide">
                  {CATEGORY_LABELS[cat]}
                </h2>
                <span className="text-xs text-gray-600 ml-1">({grouped[cat].length})</span>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                {grouped[cat].map(program => (
                  <ProgramCard
                    key={program.id}
                    program={program}
                    onEdit={() => setEditProgram(program)}
                    onAddEarning={() => setEarningProgram(program)}
                    onDetail={() => loadEarnings(program)}
                    onUpdateStatus={v => handleUpdateField(program, 'status', v)}
                    onCopyLink={copyLink}
                  />
                ))}
              </div>
            </div>
          ))}
          {programs.length === 0 && (
            <div className="text-center text-gray-500 py-16">
              Aucun programme trouvé
            </div>
          )}
        </div>
      ) : (
        // Mode tableau
        <ProgramTable
          programs={programs}
          onEdit={setEditProgram}
          onAddEarning={setEarningProgram}
          onDetail={loadEarnings}
          onUpdateStatus={(p, v) => handleUpdateField(p, 'status', v)}
          onCopyLink={copyLink}
        />
      )}

      {/* ── Modales ── */}
      {(addOpen || editProgram) && (
        <ProgramModal
          program={editProgram}
          onClose={() => { setAddOpen(false); setEditProgram(null); }}
          onSaved={() => { setAddOpen(false); setEditProgram(null); load(); }}
          token={token}
          showToast={showToast}
        />
      )}

      {earningProgram && (
        <EarningModal
          program={earningProgram}
          onClose={() => setEarningProgram(null)}
          onSaved={() => { setEarningProgram(null); load(); }}
          token={token}
          showToast={showToast}
        />
      )}

      {detailProgram && (
        <DetailModal
          program={detailProgram}
          earnings={earnings}
          onClose={() => { setDetailProgram(null); setEarnings([]); }}
          onAddEarning={() => { setEarningProgram(detailProgram); setDetailProgram(null); }}
        />
      )}
    </div>
  );
}

// ── KPI Card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, color }: { label: string; value: string | number; color?: string }) {
  return (
    <div className="bg-surface border border-border rounded-lg p-3 text-center">
      <p className={`text-xl font-bold ${color || 'text-white'}`}>{value}</p>
      <p className="text-xs text-gray-500 mt-0.5">{label}</p>
    </div>
  );
}

// ── Program Card ──────────────────────────────────────────────────────────────

function ProgramCard({
  program,
  onEdit,
  onAddEarning,
  onDetail,
  onUpdateStatus,
  onCopyLink,
}: {
  program: AffiliateProgram;
  onEdit: () => void;
  onAddEarning: () => void;
  onDetail: () => void;
  onUpdateStatus: (v: ProgramStatus) => void;
  onCopyLink: (link: string) => void;
}) {
  const balance = parseFloat(program.current_balance);
  const threshold = program.payout_threshold ? parseFloat(program.payout_threshold) : null;
  const needsPayout = threshold !== null && balance >= threshold;

  return (
    <div className={`bg-surface border rounded-lg p-4 flex flex-col gap-3 hover:border-violet/40 transition-colors ${needsPayout ? 'border-amber-500/50' : 'border-border'}`}>
      {/* Header */}
      <div className="flex items-start justify-between gap-2">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-2 mb-1">
            <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${CATEGORY_COLORS[program.category]}`}>
              {CATEGORY_ICONS[program.category]} {CATEGORY_LABELS[program.category]}
            </span>
          </div>
          <h3 className="font-semibold text-white text-sm leading-tight">{program.name}</h3>
          {program.description && (
            <p className="text-xs text-gray-500 mt-0.5 line-clamp-1">{program.description}</p>
          )}
        </div>
        {/* Statut */}
        <select
          value={program.status}
          onChange={e => onUpdateStatus(e.target.value as ProgramStatus)}
          className={`text-[10px] font-medium px-1.5 py-0.5 rounded border ${STATUS_COLORS[program.status]} bg-transparent cursor-pointer focus:outline-none`}
        >
          {(Object.keys(STATUS_LABELS) as ProgramStatus[]).map(s => (
            <option key={s} value={s} className="bg-surface text-gray-200">{STATUS_LABELS[s]}</option>
          ))}
        </select>
      </div>

      {/* Commission */}
      {program.commission_info && (
        <div className="flex items-center gap-1.5">
          <span className="text-xs text-gray-500">Commission :</span>
          <span className="text-xs font-medium text-green-400">{program.commission_info}</span>
        </div>
      )}

      {/* Solde */}
      <div className="flex items-center justify-between bg-surface2 rounded-lg px-3 py-2">
        <div>
          <p className="text-[10px] text-gray-500 uppercase tracking-wide">Solde</p>
          <p className={`text-lg font-bold ${needsPayout ? 'text-amber-400' : 'text-white'}`}>
            {balance.toFixed(2)} €
            {needsPayout && <span className="ml-1 text-xs text-amber-400">⚠️ Encaisser</span>}
          </p>
        </div>
        <div className="text-right">
          <p className="text-[10px] text-gray-500 uppercase tracking-wide">Total gagné</p>
          <p className="text-sm font-medium text-green-400">{parseFloat(program.total_earned).toFixed(2)} €</p>
        </div>
      </div>

      {/* Seuil de paiement */}
      {threshold !== null && (
        <div>
          <div className="flex justify-between text-[10px] text-gray-500 mb-1">
            <span>Seuil paiement</span>
            <span>{balance.toFixed(2)} / {threshold.toFixed(2)} €</span>
          </div>
          <div className="w-full h-1.5 bg-surface2 rounded-full overflow-hidden">
            <div
              className={`h-full rounded-full transition-all ${needsPayout ? 'bg-amber-400' : 'bg-violet'}`}
              style={{ width: `${Math.min(100, (balance / threshold) * 100)}%` }}
            />
          </div>
        </div>
      )}

      {/* Mon lien affilié */}
      {program.my_affiliate_link ? (
        <div className="flex items-center gap-2 bg-surface2 rounded px-2 py-1.5">
          <span className="text-[10px] text-gray-500 shrink-0">Mon lien :</span>
          <span className="text-[11px] text-violet flex-1 truncate">{program.my_affiliate_link}</span>
          <button
            onClick={() => onCopyLink(program.my_affiliate_link!)}
            className="text-gray-500 hover:text-white transition-colors shrink-0"
            title="Copier le lien"
          >
            📋
          </button>
        </div>
      ) : (
        <p className="text-[11px] text-gray-600 italic">Aucun lien affilié enregistré</p>
      )}

      {/* Actions */}
      <div className="flex items-center gap-2 pt-1 border-t border-border">
        {/* Aller sur le site du programme */}
        {program.affiliate_dashboard_url ? (
          <a
            href={program.affiliate_dashboard_url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex-1 text-center text-xs px-2 py-1.5 bg-violet/20 text-violet-light rounded hover:bg-violet/30 transition-colors"
          >
            🔗 Mon dashboard
          </a>
        ) : program.affiliate_signup_url ? (
          <a
            href={program.affiliate_signup_url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex-1 text-center text-xs px-2 py-1.5 bg-blue-500/20 text-blue-300 rounded hover:bg-blue-500/30 transition-colors"
          >
            ✍️ S'inscrire
          </a>
        ) : (
          <a
            href={program.website_url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex-1 text-center text-xs px-2 py-1.5 bg-surface2 text-gray-400 rounded hover:bg-surface3 transition-colors"
          >
            🌐 Site web
          </a>
        )}
        <button
          onClick={onAddEarning}
          className="text-xs px-2 py-1.5 bg-green-500/20 text-green-300 rounded hover:bg-green-500/30 transition-colors"
          title="Ajouter une entrée"
        >
          +€
        </button>
        <button
          onClick={onDetail}
          className="text-xs px-2 py-1.5 bg-surface2 text-gray-400 rounded hover:bg-surface3 transition-colors"
          title="Voir l'historique"
        >
          📊
        </button>
        <button
          onClick={onEdit}
          className="text-xs px-2 py-1.5 bg-surface2 text-gray-400 rounded hover:bg-surface3 transition-colors"
          title="Modifier"
        >
          ✏️
        </button>
      </div>
    </div>
  );
}

// ── Program Table ─────────────────────────────────────────────────────────────

function ProgramTable({
  programs,
  onEdit,
  onAddEarning,
  onDetail,
  onUpdateStatus,
  onCopyLink,
}: {
  programs: AffiliateProgram[];
  onEdit: (p: AffiliateProgram) => void;
  onAddEarning: (p: AffiliateProgram) => void;
  onDetail: (p: AffiliateProgram) => void;
  onUpdateStatus: (p: AffiliateProgram, v: ProgramStatus) => void;
  onCopyLink: (link: string) => void;
}) {
  return (
    <div className="overflow-x-auto rounded-lg border border-border">
      <table className="w-full text-sm">
        <thead>
          <tr className="border-b border-border bg-surface2">
            <th className="text-left px-4 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Programme</th>
            <th className="text-left px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Catégorie</th>
            <th className="text-left px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Commission</th>
            <th className="text-left px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Statut</th>
            <th className="text-right px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Solde</th>
            <th className="text-right px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Total gagné</th>
            <th className="text-center px-3 py-2.5 text-xs font-semibold text-gray-400 uppercase tracking-wide">Actions</th>
          </tr>
        </thead>
        <tbody>
          {programs.map(p => {
            const balance = parseFloat(p.current_balance);
            const threshold = p.payout_threshold ? parseFloat(p.payout_threshold) : null;
            const needsPayout = threshold !== null && balance >= threshold;
            return (
              <tr key={p.id} className={`border-b border-border hover:bg-surface2/50 transition-colors ${needsPayout ? 'bg-amber-500/5' : ''}`}>
                <td className="px-4 py-3">
                  <div className="font-medium text-white">{p.name}</div>
                  {p.my_affiliate_link && (
                    <div className="flex items-center gap-1 mt-0.5">
                      <span className="text-[10px] text-violet truncate max-w-[180px]">{p.my_affiliate_link}</span>
                      <button onClick={() => onCopyLink(p.my_affiliate_link!)} className="text-gray-600 hover:text-gray-300 text-[10px]">📋</button>
                    </div>
                  )}
                </td>
                <td className="px-3 py-3">
                  <span className={`text-[10px] font-medium px-1.5 py-0.5 rounded ${CATEGORY_COLORS[p.category]}`}>
                    {CATEGORY_ICONS[p.category]} {CATEGORY_LABELS[p.category]}
                  </span>
                </td>
                <td className="px-3 py-3 text-xs text-green-400">{p.commission_info || '—'}</td>
                <td className="px-3 py-3">
                  <select
                    value={p.status}
                    onChange={e => onUpdateStatus(p, e.target.value as ProgramStatus)}
                    className={`text-[10px] font-medium px-1.5 py-0.5 rounded border ${STATUS_COLORS[p.status]} bg-transparent cursor-pointer focus:outline-none`}
                  >
                    {(Object.keys(STATUS_LABELS) as ProgramStatus[]).map(s => (
                      <option key={s} value={s} className="bg-surface text-gray-200">{STATUS_LABELS[s]}</option>
                    ))}
                  </select>
                </td>
                <td className="px-3 py-3 text-right">
                  <span className={`font-semibold ${needsPayout ? 'text-amber-400' : 'text-white'}`}>
                    {balance.toFixed(2)} €
                    {needsPayout && ' ⚠️'}
                  </span>
                </td>
                <td className="px-3 py-3 text-right text-green-400">{parseFloat(p.total_earned).toFixed(2)} €</td>
                <td className="px-3 py-3">
                  <div className="flex items-center justify-center gap-1">
                    {(p.affiliate_dashboard_url || p.affiliate_signup_url) && (
                      <a
                        href={p.affiliate_dashboard_url || p.affiliate_signup_url || ''}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-[10px] px-1.5 py-1 bg-violet/20 text-violet-light rounded hover:bg-violet/30"
                      >
                        🔗
                      </a>
                    )}
                    <button onClick={() => onAddEarning(p)} className="text-[10px] px-1.5 py-1 bg-green-500/20 text-green-300 rounded hover:bg-green-500/30">+€</button>
                    <button onClick={() => onDetail(p)} className="text-[10px] px-1.5 py-1 bg-surface2 text-gray-400 rounded hover:bg-surface3">📊</button>
                    <button onClick={() => onEdit(p)} className="text-[10px] px-1.5 py-1 bg-surface2 text-gray-400 rounded hover:bg-surface3">✏️</button>
                  </div>
                </td>
              </tr>
            );
          })}
          {programs.length === 0 && (
            <tr>
              <td colSpan={7} className="text-center py-12 text-gray-500">
                Aucun programme trouvé
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

// ── Program Modal (Add / Edit) ────────────────────────────────────────────────

function ProgramModal({
  program,
  onClose,
  onSaved,
  token,
  showToast,
}: {
  program: AffiliateProgram | null;
  onClose: () => void;
  onSaved: () => void;
  token: string;
  showToast: (msg: string, type: 'success' | 'error') => void;
}) {
  const isEdit = !!program;
  const [form, setForm] = useState({
    name: program?.name ?? '',
    category: program?.category ?? 'other',
    description: program?.description ?? '',
    website_url: program?.website_url ?? '',
    affiliate_dashboard_url: program?.affiliate_dashboard_url ?? '',
    affiliate_signup_url: program?.affiliate_signup_url ?? '',
    my_affiliate_link: program?.my_affiliate_link ?? '',
    commission_type: program?.commission_type ?? 'unknown',
    commission_info: program?.commission_info ?? '',
    payout_threshold: program?.payout_threshold ?? '',
    payout_method: program?.payout_method ?? '',
    payout_frequency: program?.payout_frequency ?? '',
    status: program?.status ?? 'not_applied',
    network: program?.network ?? '',
    notes: program?.notes ?? '',
  });
  const [saving, setSaving] = useState(false);

  const set = (k: string, v: any) => setForm(f => ({ ...f, [k]: v }));

  async function save() {
    setSaving(true);
    try {
      const payload: any = { ...form };
      // Convertir les champs vides en null
      ['description', 'affiliate_dashboard_url', 'affiliate_signup_url', 'my_affiliate_link',
       'commission_info', 'payout_threshold', 'payout_method', 'payout_frequency', 'network', 'notes'
      ].forEach(k => { if (payload[k] === '') payload[k] = null; });

      if (isEdit) {
        await apiFetch(`/affiliates/${program!.id}`, { method: 'PUT', body: JSON.stringify(payload) }, token);
      } else {
        await apiFetch('/affiliates', { method: 'POST', body: JSON.stringify(payload) }, token);
      }
      showToast(isEdit ? 'Programme mis à jour' : 'Programme créé', 'success');
      onSaved();
    } catch (e: any) {
      showToast(e.message, 'error');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div className="bg-surface border border-border rounded-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div className="p-4 border-b border-border flex items-center justify-between">
          <h2 className="font-semibold text-white">{isEdit ? 'Modifier le programme' : 'Nouveau programme'}</h2>
          <button onClick={onClose} className="text-gray-500 hover:text-white">✕</button>
        </div>
        <div className="overflow-y-auto p-4 space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="col-span-2">
              <label className="block text-xs text-gray-400 mb-1">Nom du programme *</label>
              <input value={form.name} onChange={e => set('name', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Catégorie *</label>
              <select value={form.category} onChange={e => set('category', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-gray-200 focus:outline-none">
                {(Object.keys(CATEGORY_LABELS) as ProgramCategory[]).map(c => (
                  <option key={c} value={c}>{CATEGORY_ICONS[c]} {CATEGORY_LABELS[c]}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Statut *</label>
              <select value={form.status} onChange={e => set('status', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-gray-200 focus:outline-none">
                {(Object.keys(STATUS_LABELS) as ProgramStatus[]).map(s => (
                  <option key={s} value={s}>{STATUS_LABELS[s]}</option>
                ))}
              </select>
            </div>
            <div className="col-span-2">
              <label className="block text-xs text-gray-400 mb-1">Site web *</label>
              <input value={form.website_url} onChange={e => set('website_url', e.target.value)}
                placeholder="https://..."
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Dashboard affilié</label>
              <input value={form.affiliate_dashboard_url} onChange={e => set('affiliate_dashboard_url', e.target.value)}
                placeholder="https://..."
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">URL inscription</label>
              <input value={form.affiliate_signup_url} onChange={e => set('affiliate_signup_url', e.target.value)}
                placeholder="https://..."
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div className="col-span-2">
              <label className="block text-xs text-gray-400 mb-1">Mon lien affilié personnel</label>
              <input value={form.my_affiliate_link} onChange={e => set('my_affiliate_link', e.target.value)}
                placeholder="https://mon-lien-tracké..."
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Type de commission</label>
              <select value={form.commission_type} onChange={e => set('commission_type', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-gray-200 focus:outline-none">
                <option value="percentage">Pourcentage</option>
                <option value="fixed_per_lead">Fixe par lead</option>
                <option value="fixed_per_sale">Fixe par vente</option>
                <option value="recurring">Récurrent</option>
                <option value="hybrid">Hybride</option>
                <option value="cpc">CPC</option>
                <option value="unknown">Inconnu</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Détail commission</label>
              <input value={form.commission_info} onChange={e => set('commission_info', e.target.value)}
                placeholder="Ex: 40% + 30% récurrent"
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Seuil de paiement (€)</label>
              <input type="number" value={form.payout_threshold} onChange={e => set('payout_threshold', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Réseau</label>
              <input value={form.network} onChange={e => set('network', e.target.value)}
                placeholder="Ex: CJ Affiliate, Impact, Direct..."
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
            <div className="col-span-2">
              <label className="block text-xs text-gray-400 mb-1">Notes</label>
              <textarea value={form.notes} onChange={e => set('notes', e.target.value)}
                rows={3}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet resize-none" />
            </div>
          </div>
        </div>
        <div className="p-4 border-t border-border flex justify-end gap-2">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-400 hover:text-white transition-colors">
            Annuler
          </button>
          <button onClick={save} disabled={saving}
            className="px-4 py-2 bg-violet text-white rounded-lg text-sm font-medium hover:bg-violet/80 disabled:opacity-50 transition-colors">
            {saving ? 'Enregistrement...' : 'Enregistrer'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Earning Modal ─────────────────────────────────────────────────────────────

function EarningModal({
  program,
  onClose,
  onSaved,
  token,
  showToast,
}: {
  program: AffiliateProgram;
  onClose: () => void;
  onSaved: () => void;
  token: string;
  showToast: (msg: string, type: 'success' | 'error') => void;
}) {
  const today = new Date().toISOString().split('T')[0];
  const [form, setForm] = useState({
    amount: '',
    type: 'commission' as EarningType,
    description: '',
    reference: '',
    earned_at: today,
  });
  const [saving, setSaving] = useState(false);
  const set = (k: string, v: any) => setForm(f => ({ ...f, [k]: v }));

  async function save() {
    if (!form.amount || parseFloat(form.amount) <= 0) {
      showToast('Montant invalide', 'error');
      return;
    }
    setSaving(true);
    try {
      await apiFetch(`/affiliates/${program.id}/earnings`, {
        method: 'POST',
        body: JSON.stringify({ ...form, amount: parseFloat(form.amount) }),
      }, token);
      showToast('Entrée ajoutée', 'success');
      onSaved();
    } catch (e: any) {
      showToast(e.message, 'error');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div className="bg-surface border border-border rounded-xl w-full max-w-md">
        <div className="p-4 border-b border-border flex items-center justify-between">
          <h2 className="font-semibold text-white">Ajouter une entrée — {program.name}</h2>
          <button onClick={onClose} className="text-gray-500 hover:text-white">✕</button>
        </div>
        <div className="p-4 space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-xs text-gray-400 mb-1">Type</label>
              <select value={form.type} onChange={e => set('type', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-gray-200 focus:outline-none">
                <option value="commission">Commission</option>
                <option value="payout">Paiement reçu</option>
                <option value="adjustment">Ajustement</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-gray-400 mb-1">Montant (€) *</label>
              <input type="number" step="0.01" value={form.amount} onChange={e => set('amount', e.target.value)}
                className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
            </div>
          </div>
          <div>
            <label className="block text-xs text-gray-400 mb-1">Date *</label>
            <input type="date" value={form.earned_at} onChange={e => set('earned_at', e.target.value)}
              className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
          </div>
          <div>
            <label className="block text-xs text-gray-400 mb-1">Description</label>
            <input value={form.description} onChange={e => set('description', e.target.value)}
              placeholder="Ex: Commission janvier 2026"
              className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
          </div>
          <div>
            <label className="block text-xs text-gray-400 mb-1">Référence externe</label>
            <input value={form.reference} onChange={e => set('reference', e.target.value)}
              placeholder="ID transaction..."
              className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet" />
          </div>

          {/* Info contextuelle selon le type */}
          {form.type === 'payout' && (
            <p className="text-xs text-amber-400 bg-amber-500/10 rounded p-2">
              ⚠️ Un "Paiement reçu" réduit le solde courant du montant saisi.
            </p>
          )}
        </div>
        <div className="p-4 border-t border-border flex justify-end gap-2">
          <button onClick={onClose} className="px-4 py-2 text-sm text-gray-400 hover:text-white">Annuler</button>
          <button onClick={save} disabled={saving}
            className="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-500 disabled:opacity-50">
            {saving ? '...' : 'Ajouter'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Detail Modal (historique) ────────────────────────────────────────────────

function DetailModal({
  program,
  earnings,
  onClose,
  onAddEarning,
}: {
  program: AffiliateProgram;
  earnings: AffiliateEarning[];
  onClose: () => void;
  onAddEarning: () => void;
}) {
  const TYPE_LABELS: Record<EarningType, string> = {
    commission: '💰 Commission',
    payout: '💸 Paiement reçu',
    adjustment: '⚙️ Ajustement',
  };

  const commissions = earnings.filter(e => e.type === 'commission');
  const payouts = earnings.filter(e => e.type === 'payout');
  const totalCommissions = commissions.reduce((s, e) => s + parseFloat(e.amount), 0);
  const totalPayouts = payouts.reduce((s, e) => s + parseFloat(e.amount), 0);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4">
      <div className="bg-surface border border-border rounded-xl w-full max-w-xl max-h-[80vh] flex flex-col">
        <div className="p-4 border-b border-border flex items-center justify-between">
          <div>
            <h2 className="font-semibold text-white">{program.name}</h2>
            <p className="text-xs text-gray-500 mt-0.5">Historique des gains</p>
          </div>
          <div className="flex gap-2">
            <button onClick={onAddEarning}
              className="px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-500">
              + Entrée
            </button>
            <button onClick={onClose} className="text-gray-500 hover:text-white">✕</button>
          </div>
        </div>

        {/* Résumé */}
        <div className="grid grid-cols-3 gap-3 p-4 border-b border-border">
          <div className="text-center">
            <p className="text-[10px] text-gray-500 uppercase">Solde actuel</p>
            <p className="text-lg font-bold text-white">{parseFloat(program.current_balance).toFixed(2)} €</p>
          </div>
          <div className="text-center">
            <p className="text-[10px] text-gray-500 uppercase">Commissions</p>
            <p className="text-lg font-bold text-green-400">{totalCommissions.toFixed(2)} €</p>
          </div>
          <div className="text-center">
            <p className="text-[10px] text-gray-500 uppercase">Encaissé</p>
            <p className="text-lg font-bold text-violet">{totalPayouts.toFixed(2)} €</p>
          </div>
        </div>

        {/* Liste */}
        <div className="overflow-y-auto flex-1">
          {earnings.length === 0 ? (
            <div className="text-center py-12 text-gray-500">
              <p>Aucune entrée enregistrée</p>
              <button onClick={onAddEarning} className="mt-2 text-xs text-violet hover:underline">
                Ajouter la première entrée
              </button>
            </div>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-surface2 border-b border-border">
                  <th className="text-left px-4 py-2 text-xs text-gray-400">Date</th>
                  <th className="text-left px-3 py-2 text-xs text-gray-400">Type</th>
                  <th className="text-left px-3 py-2 text-xs text-gray-400">Description</th>
                  <th className="text-right px-4 py-2 text-xs text-gray-400">Montant</th>
                </tr>
              </thead>
              <tbody>
                {earnings.map(e => (
                  <tr key={e.id} className="border-b border-border/50 hover:bg-surface2/30">
                    <td className="px-4 py-2.5 text-xs text-gray-400">{e.earned_at}</td>
                    <td className="px-3 py-2.5 text-xs">{TYPE_LABELS[e.type]}</td>
                    <td className="px-3 py-2.5 text-xs text-gray-400">{e.description || '—'}</td>
                    <td className={`px-4 py-2.5 text-right font-semibold text-xs ${e.type === 'payout' ? 'text-violet' : 'text-green-400'}`}>
                      {e.type === 'payout' ? '-' : '+'}{parseFloat(e.amount).toFixed(2)} {e.currency}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        {program.affiliate_dashboard_url && (
          <div className="p-3 border-t border-border">
            <a href={program.affiliate_dashboard_url} target="_blank" rel="noopener noreferrer"
              className="block text-center text-xs py-2 bg-violet/20 text-violet-light rounded-lg hover:bg-violet/30 transition-colors">
              🔗 Ouvrir le dashboard affilié pour vérifier les chiffres
            </a>
          </div>
        )}
      </div>
    </div>
  );
}
