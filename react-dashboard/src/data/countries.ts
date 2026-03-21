export interface CountryEntry {
  name: string;
  flag: string;
}

export interface ContinentData {
  label: string;
  countries: CountryEntry[];
}

export const CONTINENTS: Record<string, ContinentData> = {
  europe: {
    label: 'Europe',
    countries: [
      { name: 'France', flag: '🇫🇷' },
      { name: 'Germany', flag: '🇩🇪' },
      { name: 'United Kingdom', flag: '🇬🇧' },
      { name: 'Spain', flag: '🇪🇸' },
      { name: 'Italy', flag: '🇮🇹' },
      { name: 'Portugal', flag: '🇵🇹' },
      { name: 'Belgium', flag: '🇧🇪' },
      { name: 'Netherlands', flag: '🇳🇱' },
      { name: 'Switzerland', flag: '🇨🇭' },
      { name: 'Austria', flag: '🇦🇹' },
      { name: 'Sweden', flag: '🇸🇪' },
      { name: 'Norway', flag: '🇳🇴' },
      { name: 'Denmark', flag: '🇩🇰' },
      { name: 'Finland', flag: '🇫🇮' },
      { name: 'Ireland', flag: '🇮🇪' },
      { name: 'Poland', flag: '🇵🇱' },
      { name: 'Czech Republic', flag: '🇨🇿' },
      { name: 'Romania', flag: '🇷🇴' },
      { name: 'Hungary', flag: '🇭🇺' },
      { name: 'Greece', flag: '🇬🇷' },
      { name: 'Croatia', flag: '🇭🇷' },
      { name: 'Bulgaria', flag: '🇧🇬' },
      { name: 'Serbia', flag: '🇷🇸' },
      { name: 'Slovakia', flag: '🇸🇰' },
      { name: 'Slovenia', flag: '🇸🇮' },
      { name: 'Luxembourg', flag: '🇱🇺' },
      { name: 'Malta', flag: '🇲🇹' },
      { name: 'Iceland', flag: '🇮🇸' },
      { name: 'Cyprus', flag: '🇨🇾' },
      { name: 'Estonia', flag: '🇪🇪' },
      { name: 'Latvia', flag: '🇱🇻' },
      { name: 'Lithuania', flag: '🇱🇹' },
      { name: 'Ukraine', flag: '🇺🇦' },
      { name: 'Russia', flag: '🇷🇺' },
      { name: 'Albania', flag: '🇦🇱' },
      { name: 'Montenegro', flag: '🇲🇪' },
      { name: 'North Macedonia', flag: '🇲🇰' },
      { name: 'Bosnia and Herzegovina', flag: '🇧🇦' },
      { name: 'Moldova', flag: '🇲🇩' },
      { name: 'Belarus', flag: '🇧🇾' },
      { name: 'Kosovo', flag: '🇽🇰' },
      { name: 'Andorra', flag: '🇦🇩' },
      { name: 'Monaco', flag: '🇲🇨' },
      { name: 'Liechtenstein', flag: '🇱🇮' },
    ],
  },
  africa: {
    label: 'Afrique',
    countries: [
      { name: 'Morocco', flag: '🇲🇦' },
      { name: 'Tunisia', flag: '🇹🇳' },
      { name: 'Algeria', flag: '🇩🇿' },
      { name: 'Egypt', flag: '🇪🇬' },
      { name: 'Senegal', flag: '🇸🇳' },
      { name: 'Ivory Coast', flag: '🇨🇮' },
      { name: 'Cameroon', flag: '🇨🇲' },
      { name: 'South Africa', flag: '🇿🇦' },
      { name: 'Nigeria', flag: '🇳🇬' },
      { name: 'Ghana', flag: '🇬🇭' },
      { name: 'Kenya', flag: '🇰🇪' },
      { name: 'Ethiopia', flag: '🇪🇹' },
      { name: 'Tanzania', flag: '🇹🇿' },
      { name: 'Uganda', flag: '🇺🇬' },
      { name: 'Mozambique', flag: '🇲🇿' },
      { name: 'Madagascar', flag: '🇲🇬' },
      { name: 'Congo', flag: '🇨🇬' },
      { name: 'Mali', flag: '🇲🇱' },
      { name: 'Burkina Faso', flag: '🇧🇫' },
      { name: 'Niger', flag: '🇳🇪' },
      { name: 'Guinea', flag: '🇬🇳' },
      { name: 'Benin', flag: '🇧🇯' },
      { name: 'Togo', flag: '🇹🇬' },
      { name: 'Gabon', flag: '🇬🇦' },
      { name: 'Rwanda', flag: '🇷🇼' },
      { name: 'Mauritius', flag: '🇲🇺' },
      { name: 'Libya', flag: '🇱🇾' },
      { name: 'Sudan', flag: '🇸🇩' },
      { name: 'Zambia', flag: '🇿🇲' },
      { name: 'Zimbabwe', flag: '🇿🇼' },
      { name: 'Botswana', flag: '🇧🇼' },
      { name: 'Namibia', flag: '🇳🇦' },
      { name: 'Angola', flag: '🇦🇴' },
      { name: 'Chad', flag: '🇹🇩' },
      { name: 'Somalia', flag: '🇸🇴' },
      { name: 'Eritrea', flag: '🇪🇷' },
      { name: 'Djibouti', flag: '🇩🇯' },
      { name: 'Comoros', flag: '🇰🇲' },
      { name: 'Mauritania', flag: '🇲🇷' },
      { name: 'Sierra Leone', flag: '🇸🇱' },
      { name: 'Liberia', flag: '🇱🇷' },
      { name: 'Burundi', flag: '🇧🇮' },
      { name: 'Malawi', flag: '🇲🇼' },
      { name: 'Lesotho', flag: '🇱🇸' },
      { name: 'Eswatini', flag: '🇸🇿' },
      { name: 'Gambia', flag: '🇬🇲' },
      { name: 'Cape Verde', flag: '🇨🇻' },
      { name: 'Seychelles', flag: '🇸🇨' },
      { name: 'South Sudan', flag: '🇸🇸' },
      { name: 'Reunion', flag: '🇷🇪' },
    ],
  },
  americas: {
    label: 'Amériques',
    countries: [
      { name: 'USA', flag: '🇺🇸' },
      { name: 'Canada', flag: '🇨🇦' },
      { name: 'Mexico', flag: '🇲🇽' },
      { name: 'Brazil', flag: '🇧🇷' },
      { name: 'Argentina', flag: '🇦🇷' },
      { name: 'Colombia', flag: '🇨🇴' },
      { name: 'Chile', flag: '🇨🇱' },
      { name: 'Peru', flag: '🇵🇪' },
      { name: 'Venezuela', flag: '🇻🇪' },
      { name: 'Ecuador', flag: '🇪🇨' },
      { name: 'Bolivia', flag: '🇧🇴' },
      { name: 'Paraguay', flag: '🇵🇾' },
      { name: 'Uruguay', flag: '🇺🇾' },
      { name: 'Costa Rica', flag: '🇨🇷' },
      { name: 'Panama', flag: '🇵🇦' },
      { name: 'Guatemala', flag: '🇬🇹' },
      { name: 'Honduras', flag: '🇭🇳' },
      { name: 'El Salvador', flag: '🇸🇻' },
      { name: 'Nicaragua', flag: '🇳🇮' },
      { name: 'Cuba', flag: '🇨🇺' },
      { name: 'Dominican Republic', flag: '🇩🇴' },
      { name: 'Haiti', flag: '🇭🇹' },
      { name: 'Jamaica', flag: '🇯🇲' },
      { name: 'Trinidad and Tobago', flag: '🇹🇹' },
      { name: 'Puerto Rico', flag: '🇵🇷' },
      { name: 'Guadeloupe', flag: '🇬🇵' },
      { name: 'Martinique', flag: '🇲🇶' },
      { name: 'French Guiana', flag: '🇬🇫' },
      { name: 'Guyana', flag: '🇬🇾' },
      { name: 'Suriname', flag: '🇸🇷' },
      { name: 'Belize', flag: '🇧🇿' },
      { name: 'Bahamas', flag: '🇧🇸' },
      { name: 'Barbados', flag: '🇧🇧' },
    ],
  },
  asia: {
    label: 'Asie',
    countries: [
      { name: 'Japan', flag: '🇯🇵' },
      { name: 'China', flag: '🇨🇳' },
      { name: 'India', flag: '🇮🇳' },
      { name: 'South Korea', flag: '🇰🇷' },
      { name: 'Thailand', flag: '🇹🇭' },
      { name: 'Vietnam', flag: '🇻🇳' },
      { name: 'Indonesia', flag: '🇮🇩' },
      { name: 'Philippines', flag: '🇵🇭' },
      { name: 'Malaysia', flag: '🇲🇾' },
      { name: 'Singapore', flag: '🇸🇬' },
      { name: 'Taiwan', flag: '🇹🇼' },
      { name: 'Hong Kong', flag: '🇭🇰' },
      { name: 'Bangladesh', flag: '🇧🇩' },
      { name: 'Pakistan', flag: '🇵🇰' },
      { name: 'Sri Lanka', flag: '🇱🇰' },
      { name: 'Nepal', flag: '🇳🇵' },
      { name: 'Myanmar', flag: '🇲🇲' },
      { name: 'Cambodia', flag: '🇰🇭' },
      { name: 'Laos', flag: '🇱🇦' },
      { name: 'Mongolia', flag: '🇲🇳' },
      { name: 'Uzbekistan', flag: '🇺🇿' },
      { name: 'Kazakhstan', flag: '🇰🇿' },
      { name: 'Kyrgyzstan', flag: '🇰🇬' },
      { name: 'Tajikistan', flag: '🇹🇯' },
      { name: 'Turkmenistan', flag: '🇹🇲' },
      { name: 'Afghanistan', flag: '🇦🇫' },
      { name: 'Maldives', flag: '🇲🇻' },
      { name: 'Bhutan', flag: '🇧🇹' },
      { name: 'Brunei', flag: '🇧🇳' },
    ],
  },
  middle_east: {
    label: 'Moyen-Orient',
    countries: [
      { name: 'Turkey', flag: '🇹🇷' },
      { name: 'Saudi Arabia', flag: '🇸🇦' },
      { name: 'UAE', flag: '🇦🇪' },
      { name: 'Qatar', flag: '🇶🇦' },
      { name: 'Kuwait', flag: '🇰🇼' },
      { name: 'Bahrain', flag: '🇧🇭' },
      { name: 'Oman', flag: '🇴🇲' },
      { name: 'Yemen', flag: '🇾🇪' },
      { name: 'Iraq', flag: '🇮🇶' },
      { name: 'Iran', flag: '🇮🇷' },
      { name: 'Israel', flag: '🇮🇱' },
      { name: 'Palestine', flag: '🇵🇸' },
      { name: 'Jordan', flag: '🇯🇴' },
      { name: 'Lebanon', flag: '🇱🇧' },
      { name: 'Syria', flag: '🇸🇾' },
      { name: 'Armenia', flag: '🇦🇲' },
      { name: 'Georgia', flag: '🇬🇪' },
      { name: 'Azerbaijan', flag: '🇦🇿' },
    ],
  },
  oceania: {
    label: 'Océanie',
    countries: [
      { name: 'Australia', flag: '🇦🇺' },
      { name: 'New Zealand', flag: '🇳🇿' },
      { name: 'Fiji', flag: '🇫🇯' },
      { name: 'Papua New Guinea', flag: '🇵🇬' },
      { name: 'New Caledonia', flag: '🇳🇨' },
      { name: 'French Polynesia', flag: '🇵🇫' },
      { name: 'Samoa', flag: '🇼🇸' },
      { name: 'Tonga', flag: '🇹🇴' },
      { name: 'Vanuatu', flag: '🇻🇺' },
      { name: 'Guam', flag: '🇬🇺' },
    ],
  },
};

/** Flat list of all country names */
export const ALL_COUNTRIES: CountryEntry[] = Object.values(CONTINENTS)
  .flatMap(c => c.countries)
  .sort((a, b) => a.name.localeCompare(b.name));

/** Total number of countries across all continents */
export const TOTAL_COUNTRIES = ALL_COUNTRIES.length;

/** Get flag for a country name (case-insensitive) */
export function getCountryFlag(country: string | null): string {
  if (!country) return '🌍';
  const lower = country.toLowerCase();
  for (const entry of ALL_COUNTRIES) {
    if (entry.name.toLowerCase() === lower) return entry.flag;
  }
  return '🏳️';
}

/** Get continent label for a country name */
export function getContinentForCountry(country: string): string | null {
  const lower = country.toLowerCase();
  for (const [, data] of Object.entries(CONTINENTS)) {
    if (data.countries.some(c => c.name.toLowerCase() === lower)) {
      return data.label;
    }
  }
  return null;
}
