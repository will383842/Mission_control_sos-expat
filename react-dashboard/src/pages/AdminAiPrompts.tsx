import React, { useEffect, useState } from 'react';
import api from '../api/client';

interface ContactTypeItem {
  id: number;
  value: string;
  label: string;
  icon: string;
  color: string;
}

interface PromptItem {
  contact_type: string;
  prompt_template: string;
  is_active: boolean;
  is_default?: boolean;
}

export default function AdminAiPrompts() {
  const [types, setTypes] = useState<ContactTypeItem[]>([]);
  const [prompts, setPrompts] = useState<Record<string, PromptItem>>({});
  const [selected, setSelected] = useState<string | null>(null);
  const [editText, setEditText] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState('');
  const [error, setError] = useState('');

  // Load types + all prompts
  useEffect(() => {
    (async () => {
      try {
        const [typesRes, promptsRes] = await Promise.all([
          api.get('/contact-types'),
          api.get('/ai-prompts'),
        ]);
        setTypes(typesRes.data);

        const map: Record<string, PromptItem> = {};
        for (const p of promptsRes.data) {
          map[p.contact_type] = p;
        }
        setPrompts(map);
      } catch { setError('Erreur chargement'); }
      finally { setLoading(false); }
    })();
  }, []);

  const handleSelect = async (contactType: string) => {
    setSelected(contactType);
    setSuccess('');
    setError('');

    // If we already have it in state, use it
    if (prompts[contactType]) {
      setEditText(prompts[contactType].prompt_template);
      return;
    }

    // Otherwise fetch the default from backend
    try {
      const { data } = await api.get(`/ai-prompts/${contactType}`);
      setEditText(data.prompt_template);
      setPrompts(prev => ({ ...prev, [contactType]: data }));
    } catch {
      setEditText('');
    }
  };

  const handleSave = async () => {
    if (!selected || !editText.trim()) return;
    setSaving(true);
    setError('');
    setSuccess('');

    try {
      const { data } = await api.put('/ai-prompts', {
        contact_type: selected,
        prompt_template: editText,
        is_active: true,
      });
      setPrompts(prev => ({ ...prev, [selected]: { ...data, is_default: false } }));
      setSuccess('Prompt sauvegardé !');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur sauvegarde');
    } finally {
      setSaving(false);
    }
  };

  const handleReset = async () => {
    if (!selected) return;
    if (!confirm('Supprimer le prompt personnalisé ? Le prompt par défaut sera utilisé.')) return;

    try {
      await api.delete(`/ai-prompts/${selected}`);
      // Reload the default
      const { data } = await api.get(`/ai-prompts/${selected}`);
      setEditText(data.prompt_template);
      setPrompts(prev => ({ ...prev, [selected]: data }));
      setSuccess('Prompt réinitialisé au défaut');
    } catch (err: any) {
      setError(err.response?.data?.message || 'Erreur');
    }
  };

  const selectedType = types.find(t => t.value === selected);
  const currentPrompt = selected ? prompts[selected] : null;

  if (loading) return (
    <div className="flex items-center justify-center h-32">
      <div className="w-6 h-6 border-2 border-violet border-t-transparent rounded-full animate-spin" />
    </div>
  );

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h2 className="font-title text-2xl font-bold text-white">🤖 Prompts IA</h2>
        <p className="text-muted text-sm mt-1">
          Personnalise les prompts de recherche pour chaque type de contact.
          Utilise <code className="text-violet-light bg-violet/10 px-1 rounded">{'{{PAYS}}'}</code> et <code className="text-violet-light bg-violet/10 px-1 rounded">{'{{LANGUE}}'}</code> comme variables.
        </p>
      </div>

      {error && <div className="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-red-400 text-sm">{error}</div>}
      {success && <div className="bg-emerald-500/10 border border-emerald-500/20 rounded-lg p-3 text-emerald-400 text-sm">{success}</div>}

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Types list */}
        <div className="space-y-1">
          <p className="text-xs text-muted font-semibold mb-2">TYPES ({types.length})</p>
          {types.map(type => {
            const hasCustom = prompts[type.value] && !prompts[type.value].is_default;
            return (
              <button key={type.value}
                onClick={() => handleSelect(type.value)}
                className={`w-full text-left px-3 py-2 rounded-lg text-sm transition-colors flex items-center justify-between ${
                  selected === type.value
                    ? 'bg-violet/20 text-violet-light'
                    : 'text-gray-400 hover:bg-surface2 hover:text-white'
                }`}>
                <span className="flex items-center gap-2">
                  <span>{type.icon}</span>
                  <span>{type.label}</span>
                </span>
                {hasCustom && (
                  <span className="text-[9px] bg-emerald-500/20 text-emerald-400 px-1.5 py-0.5 rounded-full">
                    personnalisé
                  </span>
                )}
              </button>
            );
          })}
        </div>

        {/* Editor */}
        <div className="lg:col-span-2">
          {selected ? (
            <div className="bg-surface border border-border rounded-xl p-5 space-y-4">
              <div className="flex items-center justify-between">
                <h3 className="font-title font-semibold text-white flex items-center gap-2">
                  <span>{selectedType?.icon}</span> Prompt : {selectedType?.label}
                </h3>
                <div className="flex items-center gap-2">
                  {currentPrompt?.is_default ? (
                    <span className="text-[10px] bg-amber/20 text-amber px-2 py-0.5 rounded-full">défaut (code)</span>
                  ) : (
                    <span className="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full">personnalisé (DB)</span>
                  )}
                </div>
              </div>

              {/* Help */}
              <div className="bg-bg border border-border rounded-lg p-3 text-[11px] text-muted space-y-1">
                <p className="font-semibold text-white">Guide d'écriture de prompt :</p>
                <p>1. <strong>Commence par "Cherche sur le web..."</strong> — c'est Perplexity qui cherche</p>
                <p>2. <strong>Liste des mots-clés</strong> entre guillemets — c'est ce que Perplexity tape dans Google</p>
                <p>3. <strong>Demande NOM, EMAIL, URL, TEL, SOURCE</strong> — Claude structurera ensuite</p>
                <p>4. Variables : <code className="text-violet-light">{'{{PAYS}}'}</code> = pays sélectionné, <code className="text-violet-light">{'{{LANGUE}}'}</code> = langue</p>
              </div>

              <textarea
                value={editText}
                onChange={e => setEditText(e.target.value)}
                rows={18}
                className="w-full bg-bg border border-border rounded-lg px-4 py-3 text-sm text-white outline-none focus:border-violet resize-y font-mono leading-relaxed"
                placeholder="Écris ton prompt ici..."
              />

              <div className="flex items-center justify-between">
                <p className="text-[10px] text-muted">{editText.length} caractères</p>
                <div className="flex gap-2">
                  {!currentPrompt?.is_default && (
                    <button onClick={handleReset}
                      className="text-xs text-muted hover:text-red-400 px-3 py-1.5 rounded-lg transition-colors border border-border hover:border-red-500/30">
                      Réinitialiser au défaut
                    </button>
                  )}
                  <button onClick={handleSave} disabled={saving || !editText.trim()}
                    className="bg-violet hover:bg-violet/80 disabled:opacity-50 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors">
                    {saving ? 'Sauvegarde...' : 'Sauvegarder'}
                  </button>
                </div>
              </div>
            </div>
          ) : (
            <div className="bg-surface border border-border rounded-xl p-12 text-center">
              <p className="text-4xl mb-3">👈</p>
              <p className="text-muted">Sélectionne un type de contact pour voir et éditer son prompt</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
