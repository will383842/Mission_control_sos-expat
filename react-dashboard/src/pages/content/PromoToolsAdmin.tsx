import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchPromoTemplates,
  createPromoTemplate,
  updatePromoTemplate,
  deletePromoTemplate,
  type PromoTemplate,
  type PromoTemplateType,
  type PromoTemplateRole,
  type PromoTemplateFormData,
} from '../../api/promoTemplatesApi';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';

const TYPE_LABELS: Record<PromoTemplateType, string> = {
  utm_campaign: 'Campagne UTM',
  promo_text: 'Texte promo',
};

const TYPE_COLORS: Record<PromoTemplateType, string> = {
  utm_campaign: 'bg-blue-500/20 text-blue-300',
  promo_text: 'bg-purple-500/20 text-purple-300',
};

const ROLE_LABELS: Record<PromoTemplateRole, string> = {
  all: 'Tous',
  influencer: 'Influenceur',
  blogger: 'Blogueur',
};

const ROLE_COLORS: Record<PromoTemplateRole, string> = {
  all: 'bg-surface2 text-muted',
  influencer: 'bg-pink-500/20 text-pink-300',
  blogger: 'bg-violet/20 text-violet',
};

const LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

const EMPTY_FORM: PromoTemplateFormData = {
  name: '',
  type: 'utm_campaign',
  role: 'all',
  content: '',
  language: 'fr',
  is_active: true,
  sort_order: 0,
};

