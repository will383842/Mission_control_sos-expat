<?php

namespace App\Services\Content;

/**
 * Audience Context Service — maps each of the 9 supported languages to its
 * target readership (nationalities that speak that language) plus concrete
 * examples of banks, tax authorities, consulates and typical first names
 * that the LLM should use when generating or translating content.
 *
 * Used by KnowledgeBaseService and TranslationService to inject a per-language
 * audience block into every generation and translation prompt. This is what
 * guarantees that a single article truly addresses readers of ALL relevant
 * nationalities (not just the French, for the FR version, and not just the
 * Americans for the EN version).
 *
 * To add a new language, simply append a new entry to $languageContexts below.
 */
class AudienceContextService
{
    /**
     * Return a ready-to-inject prompt block for a given language code.
     * Falls back to the English block if the language is unknown.
     */
    public static function getContextFor(?string $language): string
    {
        $lang = $language ?: 'fr';
        $data = self::$languageContexts[$lang] ?? self::$languageContexts['en'];

        $label             = $data['label'];
        $nationalities     = $data['nationalities'];
        $bankExamples      = $data['bank_examples'];
        $taxAuthority      = $data['tax_authority'];
        $consulate         = $data['consulate_reference'];
        $firstNames        = $data['first_names'];

        return <<<BLOCK

=== PUBLIC CIBLE POUR CETTE LANGUE ({$label}) — CRITIQUE ===

Cet article sera lu par des lecteurs de PLUSIEURS nationalites :
{$nationalities}

REGLE ABSOLUE : Ne presume JAMAIS que le lecteur est d'UNE seule nationalite.
Ecris comme si plusieurs nationalites de ce groupe linguistique allaient lire l'article
en meme temps. Utilise la formulation "votre pays d'origine", "votre consulat",
"votre caisse d'assurance maladie nationale" au lieu de nommer un pays specifique.

QUAND tu dois donner un exemple concret (banque, organisme, administration),
VARIE les exemples entre plusieurs nationalites de ce groupe — JAMAIS un seul pays.

EXEMPLES DE BANQUES A UTILISER (varie-les, ne cite pas toujours les memes) :
{$bankExamples}

AUTORITES FISCALES A CITER (selon le pays d'origine du lecteur) :
{$taxAuthority}

CONSULATS / AMBASSADES DE REFERENCE :
{$consulate}

PRENOMS POUR ANECDOTES ET TEMOIGNAGES (varie-les, utilise au moins 2-3 nationalites
differentes dans un meme article) :
{$firstNames}

INTERDIT :
- Mentionner UNE SEULE nationalite comme si c'etait la seule audience
- Dire "pour nous, les Francais" ou "chez nous en France" (ou equivalent pour toute autre nationalite)
- Inventer des organismes ou des banques hors de cette liste
- Parler de fiscalite en citant une seule administration (toujours mentionner 2-3 pays differents)

=== FIN PUBLIC CIBLE ===

BLOCK;
    }

    /**
     * Return the raw data for a language (useful for tests and introspection).
     */
    public static function getDataFor(?string $language): array
    {
        $lang = $language ?: 'fr';
        return self::$languageContexts[$lang] ?? self::$languageContexts['en'];
    }

    /**
     * Return all supported language codes.
     */
    public static function getSupportedLanguages(): array
    {
        return array_keys(self::$languageContexts);
    }

    /**
     * Per-language audience data: nationalities, banks, tax authorities,
     * consulates, first names. 9 languages supported (matches TranslationService).
     */
    private static array $languageContexts = [
        'fr' => [
            'label' => 'francophone',
            'nationalities' => 'France, Belgique, Suisse (Romande), Canada (Quebec), Luxembourg, Monaco, '
                . 'Maroc, Tunisie, Algerie, Senegal, Cote d\'Ivoire, Cameroun, Mali, Burkina Faso, '
                . 'Niger, Madagascar, Djibouti, Haiti, Rwanda, Guinee, Benin, Togo, Gabon, Republique du Congo, '
                . 'Comores, Vanuatu, Liban (francophones), plus toute personne d\'autre nationalite qui lit en francais',
            'bank_examples' => 'BNP Paribas, Credit Agricole, Societe Generale, La Banque Postale (FR) | '
                . 'BNP Paribas Fortis, KBC, Belfius, ING Belgium (BE) | UBS, PostFinance, Raiffeisen, BCV (CH) | '
                . 'RBC, TD, Desjardins, Banque Nationale (CA) | Attijariwafa Bank, Banque Populaire, BMCE, BMCI (MA) | '
                . 'BIAT, BNA, BIA (TN) | CBAO, Ecobank, UBA (SN) | SGBCI, BICICI (CI)',
            'tax_authority' => 'DGFiP / impots.gouv.fr (FR) | SPF Finances (BE) | AFC Administration federale des contributions (CH) | '
                . 'Agence du revenu du Canada / Revenu Quebec (CA) | Direction generale des impots DGI (MA/TN/SN)',
            'consulate_reference' => 'consulat francais, belge, suisse, canadien, luxembourgeois, marocain, tunisien, algerien, '
                . 'senegalais, ivoirien, camerounais, selon la nationalite du lecteur — JAMAIS uniquement "consulat de France"',
            'first_names' => 'Sophie, Marc, Camille, Thomas (FR) | Frederic, Annelies, Joris (BE) | Lukas, Seraina (CH) | '
                . 'Marie-Eve, Pierre-Luc (CA-Quebec) | Youssef, Fatima, Karim (MA/DZ/TN) | Mariama, Ousmane, Aissatou (SN) | '
                . 'Jean-Marie, Cedric (CM/CG) | Rivo, Hery (MG) — varie systematiquement',
        ],

        'en' => [
            'label' => 'anglophone',
            'nationalities' => 'USA, United Kingdom, Canada (Anglophone), Australia, New Zealand, Ireland, South Africa, '
                . 'India, Pakistan, Bangladesh, Nigeria, Ghana, Kenya, Uganda, Tanzania, Zambia, Zimbabwe, '
                . 'Jamaica, Trinidad and Tobago, Barbados, Singapore, Malaysia, Philippines, Hong Kong, Malta, '
                . 'plus any other nationality reading in English',
            'bank_examples' => 'Chase, Bank of America, Wells Fargo, Citibank (US) | HSBC, Barclays, Lloyds, NatWest (UK) | '
                . 'RBC, TD, Scotiabank, BMO (CA) | ANZ, Commonwealth, Westpac, NAB (AU) | ASB, BNZ, Kiwibank (NZ) | '
                . 'SBI, HDFC, ICICI, Axis (IN) | HBL, UBL, MCB (PK) | Standard Bank, ABSA, FNB (ZA) | GTBank, Zenith, Access (NG) | '
                . 'Equity Bank, KCB (KE) | DBS, OCBC, UOB (SG)',
            'tax_authority' => 'IRS (US) | HMRC (UK) | CRA (CA) | ATO (AU) | IRD (NZ/HK) | SARS (ZA) | Income Tax Dept / CBDT (IN) | '
                . 'FBR (PK) | FIRS (NG) | KRA (KE) | IRAS (SG) | LHDN (MY)',
            'consulate_reference' => 'US / British / Canadian / Australian / Irish / South African / Indian / Pakistani / '
                . 'Nigerian / Kenyan / Singaporean / Philippine consulate, depending on the reader nationality',
            'first_names' => 'Emily, Michael, James, Sarah (US/UK) | Priya, Rajesh, Arjun, Anjali (IN) | Aiden, Liam (CA/AU) | '
                . 'Chinedu, Ngozi, Kwame (NG/GH) | Wanjiru, Kipchoge (KE) | Mei Ling, Wei (SG/HK) | Siti, Faisal (MY/SG) | '
                . 'Juanita, Maria (PH) — always rotate between nationalities',
        ],

        'es' => [
            'label' => 'hispanophone',
            'nationalities' => 'Espana, Mexico, Argentina, Colombia, Peru, Venezuela, Chile, Ecuador, Guatemala, Cuba, '
                . 'Republica Dominicana, Bolivia, Uruguay, Paraguay, Honduras, El Salvador, Nicaragua, Costa Rica, '
                . 'Panama, Puerto Rico, Guinea Ecuatorial, mas cualquier hispanohablante en otra nacionalidad',
            'bank_examples' => 'BBVA, Santander, CaixaBank, Sabadell (ES) | Banamex, BBVA Mexico, Banorte (MX) | '
                . 'Banco Nacion, Santander Rio, Galicia (AR) | Bancolombia, BBVA Colombia, Davivienda (CO) | '
                . 'BCP, Interbank, Scotiabank Peru (PE) | Banco de Chile, Santander Chile, BCI (CL) | '
                . 'Banco Pichincha (EC) | Banco Industrial (GT) | Banreservas (DO)',
            'tax_authority' => 'AEAT / Agencia Tributaria (ES) | SAT (MX) | AFIP (AR) | DIAN (CO) | SUNAT (PE) | SII (CL) | '
                . 'SRI (EC) | SET (PY) | DGI (UY) | DGII (DO) | SAT (GT) | DGII (PA)',
            'consulate_reference' => 'consulado espanol, mexicano, argentino, colombiano, peruano, chileno, ecuatoriano, '
                . 'venezolano, cubano, o relevante segun la nacionalidad del lector',
            'first_names' => 'Maria, Pablo, Lucia, Javier (ES) | Sofia, Diego, Camila (MX/AR/CL) | Andres, Valentina (CO) | '
                . 'Rosa, Juan Carlos (PE/BO) | Yolanda, Rafael (VE/CU) | Isabela, Mateo (EC/UY) — varie entre paises',
        ],

        'de' => [
            'label' => 'germanophone',
            'nationalities' => 'Deutschland, Osterreich, Schweiz (Deutschschweiz), Luxembourg (deutschsprachig), '
                . 'Belgien (deutschsprachige Gemeinschaft), Liechtenstein, Sudtirol (Italien), '
                . 'plus jede andere Nationalitat die auf Deutsch liest',
            'bank_examples' => 'Deutsche Bank, Commerzbank, Sparkasse, DKB, ING Deutschland (DE) | '
                . 'Erste Bank, Raiffeisen, Bank Austria, BAWAG (AT) | UBS, Credit Suisse, PostFinance, Raiffeisen Schweiz (CH) | '
                . 'LLB Liechtensteinische Landesbank (LI) | BIL, BCEE (LU)',
            'tax_authority' => 'Finanzamt / ELSTER (DE) | BMF Bundesministerium fur Finanzen (AT) | '
                . 'ESTV Eidgenossische Steuerverwaltung (CH) | ACD Administration des contributions directes (LU)',
            'consulate_reference' => 'Deutsches, Osterreichisches, Schweizerisches, Luxemburgisches oder Liechtensteiner Konsulat, '
                . 'je nach Nationalitat des Lesers',
            'first_names' => 'Hans, Greta, Lukas, Anna (DE) | Stefan, Julia (AT) | Roger, Nadine (CH) | '
                . 'Sofia, Max (international Leser) — variiere zwischen Landern',
        ],

        'pt' => [
            'label' => 'lusophone',
            'nationalities' => 'Brasil, Portugal, Angola, Mocambique, Cabo Verde, Guine-Bissau, Sao Tome e Principe, '
                . 'Timor-Leste, Macau, Guine Equatorial (portugues oficial), mais qualquer outra nacionalidade que le em portugues',
            'bank_examples' => 'Itau, Bradesco, Banco do Brasil, Santander Brasil, Nubank (BR) | '
                . 'CGD Caixa Geral de Depositos, Millennium BCP, Novo Banco, Santander Totta (PT) | '
                . 'BAI, BFA, Banco Millennium Atlantico (AO) | BCI, Millennium BIM (MZ) | BCA (CV)',
            'tax_authority' => 'Receita Federal (BR) | AT Autoridade Tributaria (PT) | AGT (AO) | AT Mocambique (MZ) | '
                . 'DGCI (CV)',
            'consulate_reference' => 'consulado brasileiro, portugues, angolano, mocambicano, cabo-verdiano, '
                . 'ou relevante segundo a nacionalidade do leitor',
            'first_names' => 'Joao, Maria, Pedro, Beatriz (PT) | Lucas, Julia, Gabriel, Amanda (BR) | '
                . 'Domingos, Isabel (AO/MZ) | Nuno, Ines (CV) — varie entre paises',
        ],

        'ru' => [
            'label' => 'russophone',
            'nationalities' => 'Russie, Bielorussie, Kazakhstan, Kirghizistan, Ouzbekistan, Tadjikistan, Turkmenistan, '
                . 'Armenie, Azerbaidjan, Moldavie, Ukraine (russophones), Israel (communaute russe), '
                . 'Estonie/Lettonie/Lituanie (minorite russe), diaspora russe mondiale',
            'bank_examples' => 'Sberbank, VTB, Alfa-Bank, Tinkoff, Gazprombank, Otkritie (RU) | '
                . 'Belarusbank, Belagroprombank, Priorbank (BY) | Halyk Bank, Kaspi Bank, ForteBank (KZ) | '
                . 'Ardshinbank, Ameriabank (AM) | Kapital Bank (AZ)',
            'tax_authority' => 'FNS Federal Tax Service (RU) | MNS Ministry of Taxes (BY) | KGD State Revenue Committee (KZ) | '
                . 'SRC State Revenue Committee (AM)',
            'consulate_reference' => 'Russian / Belarusian / Kazakh / Kyrgyz / Uzbek / Armenian / Azerbaijani consulate, '
                . 'in accordance with the reader nationality',
            'first_names' => 'Olga, Dmitri, Ivan, Elena (RU) | Natallia, Pavel (BY) | Aigul, Nurlan (KZ) | '
                . 'Armen, Anahit (AM) | Leila, Rashid (AZ) — rotate between nationalities',
        ],

        'zh' => [
            'label' => 'sinophone',
            'nationalities' => 'Chine continentale, Taiwan, Hong Kong, Macao, Singapour (communaute chinoise), '
                . 'Malaisie (communaute chinoise), Indonesie (communaute chinoise), Thailande (communaute chinoise), '
                . 'diaspora chinoise mondiale (US, Canada, Australie, UK, France)',
            'bank_examples' => 'ICBC, Bank of China, CCB, ABC, China Merchants Bank (CN) | Bank of Taiwan, CTBC, Cathay United (TW) | '
                . 'HSBC, Hang Seng, Bank of East Asia, Standard Chartered (HK) | BCM Banco Comercial de Macau (MO) | '
                . 'DBS, OCBC, UOB (SG) | Maybank, Public Bank (MY)',
            'tax_authority' => 'STA State Taxation Administration (CN) | MOF Ministry of Finance / National Taxation Bureau (TW) | '
                . 'IRD Inland Revenue Department (HK) | DSF Financial Services Bureau (MO) | IRAS (SG) | LHDN (MY)',
            'consulate_reference' => 'consulate general of the PRC, Taiwan (TECO), Hong Kong, Macau, Singapore, Malaysia, '
                . 'depending on the reader',
            'first_names' => 'Wei, Mei Ling, Chen, Jing (CN) | Ming-Hua, Yu-Hsuan (TW) | Ka Yee, Wai Kit (HK) | '
                . 'Siti, Ahmad Tan (MY) | Li Wei, Pei Ying (SG) — rotate between Chinese communities',
        ],

        'ar' => [
            'label' => 'arabophone',
            'nationalities' => 'Arabie Saoudite, Emirats Arabes Unis, Qatar, Koweit, Bahrein, Oman, Yemen, '
                . 'Egypte, Maroc, Algerie, Tunisie, Libye, Soudan, Mauritanie, Djibouti, Comores, '
                . 'Liban, Jordanie, Syrie, Irak, Palestine, plus diaspora arabe mondiale',
            'bank_examples' => 'Al Rajhi, SNB Saudi National Bank, Riyad Bank (SA) | Emirates NBD, ADCB, FAB First Abu Dhabi Bank (AE) | '
                . 'QNB Qatar National Bank, Commercial Bank (QA) | NBK National Bank of Kuwait (KW) | '
                . 'NBE National Bank of Egypt, CIB, Banque Misr (EG) | Attijariwafa Bank, BCP (MA) | '
                . 'BIAT, BNA (TN) | Bank Audi, Blom Bank (LB) | Arab Bank (JO)',
            'tax_authority' => 'ZATCA (SA) | FTA Federal Tax Authority (AE) | GTA General Tax Authority (QA) | '
                . 'ETA Egyptian Tax Authority (EG) | DGI Direction Generale des Impots (MA/TN)',
            'consulate_reference' => 'consulat saoudien, emirati, qatari, koweitien, bahreini, egyptien, libanais, jordanien, '
                . 'marocain, tunisien, algerien, selon la nationalite du lecteur',
            'first_names' => 'Ahmad, Fatima, Mohammed, Aisha (general arabe) | Khalid, Noor (SA/AE/QA/KW) | '
                . 'Omar, Yasmine (EG/LB/JO) | Youssef, Leila (MA/DZ/TN) — varie entre pays arabes',
        ],

        'hi' => [
            'label' => 'indophone (hindi + ourdou)',
            'nationalities' => 'Inde, Pakistan (ourdou), Nepal (hindi parle), Bangladesh (minorites hindiphones), '
                . 'Fidji (communaute indo-fidjienne), Maurice (communaute indo-mauricienne), '
                . 'Guyana, Trinite (diaspora indienne), ainsi que la diaspora indo-pakistanaise mondiale (USA, UK, Canada, Australie, Golfe)',
            'bank_examples' => 'SBI State Bank of India, HDFC, ICICI, Axis Bank, Punjab National Bank, Kotak Mahindra (IN) | '
                . 'HBL Habib Bank, UBL United Bank Limited, MCB, Allied Bank (PK) | '
                . 'Nepal Bank, Rastriya Banijya Bank (NP) | MCB Mauritius Commercial Bank (MU)',
            'tax_authority' => 'Income Tax Department / CBDT (IN) | FBR Federal Board of Revenue (PK) | IRD Inland Revenue (NP) | '
                . 'NBR National Board of Revenue (BD)',
            'consulate_reference' => 'Indian / Pakistani / Nepali / Bangladeshi / Mauritian consulate, '
                . 'as per the reader nationality',
            'first_names' => 'Priya, Rajesh, Arjun, Anjali (IN - Hindi) | Amit, Pooja, Vikram (IN - general) | '
                . 'Fatima, Ali, Ayesha (PK - Urdu) | Bikram, Sita (NP) — always rotate',
        ],
    ];
}
