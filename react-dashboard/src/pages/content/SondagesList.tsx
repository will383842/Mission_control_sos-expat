import React, { useEffect, useState, useCallback } from 'react';
import {
  fetchSondages,
  createSondage,
  updateSondage,
  deleteSondage,
  syncSondageToBlog,
  type Sondage,
  type SondageStatus,
  type QuestionType,
  type SondageFormData,
} from '../../api/sondagesApi';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { Modal } from '../../ui/Modal';
import { Button } from '../../ui/Button';

const STATUS_COLORS: Record<SondageStatus, string> = {
  draft: 'bg-yellow-500/20 text-yellow-300',
  active: 'bg-green-500/20 text-green-300',
  closed: 'bg-surface2 text-muted',
};

const STATUS_LABELS: Record<SondageStatus, string> = {
  draft: 'Brouillon',
  active: 'Actif',
  closed: 'Clos',
};

const QUESTION_TYPE_LABELS: Record<QuestionType, string> = {
  single: 'Choix unique',
  multiple: 'Choix multiple',
  open: 'Réponse libre',
  scale: 'Échelle 1–10',
};

const LANGUAGES = ['fr', 'en', 'es', 'de', 'pt', 'ru', 'zh', 'hi', 'ar'];

function formatDate(d: string | null): string {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' });
}

interface LocalQuestion {
  _key: number;
  text: string;
  type: QuestionType;
  options: string[];
}

function emptyQuestion(): LocalQuestion {
  return { _key: Date.now() + Math.random(), text: '', type: 'single', options: ['', ''] };
}

function emptyForm(): SondageFormData & { _questions: LocalQuestion[] } {
  return {
    title: '',
    description: null,
    status: 'draft',
    language: 'fr',
    closes_at: null,
    questions: [],
    _questions: [emptyQuestion()],
  };
}