export default function PromoToolsAdmin() {
  const [templates, setTemplates] = useState<PromoTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [filterType, setFilterType] = useState<PromoTemplateType | ''>('');
  const [filterRole, setFilterRole] = useState<PromoTemplateRole | ''>('');

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<PromoTemplate | null>(null);
  const [form, setForm] = useState<PromoTemplateFormData>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);

  const [deleteTarget, setDeleteTarget] = useState<PromoTemplate | null>(null);
  const [deleting, setDeleting] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params: { type?: PromoTemplateType; role?: PromoTemplateRole } = {};
      if (filterType) params.type = filterType;
      if (filterRole) params.role = filterRole;
      const data = await fetchPromoTemplates(params);
      setTemplates(data.data);
    } catch {
      toast.error('Erreur lors du chargement');
    } finally {
      setLoading(false);
    }
  }, [filterType, filterRole]);

  useEffect(() => { load(); }, [load]);

  const openCreate = () => {
    setEditing(null);
    setForm(EMPTY_FORM);
    setShowModal(true);
  };

  const openEdit = (t: PromoTemplate) => {
    setEditing(t);
    setForm({
      name: t.name,
      type: t.type,
      role: t.role,
      content: t.content,
      language: t.language,
      is_active: t.is_active,
      sort_order: t.sort_order,
    });
    setShowModal(true);
  };

  const handleSave = async () => {
    if (!form.name.trim() || !form.content.trim()) {
      toast.error('Nom et contenu requis');
      return;
    }
    setSaving(true);
    try {
      if (editing) {
        await updatePromoTemplate(editing.id, form);
        toast.success('Template mis à jour');
      } else {
        await createPromoTemplate(form);
        toast.success('Template créé');
      }
      setShowModal(false);
      load();
    } catch {
      toast.error('Erreur lors de la sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const handleToggleActive = async (t: PromoTemplate) => {
    try {
      await updatePromoTemplate(t.id, { is_active: !t.is_active });
      setTemplates(prev => prev.map(x => x.id === t.id ? { ...x, is_active: !x.is_active } : x));
    } catch {
      toast.error('Erreur lors de la mise à jour');
    }
  };

  const handleDelete = async () => {
    if (!deleteTarget) return;
    setDeleting(true);
    try {
      await deletePromoTemplate(deleteTarget.id);
      toast.success('Template supprimé');
      setDeleteTarget(null);
      load();
    } catch {
      toast.error('Erreur lors de la suppression');
    } finally {
      setDeleting(false);
    }
  };

  const utmCount = templates.filter(t => t.type === 'utm_campaign').length;
  const textCount = templates.filter(t => t.type === 'promo_text').length;
  const activeCount = templates.filter(t => t.is_active).length;

  return (
    <div className="p-6 space-y-6">
      {/* Header */}
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-text">Outils Promo</h1>
          <p className="text-muted text-sm mt-1">
            Templates UTM et textes promotionnels pour influenceurs &amp; blogueurs
          </p>
        </div>
        <button
          onClick={openCreate}
          className="bg-violet hover:bg-violet/90 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shrink-0"
        >
          + Nouveau template
        </button>
      </div>

      {/* Stats */}
      <div className="grid grid-cols-3 gap-4">
        <div className="bg-surface1 rounded-xl p-4 border border-border">
          <p className="text-muted text-xs mb-1">Campagnes UTM</p>
          <p className="text-2xl font-bold text-blue-400">{utmCount}</p>
        </div>
        <div className="bg-surface1 rounded-xl p-4 border border-border">
          <p className="text-muted text-xs mb-1">Textes promo</p>
          <p className="text-2xl font-bold text-purple-400">{textCount}</p>
        </div>
        <div className="bg-surface1 rounded-xl p-4 border border-border">
          <p className="text-muted text-xs mb-1">Actifs</p>
          <p className="text-2xl font-bold text-green-400">{activeCount}</p>
        </div>
      </div>

      {/* Filters */}
      <div className="flex gap-3 flex-wrap">
        <select
          value={filterType}
          onChange={e => setFilterType(e.target.value as PromoTemplateType | '')}
          className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2"
        >
          <option value="">Tous les types</option>
          <option value="utm_campaign">Campagnes UTM</option>
          <option value="promo_text">Textes promo</option>
        </select>
        <select
          value={filterRole}
          onChange={e => setFilterRole(e.target.value as PromoTemplateRole | '')}
          className="bg-surface1 border border-border text-text text-sm rounded-lg px-3 py-2"
        >
          <option value="">Tous les rôles</option>
          <option value="all">Tous</option>
          <option value="influencer">Influenceur</option>
          <option value="blogger">Blogueur</option>
        </select>
      </div>

      {/* Table */}
      {loading ? (
        <div className="text-center py-12 text-muted">Chargement...</div>
      ) : templates.length === 0 ? (
        <div className="text-center py-12">
          <p className="text-muted text-lg mb-2">Aucun template</p>
          <p className="text-muted/60 text-sm">Créez votre premier template UTM ou texte promo</p>
          <button onClick={openCreate} className="mt-4 bg-violet text-white px-4 py-2 rounded-lg text-sm">
            Créer un template
          </button>
        </div>
      ) : (
        <div className="bg-surface1 rounded-xl border border-border overflow-hidden">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-muted text-xs uppercase">
                <th className="text-left px-4 py-3">Nom</th>
                <th className="text-left px-4 py-3">Type</th>
                <th className="text-left px-4 py-3">Rôle</th>
                <th className="text-left px-4 py-3">Langue</th>
                <th className="text-left px-4 py-3 max-w-xs">Contenu</th>
                <th className="text-center px-4 py-3">Actif</th>
                <th className="text-right px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-border">
              {templates.map(t => (
                <tr key={t.id} className="hover:bg-surface2/50 transition-colors">
                  <td className="px-4 py-3 font-medium text-text">{t.name}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${TYPE_COLORS[t.type]}`}>
                      {TYPE_LABELS[t.type]}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs font-medium ${ROLE_COLORS[t.role]}`}>
                      {ROLE_LABELS[t.role]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted uppercase text-xs">{t.language}</td>
                  <td className="px-4 py-3 text-muted max-w-xs">
                    <span className="truncate block max-w-[200px]" title={t.content}>{t.content}</span>
                  </td>
                  <td className="px-4 py-3 text-center">
                    <button
                      onClick={() => handleToggleActive(t)}
                      className={`w-9 h-5 rounded-full transition-colors relative ${t.is_active ? 'bg-green-500' : 'bg-surface2'}`}
                    >
                      <span className={`absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform ${t.is_active ? 'translate-x-4' : 'translate-x-0.5'}`} />
                    </button>
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex justify-end gap-2">
                      <button
                        onClick={() => openEdit(t)}
                        className="text-muted hover:text-text transition-colors text-xs px-2 py-1 rounded hover:bg-surface2"
                      >
                        Éditer
                      </button>
                      <button
                        onClick={() => setDeleteTarget(t)}
                        className="text-red-400 hover:text-red-300 transition-colors text-xs px-2 py-1 rounded hover:bg-red-500/10"
                      >
                        Supprimer
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Create/Edit Modal */}
      <Modal
        open={showModal}
        onClose={() => setShowModal(false)}
        title={editing ? 'Modifier le template' : 'Nouveau template'}
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowModal(false)}>Annuler</Button>
            <Button variant="primary" onClick={handleSave} loading={saving}>
              {editing ? 'Mettre à jour' : 'Créer'}
            </Button>
          </>
        }
      >
        <div className="space-y-3">
              <div>
                <label className="block text-xs text-muted mb-1">Nom *</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  placeholder="ex: Lancement produit"
                  className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-muted mb-1">Type *</label>
                  <select
                    value={form.type}
                    onChange={e => setForm(f => ({ ...f, type: e.target.value as PromoTemplateType }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet"
                  >
                    <option value="utm_campaign">Campagne UTM</option>
                    <option value="promo_text">Texte promo</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1">Rôle *</label>
                  <select
                    value={form.role}
                    onChange={e => setForm(f => ({ ...f, role: e.target.value as PromoTemplateRole }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet"
                  >
                    <option value="all">Tous</option>
                    <option value="influencer">Influenceur</option>
                    <option value="blogger">Blogueur</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs text-muted mb-1">Langue</label>
                  <select
                    value={form.language}
                    onChange={e => setForm(f => ({ ...f, language: e.target.value }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet"
                  >
                    {LANGUAGES.map(l => <option key={l} value={l}>{l.toUpperCase()}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1">Ordre</label>
                  <input
                    type="number"
                    value={form.sort_order}
                    onChange={e => setForm(f => ({ ...f, sort_order: parseInt(e.target.value) || 0 }))}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet"
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs text-muted mb-1">
                  Contenu * {form.type === 'utm_campaign' ? '(valeur utm_campaign)' : '(texte à copier)'}
                </label>
                <textarea
                  value={form.content}
                  onChange={e => setForm(f => ({ ...f, content: e.target.value }))}
                  placeholder={form.type === 'utm_campaign' ? 'ex: lancement_produit_2024' : 'ex: Rejoignez SOS Expat...'}
                  rows={form.type === 'promo_text' ? 4 : 2}
                  className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-text text-sm focus:outline-none focus:border-violet resize-none"
                />
              </div>

              <div className="flex items-center gap-2">
                <input
                  type="checkbox"
                  id="is_active"
                  checked={form.is_active}
                  onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))}
                  className="w-4 h-4 accent-violet"
                />
                <label htmlFor="is_active" className="text-sm text-text">Actif (visible dans les outils)</label>
              </div>
        </div>
      </Modal>

      {/* Delete Confirm */}
      <ConfirmModal
        open={!!deleteTarget}
        title="Supprimer le template"
        message={deleteTarget ? `Supprimer "${deleteTarget.name}" ? Cette action est irréversible.` : ''}
        variant="danger"
        confirmLabel="Supprimer"
        loading={deleting}
        onConfirm={handleDelete}
        onCancel={() => setDeleteTarget(null)}
      />
    </div>
  );
}
