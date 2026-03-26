import React, { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  fetchPressReleases,
  deletePressRelease,
  exportPressReleasePdf,
  exportPressReleaseWord,
  fetchPressDossiers,
  deletePressDossier,
  exportDossierPdf,
} from '../../api/contentApi';
import type { PressRelease, PressDossier, ContentStatus, PaginatedResponse } from '../../types/content';
import { toast } from '../../components/Toast';
import { ConfirmModal } from '../../components/ConfirmModal';
import { errMsg, seoBarColor, inputClass, STATUS_COLORS, STATUS_LABELS } from './helpers';

function formatDate(d: string | null): string {
  if (!d) return '\u2014';
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

type SubTab = 'releases' | 'dossiers';

// ── Component ───────────────────────────────────────────────
export default function PressList() {
  const navigate = useNavigate();
  const [subTab, setSubTab] = useState<SubTab>('releases');

  // Releases state
  const [releases, setReleases] = useState<PressRelease[]>([]);
  const [releasesLoading, setReleasesLoading] = useState(true);
  const [releasesTotal, setReleasesTotal] = useState(0);
  const [releasesPage, setReleasesPage] = useState(1);
  const [releasesLastPage, setReleasesLastPage] = useState(1);

  // Dossiers state
  const [dossiers, setDossiers] = useState<PressDossier[]>([]);
  const [dossiersLoading, setDossiersLoading] = useState(true);
  const [dossiersTotal, setDossiersTotal] = useState(0);
  const [dossiersPage, setDossiersPage] = useState(1);
  const [dossiersLastPage, setDossiersLastPage] = useState(1);

  // Delete
  const [confirmDelete, setConfirmDelete] = useState<{ type: 'release' | 'dossier'; id: number; name: string } | null>(null);

  // Exporting
  const [exporting, setExporting] = useState<string | null>(null);

  const loadReleases = useCallback(async () => {
    setReleasesLoading(true);
    try {
      const res = await fetchPressReleases({ page: releasesPage });
      const data = res.data as unknown as PaginatedResponse<PressRelease>;
      setReleases(data.data);
      setReleasesTotal(data.total);
      setReleasesLastPage(data.last_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setReleasesLoading(false);
    }
  }, [releasesPage]);

  const loadDossiers = useCallback(async () => {
    setDossiersLoading(true);
    try {
      const res = await fetchPressDossiers({ page: dossiersPage });
      const data = res.data as unknown as PaginatedResponse<PressDossier>;
      setDossiers(data.data);
      setDossiersTotal(data.total);
      setDossiersLastPage(data.last_page);
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setDossiersLoading(false);
    }
  }, [dossiersPage]);

  useEffect(() => { loadReleases(); }, [loadReleases]);
  useEffect(() => { loadDossiers(); }, [loadDossiers]);

  const handleDelete = async () => {
    if (!confirmDelete) return;
    try {
      if (confirmDelete.type === 'release') {
        await deletePressRelease(confirmDelete.id);
        toast('success', 'Communique supprime.');
        loadReleases();
      } else {
        await deletePressDossier(confirmDelete.id);
        toast('success', 'Dossier supprime.');
        loadDossiers();
      }
      setConfirmDelete(null);
    } catch (err) {
      toast('error', errMsg(err));
    }
  };

  const handleExportReleasePdf = async (release: PressRelease) => {
    setExporting(`pdf-${release.id}`);
    try {
      const res = await exportPressReleasePdf(release.id);
      downloadBlob(res.data, `${release.slug || 'communique'}.pdf`);
      toast('success', 'PDF exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setExporting(null);
    }
  };

  const handleExportReleaseWord = async (release: PressRelease) => {
    setExporting(`word-${release.id}`);
    try {
      const res = await exportPressReleaseWord(release.id);
      downloadBlob(res.data, `${release.slug || 'communique'}.docx`);
      toast('success', 'Word exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setExporting(null);
    }
  };

  const handleExportDossierPdf = async (dossier: PressDossier) => {
    setExporting(`dpdf-${dossier.id}`);
    try {
      const res = await exportDossierPdf(dossier.id);
      downloadBlob(res.data, `${dossier.slug || 'dossier'}.pdf`);
      toast('success', 'PDF exporte.');
    } catch (err) {
      toast('error', errMsg(err));
    } finally {
      setExporting(null);
    }
  };

  const isLoading = subTab === 'releases' ? releasesLoading : dossiersLoading;

  if (isLoading && (subTab === 'releases' ? releases.length === 0 : dossiers.length === 0)) {
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
          <h2 className="font-title text-2xl font-bold text-white">Presse</h2>
          <p className="text-sm text-muted mt-1">Communiques de presse et dossiers</p>
        </div>
      </div>

      {/* Sub-tabs */}
      <div className="flex gap-1 border-b border-border">
        <button
          onClick={() => setSubTab('releases')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            subTab === 'releases' ? 'border-violet text-violet-light' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          Communiques ({releasesTotal})
        </button>
        <button
          onClick={() => setSubTab('dossiers')}
          className={`px-4 py-2 text-sm font-medium border-b-2 transition-colors ${
            subTab === 'dossiers' ? 'border-violet text-violet-light' : 'border-transparent text-muted hover:text-white'
          }`}
        >
          Dossiers ({dossiersTotal})
        </button>
      </div>

      {/* Releases tab */}
      {subTab === 'releases' && (
        <>
          <div className="flex justify-end">
            <button
              onClick={() => navigate('/content/press/releases/new')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              + Nouveau communique
            </button>
          </div>

          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-muted text-xs uppercase tracking-wide">
                    <th className="text-left px-4 py-3">Titre</th>
                    <th className="text-left px-4 py-3">Langue</th>
                    <th className="text-left px-4 py-3">Statut</th>
                    <th className="text-left px-4 py-3">SEO</th>
                    <th className="text-right px-4 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {releases.map(release => (
                    <tr key={release.id} className="border-b border-border/50 hover:bg-surface2/30 transition-colors">
                      <td className="px-4 py-3">
                        <button
                          onClick={() => navigate(`/content/press/releases/${release.id}`)}
                          className="text-white hover:text-violet-light transition-colors text-left font-medium"
                        >
                          {release.title || 'Sans titre'}
                        </button>
                      </td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-0.5 rounded text-xs bg-violet/20 text-violet-light uppercase">{release.language}</span>
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[release.status]}`}>
                          {STATUS_LABELS[release.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3">
                        <div className="flex items-center gap-2">
                          <div className="w-16 h-1.5 bg-surface2 rounded-full overflow-hidden">
                            <div className={`h-full rounded-full ${seoBarColor(release.seo_score)}`} style={{ width: `${release.seo_score}%` }} />
                          </div>
                          <span className="text-xs text-muted">{release.seo_score}</span>
                        </div>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button onClick={() => navigate(`/content/press/releases/${release.id}`)} className="text-xs text-muted hover:text-white transition-colors">Voir</button>
                          <button
                            onClick={() => handleExportReleasePdf(release)}
                            disabled={exporting === `pdf-${release.id}`}
                            className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                          >
                            PDF
                          </button>
                          <button
                            onClick={() => handleExportReleaseWord(release)}
                            disabled={exporting === `word-${release.id}`}
                            className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                          >
                            Word
                          </button>
                          <button
                            onClick={() => setConfirmDelete({ type: 'release', id: release.id, name: release.title })}
                            className="text-xs text-danger hover:text-red-300 transition-colors"
                          >
                            Supprimer
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {releases.length === 0 && (
                    <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Aucun communique de presse.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {releasesLastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setReleasesPage(p => Math.max(1, p - 1))} disabled={releasesPage === 1} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Precedent</button>
              <span className="text-xs text-muted">Page {releasesPage} / {releasesLastPage}</span>
              <button onClick={() => setReleasesPage(p => Math.min(releasesLastPage, p + 1))} disabled={releasesPage === releasesLastPage} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Suivant</button>
            </div>
          )}
        </>
      )}

      {/* Dossiers tab */}
      {subTab === 'dossiers' && (
        <>
          <div className="flex justify-end">
            <button
              onClick={() => navigate('/content/press/dossiers/new')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              + Nouveau dossier
            </button>
          </div>

          <div className="bg-surface border border-border rounded-xl overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border text-muted text-xs uppercase tracking-wide">
                    <th className="text-left px-4 py-3">Nom</th>
                    <th className="text-left px-4 py-3">Langue</th>
                    <th className="text-left px-4 py-3">Elements</th>
                    <th className="text-left px-4 py-3">Statut</th>
                    <th className="text-right px-4 py-3">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {dossiers.map(dossier => (
                    <tr key={dossier.id} className="border-b border-border/50 hover:bg-surface2/30 transition-colors">
                      <td className="px-4 py-3">
                        <button
                          onClick={() => navigate(`/content/press/dossiers/${dossier.id}`)}
                          className="text-white hover:text-violet-light transition-colors text-left font-medium"
                        >
                          {dossier.name || 'Sans nom'}
                        </button>
                      </td>
                      <td className="px-4 py-3">
                        <span className="px-2 py-0.5 rounded text-xs bg-violet/20 text-violet-light uppercase">{dossier.language}</span>
                      </td>
                      <td className="px-4 py-3 text-muted">
                        {dossier.items?.length ?? 0}
                      </td>
                      <td className="px-4 py-3">
                        <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[dossier.status]}`}>
                          {STATUS_LABELS[dossier.status]}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-right">
                        <div className="flex items-center justify-end gap-2">
                          <button onClick={() => navigate(`/content/press/dossiers/${dossier.id}`)} className="text-xs text-muted hover:text-white transition-colors">Voir</button>
                          <button
                            onClick={() => handleExportDossierPdf(dossier)}
                            disabled={exporting === `dpdf-${dossier.id}`}
                            className="text-xs text-blue-400 hover:text-blue-300 transition-colors disabled:opacity-50"
                          >
                            PDF
                          </button>
                          <button
                            onClick={() => setConfirmDelete({ type: 'dossier', id: dossier.id, name: dossier.name })}
                            className="text-xs text-danger hover:text-red-300 transition-colors"
                          >
                            Supprimer
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {dossiers.length === 0 && (
                    <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Aucun dossier de presse.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>

          {dossiersLastPage > 1 && (
            <div className="flex items-center justify-center gap-2">
              <button onClick={() => setDossiersPage(p => Math.max(1, p - 1))} disabled={dossiersPage === 1} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Precedent</button>
              <span className="text-xs text-muted">Page {dossiersPage} / {dossiersLastPage}</span>
              <button onClick={() => setDossiersPage(p => Math.min(dossiersLastPage, p + 1))} disabled={dossiersPage === dossiersLastPage} className="px-3 py-1.5 text-xs bg-surface2 text-muted hover:text-white rounded-lg disabled:opacity-30 transition-colors">Suivant</button>
            </div>
          )}
        </>
      )}

      <ConfirmModal
        open={!!confirmDelete}
        title={confirmDelete?.type === 'release' ? 'Supprimer le communique' : 'Supprimer le dossier'}
        message={`Voulez-vous vraiment supprimer "${confirmDelete?.name}" ?`}
        variant="danger"
        confirmLabel="Supprimer"
        onConfirm={handleDelete}
        onCancel={() => setConfirmDelete(null)}
      />
    </div>
  );
}