export default function SondagesList() {
  const [sondages, setSondages] = useState<Sondage[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);

  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm());
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState<number | null>(null);
  const [confirmDelete, setConfirmDelete] = useState<{ id: number; title: string } | null>(null);

  // ── Load ─────────────────────────────────────────────────────

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await fetchSondages({ page });
      const data = res.data;
      setSondages(data.data);
      setTotal(data.total);
      setLastPage(data.last_page);
    } catch {
      toast('error', 'Erreur lors du chargement des sondages.');
    } finally {
      setLoading(false);
    }
  }, [page]);

  useEffect(() => { load(); }, [load]);

  // ── Form helpers ─────────────────────────────────────────────

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm());
    setShowForm(true);
  }

  function openEdit(s: Sondage) {
    setEditingId(s.id);
    setForm({
      title: s.title,
      description: s.description,
      status: s.status,
      language: s.language,
      closes_at: s.closes_at ? s.closes_at.slice(0, 10) : null,
      questions: [],
      _questions: s.questions.map(q => ({
        _key: q.id,
        text: q.text,
        type: q.type,
        options: q.options ?? ['', ''],
      })),
    });
    setShowForm(true);
  }

  function closeForm() {
    setShowForm(false);
    setEditingId(null);
    setForm(emptyForm());
  }

  function setField<K extends keyof SondageFormData>(key: K, value: SondageFormData[K]) {
    setForm(f => ({ ...f, [key]: value }));
  }

  function addQuestion() {
    setForm(f => ({ ...f, _questions: [...f._questions, emptyQuestion()] }));
  }

  function removeQuestion(idx: number) {
    setForm(f => ({ ...f, _questions: f._questions.filter((_, i) => i !== idx) }));
  }

  function updateQuestion(idx: number, patch: Partial<LocalQuestion>) {
    setForm(f => ({
      ...f,
      _questions: f._questions.map((q, i) => i === idx ? { ...q, ...patch } : q),
    }));
  }

  function addOption(qIdx: number) {
    setForm(f => ({
      ...f,
      _questions: f._questions.map((q, i) => i === qIdx ? { ...q, options: [...q.options, ''] } : q),
    }));
  }

  function updateOption(qIdx: number, oIdx: number, value: string) {
    setForm(f => ({
      ...f,
      _questions: f._questions.map((q, i) =>
        i === qIdx ? { ...q, options: q.options.map((o, j) => j === oIdx ? value : o) } : q
      ),
    }));
  }

  function removeOption(qIdx: number, oIdx: number) {
    setForm(f => ({
      ...f,
      _questions: f._questions.map((q, i) =>
        i === qIdx ? { ...q, options: q.options.filter((_, j) => j !== oIdx) } : q
      ),
    }));
  }

  // ── Save ─────────────────────────────────────────────────────

  async function handleSave() {
    if (!form.title.trim()) { toast('error', 'Le titre est requis.'); return; }
    if (form._questions.some(q => !q.text.trim())) { toast('error', 'Toutes les questions doivent avoir un texte.'); return; }

    const payload: SondageFormData = {
      title: form.title,
      description: form.description || null,
      status: form.status,
      language: form.language,
      closes_at: form.closes_at || null,
      questions: form._questions.map(q => ({
        text: q.text,
        type: q.type,
        options: (q.type === 'single' || q.type === 'multiple')
          ? q.options.filter(o => o.trim())
          : undefined,
      })),
    };

    setSaving(true);
    try {
      if (editingId !== null) {
        await updateSondage(editingId, payload);
        toast('success', 'Sondage mis à jour.');
      } else {
        await createSondage(payload);
        toast('success', 'Sondage créé.');
      }
      closeForm();
      load();
    } catch {
      toast('error', 'Erreur lors de la sauvegarde.');
    } finally {
      setSaving(false);
    }
  }

  // ── Delete ────────────────────────────────────────────────────

  async function handleDelete() {
    if (!confirmDelete) return;
    try {
      await deleteSondage(confirmDelete.id);
      toast('success', 'Sondage supprimé.');
      setConfirmDelete(null);
      load();
    } catch {
      toast('error', 'Erreur lors de la suppression.');
    }
  }

  // ── Sync ──────────────────────────────────────────────────────

  async function handleSync(s: Sondage) {
    setSyncing(s.id);
    try {
      await syncSondageToBlog(s.id);
      toast('success', 'Sondage synchronisé avec le Blog.');
      load();
    } catch {
      toast('error', 'Erreur de synchronisation avec le Blog.');
    } finally {
      setSyncing(null);
    }
  }

  // ── Render ────────────────────────────────────────────────────

  if (loading && sondages.length === 0) {
    return (
      <div className="p-4 md:p-6 space-y-6">
        <div className="animate-pulse bg-surface2 rounded-lg h-8 w-64" />
        <div className="animate-pulse bg-surface2 rounded-xl h-64" />
      </div>
    );
  }

  return (
    <div className="p-4 md:p-6 space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h2 className="font-title text-2xl font-bold text-white">Sondages</h2>
          <p className="text-sm text-muted mt-1">{total} sondage{total !== 1 ? 's' : ''} · publiés sur le Blog SEO</p>
        </div>
        <button
          onClick={openCreate}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
        >
          + Nouveau sondage
        </button>
      </div>

      {/* Liste */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-muted text-xs uppercase tracking-wide">
                <th className="text-left px-4 py-3">Titre</th>
                <th className="text-left px-4 py-3">Langue</th>
                <th className="text-left px-4 py-3">Questions</th>
                <th className="text-left px-4 py-3">Statut</th>
                <th className="text-left px-4 py-3">Fermeture</th>
                <th className="text-left px-4 py-3">Blog</th>
                <th className="text-right px-4 py-3">Actions</th>
              </tr>
            </thead>
            <tbody>
              {sondages.map(s => (
                <tr key={s.id} className="border-b border-border/50 hover:bg-surface2/30 transition-colors">
                  <td className="px-4 py-3">
                    <button
                      onClick={() => openEdit(s)}
                      className="text-white hover:text-violet-light transition-colors text-left font-medium"
                    >
                      {s.title}
                    </button>
                    {s.description && (
                      <p className="text-xs text-muted mt-0.5 truncate max-w-xs">{s.description}</p>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <span className="px-2 py-0.5 rounded text-xs bg-violet/20 text-violet-light uppercase">{s.language}</span>
                  </td>
                  <td className="px-4 py-3 text-muted">{s.questions.length}</td>
                  <td className="px-4 py-3">
                    <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[s.status]}`}>
                      {STATUS_LABELS[s.status]}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-muted text-xs">{formatDate(s.closes_at)}</td>
                  <td className="px-4 py-3">
                    {s.synced_to_blog ? (
                      <span className="text-xs text-green-400">✓ Sync</span>
                    ) : (
                      <span className="text-xs text-muted">Non sync</span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-right">
                    <div className="flex items-center justify-end gap-2">
                      <button onClick={() => openEdit(s)} className="text-xs text-muted hover:text-white transition-colors">Modifier</button>
                      <button
                        onClick={() => handleSync(s)}
                        disabled={syncing === s.id}
                        className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                      >
                        {syncing === s.id ? 'Sync...' : '↑ Blog'}
                      </button>
                      <button onClick={() => setConfirmDelete({ id: s.id, title: s.title })} className="text-xs text-danger hover:text-red-300 transition-colors">Supprimer</button>
                    </div>
                  </td>
                </tr>
              ))}
              {sondages.length === 0 && !loading && (
                <tr>
                  <td colSpan={7} className="px-4 py-16 text-center">
                    <div className="text-4xl mb-3">📋</div>
                    <p className="text-muted text-sm">Aucun sondage pour l'instant.</p>
                    <button onClick={openCreate} className="mt-3 text-violet-light hover:text-violet text-sm transition-colors">
                      Créer le premier sondage →
                    </button>
                  </td>
                </tr>
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Pagination */}
      {lastPage > 1 && (
        <div className="flex items-center justify-center gap-2">
          <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Précédent</button>
          <span className="text-xs text-muted">Page {page} / {lastPage}</span>
          <button onClick={() => setPage(p => Math.min(lastPage, p + 1))} disabled={page === lastPage} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Suivant</button>
        </div>
      )}

      {/* ── Modal formulaire ─────────────────────────────────────── */}
      <Modal
        open={showForm}
        onClose={closeForm}
        title={editingId !== null ? 'Modifier le sondage' : 'Nouveau sondage'}
        size="lg"
        footer={
          <>
            <Button variant="ghost" onClick={closeForm}>Annuler</Button>
            <Button variant="primary" onClick={handleSave} loading={saving}>
              {saving ? 'Enregistrement...' : editingId !== null ? 'Mettre à jour' : 'Créer le sondage'}
            </Button>
          </>
        }
      >
        <div className="space-y-5">
              {/* Titre */}
              <div>
                <label className="block text-xs text-muted mb-1.5 uppercase tracking-wide">Titre *</label>
                <input
                  type="text"
                  value={form.title}
                  onChange={e => setField('title', e.target.value)}
                  placeholder="Ex : Satisfaction service client Q1 2026"
                  className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm placeholder:text-muted focus:outline-none focus:border-violet transition-colors"
                />
              </div>

              {/* Description */}
              <div>
                <label className="block text-xs text-muted mb-1.5 uppercase tracking-wide">Description</label>
                <textarea
                  value={form.description ?? ''}
                  onChange={e => setField('description', e.target.value || null)}
                  rows={2}
                  placeholder="Contexte ou instructions affichés aux participants"
                  className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm placeholder:text-muted focus:outline-none focus:border-violet transition-colors resize-none"
                />
              </div>

              {/* Langue + Statut + Fermeture */}
              <div className="grid grid-cols-3 gap-3">
                <div>
                  <label className="block text-xs text-muted mb-1.5 uppercase tracking-wide">Langue</label>
                  <select
                    value={form.language}
                    onChange={e => setField('language', e.target.value)}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors"
                  >
                    {LANGUAGES.map(l => <option key={l} value={l}>{l.toUpperCase()}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1.5 uppercase tracking-wide">Statut</label>
                  <select
                    value={form.status}
                    onChange={e => setField('status', e.target.value as SondageStatus)}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors"
                  >
                    {(Object.keys(STATUS_LABELS) as SondageStatus[]).map(s => (
                      <option key={s} value={s}>{STATUS_LABELS[s]}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs text-muted mb-1.5 uppercase tracking-wide">Fermeture</label>
                  <input
                    type="date"
                    value={form.closes_at ?? ''}
                    onChange={e => setField('closes_at', e.target.value || null)}
                    className="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-violet transition-colors"
                  />
                </div>
              </div>

              {/* Questions */}
              <div>
                <div className="flex items-center justify-between mb-2">
                  <label className="text-xs text-muted uppercase tracking-wide">Questions *</label>
                  <button onClick={addQuestion} className="text-xs text-violet-light hover:text-violet transition-colors">+ Ajouter</button>
                </div>
                <div className="space-y-4">
                  {form._questions.map((q, qIdx) => (
                    <div key={q._key} className="bg-surface2 border border-border rounded-xl p-4 space-y-3">
                      <div className="flex items-start gap-2">
                        <span className="text-xs text-muted mt-2 shrink-0">Q{qIdx + 1}</span>
                        <div className="flex-1 space-y-2">
                          <input
                            type="text"
                            value={q.text}
                            onChange={e => updateQuestion(qIdx, { text: e.target.value })}
                            placeholder="Texte de la question"
                            className="w-full bg-surface border border-border rounded-lg px-3 py-2 text-white text-sm placeholder:text-muted focus:outline-none focus:border-violet transition-colors"
                          />
                          <div className="flex items-center gap-2">
                            <select
                              value={q.type}
                              onChange={e => {
                                const t = e.target.value as QuestionType;
                                updateQuestion(qIdx, { type: t, options: t !== 'open' ? (q.options.length >= 2 ? q.options : ['', '']) : [] });
                              }}
                              className="bg-surface border border-border rounded-lg px-2 py-1.5 text-white text-xs focus:outline-none focus:border-violet transition-colors"
                            >
                              {(Object.keys(QUESTION_TYPE_LABELS) as QuestionType[]).map(t => (
                                <option key={t} value={t}>{QUESTION_TYPE_LABELS[t]}</option>
                              ))}
                            </select>
                            {form._questions.length > 1 && (
                              <button onClick={() => removeQuestion(qIdx)} className="text-xs text-danger hover:text-red-300 transition-colors ml-auto">Supprimer</button>
                            )}
                          </div>
                        </div>
                      </div>

                      {(q.type === 'single' || q.type === 'multiple') && (
                        <div className="ml-6 space-y-1.5">
                          {q.options.map((opt, oIdx) => (
                            <div key={oIdx} className="flex items-center gap-2">
                              <span className="text-xs text-muted w-4 text-right">{oIdx + 1}.</span>
                              <input
                                type="text"
                                value={opt}
                                onChange={e => updateOption(qIdx, oIdx, e.target.value)}
                                placeholder={`Option ${oIdx + 1}`}
                                className="flex-1 bg-surface border border-border rounded-lg px-2 py-1.5 text-white text-xs placeholder:text-muted focus:outline-none focus:border-violet transition-colors"
                              />
                              {q.options.length > 2 && (
                                <button onClick={() => removeOption(qIdx, oIdx)} className="text-muted hover:text-danger transition-colors text-sm">×</button>
                              )}
                            </div>
                          ))}
                          <button onClick={() => addOption(qIdx)} className="text-xs text-violet-light hover:text-violet transition-colors ml-6">+ Option</button>
                        </div>
                      )}

                      {q.type === 'scale' && (
                        <p className="ml-6 text-xs text-muted">Échelle de 1 à 10 (affichée automatiquement)</p>
                      )}
                    </div>
                  ))}
                </div>
              </div>
        </div>
      </Modal>

      <ConfirmModal
        open={!!confirmDelete}
        title="Supprimer le sondage"
        message={`Voulez-vous vraiment supprimer "${confirmDelete?.title}" ? Toutes les réponses associées seront perdues.`}
        variant="danger"
        confirmLabel="Supprimer"
        onConfirm={handleDelete}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}
