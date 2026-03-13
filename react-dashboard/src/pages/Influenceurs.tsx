import React, { useEffect, useRef, useState } from 'react';
import { useInfluenceurs } from '../hooks/useInfluenceurs';
import InfluenceurCard from '../components/InfluenceurCard';
import InfluenceurTable from '../components/InfluenceurTable';
import FilterSidebar from '../components/FilterSidebar';
import type { InfluenceurFilters } from '../types/influenceur';

export default function Influenceurs() {
  const { influenceurs, loading, hasMore, load, loadMore } = useInfluenceurs();
  const [view, setView] = useState<'cards' | 'table'>('cards');
  const [showCreate, setShowCreate] = useState(false);
  const loaderRef = useRef<HTMLDivElement>(null);

  useEffect(() => { load(); }, []);

  // Infinite scroll observer
  useEffect(() => {
    const observer = new IntersectionObserver(
      entries => { if (entries[0].isIntersecting && hasMore && !loading) loadMore(); },
      { threshold: 0.1 }
    );
    if (loaderRef.current) observer.observe(loaderRef.current);
    return () => observer.disconnect();
  }, [hasMore, loading, loadMore]);

  const handleFilterChange = (filters: InfluenceurFilters) => {
    load(filters);
  };

  return (
    <div className="flex h-screen overflow-hidden">
      <FilterSidebar onFilterChange={handleFilterChange} />

      <div className="flex-1 overflow-auto p-6">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div>
            <h2 className="font-title text-2xl font-bold text-white">Influenceurs</h2>
            <p className="text-muted text-sm mt-1">{influenceurs.length} chargé{influenceurs.length !== 1 ? 's' : ''}</p>
          </div>
          <div className="flex items-center gap-3">
            {/* Toggle vue */}
            <div className="flex bg-surface border border-border rounded-lg p-1">
              <button
                onClick={() => setView('cards')}
                className={`px-3 py-1.5 rounded text-sm transition-colors ${view === 'cards' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}
              >
                Cartes
              </button>
              <button
                onClick={() => setView('table')}
                className={`px-3 py-1.5 rounded text-sm transition-colors ${view === 'table' ? 'bg-violet/20 text-violet-light' : 'text-muted hover:text-white'}`}
              >
                Tableau
              </button>
            </div>
            <button
              onClick={() => alert('Fonctionnalité à venir : formulaire de création d\'influenceur.')}
              className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors"
            >
              + Ajouter
            </button>
          </div>
        </div>

        {/* Content */}
        {view === 'cards' ? (
          <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            {influenceurs.map(inf => (
              <InfluenceurCard key={inf.id} influenceur={inf} />
            ))}
          </div>
        ) : (
          <InfluenceurTable influenceurs={influenceurs} />
        )}

        {/* Loader infinite scroll */}
        <div ref={loaderRef} className="py-6 flex justify-center">
          {loading && (
            <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
          )}
          {!loading && !hasMore && influenceurs.length > 0 && (
            <p className="text-muted text-sm">Tous les influenceurs sont chargés.</p>
          )}
          {!loading && influenceurs.length === 0 && (
            <div className="text-center py-12">
              <p className="text-4xl mb-3">👥</p>
              <p className="text-white font-medium">Aucun influenceur trouvé</p>
              <p className="text-muted text-sm mt-1">Modifiez les filtres ou ajoutez un influenceur.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
