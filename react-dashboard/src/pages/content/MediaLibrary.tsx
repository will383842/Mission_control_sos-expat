import { useState } from 'react';
import * as contentApi from '../../api/contentApi';
import type { UnsplashImage } from '../../types/content';

type Tab = 'unsplash' | 'dalle';

const DALLE_SIZES = ['1024x1024', '1792x1024', '1024x1792'] as const;

export default function MediaLibrary() {
  const [tab, setTab] = useState<Tab>('unsplash');

  // Unsplash state
  const [unsplashQuery, setUnsplashQuery] = useState('');
  const [unsplashResults, setUnsplashResults] = useState<UnsplashImage[]>([]);
  const [searchingUnsplash, setSearchingUnsplash] = useState(false);
  const [unsplashError, setUnsplashError] = useState<string | null>(null);
  const [copiedUrl, setCopiedUrl] = useState<string | null>(null);

  // DALL-E state
  const [dallePrompt, setDallePrompt] = useState('');
  const [dalleSize, setDalleSize] = useState<string>('1024x1024');
  const [generatingDalle, setGeneratingDalle] = useState(false);
  const [dalleResult, setDalleResult] = useState<string | null>(null);
  const [dalleError, setDalleError] = useState<string | null>(null);
  const [dalleHistory, setDalleHistory] = useState<string[]>([]);

  const handleSearchUnsplash = async () => {
    if (!unsplashQuery.trim()) return;
    setSearchingUnsplash(true);
    setUnsplashError(null);
    try {
      const { data } = await contentApi.searchUnsplash(unsplashQuery.trim(), 18);
      setUnsplashResults(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur recherche Unsplash';
      setUnsplashError(message);
    } finally {
      setSearchingUnsplash(false);
    }
  };

  const handleCopyUrl = async (url: string) => {
    try {
      await navigator.clipboard.writeText(url);
      setCopiedUrl(url);
      setTimeout(() => setCopiedUrl(null), 2000);
    } catch { /* silent */ }
  };

  const handleGenerateDalle = async () => {
    if (!dallePrompt.trim()) return;
    setGeneratingDalle(true);
    setDalleError(null);
    setDalleResult(null);
    try {
      const { data } = await contentApi.generateDalleImage(dallePrompt.trim(), dalleSize);
      setDalleResult(data.url);
      setDalleHistory(prev => [data.url, ...prev.slice(0, 19)]);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur generation DALL-E';
      setDalleError(message);
    } finally {
      setGeneratingDalle(false);
    }
  };

  const handleDownload = (url: string, filename: string) => {
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.target = '_blank';
    a.rel = 'noopener noreferrer';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  return (
    <div className="space-y-6">
      <h1 className="font-title text-2xl font-bold text-white">Mediatheque</h1>

      {/* Tab toggle */}
      <div className="flex gap-2">
        <button
          onClick={() => setTab('unsplash')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
            tab === 'unsplash'
              ? 'bg-violet text-white'
              : 'bg-surface2 text-white hover:bg-surface2/80'
          }`}
        >
          Unsplash
        </button>
        <button
          onClick={() => setTab('dalle')}
          className={`px-4 py-2 rounded-lg text-sm font-medium transition ${
            tab === 'dalle'
              ? 'bg-amber text-white'
              : 'bg-surface2 text-white hover:bg-surface2/80'
          }`}
        >
          DALL-E
        </button>
      </div>

      {/* ===== UNSPLASH TAB ===== */}
      {tab === 'unsplash' && (
        <div className="space-y-6">
          {/* Search */}
          <div className="flex gap-3">
            <input
              type="text"
              value={unsplashQuery}
              onChange={e => setUnsplashQuery(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleSearchUnsplash()}
              placeholder="Rechercher des images..."
              className="flex-1 px-4 py-2.5 bg-surface2 border border-border rounded-lg text-sm text-white placeholder-muted focus:outline-none focus:border-violet"
            />
            <button
              onClick={handleSearchUnsplash}
              disabled={searchingUnsplash || !unsplashQuery.trim()}
              className="px-6 py-2.5 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
            >
              {searchingUnsplash ? (
                <span className="flex items-center gap-2">
                  <span className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" />
                  Recherche...
                </span>
              ) : 'Rechercher'}
            </button>
          </div>

          {unsplashError && (
            <p className="text-danger text-sm">{unsplashError}</p>
          )}

          {/* Results grid */}
          {unsplashResults.length > 0 && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              {unsplashResults.map((img, i) => (
                <div key={i} className="bg-surface rounded-xl border border-border overflow-hidden group">
                  <div className="relative aspect-video bg-bg">
                    <img
                      src={img.thumb_url}
                      alt={img.alt_text}
                      className="w-full h-full object-cover"
                      loading="lazy"
                    />
                    <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                      <button
                        onClick={() => handleCopyUrl(img.url)}
                        className="px-3 py-1.5 bg-violet hover:bg-violet/90 text-white rounded text-xs font-medium transition"
                      >
                        {copiedUrl === img.url ? 'Copie !' : 'Copier URL'}
                      </button>
                    </div>
                  </div>
                  <div className="p-3">
                    <p className="text-muted text-xs truncate" title={img.attribution}>
                      {img.attribution}
                    </p>
                    <p className="text-muted text-xs mt-1">
                      {img.width} x {img.height}
                    </p>
                  </div>
                </div>
              ))}
            </div>
          )}

          {!searchingUnsplash && unsplashResults.length === 0 && unsplashQuery && !unsplashError && (
            <p className="text-muted text-sm text-center py-12">
              Aucun resultat pour "{unsplashQuery}"
            </p>
          )}

          {!unsplashQuery && (
            <div className="flex items-center justify-center py-16">
              <div className="text-center">
                <svg className="w-16 h-16 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p className="text-muted text-sm">
                  Recherchez des images libres de droits sur Unsplash
                </p>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ===== DALL-E TAB ===== */}
      {tab === 'dalle' && (
        <div className="space-y-6">
          {/* Generation form */}
          <div className="bg-surface rounded-xl p-6 border border-border space-y-4">
            <div>
              <label className="text-sm text-muted block mb-1">Prompt</label>
              <textarea
                value={dallePrompt}
                onChange={e => setDallePrompt(e.target.value)}
                placeholder="Decrivez l'image souhaitee..."
                rows={3}
                className="w-full px-4 py-2.5 bg-surface2 border border-border rounded-lg text-sm text-white placeholder-muted focus:outline-none focus:border-violet resize-none"
              />
            </div>

            <div className="flex items-end gap-4">
              <div>
                <label className="text-sm text-muted block mb-1">Taille</label>
                <select
                  value={dalleSize}
                  onChange={e => setDalleSize(e.target.value)}
                  className="px-3 py-2.5 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                >
                  {DALLE_SIZES.map(size => (
                    <option key={size} value={size}>{size}</option>
                  ))}
                </select>
              </div>

              <button
                onClick={handleGenerateDalle}
                disabled={generatingDalle || !dallePrompt.trim()}
                className="px-6 py-2.5 bg-amber hover:bg-amber disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
              >
                {generatingDalle ? (
                  <span className="flex items-center gap-2">
                    <span className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" />
                    Generation...
                  </span>
                ) : 'Generer (~$0.08)'}
              </button>
            </div>

            {dalleError && (
              <p className="text-danger text-sm">{dalleError}</p>
            )}
          </div>

          {/* Result */}
          {dalleResult && (
            <div className="bg-surface rounded-xl border border-border overflow-hidden">
              <div className="relative">
                <img
                  src={dalleResult}
                  alt="Image generee par DALL-E"
                  className="w-full max-h-[600px] object-contain bg-bg"
                />
              </div>
              <div className="p-4 flex gap-3">
                <button
                  onClick={() => handleDownload(dalleResult!, `dalle-${Date.now()}.png`)}
                  className="px-4 py-2 bg-success hover:bg-success/90 text-white rounded-lg text-sm font-medium transition"
                >
                  Telecharger
                </button>
                <button
                  onClick={() => handleCopyUrl(dalleResult!)}
                  className="px-4 py-2 bg-violet hover:bg-violet/90 text-white rounded-lg text-sm font-medium transition"
                >
                  {copiedUrl === dalleResult ? 'Copie !' : 'Copier URL'}
                </button>
              </div>
            </div>
          )}

          {/* History */}
          {dalleHistory.length > 0 && (
            <div>
              <h2 className="text-lg font-semibold text-white mb-4">
                Historique ({dalleHistory.length})
              </h2>
              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                {dalleHistory.map((url, i) => (
                  <div
                    key={i}
                    className="bg-surface rounded-lg border border-border overflow-hidden group cursor-pointer"
                    onClick={() => handleCopyUrl(url)}
                  >
                    <div className="relative aspect-square">
                      <img
                        src={url}
                        alt={`Generation ${i + 1}`}
                        className="w-full h-full object-cover"
                        loading="lazy"
                      />
                      <div className="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span className="text-white text-xs font-medium">
                          {copiedUrl === url ? 'Copie !' : 'Copier URL'}
                        </span>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {!dalleResult && dalleHistory.length === 0 && (
            <div className="flex items-center justify-center py-16">
              <div className="text-center">
                <svg className="w-16 h-16 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                </svg>
                <p className="text-muted text-sm">
                  Generez des images uniques avec DALL-E 3
                </p>
                <p className="text-muted text-xs mt-1">Cout estime : ~$0.08 par image</p>
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
