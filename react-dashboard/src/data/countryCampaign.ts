/**
 * Country Campaign — ISO code to name/flag mapping for campaign countries.
 */
export interface CampaignCountry {
  code: string;
  name: string;
  flag: string;
}

export const CAMPAIGN_COUNTRIES: Record<string, CampaignCountry> = {
  TH: { code: 'TH', name: 'Thailande', flag: '\u{1F1F9}\u{1F1ED}' },
  US: { code: 'US', name: 'Etats-Unis', flag: '\u{1F1FA}\u{1F1F8}' },
  VN: { code: 'VN', name: 'Vietnam', flag: '\u{1F1FB}\u{1F1F3}' },
  SG: { code: 'SG', name: 'Singapour', flag: '\u{1F1F8}\u{1F1EC}' },
  PT: { code: 'PT', name: 'Portugal', flag: '\u{1F1F5}\u{1F1F9}' },
  ES: { code: 'ES', name: 'Espagne', flag: '\u{1F1EA}\u{1F1F8}' },
  ID: { code: 'ID', name: 'Indonesie', flag: '\u{1F1EE}\u{1F1E9}' },
  MX: { code: 'MX', name: 'Mexique', flag: '\u{1F1F2}\u{1F1FD}' },
  MA: { code: 'MA', name: 'Maroc', flag: '\u{1F1F2}\u{1F1E6}' },
  AE: { code: 'AE', name: 'Emirats arabes unis', flag: '\u{1F1E6}\u{1F1EA}' },
  JP: { code: 'JP', name: 'Japon', flag: '\u{1F1EF}\u{1F1F5}' },
  DE: { code: 'DE', name: 'Allemagne', flag: '\u{1F1E9}\u{1F1EA}' },
  GB: { code: 'GB', name: 'Royaume-Uni', flag: '\u{1F1EC}\u{1F1E7}' },
  CA: { code: 'CA', name: 'Canada', flag: '\u{1F1E8}\u{1F1E6}' },
  AU: { code: 'AU', name: 'Australie', flag: '\u{1F1E6}\u{1F1FA}' },
  BR: { code: 'BR', name: 'Bresil', flag: '\u{1F1E7}\u{1F1F7}' },
  CO: { code: 'CO', name: 'Colombie', flag: '\u{1F1E8}\u{1F1F4}' },
  CR: { code: 'CR', name: 'Costa Rica', flag: '\u{1F1E8}\u{1F1F7}' },
  GR: { code: 'GR', name: 'Grece', flag: '\u{1F1EC}\u{1F1F7}' },
  HR: { code: 'HR', name: 'Croatie', flag: '\u{1F1ED}\u{1F1F7}' },
  IT: { code: 'IT', name: 'Italie', flag: '\u{1F1EE}\u{1F1F9}' },
  NL: { code: 'NL', name: 'Pays-Bas', flag: '\u{1F1F3}\u{1F1F1}' },
  BE: { code: 'BE', name: 'Belgique', flag: '\u{1F1E7}\u{1F1EA}' },
  CH: { code: 'CH', name: 'Suisse', flag: '\u{1F1E8}\u{1F1ED}' },
  TR: { code: 'TR', name: 'Turquie', flag: '\u{1F1F9}\u{1F1F7}' },
  PH: { code: 'PH', name: 'Philippines', flag: '\u{1F1F5}\u{1F1ED}' },
  MY: { code: 'MY', name: 'Malaisie', flag: '\u{1F1F2}\u{1F1FE}' },
  KH: { code: 'KH', name: 'Cambodge', flag: '\u{1F1F0}\u{1F1ED}' },
  IN: { code: 'IN', name: 'Inde', flag: '\u{1F1EE}\u{1F1F3}' },
  PL: { code: 'PL', name: 'Pologne', flag: '\u{1F1F5}\u{1F1F1}' },
  FR: { code: 'FR', name: 'France', flag: '\u{1F1EB}\u{1F1F7}' },
  RO: { code: 'RO', name: 'Roumanie', flag: '\u{1F1F7}\u{1F1F4}' },
  CZ: { code: 'CZ', name: 'Republique tcheque', flag: '\u{1F1E8}\u{1F1FF}' },
  HU: { code: 'HU', name: 'Hongrie', flag: '\u{1F1ED}\u{1F1FA}' },
  SE: { code: 'SE', name: 'Suede', flag: '\u{1F1F8}\u{1F1EA}' },
  NO: { code: 'NO', name: 'Norvege', flag: '\u{1F1F3}\u{1F1F4}' },
  DK: { code: 'DK', name: 'Danemark', flag: '\u{1F1E9}\u{1F1F0}' },
  FI: { code: 'FI', name: 'Finlande', flag: '\u{1F1EB}\u{1F1EE}' },
  IE: { code: 'IE', name: 'Irlande', flag: '\u{1F1EE}\u{1F1EA}' },
  NZ: { code: 'NZ', name: 'Nouvelle-Zelande', flag: '\u{1F1F3}\u{1F1FF}' },
  ZA: { code: 'ZA', name: 'Afrique du Sud', flag: '\u{1F1FF}\u{1F1E6}' },
  KR: { code: 'KR', name: 'Coree du Sud', flag: '\u{1F1F0}\u{1F1F7}' },
  TW: { code: 'TW', name: 'Taiwan', flag: '\u{1F1F9}\u{1F1FC}' },
  CL: { code: 'CL', name: 'Chili', flag: '\u{1F1E8}\u{1F1F1}' },
  AR: { code: 'AR', name: 'Argentine', flag: '\u{1F1E6}\u{1F1F7}' },
  PE: { code: 'PE', name: 'Perou', flag: '\u{1F1F5}\u{1F1EA}' },
  EG: { code: 'EG', name: 'Egypte', flag: '\u{1F1EA}\u{1F1EC}' },
  KE: { code: 'KE', name: 'Kenya', flag: '\u{1F1F0}\u{1F1EA}' },
  TN: { code: 'TN', name: 'Tunisie', flag: '\u{1F1F9}\u{1F1F3}' },
  SN: { code: 'SN', name: 'Senegal', flag: '\u{1F1F8}\u{1F1F3}' },
  CI: { code: 'CI', name: "Cote d'Ivoire", flag: '\u{1F1E8}\u{1F1EE}' },
};

export function getCountryInfo(code: string): CampaignCountry {
  return CAMPAIGN_COUNTRIES[code] || { code, name: code, flag: '\u{1F3F3}\u{FE0F}' };
}

/** All codes sorted alphabetically by name, for the "add country" dropdown. */
export const ALL_CAMPAIGN_CODES = Object.keys(CAMPAIGN_COUNTRIES).sort(
  (a, b) => CAMPAIGN_COUNTRIES[a].name.localeCompare(CAMPAIGN_COUNTRIES[b].name),
);
