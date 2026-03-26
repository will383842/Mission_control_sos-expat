import { useEffect, useState, type FormEvent } from 'react';
import * as contentApi from '../../api/contentApi';
import type { PromptTemplate } from '../../types/content';

const CONTENT_TYPES = [
  { value: 'article', label: 'Article', color: 'bg-violet/20 text-violet-light' },
  { value: 'comparative', label: 'Comparatif', color: 'bg-blue-500/20 text-blue-400' },
  { value: 'landing', label: 'Landing', color: 'bg-amber/20 text-amber' },
  { value: 'press', label: 'Presse', color: 'bg-success/20 text-success' },
  { value: 'translation', label: 'Traduction', color: 'bg-muted/20 text-muted' },
  { value: 'faq', label: 'FAQ', color: 'bg-amber/20 text-amber' },
];

const PHASES = [
  { value: 'validate', label: 'Validate', color: 'bg-muted/20 text-muted' },
  { value: 'research', label: 'Research', color: 'bg-blue-600/20 text-blue-300' },
  { value: 'title', label: 'Title', color: 'bg-violet/20 text-violet-light' },
  { value: 'excerpt', label: 'Excerpt', color: 'bg-indigo-600/20 text-indigo-300' },
  { value: 'content', label: 'Content', color: 'bg-emerald-600/20 text-success' },
  { value: 'faq', label: 'FAQ', color: 'bg-amber/20 text-amber' },
  { value: 'meta', label: 'Meta', color: 'bg-amber/20 text-amber' },
  { value: 'jsonld', label: 'JSON-LD', color: 'bg-cyan/20 text-cyan' },
  { value: 'internal_links', label: 'Internal Links', color: 'bg-success/20 text-success' },
  { value: 'external_links', label: 'External Links', color: 'bg-blue-500/20 text-blue-400' },
  { value: 'affiliate_links', label: 'Affiliate Links', color: 'bg-amber/20 text-amber' },
  { value: 'images', label: 'Images', color: 'bg-violet/20 text-violet-light' },
  { value: 'slugs', label: 'Slugs', color: 'bg-muted/20 text-muted' },
  { value: 'quality', label: 'Quality', color: 'bg-danger/20 text-danger' },
  { value: 'translations', label: 'Translations', color: 'bg-cyan/20 text-cyan' },
];

const MODELS = ['gpt-4o', 'gpt-4o-mini'];

const AVAILABLE_VARIABLES = [
  '{{topic}}', '{{language}}', '{{country}}', '{{keywords}}',
  '{{facts}}', '{{title}}', '{{excerpt}}', '{{sources}}',
  '{{content}}', '{{tone}}', '{{length}}',
];

