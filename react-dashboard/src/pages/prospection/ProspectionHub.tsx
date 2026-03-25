import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

interface HubStats {
  emails_sent_week: number;
  pending_review: number;
  active_sequences: number;
  eligible_contacts: number;
  configured_types: number;
  bounce_rate: number;
  alerts_count: number;
}

export default function ProspectionHub() {
  const [stats, setStats] = useState<HubStats | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const [statsRes, alertsRes, seqRes] = await Promise.all([
          api.get('/outreach/stats'),
          api.get('/outreach/alerts').catch(() => ({ data: [] })),
          api.get('/outreach/sequences?status=active').catch(() => ({ data: { total: 0 } })),
        ]);
        const g = statsRes.data.global;
        setStats({
          emails_sent_week: g?.sent || 0,
          pending_review: g?.pending_review || 0,
          active_sequences: seqRes.data?.total || seqRes.data?.data?.length || 0,
          eligible_contacts: g?.total || 0,
          configured_types: statsRes.data.by_type?.length || 0,
          bounce_rate: g?.total > 0 ? Math.round((g?.bounced || 0) / Math.max(g?.sent || 1, 1) * 100) : 0,
          alerts_count: Array.isArray(alertsRes.data) ? alertsRes.data.length : 0,
        });
      } catch { /* ignore */ }
      setLoading(false);
    })();
  }, []);

  if (loading) return <div className="flex items-center justify-center h-64"><div className="w-8 h-8 border-2 border-violet border-t-transparent rounded-full animate-spin" /></div>;

  const cards = [
    {
      to: '/prospection/overview',
      icon: '📊', title: 'Vue d\'ensemble',
      description: 'KPIs, funnel de conversion, graphiques, alertes',
      stat: stats ? `${stats.emails_sent_week} emails envoyes` : '—',
      statColor: 'text-cyan',
      alert: stats && stats.alerts_count > 0 ? `${stats.alerts_count} alerte${stats.alerts_count > 1 ? 's' : ''}` : null,
    },
    {
      to: '/prospection/emails',
      icon: '✉️', title: 'Emails',
      description: 'Generer, reviewer, approuver et suivre les emails',
      stat: stats ? `${stats.pending_review} en review` : '—',
      statColor: stats && stats.pending_review > 0 ? 'text-amber' : 'text-muted',
    },
    {
      to: '/prospection/sequences',
      icon: '🔄', title: 'Sequences',
      description: 'Suivi des sequences multi-step par contact',
      stat: stats ? `${stats.active_sequences} actives` : '—',
      statColor: 'text-emerald-400',
    },
    {
      to: '/prospection/contacts',
      icon: '👥', title: 'Contacts eligibles',
      description: 'Contacts avec email verifie, prets pour la prospection',
      stat: stats ? `${stats.eligible_contacts} contacts` : '—',
      statColor: 'text-white',
    },
    {
      to: '/prospection/config',
      icon: '⚙️', title: 'Configuration',
      description: 'Types, Calendly, prompts IA, domaines, warmup',
      stat: stats ? `${stats.configured_types} types` : '—',
      statColor: 'text-muted',
    },
  ];

  return (
    <div className="p-4 md:p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-title font-bold text-white">Prospection Email</h1>
        <p className="text-muted text-sm mt-1">Generation IA, review, envoi et suivi des emails de prospection</p>
      </div>

      {/* Alert banner */}
      {stats && stats.alerts_count > 0 && (
        <div className="bg-red-500/10 border border-red-500/30 rounded-xl p-4 flex items-center gap-3">
          <span className="text-red-400 text-lg">⚠</span>
          <div>
            <p className="text-red-400 text-sm font-medium">{stats.alerts_count} alerte{stats.alerts_count > 1 ? 's' : ''} active{stats.alerts_count > 1 ? 's' : ''}</p>
            <p className="text-red-400/60 text-xs">Verifiez la vue d'ensemble pour plus de details</p>
          </div>
        </div>
      )}

      {/* Hub cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {cards.map(card => (
          <Link key={card.to} to={card.to}
            className="bg-surface border border-border rounded-xl p-6 hover:border-violet/50 transition-all group">
            <div className="flex items-start justify-between">
              <div className="flex items-center gap-3 mb-3">
                <span className="text-2xl">{card.icon}</span>
                <h3 className="text-white font-title font-semibold group-hover:text-violet-light transition-colors">{card.title}</h3>
              </div>
              {card.alert && (
                <span className="px-2 py-0.5 bg-red-500/20 text-red-400 text-[10px] rounded-full font-medium">{card.alert}</span>
              )}
            </div>
            <p className="text-muted text-sm mb-4">{card.description}</p>
            <div className={`text-lg font-bold font-title ${card.statColor}`}>{card.stat}</div>
          </Link>
        ))}
      </div>

      {/* Quick actions */}
      <div className="bg-surface border border-border rounded-xl p-5">
        <h3 className="text-white font-title font-semibold mb-3">Actions rapides</h3>
        <div className="flex flex-wrap gap-3">
          <Link to="/prospection/emails" className="px-4 py-2 bg-violet hover:bg-violet/90 text-white text-sm rounded-lg transition-colors">
            Generer des emails
          </Link>
          {stats && stats.pending_review > 0 && (
            <Link to="/prospection/emails" className="px-4 py-2 bg-amber/20 text-amber text-sm rounded-lg hover:bg-amber/30 transition-colors">
              Reviewer {stats.pending_review} email{stats.pending_review > 1 ? 's' : ''}
            </Link>
          )}
          <Link to="/prospection/config" className="px-4 py-2 bg-surface2 text-muted hover:text-white text-sm rounded-lg border border-border transition-colors">
            Configurer les types
          </Link>
        </div>
      </div>
    </div>
  );
}
