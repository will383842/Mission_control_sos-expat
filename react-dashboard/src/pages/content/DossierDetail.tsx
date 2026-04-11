import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  fetchPressDossier,
  updatePressDossier,
  deletePressDossier,
  addDossierItem,
  removeDossierItem,
  reorderDossierItems,
  exportDossierPdf,
} from '../../api/contentApi';
import type { PressDossier, PressDossierItem, ContentStatus } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';
import { errMsg } from './helpers';
import { useDirtyGuard } from '../../hooks/useDirtyGuard';

// ── Constants ───────────────────────────────────────────────
const STATUS_COLORS: Record<ContentStatus, string> = {
  draft: 'bg-muted/20 text-muted',
  generating: 'bg-amber/20 text-amber animate-pulse',
  review: 'bg-blue-500/20 text-blue-400',
  scheduled: 'bg-violet/20 text-violet-light',
  published: 'bg-success/20 text-success',
  archived: 'bg-muted/20 text-muted line-through',
};

const STATUS_LABELS: Record<ContentStatus, string> = {
  draft: 'Brouillon',
  generating: 'Generation...',
  review: 'A relire',
  scheduled: 'Planifie',
  published: 'Publie',
  archived: 'Archive',
};

const inputClass = 'bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors';

function formatDate(d: string): string {
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

function downloadBlob(data: unknown, filename: string) {
  const blob = data instanceof Blob ? data : new Blob([data as BlobPart]);
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Component ───────────────────────────────────────────────
export default function DossierDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { markDirty, markClean } = useDirtyGuard();

  const [dossier, setDossier] = useState<PressDossier | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [actionLoading, setActionLoading] = useState<string | null>(null);
  const [confirmAction, setConfirmAction] = useState<{ title: string; message: string; variant?: 'danger' | 'warning' | 'default'; action: () => void } | null>(null);

  // Editing
  const [editName, setEditName] = useState('');
  const [editDescription, setEditDescription] = useState('');

  // Add item modal
  const [showAddItem, setShowAddItem] = useState(false);
  const [addItemType, setAddItemType] = useState<'article' | 'press_release'>('article');
  const [addItemId, setAddItemId] = useState('');

  const loadDossier = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      const res = await fetchPressDossier(Number(id));
      const data = res.data as unknown as PressDossier;
      setDossier(data);
      setEditName(data.name ?? '');
      setEditDescription(data.description ?? '');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Erreur de chargement');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { loadDossier(); }, [loadDossier]);

  const handleSave = async () => {
    if (!dossier) return;
    setActionLoading('save');
    try {
      await updatePressDossier(dossier.id, {
        name: editName,
        description: editDescription,
      });
      toast('success', 'Dossier sauvegarde.');
      markClean();
      loadDossier();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleAddItem = async () => {
    if (!dossier || !addItemId.trim()) return;
    setActionLoading('add-item');
    try {
      const itemableType = addItemType === 'article' ? 'App\\Models\\GeneratedArticle' : 'App\\Models\\PressRelease';
      await addDossierItem(dossier.id, {
        itemable_type: itemableType,
        itemable_id: Number(addItemId),
      });
      toast('success', 'Element ajoute.');
      setShowAddItem(false);
      setAddItemId('');
      loadDossier();
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleRemoveItem = async (item: PressDossierItem) => {
    if (!dossier) return;
    try {
      await removeDossierItem(dossier.id, item.id);
      toast('success', 'Element supprime.');
      loadDossier();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const moveItem = async (index: number, direction: 'up' | 'down') => {
    if (!dossier || !dossier.items) return;
    const target = direction === 'up' ? index - 1 : index + 1;
    if (target < 0 || target >= dossier.items.length) return;

    const items = [...dossier.items];
    [items[index], items[target]] = [items[target], items[index]];

    try {
      await reorderDossierItems(dossier.id, items.map(i => i.id));
      toast('success', 'Ordre mis a jour.');
      loadDossier();
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const handleExportPdf = async () => {
    if (!dossier) return;
    setActionLoading('pdf');
    try {
      const res = await exportDossierPdf(dossier.id);
      downloadBlob(res.data, `${dossier.slug || 'dossier'}.pdf`);
      toast('success', 'PDF exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = () => {
    if (!dossier) return;
    setConfirmAction({
      title: 'Supprimer le dossier',
      message: `Voulez-vous vraiment supprimer "${dossier.name}" ?`,
      variant: 'danger',
      action: async () => {
        try {
          await deletePressDossier(dossier.id);
          toast('success', 'Dossier supprime.');
          navigate('/content/press');
        } catch (err) {
          toast('error', errMsg(err));
        }
      },
    });
  };

  const getItemTitle = (item: PressDossierItem): string => {
    if (item.itemable && 'title' in item.itemable) return (item.itemable as { title: string }).title;
    if (item.itemable && 'name' in item.itemable) return (item.itemable as { name: string }).name;
    return `${item.itemable_type.split('\\').pop()} #${item.itemable_id}`;
  };

  if (loading) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="space-y-4">
          {[1, 2, 3].map(i => <div key={i} className="animate-pulse bg-surface2 rounded-xl h-20" />)}
        </div>
      </div>
    );
  }

  if (error || !dossier) {
    return (
      <div className="p-4 md:p-6">
        <div className="bg-danger/10 border border-danger/30 rounded-xl p-6 text-center">
          <p className="text-danger">{error ?? 'Dossier introuvable'}</p>
          <button onClick={() => navigate('/content/press')} className="mt-4 text-sm text-muted hover:text-white transition-colors">
            Retour a la presse
          </button>
        </div>
      </div>
    );
  }

  const items = dossier.items ?? [];

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Breadcrumb */}
      <div className="flex items-center justify-between">
        <div>
          <button onClick={() => navigate('/content/press')} className="text-xs text-muted hover:text-white transition-colors inline-flex items-center gap-1 mb-2">
            <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M15 19l-7-7 7-7" /></svg>
            Retour a la presse
          </button>
          <h2 className="font-title text-2xl font-bold text-white">{dossier.name || 'Sans nom'}</h2>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={handleExportPdf}
            disabled={actionLoading === 'pdf'}
            className="px-3 py-1.5 bg-surface2 text-white text-xs rounded-lg hover:bg-surface2/80 transition-colors disabled:opacity-50"
          >
            {actionLoading === 'pdf' ? 'Export...' : 'Exporter PDF'}
          </button>
          <button onClick={handleDelete} className="text-xs text-danger hover:text-red-300 transition-colors">
            Supprimer
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-10 gap-6">
        {/* Main content */}
        <div className="lg:col-span-7 space-y-5">
          {/* Name & Description */}
          <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
            <div>
              <label className="block text-xs text-muted uppercase tracking-wide mb-1">Nom</label>
              <input
                type="text"
                value={editName}
                onChange={e => { setEditName(e.target.value); markDirty(); }}
                className={inputClass + ' w-full'}
              />
            </div>
            <div>
              <label className="block text-xs text-muted uppercase tracking-wide mb-1">Description</label>
              <textarea
                value={editDescription}
                onChange={e => { setEditDescription(e.target.value); markDirty(); }}
                rows={3}
                className={inputClass + ' w-full resize-y'}
                placeholder="Description du dossier..."
              />
            </div>
            <button
              type="button"
              onClick={handleSave}
              disabled={actionLoading === 'save'}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors disabled:opacity-50"
            >
              {actionLoading === 'save' ? 'Sauvegarde...' : 'Sauvegarder'}
            </button>
          </div>

          {/* Items list */}
          <div className="bg-surface border border-border rounded-xl p-5">
            <div className="flex items-center justify-between mb-3">
              <h3 className="text-sm font-medium text-white">Elements ({items.length})</h3>
              <button type="button" onClick={() => setShowAddItem(true)} className="text-xs text-violet hover:text-violet-light transition-colors">
                + Ajouter un element
              </button>
            </div>
            {items.length === 0 ? (
              <p className="text-sm text-muted py-4 text-center">Aucun element dans ce dossier.</p>
            ) : (
              <div className="space-y-2">
                {items.map((item, index) => (
                  <div key={item.id} className="bg-bg border border-border rounded-lg p-3 flex items-center gap-3">
                    <span className="text-xs text-muted font-mono w-6 text-center">#{index + 1}</span>
                    <div className="flex-1 min-w-0">
                      <p className="text-sm text-white truncate">{getItemTitle(item)}</p>
                      <p className="text-[10px] text-muted">{item.itemable_type.split('\\').pop()}</p>
                    </div>
                    <button
                      type="button"
                      onClick={() => moveItem(index, 'up')}
                      disabled={index === 0}
                      className="text-muted hover:text-white disabled:opacity-30 transition-colors"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M5 15l7-7 7 7" /></svg>
                    </button>
                    <button
                      type="button"
                      onClick={() => moveItem(index, 'down')}
                      disabled={index === items.length - 1}
                      className="text-muted hover:text-white disabled:opacity-30 transition-colors"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <button
                      type="button"
                      onClick={() => handleRemoveItem(item)}
                      className="text-danger hover:text-red-300 transition-colors"
                    >
                      <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}><path strokeLinecap="round" strokeLinejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Sidebar */}
        <div className="lg:col-span-3 space-y-4">
          <div className="bg-surface border border-border rounded-xl p-5 space-y-3">
            <h4 className="font-title font-semibold text-white">Informations</h4>
            <div className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-muted">Statut</span>
                <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[dossier.status]}`}>
                  {STATUS_LABELS[dossier.status]}
                </span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Langue</span>
                <span className="text-white uppercase">{dossier.language}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Elements</span>
                <span className="text-white">{items.length}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted">Cree le</span>
                <span className="text-white text-xs">{formatDate(dossier.created_at)}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Add item modal */}
      <Modal
        open={showAddItem}
        onClose={() => setShowAddItem(false)}
        title="Ajouter un element"
        size="md"
        footer={
          <>
            <Button variant="ghost" onClick={() => setShowAddItem(false)}>Annuler</Button>
            <Button
              variant="primary"
              onClick={handleAddItem}
              disabled={!addItemId.trim()}
              loading={actionLoading === 'add-item'}
            >
              Ajouter
            </Button>
          </>
        }
      >
        <div className="space-y-4">
          <div>
            <label className="block text-xs text-muted uppercase tracking-wide mb-1">Type</label>
            <select value={addItemType} onChange={e => setAddItemType(e.target.value as 'article' | 'press_release')} className={inputClass + ' w-full'}>
              <option value="article">Article</option>
              <option value="press_release">Communique de presse</option>
            </select>
          </div>
          <div>
            <label className="block text-xs text-muted uppercase tracking-wide mb-1">ID de l'element</label>
            <input
              type="number"
              value={addItemId}
              onChange={e => setAddItemId(e.target.value)}
              placeholder="Ex: 42"
              className={inputClass + ' w-full'}
            />
          </div>
        </div>
      </Modal>

      <ConfirmModal
        open={!!confirmAction}
        title={confirmAction?.title ?? ''}
        message={confirmAction?.message ?? ''}
        variant={confirmAction?.variant}
        onConfirm={() => { confirmAction?.action(); setConfirmAction(null); }}
        onCancel={() => setConfirmAction(null)}
      />
    </div>
  );
}
