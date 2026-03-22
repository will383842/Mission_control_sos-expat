import type { ContactType, PipelineStatus } from '../types/influenceur';
import { countriesData, type CountryData } from '../data/countries-full';

// ============================================================
// 19 CONTACT TYPES — icon, label, color, tailwind classes
// ============================================================

export interface ContactTypeConfig {
  value: ContactType;
  label: string;
  icon: string;
  color: string;        // hex
  bg: string;           // tailwind bg class
  text: string;         // tailwind text class
}

export const CONTACT_TYPES: ContactTypeConfig[] = [
  { value: 'school',         label: 'Écoles',              icon: '🏫', color: '#10B981', bg: 'bg-emerald-500/20',  text: 'text-emerald-400' },
  { value: 'chatter',        label: 'Chatters',            icon: '💬', color: '#FF6B6B', bg: 'bg-red-400/20',      text: 'text-red-400' },
  { value: 'tiktoker',       label: 'TikTokeurs',          icon: '🎵', color: '#FF0050', bg: 'bg-rose-500/20',     text: 'text-rose-400' },
  { value: 'youtuber',       label: 'YouTubeurs',          icon: '🎬', color: '#FF0000', bg: 'bg-red-600/20',      text: 'text-red-500' },
  { value: 'instagramer',    label: 'Instagrameurs',       icon: '📸', color: '#E1306C', bg: 'bg-pink-500/20',     text: 'text-pink-400' },
  { value: 'influenceur',    label: 'Influenceurs',        icon: '✨', color: '#FFD60A', bg: 'bg-yellow-400/20',   text: 'text-yellow-300' },
  { value: 'blogger',        label: 'Blogueurs',           icon: '📰', color: '#A855F7', bg: 'bg-purple-500/20',   text: 'text-purple-400' },
  { value: 'backlink',       label: 'Backlinks',           icon: '🔗', color: '#F59E0B', bg: 'bg-amber-500/20',    text: 'text-amber-400' },
  { value: 'association',    label: 'Associations',        icon: '🤝', color: '#EC4899', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
  { value: 'travel_agency',  label: 'Agences voyage',      icon: '✈️', color: '#06B6D4', bg: 'bg-cyan-500/20',     text: 'text-cyan-400' },
  { value: 'real_estate',    label: 'Agents immobiliers',  icon: '🏠', color: '#84CC16', bg: 'bg-lime-500/20',     text: 'text-lime-400' },
  { value: 'translator',     label: 'Traducteurs',         icon: '🌐', color: '#0EA5E9', bg: 'bg-sky-500/20',      text: 'text-sky-400' },
  { value: 'insurer',        label: 'Assureurs/B2B',       icon: '🛡️', color: '#3B82F6', bg: 'bg-blue-500/20',     text: 'text-blue-400' },
  { value: 'enterprise',     label: 'Entreprises',         icon: '🏢', color: '#14B8A6', bg: 'bg-teal-500/20',     text: 'text-teal-400' },
  { value: 'press',          label: 'Presse',              icon: '📺', color: '#E11D48', bg: 'bg-rose-600/20',     text: 'text-rose-400' },
  { value: 'partner',        label: 'Partenariats',        icon: '🏛️', color: '#D97706', bg: 'bg-amber-600/20',    text: 'text-amber-500' },
  { value: 'lawyer',         label: 'Avocats',             icon: '⚖️', color: '#8B5CF6', bg: 'bg-violet-500/20',   text: 'text-violet-400' },
  { value: 'job_board',      label: 'Sites emploi',        icon: '💼', color: '#78716C', bg: 'bg-stone-500/20',    text: 'text-stone-400' },
  { value: 'group_admin',    label: 'Group Admins',        icon: '👥', color: '#F472B6', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
];

export const CONTACT_TYPE_MAP = Object.fromEntries(
  CONTACT_TYPES.map(t => [t.value, t])
) as Record<ContactType, ContactTypeConfig>;

export function getContactType(type: ContactType): ContactTypeConfig {
  return CONTACT_TYPE_MAP[type] ?? CONTACT_TYPES[0];
}

// ============================================================
// 14 PIPELINE STATUSES
// ============================================================

export interface StatusConfig {
  value: PipelineStatus;
  label: string;
  color: string;
  bg: string;
  text: string;
}

export const PIPELINE_STATUSES: StatusConfig[] = [
  { value: 'new',          label: 'Nouveau',     color: '#4A5568', bg: 'bg-gray-600/20',    text: 'text-gray-400' },
  { value: 'prospect',     label: 'Prospect',    color: '#6B7280', bg: 'bg-gray-500/20',    text: 'text-gray-400' },
  { value: 'contacted1',   label: '1er contact', color: '#F59E0B', bg: 'bg-amber-500/20',   text: 'text-amber-400' },
  { value: 'contacted2',   label: 'Relance 1',   color: '#F97316', bg: 'bg-orange-500/20',  text: 'text-orange-400' },
  { value: 'contacted3',   label: 'Relance 2',   color: '#EF4444', bg: 'bg-red-500/20',     text: 'text-red-400' },
  { value: 'contacted',    label: 'Contacté',    color: '#F59E0B', bg: 'bg-amber-500/20',   text: 'text-amber-400' },
  { value: 'negotiating',  label: 'Négociation', color: '#D97706', bg: 'bg-amber-600/20',   text: 'text-amber-500' },
  { value: 'replied',      label: 'Répondu',     color: '#06B6D4', bg: 'bg-cyan-500/20',    text: 'text-cyan-400' },
  { value: 'meeting',      label: 'Meeting',     color: '#A855F7', bg: 'bg-purple-500/20',  text: 'text-purple-400' },
  { value: 'active',       label: 'Actif',       color: '#10B981', bg: 'bg-emerald-500/20', text: 'text-emerald-400' },
  { value: 'signed',       label: 'Signé',       color: '#10B981', bg: 'bg-green-500/20',   text: 'text-green-400' },
  { value: 'refused',      label: 'Refusé',      color: '#374151', bg: 'bg-gray-700/20',    text: 'text-gray-500' },
  { value: 'inactive',     label: 'Inactif',     color: '#6B7280', bg: 'bg-gray-500/20',    text: 'text-gray-500' },
  { value: 'lost',         label: 'Perdu',        color: '#374151', bg: 'bg-gray-700/20',    text: 'text-gray-600' },
];

export const STATUS_MAP = Object.fromEntries(
  PIPELINE_STATUSES.map(s => [s.value, s])
) as Record<PipelineStatus, StatusConfig>;

export function getStatus(status: PipelineStatus): StatusConfig {
  return STATUS_MAP[status] ?? PIPELINE_STATUSES[0];
}

// ============================================================
// COUNTRIES — Generated from SOS-Expat master file (195 countries, 10 languages)
// Source: data/countries-full.ts (shared with sos-expat.com)
// ============================================================

export { countriesData } from '../data/countries-full';
export type { CountryData } from '../data/countries-full';

// Simplified COUNTRIES array for dropdowns/filters (backward compatible)
export const COUNTRIES = countriesData
  .filter(c => c.code !== 'SEPARATOR' && !c.disabled)
  .map(c => ({ name: c.nameFr, flag: c.flag, code: c.code, phoneCode: c.phoneCode, region: c.region }))
  .sort((a, b) => a.name.localeCompare(b.name, 'fr'));

// Add aliases for backward compatibility with existing data
const ALIASES = [
  { name: 'USA',   flag: '🇺🇸', code: 'US', phoneCode: '+1', region: 'Americas' },
  { name: 'UK',    flag: '🇬🇧', code: 'GB', phoneCode: '+44', region: 'Europe' },
  { name: 'Dubaï', flag: '🇦🇪', code: 'AE', phoneCode: '+971', region: 'Asia' },
];
for (const alias of ALIASES) {
  if (!COUNTRIES.find(c => c.name === alias.name)) {
    COUNTRIES.push(alias);
  }
}

// Old hardcoded list removed — now using data/countries-full.ts

export const COUNTRY_MAP = Object.fromEntries(
  COUNTRIES.map(c => [c.name, c])
);

export function getCountryFlag(name: string): string {
  return COUNTRY_MAP[name]?.flag ?? '🌍';
}

// ============================================================
// LANGUAGES
// ============================================================

export const LANGUAGES = [
  { code: 'fr', label: 'Français',  flag: '🇫🇷' },
  { code: 'en', label: 'English',   flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch',   flag: '🇩🇪' },
  { code: 'es', label: 'Español',   flag: '🇪🇸' },
  { code: 'pt', label: 'Português', flag: '🇵🇹' },
  { code: 'ar', label: 'العربية',     flag: '🇸🇦' },
  { code: 'ru', label: 'Русский',   flag: '🇷🇺' },
  { code: 'zh', label: '中文',        flag: '🇨🇳' },
  { code: 'hi', label: 'हिन्दी',       flag: '🇮🇳' },
];

export const LANGUAGE_MAP = Object.fromEntries(
  LANGUAGES.map(l => [l.code, l])
) as Record<string, (typeof LANGUAGES)[number]>;

export function getLanguageLabel(code: string): string {
  const lang = LANGUAGES.find(l => l.code === code);
  return lang ? `${lang.flag} ${lang.label}` : code;
}

export function getLanguageFlag(code: string): string {
  return LANGUAGES.find(l => l.code === code)?.flag ?? '🌍';
}

// ============================================================
// CONTENT ENGINE METRICS CONFIG
// ============================================================

export const CONTENT_METRICS_CONFIG = [
  { key: 'landing_pages',   label: 'Landing Pages',  icon: '🌍', color: '#A855F7' },
  { key: 'articles',        label: 'Articles',        icon: '📝', color: '#3B82F6' },
  { key: 'indexed_pages',   label: 'Indexées',        icon: '🔍', color: '#10B981' },
  { key: 'top10_positions', label: 'Top 10',          icon: '🏅', color: '#06B6D4' },
  { key: 'position_zero',   label: 'Position 0',      icon: '🏆', color: '#FFD60A' },
  { key: 'ai_cited',        label: 'Citées IA',       icon: '🤖', color: '#A855F7' },
  { key: 'daily_visits',    label: 'Visites/j',       icon: '👁', color: '#14B8A6' },
  { key: 'calls_generated', label: 'Appels',          icon: '📞', color: '#10B981' },
  { key: 'revenue_cents',   label: 'Revenue €',       icon: '💰', color: '#FFD60A' },
] as const;
