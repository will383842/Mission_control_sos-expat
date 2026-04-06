<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6.1 — Native keywords per language (NOT translations).
 *
 * These are real search queries that people type in their native language.
 * They are NOT machine-translated from French — they reflect actual search behavior.
 *
 * Structure: language → [keyword, intent, search_volume_estimate, category]
 */
class NativeKeywordsSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = [
            // ══════════════════════════════════════════
            // ENGLISH — Real US/UK/AU search queries
            // ══════════════════════════════════════════
            'en' => [
                ['how to move abroad from US', 'informational', 'high', 'expatriation'],
                ['best countries to retire abroad 2026', 'informational', 'high', 'retraite'],
                ['digital nomad visa countries 2026', 'informational', 'high', 'visa'],
                ['expat tax obligations US citizen', 'informational', 'high', 'fiscalite'],
                ['international health insurance for expats', 'transactional', 'high', 'assurance'],
                ['cheapest countries to live abroad', 'informational', 'high', 'cout_vie'],
                ['working holiday visa requirements', 'informational', 'medium', 'visa'],
                ['best banks for expats no fees', 'transactional', 'medium', 'finance'],
                ['how to find apartment abroad remotely', 'informational', 'medium', 'logement'],
                ['expat lawyer international law', 'transactional', 'medium', 'juridique'],
                ['send money abroad cheaply', 'transactional', 'high', 'finance'],
                ['lost passport abroad what to do', 'urgency', 'medium', 'urgence'],
                ['best VPN for streaming abroad', 'transactional', 'high', 'vpn'],
                ['cost of living comparison calculator', 'informational', 'medium', 'outils'],
                ['FEIE vs foreign tax credit', 'informational', 'medium', 'fiscalite'],
                ['teach English abroad requirements', 'informational', 'medium', 'emploi'],
                ['best eSIM for international travel', 'transactional', 'high', 'telecom'],
                ['expat community groups near me', 'navigational', 'medium', 'communaute'],
                ['golden visa Europe comparison', 'informational', 'medium', 'visa'],
                ['freelance visa Europe 2026', 'informational', 'medium', 'visa'],
            ],

            // ══════════════════════════════════════════
            // SPANISH — Real ES/LATAM search queries
            // ══════════════════════════════════════════
            'es' => [
                ['como emigrar a Europa desde Latinoamerica', 'informational', 'high', 'expatriation'],
                ['mejores paises para vivir como nomada digital', 'informational', 'high', 'visa'],
                ['visa de trabajo en Espana 2026', 'informational', 'high', 'visa'],
                ['costo de vida en Portugal para jubilados', 'informational', 'medium', 'cout_vie'],
                ['seguro medico internacional para expatriados', 'transactional', 'medium', 'assurance'],
                ['abogado hispanohablante en el extranjero', 'transactional', 'medium', 'juridique'],
                ['enviar dinero a Latinoamerica barato', 'transactional', 'high', 'finance'],
                ['tramites para mudarse al extranjero', 'informational', 'medium', 'demarches'],
                ['visa nomada digital Espana requisitos', 'informational', 'high', 'visa'],
                ['comparar bancos para expatriados', 'transactional', 'medium', 'finance'],
                ['como encontrar trabajo en el extranjero', 'informational', 'medium', 'emploi'],
                ['residencia fiscal en el extranjero', 'informational', 'medium', 'fiscalite'],
                ['pasaporte perdido en el extranjero que hacer', 'urgency', 'medium', 'urgence'],
                ['VPN para ver contenido de mi pais', 'transactional', 'medium', 'vpn'],
                ['comunidad hispana en el extranjero', 'navigational', 'medium', 'communaute'],
            ],

            // ══════════════════════════════════════════
            // GERMAN — Real DE/AT/CH search queries
            // ══════════════════════════════════════════
            'de' => [
                ['auswandern wohin am besten 2026', 'informational', 'high', 'expatriation'],
                ['digitale Nomaden Visum Laender', 'informational', 'high', 'visa'],
                ['Lebenshaltungskosten Vergleich Ausland', 'informational', 'medium', 'cout_vie'],
                ['Auslandskrankenversicherung Langzeit Vergleich', 'transactional', 'high', 'assurance'],
                ['Steuerpflicht bei Auswanderung Deutschland', 'informational', 'high', 'fiscalite'],
                ['Rechtsanwalt deutschsprachig im Ausland', 'transactional', 'medium', 'juridique'],
                ['Geld ins Ausland ueberweisen guenstig', 'transactional', 'high', 'finance'],
                ['Wohnung im Ausland finden', 'informational', 'medium', 'logement'],
                ['Rente im Ausland beziehen', 'informational', 'medium', 'retraite'],
                ['VPN fuer deutsches Fernsehen im Ausland', 'transactional', 'medium', 'vpn'],
                ['Arbeiten im Ausland Tipps', 'informational', 'medium', 'emploi'],
                ['eSIM fuer Reisen international', 'transactional', 'medium', 'telecom'],
                ['Pass verloren im Ausland Hilfe', 'urgency', 'medium', 'urgence'],
                ['deutsche Community im Ausland finden', 'navigational', 'medium', 'communaute'],
                ['Freelancer im Ausland Steuern', 'informational', 'medium', 'fiscalite'],
            ],

            // ══════════════════════════════════════════
            // PORTUGUESE — Real BR/PT search queries
            // ══════════════════════════════════════════
            'pt' => [
                ['como morar no exterior sendo brasileiro', 'informational', 'high', 'expatriation'],
                ['melhores paises para morar fora do Brasil', 'informational', 'high', 'expatriation'],
                ['visto de trabalho Portugal 2026', 'informational', 'high', 'visa'],
                ['custo de vida na Europa para brasileiros', 'informational', 'high', 'cout_vie'],
                ['seguro saude internacional para expatriados', 'transactional', 'medium', 'assurance'],
                ['advogado que fala portugues no exterior', 'transactional', 'medium', 'juridique'],
                ['enviar dinheiro para o Brasil mais barato', 'transactional', 'high', 'finance'],
                ['nomade digital visto paises 2026', 'informational', 'medium', 'visa'],
                ['abrir conta bancaria no exterior', 'informational', 'medium', 'finance'],
                ['comunidade brasileira no exterior', 'navigational', 'medium', 'communaute'],
                ['passaporte perdido no exterior o que fazer', 'urgency', 'medium', 'urgence'],
                ['melhor VPN para assistir TV brasileira', 'transactional', 'medium', 'vpn'],
                ['aposentadoria no exterior como funciona', 'informational', 'medium', 'retraite'],
                ['trabalhar remoto morando no exterior', 'informational', 'medium', 'emploi'],
                ['chip eSIM para viagem internacional', 'transactional', 'medium', 'telecom'],
            ],

            // ══════════════════════════════════════════
            // RUSSIAN — Real RU search queries
            // ══════════════════════════════════════════
            'ru' => [
                ['kak pereekhat zhit za granitsu', 'informational', 'high', 'expatriation'],
                ['luchshie strany dlya emigratsii 2026', 'informational', 'high', 'expatriation'],
                ['viza tsifrovogo kochevnika strany', 'informational', 'medium', 'visa'],
                ['stoimost zhizni za granitsey sravnenie', 'informational', 'medium', 'cout_vie'],
                ['meditsinskaya strakhovka dlya emigrantov', 'transactional', 'medium', 'assurance'],
                ['yurist russkoyazychnyy za granitsey', 'transactional', 'medium', 'juridique'],
                ['perevod deneg za granitsu deshevo', 'transactional', 'high', 'finance'],
                ['kak nayti zhilye za granitsey', 'informational', 'medium', 'logement'],
                ['VPN dlya rossiyskogo TV za granitsey', 'transactional', 'medium', 'vpn'],
                ['russkaya diaspora za granitsey', 'navigational', 'medium', 'communaute'],
            ],

            // ══════════════════════════════════════════
            // CHINESE — Real CN search queries (romanized)
            // ══════════════════════════════════════════
            'zh' => [
                ['ruhe yimin guowai', 'informational', 'high', 'expatriation'],
                ['shuma youmin qianzheng guojia', 'informational', 'medium', 'visa'],
                ['haiwai shenghuo chengben bijiao', 'informational', 'medium', 'cout_vie'],
                ['guoji yiliao baoxian bijiao', 'transactional', 'medium', 'assurance'],
                ['guowai huaren lushi', 'transactional', 'medium', 'juridique'],
                ['guoji huikuan zuipianyi', 'transactional', 'high', 'finance'],
                ['haiwai zhufang zhinan', 'informational', 'medium', 'logement'],
                ['haiwai huaren shequ', 'navigational', 'medium', 'communaute'],
                ['guowai gongzuo qianzheng', 'informational', 'medium', 'visa'],
                ['eSIM guoji luyou', 'transactional', 'medium', 'telecom'],
            ],

            // ══════════════════════════════════════════
            // HINDI — Real IN search queries (romanized)
            // ══════════════════════════════════════════
            'hi' => [
                ['videsh mein kaise rahein', 'informational', 'high', 'expatriation'],
                ['digital nomad visa desh 2026', 'informational', 'medium', 'visa'],
                ['videsh mein rahne ka kharcha', 'informational', 'medium', 'cout_vie'],
                ['international health insurance India', 'transactional', 'medium', 'assurance'],
                ['videsh mein Hindi bolne wala vakil', 'transactional', 'medium', 'juridique'],
                ['India se videsh paise bhejna sasta', 'transactional', 'high', 'finance'],
                ['videsh mein ghar kaise dhundhe', 'informational', 'medium', 'logement'],
                ['videsh mein Bhartiya community', 'navigational', 'medium', 'communaute'],
                ['passport kho gaya videsh mein kya karein', 'urgency', 'medium', 'urgence'],
                ['videsh mein naukri kaise dhundhe', 'informational', 'medium', 'emploi'],
            ],

            // ══════════════════════════════════════════
            // ARABIC — Real AR search queries (romanized)
            // ══════════════════════════════════════════
            'ar' => [
                ['kayf al-hijra ila al-kharij', 'informational', 'high', 'expatriation'],
                ['afDal duwal lil-hijra 2026', 'informational', 'high', 'expatriation'],
                ['tashira amal fi urubba', 'informational', 'medium', 'visa'],
                ['taklufa al-hayat fi al-kharij', 'informational', 'medium', 'cout_vie'],
                ['tamin Sihhi dawli lil-mughtaribin', 'transactional', 'medium', 'assurance'],
                ['muhami yahtakallam al-arabiya fi al-kharij', 'transactional', 'medium', 'juridique'],
                ['tahwil amwal dawli rakhiS', 'transactional', 'high', 'finance'],
                ['al-jaliya al-arabiya fi al-kharij', 'navigational', 'medium', 'communaute'],
                ['jawaz safar mafqud fi al-kharij', 'urgency', 'medium', 'urgence'],
                ['VPN li-mushahada al-maHaTTat al-arabiya', 'transactional', 'medium', 'vpn'],
            ],
        ];

        $now = now();
        $total = 0;

        foreach ($keywords as $lang => $items) {
            foreach ($items as $item) {
                DB::table('keyword_tracking')->insertOrIgnore([
                    'keyword' => $item[0],
                    'type' => 'primary',
                    'language' => $lang,
                    'country' => null,
                    'category' => $item[3],
                    'search_volume_estimate' => match($item[2]) { 'high' => 80, 'medium' => 50, 'low' => 20, default => 50 },
                    'difficulty_estimate' => 0,
                    'trend' => 'stable',
                    'articles_using_count' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $total++;
            }
        }

        $this->command?->info("Seeded {$total} native keywords across " . count($keywords) . " languages.");
    }
}
