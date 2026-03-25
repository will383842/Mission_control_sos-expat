import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { CONTACT_TYPES } from '../../lib/constants';

interface Config {
  id: number; contact_type: string; auto_send: boolean; ai_generation_enabled: boolean;
  max_steps: number; step_delays: number[]; daily_limit: number; is_active: boolean;
  calendly_url: string | null; calendly_step: number | null;
  custom_prompt: string | null; from_name: string | null;
}

interface DomainInfo {
  domain: string; total_sent: number; total_bounced: number;
  bounce_rate: number; is_blacklisted: boolean; is_paused: boolean;
}

interface WarmupInfo {
  from_email: string; domain: string; day_count: number;
  emails_sent_today: number; current_daily_limit: number;
}

const STEP_LABELS = ['Premier contact', 'Relance J+3', 'Relance J+7', 'Dernier message J+14'];

export default function ProspectionConfig() {
  const [configs, setConfigs] = useState<Config[]>([]);
  const [domains, setDomains] = useState<DomainInfo[]>([]);
  const [warmup, setWarmup] = useState<WarmupInfo[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedType, setExpandedType] = useState<string | null>(null);
  const [saving, setSaving] = useState<string | null>(null);

  const fetchData = async () => {
    setLoading(true);
    try {
      const [configRes, healthRes] = await Promise.all([
        api.get('/outreach/config'),
        api.get('/outreach/domain-health').catch(() => ({ data: { domains: [], warmup: [] } })),
      ]);
      setConfigs(configRes.data || []);
      setDomains(healthRes.data.domains || []);
      setWarmup(healthRes.data.warmup || []);
    } catch { /* ignore */ }
    setLoading(false);
  };

  useEffect(() => { fetchData(); }, []);

  const updateField = async (contactType: string, field: string, value: any) => {
    setSaving(contactType);
    try {
      await api.put(`/outreach/config/${contactType}`, { [field]: value });
      setConfigs(prev => prev.map(c => c.contact_type === contactType ? { ...c, [field]: value } : c));
    } catch { /* ignore */ }
    setSaving(null);
  };

  const updateStepDelay = async (contactType: string, stepIndex: number, days: number) => {
    const config = configs.find(c => c.contact_type === contactType);
    const delays = [...(config?.step_delays || [0, 3, 7, 14])];
    delays[stepIndex] = days;
    await updateField(contactType, 'step_delays', delays);
  };

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">&larr; Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Configuration</h1>
      </div>

      {/* Config per type */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border">
          <h3 className="text-white font-title font-semibold">Configuration par type de contact</h3>
          <p className="text-muted text-xs mt-1">Cliquez sur un type pour configurer les delais, Calendly, prompt IA et expediteur</p>
        </div>
        <div className="divide-y divide-border">
          {CONTACT_TYPES.map(t => {
            const config = configs.find(c => c.contact_type === t.value);
            const isExpanded = expandedType === t.value;
            const delays = config?.step_delays || [0, 3, 7, 14];
            return (
              <div key={t.value}>
                {/* Main row */}
                <div className="flex items-center px-5 py-3 hover:bg-surface2/50 cursor-pointer"
                  onClick={() => setExpandedType(isExpanded ? null : t.value)}>
                  <span className="text-xs text-muted mr-2">{isExpanded ? '▼' : '▶'}</span>
                  <span className="flex items-center gap-2 flex-1 min-w-0">
                    <span>{t.icon}</span>
                    <span className="text-white text-sm font-medium truncate">{t.label}</span>
                    {saving === t.value && <span className="w-3 h-3 border border-violet border-t-transparent rounded-full animate-spin flex-shrink-0" />}
                  </span>
                  <div className="flex items-center gap-4 flex-shrink-0" onClick={e => e.stopPropagation()}>
                    <label className="flex items-center gap-2 text-xs">
                      <input type="checkbox" checked={config?.ai_generation_enabled ?? true}
                        onChange={e => updateField(t.value, 'ai_generation_enabled', e.target.checked)}
                        className="rounded border-gray-600 bg-bg text-violet focus:ring-violet" />
                      <span className="text-muted">IA</span>
                    </label>
                    <label className="flex items-center gap-2 text-xs">
                      <input type="checkbox" checked={config?.auto_send ?? false}
                        onChange={e => updateField(t.value, 'auto_send', e.target.checked)}
                        className="rounded border-gray-600 bg-bg text-violet focus:ring-violet" />
                      <span className="text-muted">Auto-send</span>
                    </label>
                    <span className="text-xs text-muted w-16 text-right">{config?.daily_limit ?? 50}/jour</span>
                  </div>
                </div>

                {/* Expanded config */}
                {isExpanded && (
                  <div className="px-5 py-5 bg-surface2/20 border-t border-border/50 space-y-5">
                    {/* Sequence flow visualization */}
                    <div>
                      <label className="block text-xs text-muted mb-3 font-medium">Delais entre les steps (jours)</label>
                      <div className="flex items-center gap-2 flex-wrap">
                        {delays.map((delay, i) => (
                          <React.Fragment key={i}>
                            {i > 0 && (
                              <div className="flex items-center gap-1">
                                <span className="text-muted text-xs">&rarr;</span>
                                <input
                                  type="number"
                                  min={0}
                                  max={60}
                                  defaultValue={delay}
                                  onBlur={e => updateStepDelay(t.value, i, Number(e.target.value))}
                                  className="w-12 bg-bg border border-border rounded px-2 py-1 text-white text-xs text-center focus:border-violet outline-none"
                                />
                                <span className="text-[10px] text-muted">j</span>
                                <span className="text-muted text-xs">&rarr;</span>
                              </div>
                            )}
                            <div className={`px-3 py-2 rounded-lg text-center min-w-[80px] ${
                              i === 0 ? 'bg-cyan/20 text-cyan' :
                              i === 1 ? 'bg-blue-500/20 text-blue-400' :
                              i === 2 ? 'bg-violet/20 text-violet-light' :
                              'bg-purple-500/20 text-purple-400'
                            }`}>
                              <div className="text-xs font-bold">Step {i + 1}</div>
                              <div className="text-[10px] opacity-70">{STEP_LABELS[i]}</div>
                            </div>
                          </React.Fragment>
                        ))}
                      </div>
                      <p className="text-[10px] text-muted mt-2">
                        Duree totale de la sequence: {delays.reduce((a, b) => a + b, 0)} jours
                      </p>
                    </div>

                    {/* Settings grid */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                      <div>
                        <label className="block text-xs text-muted mb-1">Calendly URL</label>
                        <input type="url" defaultValue={config?.calendly_url || ''}
                          onBlur={e => updateField(t.value, 'calendly_url', e.target.value || null)}
                          placeholder="https://calendly.com/..."
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
                      </div>
                      <div>
                        <label className="block text-xs text-muted mb-1">Calendly dans quel step ?</label>
                        <select defaultValue={config?.calendly_step ?? ''}
                          onChange={e => updateField(t.value, 'calendly_step', e.target.value ? Number(e.target.value) : null)}
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none">
                          <option value="">Jamais</option>
                          {[1, 2, 3, 4].map(s => <option key={s} value={s}>Step {s} — {STEP_LABELS[s - 1]}</option>)}
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs text-muted mb-1">Nom expediteur</label>
                        <input defaultValue={config?.from_name || 'Williams'}
                          onBlur={e => updateField(t.value, 'from_name', e.target.value || 'Williams')}
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
                      </div>
                      <div>
                        <label className="block text-xs text-muted mb-1">Limite emails/jour</label>
                        <input type="number" defaultValue={config?.daily_limit ?? 50} min={1} max={200}
                          onBlur={e => updateField(t.value, 'daily_limit', Number(e.target.value))}
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
                      </div>
                    </div>

                    {/* Custom prompt */}
                    <div>
                      <label className="block text-xs text-muted mb-1">Prompt personnalise (ajoute au prompt systeme)</label>
                      <textarea defaultValue={config?.custom_prompt || ''} rows={3}
                        onBlur={e => updateField(t.value, 'custom_prompt', e.target.value || null)}
                        placeholder="Laissez vide pour utiliser le prompt par defaut. Ex: Insiste sur le fait que nous proposons des avocats francophones..."
                        className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none resize-y" />
                    </div>

                    {/* Max steps */}
                    <div className="flex items-center gap-4">
                      <label className="flex items-center gap-2 text-xs">
                        <span className="text-muted">Nombre de steps:</span>
                        <select defaultValue={config?.max_steps ?? 4}
                          onChange={e => updateField(t.value, 'max_steps', Number(e.target.value))}
                          className="bg-bg border border-border rounded px-2 py-1 text-white text-xs focus:border-violet outline-none">
                          {[1, 2, 3, 4].map(s => <option key={s} value={s}>{s}</option>)}
                        </select>
                      </label>
                      <label className="flex items-center gap-2 text-xs">
                        <input type="checkbox" checked={config?.is_active ?? true}
                          onChange={e => updateField(t.value, 'is_active', e.target.checked)}
                          className="rounded border-gray-600 bg-bg text-emerald-500 focus:ring-emerald-500" />
                        <span className="text-muted">Type actif</span>
                      </label>
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      </div>

      {/* Domain health */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="text-white font-title font-semibold mb-4">Domaines d'envoi & Warm-up</h3>
        {warmup.length === 0 && domains.length === 0 ? (
          <div className="text-center py-8">
            <p className="text-muted text-sm">Aucun domaine configure</p>
            <p className="text-xs text-muted/50 mt-1">Les domaines apparaitront apres le premier envoi d'email</p>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {warmup.map(w => {
              const health = domains.find(d => d.domain === w.domain);
              const pct = Math.min(w.emails_sent_today / Math.max(w.current_daily_limit, 1) * 100, 100);
              return (
                <div key={w.from_email} className="bg-surface2 rounded-xl p-4">
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-white font-medium text-sm">{w.domain}</span>
                    <div className="flex gap-1">
                      {health?.is_blacklisted && <span className="px-2 py-0.5 bg-red-500/20 text-red-400 text-[10px] rounded-full">Blackliste</span>}
                      {health?.is_paused && <span className="px-2 py-0.5 bg-amber/20 text-amber text-[10px] rounded-full">En pause</span>}
                      {!health?.is_blacklisted && !health?.is_paused && <span className="px-2 py-0.5 bg-emerald-500/20 text-emerald-400 text-[10px] rounded-full">OK</span>}
                    </div>
                  </div>
                  <div className="text-xs text-muted mb-3">{w.from_email}</div>
                  <div className="space-y-2">
                    <div className="flex justify-between text-xs">
                      <span className="text-muted">Warm-up jour {w.day_count}</span>
                      <span className={`font-mono ${pct >= 100 ? 'text-amber' : 'text-cyan'}`}>{w.emails_sent_today}/{w.current_daily_limit}</span>
                    </div>
                    <div className="w-full bg-bg rounded-full h-2.5">
                      <div className={`h-2.5 rounded-full transition-all ${pct >= 100 ? 'bg-amber' : 'bg-cyan'}`}
                        style={{ width: `${pct}%` }} />
                    </div>
                    <div className="text-[10px] text-muted">
                      Progression: Jour 1-3: 5/j &rarr; 4-7: 15/j &rarr; 8-14: 30/j &rarr; 15+: 50/j
                    </div>
                    {health && (
                      <div className="flex justify-between text-[10px] text-muted pt-1 border-t border-border/30">
                        <span>Total envoyes: <span className="text-white">{health.total_sent}</span></span>
                        <span>Bounces: <span className={health.bounce_rate > 5 ? 'text-red-400 font-bold' : 'text-white'}>{health.bounce_rate}%</span></span>
                      </div>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>
    </div>
  );
}
