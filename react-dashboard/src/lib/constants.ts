import type { ContactType, ContactCategory, PipelineStatus } from '../types/influenceur';
import { countriesData, type CountryData } from '../data/countries-full';

// ============================================================
// CONTACT TYPES — Hardcoded defaults + dynamic types from API
// New types created in admin console are loaded via useContactTypes()
// ============================================================

export interface ContactTypeConfig {
  value: ContactType;
  label: string;
  icon: string;
  color: string;        // hex
  bg: string;           // tailwind bg class
  text: string;         // tailwind text class
}

// Default configs for known types (fallback when API not loaded yet)
export const DEFAULT_CONTACT_TYPES: ContactTypeConfig[] = [
  // Institutionnel
  { value: 'consulat',               label: 'Consulats & Ambassades',    icon: '🏛️', color: '#6366F1', bg: 'bg-indigo-500/20',  text: 'text-indigo-400' },
  { value: 'association',            label: 'Associations',              icon: '🤝', color: '#EC4899', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
  { value: 'ecole',                  label: 'Écoles & Formation',        icon: '🏫', color: '#10B981', bg: 'bg-emerald-500/20',  text: 'text-emerald-400' },
  { value: 'institut_culturel',      label: 'Instituts culturels',       icon: '🎭', color: '#8B5CF6', bg: 'bg-violet-500/20',   text: 'text-violet-400' },
  { value: 'chambre_commerce',       label: 'Chambres de commerce',      icon: '🏢', color: '#14B8A6', bg: 'bg-teal-500/20',     text: 'text-teal-400' },
  // Médias & Influence
  { value: 'presse',                 label: 'Presse & Médias',           icon: '📺', color: '#E11D48', bg: 'bg-rose-600/20',     text: 'text-rose-400' },
  { value: 'blog',                   label: 'Blogs & Créateurs',         icon: '📝', color: '#A855F7', bg: 'bg-purple-500/20',   text: 'text-purple-400' },
  { value: 'podcast_radio',          label: 'Podcasts & Radios',         icon: '🎙️', color: '#F97316', bg: 'bg-orange-500/20',   text: 'text-orange-400' },
  { value: 'influenceur',            label: 'Influenceurs',              icon: '✨', color: '#FFD60A', bg: 'bg-yellow-400/20',   text: 'text-yellow-300' },
  { value: 'youtubeur',              label: 'YouTubeurs',                icon: '▶️', color: '#FF0000', bg: 'bg-red-600/20',      text: 'text-red-400' },
  // Services B2B
  { value: 'avocat',                 label: 'Avocats',                   icon: '⚖️', color: '#8B5CF6', bg: 'bg-violet-500/20',   text: 'text-violet-400' },
  { value: 'immobilier',             label: 'Immobilier & Relocation',   icon: '🏠', color: '#84CC16', bg: 'bg-lime-500/20',     text: 'text-lime-400' },
  { value: 'assurance',              label: 'Assurances',                icon: '🛡️', color: '#3B82F6', bg: 'bg-blue-500/20',     text: 'text-blue-400' },
  { value: 'banque_fintech',         label: 'Banques & Fintechs',        icon: '🏦', color: '#0EA5E9', bg: 'bg-sky-500/20',      text: 'text-sky-400' },
  { value: 'traducteur',             label: 'Traducteurs',               icon: '🌐', color: '#06B6D4', bg: 'bg-cyan-500/20',     text: 'text-cyan-400' },
  { value: 'agence_voyage',          label: 'Agences de voyage',         icon: '✈️', color: '#06B6D4', bg: 'bg-cyan-500/20',     text: 'text-cyan-400' },
  { value: 'emploi',                 label: 'Emploi & Remote',           icon: '💼', color: '#78716C', bg: 'bg-stone-500/20',    text: 'text-stone-400' },
  // Communautés & Lieux
  { value: 'communaute_expat',       label: 'Communautés expat',         icon: '🌍', color: '#F472B6', bg: 'bg-pink-400/20',     text: 'text-pink-300' },
  { value: 'groupe_whatsapp_telegram',label: 'Groupes WhatsApp/Telegram', icon: '💬', color: '#22C55E', bg: 'bg-green-500/20',    text: 'text-green-400' },
  { value: 'coworking_coliving',     label: 'Coworkings & Colivings',    icon: '🏡', color: '#D97706', bg: 'bg-amber-600/20',    text: 'text-amber-500' },
  { value: 'logement',               label: 'Logement international',    icon: '🔑', color: '#EAB308', bg: 'bg-yellow-500/20',   text: 'text-yellow-400' },
  { value: 'lieu_communautaire',     label: 'Lieux communautaires',      icon: '☕', color: '#FB923C', bg: 'bg-orange-400/20',   text: 'text-orange-300' },
  // Digital & Technique
  { value: 'backlink',               label: 'Backlinks & SEO',           icon: '🔗', color: '#F59E0B', bg: 'bg-amber-500/20',    text: 'text-amber-400' },
  { value: 'annuaire',               label: 'Annuaires',                 icon: '📚', color: '#A3A3A3', bg: 'bg-neutral-500/20',  text: 'text-neutral-400' },
  { value: 'plateforme_nomad',       label: 'Plateformes nomad',         icon: '🧭', color: '#2DD4BF', bg: 'bg-teal-400/20',     text: 'text-teal-300' },
  { value: 'partenaire',             label: 'Partenaires B2B',           icon: '🤝', color: '#D97706', bg: 'bg-amber-600/20',    text: 'text-amber-500' },
];

// Mutable list: starts with defaults, merged with API types at runtime
export let CONTACT_TYPES: ContactTypeConfig[] = [...DEFAULT_CONTACT_TYPES];

const DEFAULT_TYPE_MAP = Object.fromEntries(
  DEFAULT_CONTACT_TYPES.map(t => [t.value, t])
) as Record<string, ContactTypeConfig>;

export let CONTACT_TYPE_MAP: Record<string, ContactTypeConfig> = { ...DEFAULT_TYPE_MAP };

/**
 * Merge API-loaded contact types into the runtime list.
 * Called by useContactTypes() hook after loading from /enums.
 */
export function mergeApiContactTypes(apiTypes: Array<{ value: string; label: string; icon: string; color: string }>) {
  const merged = new Map<string, ContactTypeConfig>();

  // Start with defaults
  for (const t of DEFAULT_CONTACT_TYPES) {
    merged.set(t.value, t);
  }

  // Overlay API types (adds new ones, updates existing labels/icons/colors)
  for (const api of apiTypes) {
    const existing = merged.get(api.value);
    if (existing) {
      // Update label/icon from DB (admin may have changed them)
      merged.set(api.value, { ...existing, label: api.label, icon: api.icon, color: api.color });
    } else {
      // New type from DB — generate tailwind classes from hex color
      merged.set(api.value, {
        value: api.value,
        label: api.label,
        icon: api.icon,
        color: api.color,
        bg: 'bg-gray-500/20',
        text: 'text-gray-300',
      });
    }
  }

  CONTACT_TYPES = Array.from(merged.values());
  CONTACT_TYPE_MAP = Object.fromEntries(CONTACT_TYPES.map(t => [t.value, t]));
}

// Default fallback for unknown types
const FALLBACK_TYPE: ContactTypeConfig = {
  value: 'unknown', label: 'Autre', icon: '📁', color: '#6B7280',
  bg: 'bg-gray-500/20', text: 'text-gray-400',
};

export function getContactType(type: ContactType): ContactTypeConfig {
  return CONTACT_TYPE_MAP[type] ?? { ...FALLBACK_TYPE, value: type, label: type };
}

// ============================================================
// CATÉGORIES DE CONTACTS — 5 groupes principaux
// ============================================================

export interface CategoryConfig {
  value: ContactCategory;
  label: string;
  icon: string;
  color: string;
  bg: string;
  text: string;
  border: string;
  types: ContactType[];
}

export const CONTACT_CATEGORIES: CategoryConfig[] = [
  {
    value: 'institutionnel',
    label: 'Institutionnel',
    icon: '🏛️',
    color: '#6366F1',
    bg: 'bg-indigo-500/20',
    text: 'text-indigo-400',
    border: 'border-indigo-500/40',
    types: ['consulat', 'association', 'ecole', 'institut_culturel', 'chambre_commerce'],
  },
  {
    value: 'medias_influence',
    label: 'Médias & Influence',
    icon: '📺',
    color: '#E11D48',
    bg: 'bg-rose-500/20',
    text: 'text-rose-400',
    border: 'border-rose-500/40',
    types: ['presse', 'blog', 'podcast_radio', 'influenceur', 'youtubeur'],
  },
  {
    value: 'services_b2b',
    label: 'Services B2B',
    icon: '💼',
    color: '#8B5CF6',
    bg: 'bg-violet-500/20',
    text: 'text-violet-400',
    border: 'border-violet-500/40',
    types: ['avocat', 'immobilier', 'assurance', 'banque_fintech', 'traducteur', 'agence_voyage', 'emploi'],
  },
  {
    value: 'communautes',
    label: 'Communautés',
    icon: '🌍',
    color: '#10B981',
    bg: 'bg-emerald-500/20',
    text: 'text-emerald-400',
    border: 'border-emerald-500/40',
    types: ['communaute_expat', 'groupe_whatsapp_telegram', 'coworking_coliving', 'logement', 'lieu_communautaire'],
  },
  {
    value: 'digital',
    label: 'Digital & SEO',
    icon: '🔗',
    color: '#F59E0B',
    bg: 'bg-amber-500/20',
    text: 'text-amber-400',
    border: 'border-amber-500/40',
    types: ['backlink', 'annuaire', 'plateforme_nomad', 'partenaire'],
  },
];

export const CATEGORY_MAP: Record<string, CategoryConfig> = Object.fromEntries(
  CONTACT_CATEGORIES.map(c => [c.value, c])
);

/** Retourne la config de catégorie pour un contact_type donné. */
export function getCategoryForType(type: ContactType): CategoryConfig {
  const cat = CONTACT_CATEGORIES.find(c => c.types.includes(type));
  return cat ?? {
    value: 'autre',
    label: 'Autre',
    icon: '📁',
    color: '#6B7280',
    bg: 'bg-gray-500/20',
    text: 'text-gray-400',
    border: 'border-gray-500/40',
    types: [],
  };
}

/** Retourne la config d'une catégorie par sa valeur. */
export function getCategory(category: ContactCategory | string | null): CategoryConfig {
  return CATEGORY_MAP[category ?? ''] ?? {
    value: 'autre' as ContactCategory,
    label: 'Autre',
    icon: '📁',
    color: '#6B7280',
    bg: 'bg-gray-500/20',
    text: 'text-gray-400',
    border: 'border-gray-500/40',
    types: [],
  };
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
  { code: 'fr', label: 'Français',   flag: '🇫🇷' },
  { code: 'en', label: 'English',    flag: '🇬🇧' },
  { code: 'de', label: 'Deutsch',    flag: '🇩🇪' },
  { code: 'es', label: 'Español',    flag: '🇪🇸' },
  { code: 'pt', label: 'Português',  flag: '🇵🇹' },
  { code: 'ar', label: 'العربية',    flag: '🇸🇦' },
  { code: 'ru', label: 'Русский',    flag: '🇷🇺' },
  { code: 'zh', label: '中文',        flag: '🇨🇳' },
  { code: 'hi', label: 'हिन्दी',      flag: '🇮🇳' },
  { code: 'it', label: 'Italiano',   flag: '🇮🇹' },
  { code: 'nl', label: 'Nederlands', flag: '🇳🇱' },
  { code: 'pl', label: 'Polski',     flag: '🇵🇱' },
  { code: 'lt', label: 'Lietuvių',   flag: '🇱🇹' },
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
