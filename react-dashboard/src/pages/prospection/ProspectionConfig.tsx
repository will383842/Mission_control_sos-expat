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

  const updateMultiple = async (contactType: string, data: Record<string, any>) => {
    setSaving(contactType);
    try {
      await api.put(`/outreach/config/${contactType}`, data);
      setConfigs(prev => prev.map(c => c.contact_type === contactType ? { ...c, ...data } : c));
    } catch { /* ignore */ }
    setSaving(null);
  };

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/prospection" className="text-muted hover:text-white transition-colors text-sm">← Prospection</Link>
        <h1 className="text-2xl font-title font-bold text-white">Configuration</h1>
      </div>

      {/* Config per type */}
      <div className="bg-surface border border-border rounded-xl overflow-hidden">
        <div className="px-5 py-4 border-b border-border">
          <h3 className="text-white font-title font-semibold">Configuration par type de contact</h3>
          <p className="text-muted text-xs mt-1">Cliquez sur un type pour voir les options avancees (Calendly, prompt, expediteur)</p>
        </div>
        <div className="divide-y divide-border">
          {CONTACT_TYPES.map(t => {
            const config = configs.find(c => c.contact_type === t.value);
            const isExpanded = expandedType === t.value;
            return (
              <div key={t.value}>
                {/* Main row */}
                <div className="flex items-center px-5 py-3 hover:bg-surface2/50 cursor-pointer"
                  onClick={() => setExpandedType(isExpanded ? null : t.value)}>
                  <span className="text-xs text-muted mr-2">{isExpanded ? '▼' : '▶'}</span>
                  <span className="flex items-center gap-2 flex-1">
                    <span>{t.icon}</span>
                    <span className="text-white text-sm font-medium">{t.label}</span>
                    {saving === t.value && <span className="w-3 h-3 border border-violet border-t-transparent rounded-full animate-spin" />}
                  </span>
                  <div className="flex items-center gap-6" onClick={e => e.stopPropagation()}>
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
                  <div className="px-5 py-4 bg-surface2/20 border-t border-border/50">
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
                          <option value="1">Step 1</option>
                          <option value="2">Step 2</option>
                          <option value="3">Step 3</option>
                          <option value="4">Step 4</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs text-muted mb-1">Nom expediteur</label>
                        <input defaultValue={config?.from_name || 'Williams'}
                          onBlur={e => updateField(t.value, 'from_name', e.target.value || 'Williams')}
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
                      </div>
                      <div>
                        <label className="block text-xs text-muted mb-1">Limite/jour</label>
                        <input type="number" defaultValue={config?.daily_limit ?? 50} min={1} max={200}
                          onBlur={e => updateField(t.value, 'daily_limit', Number(e.target.value))}
                          className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none" />
                      </div>
                    </div>
                    <div className="mt-4">
                      <label className="block text-xs text-muted mb-1">Prompt personnalise (override)</label>
                      <textarea defaultValue={config?.custom_prompt || ''} rows={3}
                        onBlur={e => updateField(t.value, 'custom_prompt', e.target.value || null)}
                        placeholder="Laissez vide pour utiliser le prompt par defaut. Ajoutez des instructions specifiques ici."
                        className="w-full bg-bg border border-border rounded-lg px-3 py-2 text-white text-sm focus:border-violet outline-none resize-y font-mono" />
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
        <h3 className="text-white font-title font-semibold mb-4">Domaines d'envoi</h3>
        {warmup.length === 0 && domains.length === 0 ? (
          <p className="text-muted text-sm">Aucun domaine configure. Les domaines apparaitront apres le premier envoi.</p>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            {warmup.map(w => {
              const health = domains.find(d => d.domain === w.domain);
              return (
                <div key={w.from_email} className="bg-surface2 rounded-xl p-4">
                  <div className="flex items-center justify-between mb-3">
                    <span className="text-white font-medium text-sm">{w.domain}</span>
                    {health?.is_blacklisted && <span className="px-2 py-0.5 bg-red-500/20 text-red-400 text-[10px] rounded-full">Blackliste</span>}
                    {health?.is_paused && <span className="px-2 py-0.5 bg-amber/20 text-amber text-[10px] rounded-full">En pause</span>}
                  </div>
                  <div className="text-xs text-muted mb-2">{w.from_email}</div>
                  <div className="space-y-2">
                    <div className="flex justify-between text-xs">
                      <span className="text-muted">Warm-up jour {w.day_count}</span>
                      <span className="text-cyan font-mono">{w.emails_sent_today}/{w.current_daily_limit} aujourd'hui</span>
                    </div>
                    <div className="w-full bg-bg rounded-full h-2">
                      <div className={`h-2 rounded-full ${w.emails_sent_today >= w.current_daily_limit ? 'bg-amber' : 'bg-cyan'}`}
                        style={{ width: `${Math.min(w.emails_sent_today / Math.max(w.current_daily_limit, 1) * 100, 100)}%` }} />
                    </div>
                    {health && (
                      <div className="flex justify-between text-[10px] text-muted pt-1">
                        <span>Envoyes: {health.total_sent}</span>
                        <span className={health.bounce_rate > 5 ? 'text-red-400' : ''}>Bounce: {health.bounce_rate}%</span>
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