function getContentTypeBadge(type: string) {
  const ct = CONTENT_TYPES.find(c => c.value === type);
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${ct?.color ?? 'bg-muted/20 text-muted'}`}>
      {ct?.label ?? type}
    </span>
  );
}

function getPhaseBadge(phase: string) {
  const p = PHASES.find(ph => ph.value === phase);
  return (
    <span className={`px-2 py-0.5 rounded text-xs font-medium ${p?.color ?? 'bg-muted/20 text-muted'}`}>
      {p?.label ?? phase}
    </span>
  );
}

interface PromptForm {
  name: string;
  content_type: string;
  phase: string;
  system_message: string;
  user_message_template: string;
  model: string;
  temperature: number;
  max_tokens: number;
  is_active: boolean;
}

const emptyForm: PromptForm = {
  name: '',
  content_type: 'article',
  phase: 'content',
  system_message: '',
  user_message_template: '',
  model: 'gpt-4o',
  temperature: 0.7,
  max_tokens: 4096,
  is_active: true,
};

export default function PromptTemplatesPage() {
  const [templates, setTemplates] = useState<PromptTemplate[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Form state
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [form, setForm] = useState<PromptForm>(emptyForm);
  const [saving, setSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);

  // Test state
  const [showTest, setShowTest] = useState(false);
  const [testVars, setTestVars] = useState<Record<string, string>>({});
  const [testResult, setTestResult] = useState<string | null>(null);
  const [testing, setTesting] = useState(false);
  const [testError, setTestError] = useState<string | null>(null);

  useEffect(() => {
    loadTemplates();
  }, []);

  const loadTemplates = async () => {
    setLoading(true);
    setError(null);
    try {
      const { data } = await contentApi.fetchPromptTemplates();
      setTemplates(data);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur chargement';
      setError(message);
    } finally {
      setLoading(false);
    }
  };

  const handleCreate = () => {
    setEditingId(null);
    setForm(emptyForm);
    setShowForm(true);
    setShowTest(false);
    setTestResult(null);
  };

  const handleEdit = (tpl: PromptTemplate) => {
    setEditingId(tpl.id);
    setForm({
      name: tpl.name,
      content_type: tpl.content_type,
      phase: tpl.phase,
      system_message: tpl.system_message,
      user_message_template: tpl.user_message_template,
      model: tpl.model,
      temperature: tpl.temperature,
      max_tokens: tpl.max_tokens,
      is_active: tpl.is_active,
    });
    setShowForm(true);
    setShowTest(false);
    setTestResult(null);
  };

  const handleSave = async (e: FormEvent) => {
    e.preventDefault();
    setSaving(true);
    setSaveError(null);
    try {
      if (editingId) {
        await contentApi.updatePromptTemplate(editingId, form);
      } else {
        await contentApi.createPromptTemplate(form);
      }
      setShowForm(false);
      setEditingId(null);
      await loadTemplates();
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur sauvegarde';
      setSaveError(message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Supprimer ce template ?')) return;
    try {
      await contentApi.deletePromptTemplate(id);
      setTemplates(prev => prev.filter(t => t.id !== id));
    } catch { /* silent */ }
  };

  const handleToggleActive = async (tpl: PromptTemplate) => {
    try {
      await contentApi.updatePromptTemplate(tpl.id, { is_active: !tpl.is_active });
      setTemplates(prev => prev.map(t => t.id === tpl.id ? { ...t, is_active: !t.is_active } : t));
    } catch { /* silent */ }
  };

  // Detect variables in template
  const detectedVars = Array.from(
    new Set(
      (form.user_message_template.match(/\{\{(\w+)\}\}/g) ?? []).map(m => m.replace(/[{}]/g, ''))
    )
  );

  const handleOpenTest = () => {
    const vars: Record<string, string> = {};
    detectedVars.forEach(v => { vars[v] = testVars[v] ?? ''; });
    setTestVars(vars);
    setShowTest(true);
    setTestResult(null);
    setTestError(null);
  };

  const handleRunTest = async () => {
    if (!editingId) return;
    setTesting(true);
    setTestError(null);
    setTestResult(null);
    try {
      const { data } = await contentApi.testPromptTemplate({
        prompt_id: editingId,
        variables: testVars,
      });
      setTestResult(data.output);
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Erreur test';
      setTestError(message);
    } finally {
      setTesting(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-violet" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="font-title text-2xl font-bold text-white">Templates de prompts</h1>
        <button
          onClick={handleCreate}
          className="px-4 py-2 bg-violet hover:bg-violet/90 text-white rounded-lg text-sm font-medium transition"
        >
          + Nouveau template
        </button>
      </div>

      {error && <p className="text-danger text-sm">{error}</p>}

      {/* Templates table */}
      {!showForm && (
        <div className="bg-surface rounded-xl border border-border overflow-x-auto">
          {templates.length === 0 ? (
            <p className="text-muted text-sm p-6 text-center">Aucun template.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="text-muted border-b border-border">
                  <th className="text-left py-2 px-3">Nom</th>
                  <th className="text-center py-2 px-3">Type</th>
                  <th className="text-center py-2 px-3">Phase</th>
                  <th className="text-center py-2 px-3">Modele</th>
                  <th className="text-center py-2 px-3">Actif</th>
                  <th className="text-center py-2 px-3">Version</th>
                  <th className="text-right py-2 px-3">Actions</th>
                </tr>
              </thead>
              <tbody>
                {templates.map(tpl => (
                  <tr key={tpl.id} className="border-b border-border/50 hover:bg-surface2/30">
                    <td className="py-2 px-3 text-white font-medium">{tpl.name}</td>
                    <td className="py-2 px-3 text-center">{getContentTypeBadge(tpl.content_type)}</td>
                    <td className="py-2 px-3 text-center">{getPhaseBadge(tpl.phase)}</td>
                    <td className="py-2 px-3 text-center text-muted text-xs">{tpl.model}</td>
                    <td className="py-2 px-3 text-center">
                      <button
                        onClick={() => handleToggleActive(tpl)}
                        className={`w-10 h-5 rounded-full transition relative ${
                          tpl.is_active ? 'bg-success' : 'bg-muted'
                        }`}
                      >
                        <span
                          className={`absolute top-0.5 w-4 h-4 rounded-full bg-white transition-all ${
                            tpl.is_active ? 'left-5' : 'left-0.5'
                          }`}
                        />
                      </button>
                    </td>
                    <td className="py-2 px-3 text-center text-muted text-xs">v{tpl.version}</td>
                    <td className="py-2 px-3 text-right">
                      <div className="flex gap-1 justify-end">
                        <button
                          onClick={() => handleEdit(tpl)}
                          className="px-2 py-0.5 text-xs bg-surface2 hover:bg-surface2/80 text-white rounded transition"
                        >
                          Modifier
                        </button>
                        <button
                          onClick={() => handleDelete(tpl.id)}
                          className="px-2 py-0.5 text-xs bg-danger/20 hover:bg-danger/40 text-danger rounded transition"
                        >
                          Supprimer
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Create/Edit form */}
      {showForm && (
        <div className="bg-surface rounded-xl p-6 border border-border">
          <h2 className="text-lg font-semibold text-white mb-4">
            {editingId ? 'Modifier template' : 'Nouveau template'}
          </h2>
          <form onSubmit={handleSave} className="space-y-5">
            {/* Row 1: Name, Type, Phase */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label className="text-xs text-muted block mb-1">Nom</label>
                <input
                  type="text"
                  value={form.name}
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  required
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                />
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">Type de contenu</label>
                <select
                  value={form.content_type}
                  onChange={e => setForm(f => ({ ...f, content_type: e.target.value }))}
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                >
                  {CONTENT_TYPES.map(ct => (
                    <option key={ct.value} value={ct.value}>{ct.label}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">Phase</label>
                <select
                  value={form.phase}
                  onChange={e => setForm(f => ({ ...f, phase: e.target.value }))}
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                >
                  {PHASES.map(p => (
                    <option key={p.value} value={p.value}>{p.label}</option>
                  ))}
                </select>
              </div>
            </div>

            {/* System message */}
            <div>
              <label className="text-xs text-muted block mb-1">Message systeme</label>
              <textarea
                value={form.system_message}
                onChange={e => setForm(f => ({ ...f, system_message: e.target.value }))}
                rows={6}
                className="w-full px-3 py-2 bg-bg border border-border rounded-lg text-sm text-white font-mono focus:outline-none focus:border-violet resize-y"
                placeholder="You are a professional SEO content writer..."
              />
            </div>

            {/* User message template */}
            <div>
              <div className="flex items-center justify-between mb-1">
                <label className="text-xs text-muted">Template message utilisateur</label>
                <span className="text-xs text-muted">
                  Variables: {detectedVars.length > 0 ? detectedVars.map(v => `{{${v}}}`).join(', ') : 'aucune'}
                </span>
              </div>
              <textarea
                value={form.user_message_template}
                onChange={e => setForm(f => ({ ...f, user_message_template: e.target.value }))}
                rows={8}
                className="w-full px-3 py-2 bg-bg border border-border rounded-lg text-sm text-white font-mono focus:outline-none focus:border-violet resize-y"
                placeholder="Write an article about {{topic}} in {{language}} for {{country}}..."
              />
              <div className="flex flex-wrap gap-1.5 mt-2">
                {AVAILABLE_VARIABLES.map(v => (
                  <button
                    key={v}
                    type="button"
                    onClick={() => setForm(f => ({
                      ...f,
                      user_message_template: f.user_message_template + v,
                    }))}
                    className="px-2 py-0.5 bg-surface2 hover:bg-surface2/80 text-muted rounded text-xs font-mono transition"
                  >
                    {v}
                  </button>
                ))}
              </div>
            </div>

            {/* Row 2: Model, Temperature, Max tokens, Active */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className="text-xs text-muted block mb-1">Modele</label>
                <select
                  value={form.model}
                  onChange={e => setForm(f => ({ ...f, model: e.target.value }))}
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                >
                  {MODELS.map(m => (
                    <option key={m} value={m}>{m}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">
                  Temperature: {form.temperature.toFixed(1)}
                </label>
                <input
                  type="range"
                  min={0}
                  max={1}
                  step={0.1}
                  value={form.temperature}
                  onChange={e => setForm(f => ({ ...f, temperature: parseFloat(e.target.value) }))}
                  className="w-full mt-1.5 accent-violet"
                />
              </div>
              <div>
                <label className="text-xs text-muted block mb-1">Max tokens</label>
                <input
                  type="number"
                  value={form.max_tokens}
                  onChange={e => setForm(f => ({ ...f, max_tokens: Number(e.target.value) }))}
                  min={256}
                  max={16384}
                  className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                />
              </div>
              <div className="flex items-end">
                <label className="flex items-center gap-2 text-sm text-white">
                  <input
                    type="checkbox"
                    checked={form.is_active}
                    onChange={e => setForm(f => ({ ...f, is_active: e.target.checked }))}
                    className="rounded border-border bg-surface2 text-violet focus:ring-violet"
                  />
                  Actif
                </label>
              </div>
            </div>

            {saveError && <p className="text-danger text-sm">{saveError}</p>}

            {/* Actions */}
            <div className="flex gap-3">
              <button
                type="submit"
                disabled={saving}
                className="px-4 py-2 bg-violet hover:bg-violet/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
              >
                {saving ? 'Sauvegarde...' : (editingId ? 'Mettre a jour' : 'Creer')}
              </button>
              {editingId && (
                <button
                  type="button"
                  onClick={handleOpenTest}
                  className="px-4 py-2 bg-amber hover:bg-amber text-white rounded-lg text-sm font-medium transition"
                >
                  Tester le prompt
                </button>
              )}
              <button
                type="button"
                onClick={() => { setShowForm(false); setEditingId(null); setShowTest(false); }}
                className="px-4 py-2 bg-surface2 hover:bg-surface2/80 text-white rounded-lg text-sm transition"
              >
                Annuler
              </button>
            </div>
          </form>

          {/* Test panel */}
          {showTest && editingId && (
            <div className="mt-6 pt-6 border-t border-border space-y-4">
              <h3 className="text-white font-medium">Tester le prompt</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {detectedVars.map(varName => (
                  <div key={varName}>
                    <label className="text-xs text-muted block mb-1 font-mono">{`{{${varName}}}`}</label>
                    <input
                      type="text"
                      value={testVars[varName] ?? ''}
                      onChange={e => setTestVars(prev => ({ ...prev, [varName]: e.target.value }))}
                      className="w-full px-3 py-2 bg-surface2 border border-border rounded-lg text-sm text-white focus:outline-none focus:border-violet"
                      placeholder={varName}
                    />
                  </div>
                ))}
              </div>
              <button
                onClick={handleRunTest}
                disabled={testing}
                className="px-4 py-2 bg-success hover:bg-success/90 disabled:opacity-50 text-white rounded-lg text-sm font-medium transition"
              >
                {testing ? (
                  <span className="flex items-center gap-2">
                    <span className="animate-spin rounded-full h-4 w-4 border-b-2 border-white" />
                    Execution...
                  </span>
                ) : 'Executer'}
              </button>

              {testError && <p className="text-danger text-sm">{testError}</p>}

              {testResult && (
                <div className="bg-bg rounded-lg p-4 border border-border">
                  <h4 className="text-xs text-muted mb-2 uppercase">Reponse IA</h4>
                  <div className="text-sm text-white whitespace-pre-wrap max-h-96 overflow-y-auto">
                    {testResult}
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
