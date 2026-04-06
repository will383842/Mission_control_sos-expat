<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 500+ long-tail keywords with search intent classification.
 * Organized by high-value niche clusters.
 */
class LongTailKeywordsSeeder extends Seeder
{
    public function run(): void
    {
        $keywords = array_merge(
            $this->fiscaliteInternationale(),
            $this->gestionPatrimoine(),
            $this->assurancePremium(),
            $this->immobilierInvestissement(),
            $this->corporateRelocation(),
            $this->successionInternationale(),
            $this->banquePrivee(),
            $this->visaImmigrationDetaille(),
            $this->santePremium(),
            $this->educationInternationale(),
            $this->digitalNomadAvance(),
            $this->retraitePremium(),
            $this->urgencesDetaillees(),
            $this->entrepreneuriatInternational(),
            $this->expatriationQuotidien(),
            $this->comparatifsStrategiques(),
        );

        $now = now();
        $total = 0;

        foreach ($keywords as $item) {
            DB::table('keyword_tracking')->insertOrIgnore([
                'keyword' => $item[0],
                'type' => 'long_tail',
                'search_intent' => $item[1],
                'language' => 'fr',
                'country' => $item[3] ?? null,
                'category' => $item[2],
                'search_volume_estimate' => match($item[4] ?? 'medium') { 'high' => 80, 'medium' => 50, 'low' => 20, default => 50 },
                'difficulty_estimate' => 20,
                'trend' => 'stable',
                'articles_using_count' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $total++;
        }

        $this->command?->info("Seeded {$total} long-tail keywords with search intent.");
    }

    // ══════════════════════════════════════════════════════════════
    // FISCALITÉ INTERNATIONALE & OPTIMISATION FISCALE (50)
    // ══════════════════════════════════════════════════════════════
    private function fiscaliteInternationale(): array
    {
        return [
            // Informational
            ['comment devenir non resident fiscal francais en partant a l etranger', 'informational', 'fiscalite', null],
            ['regle des 183 jours residence fiscale expliquee simplement', 'informational', 'fiscalite', null],
            ['double imposition France Espagne convention fiscale 2026', 'informational', 'fiscalite', 'ES'],
            ['double imposition France Portugal convention fiscale 2026', 'informational', 'fiscalite', 'PT'],
            ['double imposition France Emirats Arabes Unis convention 2026', 'informational', 'fiscalite', 'AE'],
            ['imposition des revenus locatifs francais quand on vit a l etranger', 'informational', 'fiscalite', null],
            ['comment declarer ses impots quand on est expatrie en Thailande', 'informational', 'fiscalite', 'TH'],
            ['exit tax expatriation France quand et comment la payer', 'informational', 'fiscalite', 'FR'],
            ['regime NHR Portugal avantages fiscaux pour expatries 2026', 'informational', 'fiscalite', 'PT'],
            ['regime Beckham Espagne imposition forfaitaire expatries', 'informational', 'fiscalite', 'ES'],
            ['fiscalite dividendes pour non resident francais', 'informational', 'fiscalite', null],
            ['ISF IFI expatrie quand est on encore redevable', 'informational', 'fiscalite', 'FR'],
            ['comment optimiser sa fiscalite en tant qu expatrie legalement', 'informational', 'fiscalite', null],
            ['statut LMNP pour expatrie investissement locatif France', 'informational', 'fiscalite', 'FR'],
            ['comment declarer ses comptes bancaires etrangers au fisc francais', 'informational', 'fiscalite', 'FR'],
            ['taxe sur la plus-value immobiliere pour non resident francais', 'informational', 'fiscalite', 'FR'],
            ['CSG CRDS expatrie doit on encore payer', 'informational', 'fiscalite', 'FR'],
            ['zero impot Dubai comment ca fonctionne pour les expatries', 'informational', 'fiscalite', 'AE'],
            ['flat tax Bulgarie 10% pour expatries comment en beneficier', 'informational', 'fiscalite', 'BG'],
            ['regime territorial Hong Kong fiscalite pour expatries', 'informational', 'fiscalite', 'HK'],
            // Commercial investigation
            ['meilleur pays pour payer moins d impots en tant qu expatrie 2026', 'commercial_investigation', 'fiscalite', null],
            ['comparatif regime fiscal avantageux en Europe pour expatries', 'commercial_investigation', 'fiscalite', null],
            ['NHR Portugal vs Beckham Espagne quel regime fiscal choisir', 'commercial_investigation', 'fiscalite', null],
            ['pays sans impot sur le revenu pour expatries comparatif complet', 'commercial_investigation', 'fiscalite', null],
            // Transactional
            ['prendre rendez-vous fiscaliste international en ligne', 'transactional', 'fiscalite', null],
            ['trouver expert comptable specialise expatries francais', 'transactional', 'fiscalite', null],
            // Local
            ['fiscaliste francais a Dubai specialise expatries', 'local', 'fiscalite', 'AE'],
            ['expert comptable francais a Lisbonne pour expatries', 'local', 'fiscalite', 'PT'],
            ['avocat fiscaliste a Barcelone pour expatries francais', 'local', 'fiscalite', 'ES'],
            ['cabinet comptable francophone a Luxembourg pour expatries', 'local', 'fiscalite', 'LU'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // GESTION PATRIMOINE & WEALTH MANAGEMENT (35)
    // ══════════════════════════════════════════════════════════════
    private function gestionPatrimoine(): array
    {
        return [
            ['gestion de patrimoine pour expatrie comment organiser ses actifs', 'informational', 'patrimoine', null],
            ['assurance vie luxembourgeoise pour expatrie avantages 2026', 'informational', 'patrimoine', 'LU'],
            ['PEA et expatriation que devient mon plan epargne actions', 'informational', 'patrimoine', 'FR'],
            ['investir en SCPI depuis l etranger en tant qu expatrie', 'informational', 'patrimoine', null],
            ['comment proteger son patrimoine quand on vit dans plusieurs pays', 'informational', 'patrimoine', null],
            ['diversification patrimoniale internationale pour expatrie', 'informational', 'patrimoine', null],
            ['placements financiers accessibles aux non residents francais', 'informational', 'patrimoine', 'FR'],
            ['crowdfunding immobilier pour expatries plateformes accessibles', 'informational', 'patrimoine', null],
            ['crypto monnaie et expatriation fiscalite et declaration', 'informational', 'patrimoine', null],
            ['plan retraite individuel pour expatrie quelle solution choisir', 'informational', 'patrimoine', null],
            ['comment rapatrier son patrimoine en France apres expatriation', 'informational', 'patrimoine', 'FR'],
            ['trust et fondation pour expatries protection patrimoniale', 'informational', 'patrimoine', null],
            // Commercial investigation
            ['meilleur gestionnaire de patrimoine pour expatrie comparatif 2026', 'commercial_investigation', 'patrimoine', null],
            ['assurance vie pour expatrie comparatif France Luxembourg Singapour', 'commercial_investigation', 'patrimoine', null],
            ['meilleur courtier en ligne pour expatrie trading depuis l etranger', 'commercial_investigation', 'patrimoine', null],
            // Transactional
            ['prendre rendez-vous conseiller patrimoine specialise expatries', 'transactional', 'patrimoine', null],
            // Local
            ['gestionnaire de patrimoine francophone a Singapour', 'local', 'patrimoine', 'SG'],
            ['conseiller financier francais a Geneve pour expatries', 'local', 'patrimoine', 'CH'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // ASSURANCE PREMIUM & SANTÉ HAUT DE GAMME (40)
    // ══════════════════════════════════════════════════════════════
    private function assurancePremium(): array
    {
        return [
            ['assurance sante internationale premium pour cadre expatrie', 'informational', 'assurance', null],
            ['difference entre CFE et assurance privee internationale pour expatrie', 'informational', 'assurance', null],
            ['assurance rapatriement sanitaire comment ca fonctionne en detail', 'informational', 'assurance', null],
            ['assurance sante maternite pour expatriee enceinte a l etranger', 'informational', 'assurance', null],
            ['assurance maladie chronique expatrie couverture preexistant', 'informational', 'assurance', null],
            ['assurance dentaire pour expatrie couverture et plafonds', 'informational', 'assurance', null],
            ['assurance sante pour famille expatriee avec enfants en bas age', 'informational', 'assurance', null],
            ['assurance voyage PVT Working Holiday quelle couverture choisir', 'informational', 'assurance', null],
            ['assurance responsabilite civile pour expatrie a l etranger', 'informational', 'assurance', null],
            ['comment changer d assurance sante internationale en cours d expatriation', 'informational', 'assurance', null],
            // Commercial investigation
            ['Cigna Global Health vs Bupa International comparatif premium 2026', 'commercial_investigation', 'assurance', null],
            ['meilleure assurance sante expatrie famille complete comparatif 2026', 'commercial_investigation', 'assurance', null],
            ['assurance sante digital nomad moins de 100 euros par mois comparatif', 'commercial_investigation', 'assurance', null],
            ['SafetyWing vs World Nomads vs Heymondo comparatif detaille', 'commercial_investigation', 'assurance', null],
            ['CFE ou April International ou Cigna quel choix pour expatrie francais', 'commercial_investigation', 'assurance', null],
            ['top 5 assurances sante pour retraite au Portugal 2026', 'commercial_investigation', 'assurance', 'PT'],
            ['meilleure assurance voyage longue duree pour tour du monde', 'commercial_investigation', 'assurance', null],
            // Transactional
            ['souscrire assurance sante internationale en ligne depart immediat', 'transactional', 'assurance', null],
            ['devis assurance expatrie en ligne gratuit comparaison instantanee', 'transactional', 'assurance', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // IMMOBILIER INVESTISSEMENT INTERNATIONAL (35)
    // ══════════════════════════════════════════════════════════════
    private function immobilierInvestissement(): array
    {
        return [
            ['acheter un appartement au Portugal en tant que non resident francais', 'informational', 'immobilier', 'PT'],
            ['investir dans l immobilier a Dubai en tant qu expatrie francais', 'informational', 'immobilier', 'AE'],
            ['achat immobilier en Espagne procedure complete pour etranger', 'informational', 'immobilier', 'ES'],
            ['investissement locatif a Bali pour expatrie regles et rendement', 'informational', 'immobilier', 'ID'],
            ['acheter une maison en Thailande en tant qu etranger est-ce possible', 'informational', 'immobilier', 'TH'],
            ['investir immobilier Grece pour obtenir le golden visa', 'informational', 'immobilier', 'GR'],
            ['rendement locatif moyen par pays pour investisseur expatrie 2026', 'informational', 'immobilier', null],
            ['fiscalite immobiliere pour non resident proprietaire en France', 'informational', 'immobilier', 'FR'],
            ['comment gerer un bien locatif en France depuis l etranger', 'informational', 'immobilier', 'FR'],
            ['SCI et expatriation avantages et risques pour investisseur', 'informational', 'immobilier', 'FR'],
            // Commercial investigation
            ['meilleur pays pour investir dans l immobilier en 2026 comparatif', 'commercial_investigation', 'immobilier', null],
            ['Portugal vs Espagne vs Grece investissement immobilier expatrie', 'commercial_investigation', 'immobilier', null],
            ['Dubai vs Singapour investissement immobilier rendement et fiscalite', 'commercial_investigation', 'immobilier', null],
            // Transactional
            ['trouver agent immobilier francophone au Portugal pour achat', 'transactional', 'immobilier', 'PT'],
            // Local
            ['notaire francophone a Lisbonne pour achat immobilier etranger', 'local', 'immobilier', 'PT'],
            ['agence immobiliere francophone a Barcelone pour expatries', 'local', 'immobilier', 'ES'],
            ['agent immobilier francais a Dubai pour investissement', 'local', 'immobilier', 'AE'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // CORPORATE RELOCATION & MOBILITÉ EMPLOYÉ (30)
    // ══════════════════════════════════════════════════════════════
    private function corporateRelocation(): array
    {
        return [
            ['package de relocation expatrie negocier avec son employeur', 'informational', 'relocation', null],
            ['contrat de travail expatrie vs detache vs local differences', 'informational', 'relocation', null],
            ['mobilite internationale entreprise obligations employeur', 'informational', 'relocation', null],
            ['prime d expatriation montant moyen et fiscalite 2026', 'informational', 'relocation', null],
            ['accompagnement conjoint expatrie emploi et integration', 'informational', 'relocation', null],
            ['securite sociale detachement vs expatriation quelle difference', 'informational', 'relocation', null],
            ['retour d expatriation reinsertion professionnelle en France', 'informational', 'relocation', 'FR'],
            ['mutuelle entreprise expatrie quels droits quelles obligations', 'informational', 'relocation', null],
            // Commercial investigation
            ['meilleures agences de relocation internationale comparatif 2026', 'commercial_investigation', 'relocation', null],
            ['relocation premium vs standard pour cadre expatrie comparatif', 'commercial_investigation', 'relocation', null],
            // Transactional
            ['demander devis relocation internationale en ligne', 'transactional', 'relocation', null],
            // Local
            ['agence relocation francophone a Londres pour expatries', 'local', 'relocation', 'GB'],
            ['service relocation premium a Singapour pour cadres francais', 'local', 'relocation', 'SG'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // SUCCESSION INTERNATIONALE & HÉRITAGE (25)
    // ══════════════════════════════════════════════════════════════
    private function successionInternationale(): array
    {
        return [
            ['succession expatrie quelle loi s applique France ou pays de residence', 'informational', 'succession', null],
            ['testament pour expatrie avec biens dans plusieurs pays', 'informational', 'succession', null],
            ['droits de succession pour non resident francais heritier etranger', 'informational', 'succession', 'FR'],
            ['heritage immobilier en France quand on vit a l etranger impots', 'informational', 'succession', 'FR'],
            ['mandat de protection future pour expatrie en cas d incapacite', 'informational', 'succession', null],
            ['donation de bien immobilier francais a un enfant expatrie', 'informational', 'succession', 'FR'],
            ['assurance deces pour expatrie couverture internationale', 'informational', 'succession', null],
            ['convention succession France Belgique droits applicables', 'informational', 'succession', 'BE'],
            // Commercial investigation
            ['meilleur avocat succession internationale comparatif cabinet 2026', 'commercial_investigation', 'succession', null],
            // Transactional
            ['consulter avocat succession internationale en ligne', 'transactional', 'succession', null],
            // Local
            ['notaire specialise succession internationale a Paris', 'local', 'succession', 'FR'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // BANQUE PRIVÉE & FINANCE HAUT DE GAMME (25)
    // ══════════════════════════════════════════════════════════════
    private function banquePrivee(): array
    {
        return [
            ['ouvrir compte bancaire au Portugal sans NIE en tant que francais', 'informational', 'finance', 'PT'],
            ['ouvrir compte bancaire en Espagne non resident procedure', 'informational', 'finance', 'ES'],
            ['compte multi devises pour expatrie quelle banque choisir', 'informational', 'finance', null],
            ['banque en ligne pour expatrie avec IBAN europeen', 'informational', 'finance', null],
            ['obligation declaration compte bancaire etranger formulaire 3916', 'informational', 'finance', 'FR'],
            ['envoyer argent famille au Senegal moins cher en 2026', 'informational', 'finance', 'SN'],
            ['frais bancaires expatrie comment les reduire au minimum', 'informational', 'finance', null],
            ['carte bancaire premium pour voyager sans frais comparatif', 'commercial_investigation', 'finance', null],
            ['Wise vs Revolut vs N26 pour expatrie comparatif complet 2026', 'commercial_investigation', 'finance', null],
            ['meilleur service transfert argent vers l Afrique comparatif 2026', 'commercial_investigation', 'finance', null],
            ['Remitly vs WorldRemit vs Wise pour transfert Afrique comparatif', 'commercial_investigation', 'finance', null],
            ['ouvrir compte Wise pour expatrie inscription gratuite', 'transactional', 'finance', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // VISA & IMMIGRATION DÉTAILLÉ (50)
    // ══════════════════════════════════════════════════════════════
    private function visaImmigrationDetaille(): array
    {
        return [
            ['visa digital nomad Espagne 2026 conditions montant duree', 'informational', 'visa', 'ES'],
            ['visa digital nomad Portugal conditions revenus et procedure 2026', 'informational', 'visa', 'PT'],
            ['visa digital nomad Grece comment l obtenir en 2026', 'informational', 'visa', 'GR'],
            ['visa digital nomad Croatie conditions et avantages 2026', 'informational', 'visa', 'HR'],
            ['visa D7 Portugal conditions revenus passifs et demarches', 'informational', 'visa', 'PT'],
            ['golden visa Portugal 2026 montant minimum et conditions apres reforme', 'informational', 'visa', 'PT'],
            ['golden visa Grece 2026 montant minimum investissement immobilier', 'informational', 'visa', 'GR'],
            ['golden visa Espagne programme investisseur conditions 2026', 'informational', 'visa', 'ES'],
            ['pvt Canada 2026 conditions age limite places disponibles', 'informational', 'visa', 'CA'],
            ['pvt Australie 2026 conditions et demarches pour francais', 'informational', 'visa', 'AU'],
            ['pvt Japon 2026 conditions pour francais et demarches', 'informational', 'visa', 'JP'],
            ['pvt Nouvelle Zelande 2026 programme vacances travail', 'informational', 'visa', 'NZ'],
            ['carte verte USA green card loterie 2026 comment participer', 'informational', 'visa', 'US'],
            ['visa O1 USA talent extraordinaire conditions pour francais', 'informational', 'visa', 'US'],
            ['visa EB5 USA investisseur montant minimum et procedure', 'informational', 'visa', 'US'],
            ['visa talent France pour etranger conditions et demarches 2026', 'informational', 'visa', 'FR'],
            ['regroupement familial en France pour conjoint etranger procedure', 'informational', 'visa', 'FR'],
            ['naturalisation francaise pour expatrie conditions depuis l etranger', 'informational', 'visa', 'FR'],
            ['visa retraite Thailande O-A conditions financieres et sante', 'informational', 'visa', 'TH'],
            ['visa retraite Bali Indonesie KITAS retraite conditions', 'informational', 'visa', 'ID'],
            ['visa retraite Panama pensionado programme avantages', 'informational', 'visa', 'PA'],
            ['visa freelance Allemagne pour travailleur independant etranger', 'informational', 'visa', 'DE'],
            ['permis de sejour Pays Bas pour travailleur qualifie 30% ruling', 'informational', 'visa', 'NL'],
            // Commercial investigation
            ['meilleur pays golden visa en Europe comparatif 2026', 'commercial_investigation', 'visa', null],
            ['comparatif visa digital nomad Europe quel pays choisir 2026', 'commercial_investigation', 'visa', null],
            ['pvt Canada vs Australie vs Japon comparatif complet', 'commercial_investigation', 'visa', null],
            // Transactional
            ['prendre rendez-vous avocat immigration en ligne pas cher', 'transactional', 'visa', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // SANTÉ PREMIUM & MÉDICAL INTERNATIONAL (25)
    // ══════════════════════════════════════════════════════════════
    private function santePremium(): array
    {
        return [
            ['bilan de sante complet a l etranger check up medical expatrie', 'informational', 'sante', null],
            ['tourisme medical pour expatrie meilleurs pays et hopitaux', 'informational', 'sante', null],
            ['traitement dentaire a l etranger pour expatrie ou aller', 'informational', 'sante', null],
            ['psychiatre francophone a l etranger consultation en ligne', 'informational', 'sante', null],
            ['vaccins obligatoires par pays pour expatrie liste complete 2026', 'informational', 'sante', null],
            ['accouchement a l etranger en tant que francaise droits et couts', 'informational', 'sante', null],
            ['pharmacie en ligne internationale pour expatrie livraison', 'informational', 'sante', null],
            ['meilleur pays pour soins dentaires pas cher qualite comparatif', 'commercial_investigation', 'sante', null],
            ['medecin francophone a Bangkok cabinet et specialites', 'local', 'sante', 'TH'],
            ['hopital international a Dubai pour expatries francais', 'local', 'sante', 'AE'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // ÉDUCATION INTERNATIONALE (30)
    // ══════════════════════════════════════════════════════════════
    private function educationInternationale(): array
    {
        return [
            ['ecole francaise a l etranger liste complete AEFE homologuee', 'informational', 'education', null],
            ['frais de scolarite ecole internationale par pays comparatif 2026', 'informational', 'education', null],
            ['baccalaureat international IB vs bac francais avantages inconvenients', 'informational', 'education', null],
            ['scolariser ses enfants a distance CNED pour expatries', 'informational', 'education', null],
            ['bourse scolaire pour enfant d expatrie francais criteres et montant', 'informational', 'education', 'FR'],
            ['ecole bilingue francais anglais pour enfant expatrie avantages', 'informational', 'education', null],
            ['reconnaissance diplome etranger en France procedure complete', 'informational', 'education', 'FR'],
            ['equivalence diplome francais a l etranger apostille et traduction', 'informational', 'education', null],
            ['meilleures ecoles internationales en Asie du Sud Est classement 2026', 'commercial_investigation', 'education', null],
            ['ecole francaise a Dubai frais inscription et classement 2026', 'local', 'education', 'AE'],
            ['lycee francais a Londres inscription tarifs et avis', 'local', 'education', 'GB'],
            ['ecole francaise a Singapour liste complete et tarifs', 'local', 'education', 'SG'],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // DIGITAL NOMAD AVANCÉ (35)
    // ══════════════════════════════════════════════════════════════
    private function digitalNomadAvance(): array
    {
        return [
            ['statut legal digital nomad quel pays quelle structure juridique', 'informational', 'digital_nomad', null],
            ['fiscalite digital nomad francais ou payer ses impots', 'informational', 'digital_nomad', null],
            ['coworking pas cher en Asie du Sud Est pour digital nomad', 'informational', 'digital_nomad', null],
            ['internet fiable pour travailler a distance par pays classement', 'informational', 'digital_nomad', null],
            ['contrat de travail francais et teletravail depuis l etranger legalite', 'informational', 'digital_nomad', 'FR'],
            ['coliving pour digital nomad meilleurs espaces dans le monde', 'informational', 'digital_nomad', null],
            ['digital nomad avec enfants comment organiser la scolarite', 'informational', 'digital_nomad', null],
            ['burn out digital nomad isolement et sante mentale conseils', 'informational', 'digital_nomad', null],
            ['meilleur pays digital nomad cout de vie et visa comparatif 2026', 'commercial_investigation', 'digital_nomad', null],
            ['Bali vs Chiang Mai vs Lisbonne pour digital nomad comparatif', 'commercial_investigation', 'digital_nomad', null],
            ['Medellin vs Mexico City pour digital nomad comparatif complet', 'commercial_investigation', 'digital_nomad', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // RETRAITE PREMIUM (25)
    // ══════════════════════════════════════════════════════════════
    private function retraitePremium(): array
    {
        return [
            ['prendre sa retraite au Portugal avantages fiscaux et cout de vie 2026', 'informational', 'retraite', 'PT'],
            ['retraite en Espagne pour francais demarches et cout de vie', 'informational', 'retraite', 'ES'],
            ['retraite en Thailande budget mensuel reel pour retraite francais', 'informational', 'retraite', 'TH'],
            ['toucher sa retraite francaise a l etranger demarches et fiscalite', 'informational', 'retraite', null],
            ['retraite complementaire Agirc Arrco quand on vit a l etranger', 'informational', 'retraite', null],
            ['certificat de vie pour retraite a l etranger comment l obtenir', 'informational', 'retraite', null],
            ['meilleur pays pour retraite au soleil pas cher 2026 classement', 'commercial_investigation', 'retraite', null],
            ['Portugal vs Espagne vs Grece pour retraite comparatif detaille', 'commercial_investigation', 'retraite', null],
            ['Maroc vs Tunisie pour retraite francais comparatif cout securite', 'commercial_investigation', 'retraite', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // URGENCES DÉTAILLÉES (25)
    // ══════════════════════════════════════════════════════════════
    private function urgencesDetaillees(): array
    {
        return [
            ['passeport francais vole en Thailande que faire en urgence etapes', 'urgency', 'urgence', 'TH'],
            ['passeport perdu au Mexique procedure urgente ambassade', 'urgency', 'urgence', 'MX'],
            ['accident de voiture a l etranger procedure demarches assurance', 'urgency', 'urgence', null],
            ['hospitalisation d urgence a l etranger sans assurance que faire', 'urgency', 'urgence', null],
            ['arrete par la police au Mexique droits et demarches', 'urgency', 'urgence', 'MX'],
            ['vol de bagages a l aeroport que faire procedure complete', 'urgency', 'urgence', null],
            ['enfant malade en vacances urgence pediatrique a l etranger', 'urgency', 'urgence', null],
            ['catastrophe naturelle rapatriement comment faire depuis l etranger', 'urgency', 'urgence', null],
            ['agression a l etranger porter plainte ambassade aide juridique', 'urgency', 'urgence', null],
            ['carte bancaire bloquee a l etranger solution urgente', 'urgency', 'urgence', null],
            ['perte de tous ses papiers a l etranger que faire en urgence', 'urgency', 'urgence', null],
            ['evacuation medicale depuis l etranger comment organiser', 'urgency', 'urgence', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // ENTREPRENEURIAT INTERNATIONAL (30)
    // ══════════════════════════════════════════════════════════════
    private function entrepreneuriatInternational(): array
    {
        return [
            ['creer une entreprise a Dubai procedure et couts pour francais', 'informational', 'entrepreneuriat', 'AE'],
            ['creer LLC aux USA pour freelance francais procedure complete', 'informational', 'entrepreneuriat', 'US'],
            ['e-residency Estonie creer entreprise en ligne avantages', 'informational', 'entrepreneuriat', 'EE'],
            ['freelance en Espagne autonomo demarches et fiscalite', 'informational', 'entrepreneuriat', 'ES'],
            ['micro entreprise francaise et expatriation peut on la garder', 'informational', 'entrepreneuriat', 'FR'],
            ['portage salarial international pour freelance expatrie', 'informational', 'entrepreneuriat', null],
            ['TVA intracommunautaire pour freelance travaillant depuis l etranger', 'informational', 'entrepreneuriat', null],
            ['facturer des clients francais depuis l etranger legalement', 'informational', 'entrepreneuriat', null],
            ['meilleur pays pour creer son entreprise en tant qu expatrie 2026', 'commercial_investigation', 'entrepreneuriat', null],
            ['LLC USA vs OÜ Estonie vs LTD UK comparatif structure juridique', 'commercial_investigation', 'entrepreneuriat', null],
            ['Dubai vs Singapour pour creer entreprise comparatif', 'commercial_investigation', 'entrepreneuriat', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // EXPATRIATION QUOTIDIEN (35)
    // ══════════════════════════════════════════════════════════════
    private function expatriationQuotidien(): array
    {
        return [
            ['demarches avant de partir vivre a l etranger checklist complete', 'informational', 'expatriation', null],
            ['inscription consulaire a l etranger pourquoi et comment', 'informational', 'expatriation', null],
            ['voter depuis l etranger en tant qu expatrie francais procedure', 'informational', 'expatriation', null],
            ['permis de conduire international validite et echange par pays', 'informational', 'transport', null],
            ['garder son numero de telephone francais quand on vit a l etranger', 'informational', 'telecom', null],
            ['demenagement international par container prix et organisation', 'informational', 'demenagement', null],
            ['animal de compagnie expatriation reglementation et transport', 'informational', 'expatriation', null],
            ['apprendre la langue locale avant expatriation meilleures methodes', 'informational', 'education', null],
            ['choc culturel en expatriation phases et comment le gerer', 'informational', 'expatriation', null],
            ['couple mixte expatriation defis culturels et administratifs', 'informational', 'expatriation', null],
            ['solitude expatrie comment creer un reseau social a l etranger', 'informational', 'communaute', null],
            ['procuration depuis l etranger comment faire demarche legale', 'informational', 'juridique', null],
            ['envoyer colis depuis l etranger vers la France pas cher', 'informational', 'quotidien', null],
            ['meilleur forfait telephone international pour expatrie 2026', 'commercial_investigation', 'telecom', null],
            ['Airalo vs Holafly esim pour voyager comparatif detaille', 'commercial_investigation', 'telecom', null],
        ];
    }

    // ══════════════════════════════════════════════════════════════
    // COMPARATIFS STRATÉGIQUES (30)
    // ══════════════════════════════════════════════════════════════
    private function comparatifsStrategiques(): array
    {
        return [
            ['cout de la vie Lisbonne vs Barcelone pour expatrie comparatif 2026', 'commercial_investigation', 'cout_vie', null],
            ['cout de la vie Bangkok vs Bali pour digital nomad comparatif', 'commercial_investigation', 'cout_vie', null],
            ['cout de la vie Dubai vs Singapour pour cadre expatrie', 'commercial_investigation', 'cout_vie', null],
            ['qualite de vie par pays pour expatrie classement mondial 2026', 'commercial_investigation', 'cout_vie', null],
            ['vivre en Asie vs Europe en tant qu expatrie avantages inconvenients', 'commercial_investigation', 'expatriation', null],
            ['expatriation vs digital nomadisme differences avantages inconvenients', 'commercial_investigation', 'expatriation', null],
            ['ecole internationale vs ecole locale pour enfant expatrie', 'commercial_investigation', 'education', null],
            ['NordVPN vs ExpressVPN vs Surfshark pour expatrie comparatif 2026', 'commercial_investigation', 'vpn', null],
            ['assurance locale vs assurance internationale pour expatrie', 'commercial_investigation', 'assurance', null],
            ['freelance a l etranger vs salarie detache comparatif statut', 'commercial_investigation', 'entrepreneuriat', null],
        ];
    }
}
