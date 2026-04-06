<?php

/**
 * SOS-Expat Knowledge Base v2.0 — COMPLETE Source of Truth for ALL AI content generation.
 *
 * This document is injected into EVERY content generation prompt to ensure
 * accuracy and consistency across all articles, Q/R, fiches, comparatives, etc.
 *
 * LAST VERIFIED: 2026-04-05 from SOS-Expat codebase (sos/firebase/functions/src/)
 *
 * RULES FOR AI:
 * - NEVER invent data not in this document
 * - NEVER change prices, durations, or commission rates
 * - ALWAYS use the exact service name "SOS-Expat" (with hyphen)
 * - ALWAYS mention "197 pays" and "9 langues" when relevant
 * - NEVER say SOS-Expat is free (it's a paid service)
 * - NEVER say SOS-Expat provides legal advice (it CONNECTS with lawyers)
 * - NEVER confuse lawyer (49EUR/20min) with expat expert (19EUR/30min)
 * - NEVER say SOS-Expat is an insurance or a consulate
 * - NEVER promise specific legal outcomes
 */

return [

    // =====================================================================
    // 1. IDENTITY & LEGAL ENTITY
    // =====================================================================

    'identity' => [
        'name' => 'SOS-Expat',
        'tagline_fr' => 'Parlez a un avocat ou expert local dans votre langue en moins de 5 minutes',
        'tagline_en' => 'Talk to a lawyer or local expert in your language in under 5 minutes',
        'website' => 'https://sos-expat.com',
        'founded' => 2026,
        'type' => 'Plateforme de mise en relation telephonique internationale',

        'legal_entity' => [
            'name' => 'WorldExpat OU',
            'country' => 'Estonie (Estonia)',
            'type' => 'OU (Osaühing — Estonian limited liability company)',
            'jurisdiction' => 'Tribunaux de Tallinn, Estonie',
            'dsa_compliance' => 'Regulation (EU) 2022/2065 — Digital Services Act',
            'invoice_display' => 'SOS Expat SAS (for French invoices)',
        ],

        'what_it_is' => [
            'Une plateforme qui CONNECTE les personnes a l\'etranger avec des avocats et experts locaux par telephone',
            'Service disponible 24h/24, 7j/7 dans 197 pays et 9 langues',
            'Mise en relation en moins de 5 minutes via appel telephonique',
            'Appel passe via IVR Twilio avec conference securisee',
            'De VRAIS humains au telephone — pas un chatbot, pas une IA',
        ],

        'what_it_is_NOT' => [
            'PAS un cabinet d\'avocats — SOS-Expat ne fournit PAS de conseil juridique directement',
            'PAS une assurance voyage — SOS-Expat ne rembourse rien en cas de sinistre',
            'PAS un consulat ou une ambassade — aucun pouvoir consulaire',
            'PAS un service gratuit — appels payants (avocat 49EUR/55USD, expert 19EUR/25USD)',
            'PAS un chatbot ou une IA — ce sont de VRAIS humains verifies au telephone',
            'PAS un service d\'urgence (ne pas utiliser pour urgences medicales vitales)',
            'PAS un service de conseil fiscal, medical ou reglementaire direct',
        ],
    ],

    // =====================================================================
    // 2. SERVICES & PRICING (exact from pricingService.ts)
    // =====================================================================

    'services' => [
        'lawyer' => [
            'name_fr' => 'Appel Avocat',
            'name_en' => 'Lawyer Call',
            'price_eur' => 49,
            'price_usd' => 55,
            'provider_payout_eur' => 30,
            'provider_payout_usd' => 30,
            'platform_fee_eur' => 19,
            'platform_fee_usd' => 25,
            'duration_minutes' => 20,
            'description_fr' => 'Mise en relation avec un avocat verifie dans le pays concerne. Specialites : droit de l\'immigration, droit du travail, droit commercial, fiscalite internationale, droit immobilier.',
            'description_en' => 'Connection with a verified lawyer in the relevant country. Specialties: immigration law, labor law, commercial law, international tax, real estate law.',
        ],
        'expat' => [
            'name_fr' => 'Appel Expert Local (Expat Aidant)',
            'name_en' => 'Local Expert Call (Expat Helper)',
            'price_eur' => 19,
            'price_usd' => 25,
            'provider_payout_eur' => 10,
            'provider_payout_usd' => 10,
            'platform_fee_eur' => 9,
            'platform_fee_usd' => 15,
            'duration_minutes' => 30,
            'description_fr' => 'Mise en relation avec un expatrie experimente vivant dans le pays concerne. Aide pratique : logement, banque, transport, vie quotidienne, communaute locale, demarches administratives.',
            'description_en' => 'Connection with an experienced expat living in the relevant country. Practical help: housing, banking, transport, daily life, local community, administrative procedures.',
        ],
        'note_important' => 'Les avocats restent seuls responsables de leurs conseils. Les experts locaux fournissent une aide pratique NON reglementee (orientation, contacts, traduction informelle).',
    ],

    // =====================================================================
    // 3. SUBSCRIPTIONS & AI ASSISTANT (exact from subscription/constants.ts)
    // =====================================================================

    'subscriptions' => [
        'trial' => [
            'duration_days' => 0, // Lifetime (no time limit)
            'ai_calls' => 3,
            'description' => '3 appels IA gratuits a vie (pas de limite de temps)',
        ],
        'lawyer_plans' => [
            'basic' => ['eur' => 19, 'usd' => 25, 'ai_calls' => 5],
            'standard' => ['eur' => 49, 'usd' => 59, 'ai_calls' => 15],
            'pro' => ['eur' => 79, 'usd' => 95, 'ai_calls' => 30],
            'unlimited' => ['eur' => 119, 'usd' => 145, 'ai_calls' => -1, 'fair_use_limit' => 500],
        ],
        'expat_plans' => [
            'basic' => ['eur' => 9, 'usd' => 9, 'ai_calls' => 5],
            'standard' => ['eur' => 19, 'usd' => 24, 'ai_calls' => 15],
            'pro' => ['eur' => 29, 'usd' => 34, 'ai_calls' => 30],
            'unlimited' => ['eur' => 49, 'usd' => 59, 'ai_calls' => -1, 'fair_use_limit' => 500],
        ],
        'annual_discount' => '20%',
        'grace_period_days' => 7,
        'payment_reminder_days' => 3,
    ],

    // =====================================================================
    // 4. COVERAGE & AVAILABILITY
    // =====================================================================

    'coverage' => [
        'countries' => 197,
        'languages' => ['fr', 'en', 'es', 'de', 'ru', 'pt', 'zh', 'hi', 'ar'],
        'language_names' => [
            'fr' => 'Francais',
            'en' => 'English',
            'es' => 'Espanol',
            'de' => 'Deutsch',
            'ru' => 'Russkij',
            'pt' => 'Portugues',
            'zh' => 'Zhongwen (Mandarin)',
            'hi' => 'Hindi',
            'ar' => 'Arabiyya',
        ],
        'availability' => '24/7 (24 heures sur 24, 7 jours sur 7)',
        'response_time' => 'Moins de 5 minutes',
        'tts_languages' => '50+ langues supportees pour les messages vocaux IVR',
    ],

    // =====================================================================
    // 5. HOW IT WORKS (call flow from TwilioCallManager.ts)
    // =====================================================================

    'how_it_works' => [
        'step_1' => 'L\'utilisateur choisit son besoin (avocat ou expert local), son pays et sa langue',
        'step_2' => 'SOS-Expat identifie un prestataire disponible dans le pays et la langue',
        'step_3' => 'Paiement securise (Stripe, PayPal ou Mobile Money)',
        'step_4' => 'Appel programme avec delai de 4 minutes pour preparation',
        'step_5' => 'Les deux parties recoivent un appel — confirmation DTMF (appuyez sur 1)',
        'step_6' => 'Conference telephonique securisee entre le client et le prestataire',
        'step_7' => 'Duree garantie : 20 min (avocat) ou 30 min (expert)',
        'call_retries' => 'Jusqu\'a 3 tentatives d\'appel si pas de reponse',
        'call_timeout' => '60 secondes par tentative, 90 secondes pour la connexion',
        'provider_cooldown' => '5 minutes de recuperation entre les appels',
    ],

    // =====================================================================
    // 6. TARGET AUDIENCE (10 profiles)
    // =====================================================================

    'audience' => [
        'primary' => [
            'expatries' => 'Expatries installes a l\'etranger — besoins juridiques, administratifs, pratiques',
            'voyageurs' => 'Voyageurs et vacanciers — problemes de visa, perte de passeport, urgences',
            'digital_nomads' => 'Digital nomads — fiscalite, visa, assurance, coworking, communaute',
            'etudiants' => 'Etudiants a l\'etranger — visa etudiant, logement, reconnaissance diplomes',
        ],
        'secondary' => [
            'retraites' => 'Retraites a l\'etranger — pension, fiscalite, sante, succession',
            'investisseurs' => 'Investisseurs internationaux — creation societe, fiscalite, droit commercial',
            'travailleurs_detaches' => 'Travailleurs detaches et frontaliers — contrat, securite sociale, impots',
            'voyageurs_affaires' => 'Voyageurs d\'affaires — visa business, douanes, contrats internationaux',
            'soignants' => 'Soignants et missionnaires humanitaires — visa ONG, assurance, securite',
            'refugies' => 'Refugies et demandeurs d\'asile — droit d\'asile, protection, demarches',
        ],
        'key_message' => 'SOS-Expat s\'adresse a TOUTE personne de TOUTE nationalite qui se trouve ou va se rendre a l\'etranger et a besoin d\'aide locale professionnelle.',
        'multi_nationality_rule' => 'CRITIQUE : NE JAMAIS ecrire du contenu uniquement pour les Francais. SOS-Expat est une plateforme MONDIALE. Un article en francais s\'adresse a TOUS les francophones (France, Belgique, Suisse, Canada, Afrique). Un article en anglais s\'adresse a TOUS les anglophones (US, UK, Australie, Inde, Afrique du Sud). Quand on parle d\'ambassade, dire "votre ambassade" ou "l\'ambassade de votre pays", PAS "l\'ambassade de France". Quand on parle de fiscalite, mentionner les conventions fiscales DE PLUSIEURS PAYS, pas uniquement la France.',
    ],

    // =====================================================================
    // 7. AFFILIATE PROGRAMS — ALL COMMISSIONS (exact from defaultPlans.ts)
    // All amounts in USD cents unless noted
    // =====================================================================

    'programs' => [

        // --- 7.1 CLIENT & PROVIDER (affiliate) ---
        'client_provider' => [
            'name' => 'Programme Client/Provider Affiliate',
            'signup_bonus' => 200,          // $2.00
            'lawyer_call' => 200,           // $2.00
            'expat_call' => 100,            // $1.00
            'description' => 'Tous les utilisateurs (clients et prestataires) gagnent des commissions via leurs liens affilies.',
        ],

        // --- 7.2 CHATTER ---
        'chatter' => [
            'name' => 'Programme Chatter',
            'description' => 'Partager SOS-Expat sur les reseaux sociaux et gagner des commissions sur chaque appel genere',
            'signup_bonus' => 200,              // $2.00
            'client_lawyer_call' => 500,        // $5.00 per call
            'client_expat_call' => 300,         // $3.00 per call
            'n1_call_commission' => 100,        // $1.00 (direct recruit's call)
            'n2_call_commission' => 50,         // $0.50 (indirect recruit's call)
            'activation_bonus' => 500,          // $5.00 (after recruit makes 2 calls)
            'activation_calls_required' => 2,
            'n1_recruit_bonus' => 100,          // $1.00 (when N1 recruits someone who activates)
            'provider_recruitment_lawyer' => 500,    // $5.00 (6-month window)
            'provider_recruitment_expat' => 300,     // $3.00 (6-month window)
            'provider_recruitment_window' => '6 mois',
            'telegram_bonus' => 5000,           // $50.00
            'telegram_unlock_threshold' => 15000, // $150.00 in direct client commissions
            'milestones' => [
                5 => 1500,      // 5 recruits → $15.00
                10 => 3500,     // 10 recruits → $35.00
                20 => 7500,     // 20 recruits → $75.00
                50 => 25000,    // 50 recruits → $250.00
                100 => 60000,   // 100 recruits → $600.00
                500 => 400000,  // 500 recruits → $4,000.00
            ],
            'top3_monthly' => [
                1 => ['cash' => 20000, 'multiplier' => 2.0],     // $200 + 2x next month
                2 => ['cash' => 10000, 'multiplier' => 1.5],     // $100 + 1.5x next month
                3 => ['cash' => 5000, 'multiplier' => 1.15],     // $50 + 1.15x next month
            ],
            'top3_eligibility_minimum' => 20000,  // $200 minimum to qualify
        ],

        // --- 7.3 CAPTAIN CHATTER ---
        'captain_chatter' => [
            'name' => 'Programme Captain Chatter',
            'description' => 'Leader d\'equipe chatters — bonus mensuels progressifs selon les performances de l\'equipe',
            'inherits' => 'Toutes les commissions du programme Chatter PLUS :',
            'captain_lawyer_call' => 300,       // $3.00 (on top of standard chatter commission)
            'captain_expat_call' => 200,        // $2.00
            'tiers' => [
                'bronze' => ['min_team_calls' => 20, 'bonus' => 2500],       // $25/month
                'argent' => ['min_team_calls' => 50, 'bonus' => 5000],       // $50/month
                'or' => ['min_team_calls' => 100, 'bonus' => 10000],         // $100/month
                'platine' => ['min_team_calls' => 200, 'bonus' => 20000],    // $200/month
                'diamant' => ['min_team_calls' => 400, 'bonus' => 40000],    // $400/month
            ],
            'quality_bonus' => [
                'amount' => 10000,                  // $100.00
                'min_active_recruits' => 10,
                'min_team_commissions' => 10000,    // $100.00 minimum team commissions
            ],
        ],

        // --- 7.4 INFLUENCER ---
        'influencer' => [
            'name' => 'Programme Influenceur',
            'description' => 'Createurs de contenu : monetisez votre audience avec SOS-Expat',
            'signup_bonus' => 200,              // $2.00
            'client_lawyer_call' => 500,        // $5.00
            'client_expat_call' => 300,         // $3.00
            'provider_recruitment_lawyer' => 500,
            'provider_recruitment_expat' => 300,
            'client_discount' => 500,           // $5.00 fixed discount for referred clients
            'n1_call_commission' => 100,        // $1.00
            'n2_call_commission' => 50,         // $0.50
            'activation_bonus' => 500,          // $5.00
            'activation_calls_required' => 2,
            'activation_min_direct_commissions' => 10000, // $100.00
            'n1_recruit_bonus' => 100,
            'recruitment_commission' => 500,     // $5.00 one-time when recruit earns $50
            'recruitment_threshold' => 5000,     // $50.00
            'telegram_bonus' => 5000,           // $50.00
            'milestones' => [
                5 => 1500, 10 => 3500, 20 => 7500, 50 => 25000, 100 => 60000, 500 => 400000,
            ],
            'top3_monthly_multipliers' => [1 => 2.0, 2 => 1.5, 3 => 1.15],
        ],

        // --- 7.5 BLOGGER ---
        'blogger' => [
            'name' => 'Programme Blogueur',
            'description' => 'Integrez le widget SOS-Expat sur votre blog et gagnez des commissions sur chaque appel client',
            'signup_bonus' => 200,              // $2.00
            'client_lawyer_call' => 500,        // $5.00
            'client_expat_call' => 300,         // $3.00
            'provider_recruitment_lawyer' => 500,
            'provider_recruitment_expat' => 300,
            'client_discount' => 0,             // $0.00 — PAS de reduction pour les clients des blogueurs
            'n1_call_commission' => 100,
            'n2_call_commission' => 50,
            'activation_bonus' => 500,
            'activation_calls_required' => 2,
            'activation_min_direct_commissions' => 10000, // $100.00
            'n1_recruit_bonus' => 100,
            'blogger_recruitment_bonus' => 5000,    // $50.00 one-time when recruited blogger earns $200
            'blogger_recruitment_threshold' => 20000, // $200.00
            'milestones' => [
                5 => 1500, 10 => 3500, 20 => 7500, 50 => 25000, 100 => 60000, 500 => 400000,
            ],
            'resources' => 'Widget, logos HD, bannieres, textes prets a l\'emploi, guide integration, QR codes',
        ],

        // --- 7.6 GROUP ADMIN ---
        'group_admin' => [
            'name' => 'Programme Admin Groupe',
            'description' => 'Monetisez vos groupes WhatsApp/Telegram/Facebook avec SOS-Expat',
            'signup_bonus' => 200,
            'client_lawyer_call' => 500,        // $5.00
            'client_expat_call' => 300,         // $3.00
            'provider_recruitment_lawyer' => 500,
            'provider_recruitment_expat' => 300,
            'client_discount' => 500,           // $5.00 fixed discount for referred clients
            'n1_call_commission' => 100,
            'n2_call_commission' => 50,
            'activation_bonus' => 500,
            'activation_calls_required' => 2,
            'activation_min_direct_commissions' => 10000,
            'n1_recruit_bonus' => 100,
            'milestones' => [
                5 => 1500, 10 => 3500, 20 => 7500, 50 => 25000, 100 => 60000, 500 => 400000,
            ],
            'top3_monthly' => [
                1 => ['cash' => 20000], 2 => ['cash' => 10000], 3 => ['cash' => 5000],
            ],
        ],

        // --- 7.7 B2B PARTNER ---
        'partner' => [
            'name' => 'Programme Partenaire B2B',
            'description' => 'Partenariats pour sites web et entreprises — commission pourcentage sur appels generes',
            'call_commission_rate' => 0.15,     // 15% of call revenue
            'categories' => [
                'expatriation', 'travel', 'legal', 'finance', 'insurance',
                'relocation', 'education', 'media', 'association', 'corporate', 'other',
            ],
            'traffic_tiers' => ['< 10k', '10k-50k', '50k-100k', '100k-500k', '500k-1M', '> 1M'],
            'custom_discount' => 'Remise personnalisee par partenaire',
        ],

        // --- 7.8 GENERAL AFFILIATE (percentage-based) ---
        'general_affiliate' => [
            'name' => 'Programme Affiliation General',
            'signup_bonus' => 200,              // $2.00
            'call_commission_rate' => 0.75,     // 75% (for specific configs)
            'subscription_rate' => 0.15,        // 15% of subscription revenue
            'provider_validation_bonus' => 2000, // $20.00
            'n1_call' => 100,
            'n2_call' => 50,
            'activation_bonus' => 500,
            'milestones' => [
                5 => 1500, 10 => 3500, 20 => 7500, 50 => 25000, 100 => 60000, 500 => 400000,
            ],
        ],

        // --- Common to all programs ---
        'common' => [
            'mlm_structure' => '2 niveaux (N1 direct + N2 indirect)',
            'milestone_reset' => 'Non — les milestones sont permanents (one-time bonus)',
            'top3_reset' => 'Mensuel — classement reinitialise chaque mois',
            'withdrawal_minimum' => 3000,       // $30.00
            'withdrawal_fee' => 300,            // $3.00 fixed per transaction
            'hold_period_hours' => 24,
            'max_withdrawals_per_month' => 0,   // 0 = unlimited
        ],
    ],

    // =====================================================================
    // 8. PAYMENT PROCESSORS
    // =====================================================================

    'payment' => [
        'stripe' => [
            'name' => 'Stripe',
            'type' => 'Carte bancaire (Visa, Mastercard, etc.)',
            'countries' => '44+ pays avec Stripe Express',
            'currencies' => ['EUR', 'USD'],
            'model' => 'Destination charges — SOS-Expat charge, transfert automatique au prestataire',
            'kyc' => 'Express account avec onboarding link pour verification KYC',
            'fees' => '2.9% + 0.25EUR/0.30USD + 1% FX cross-border',
        ],
        'paypal' => [
            'name' => 'PayPal',
            'type' => 'Paiement en ligne',
            'countries' => '150+ pays',
            'model' => 'Commerce Platform avec Partner Referrals API',
            'fees' => '2.9% + 0.35EUR/0.49USD + 3% FX, payout max 20EUR/20USD cap',
        ],
        'wise' => [
            'name' => 'Wise (TransferWise)',
            'type' => 'Virement bancaire international',
            'countries' => '195+ pays (couverture mondiale)',
            'usage' => 'Retraits affilies hors Afrique',
            'webhook' => 'Suivi statut en temps reel (processing, funds_converted, outgoing_payment_sent)',
        ],
        'flutterwave' => [
            'name' => 'Flutterwave',
            'type' => 'Mobile Money Afrique',
            'countries' => '30+ pays africains',
            'currencies' => ['XOF', 'XAF', 'GHS', 'KES', 'UGX', 'TZS', 'RWF', 'NGN', 'GNF', 'CDF', 'MZN', 'DZD', 'ZWL', 'SLL', 'SOS'],
            'providers' => [
                'Orange Money' => 'SN, CI, ML, BF, GN, CM, NE (XOF, XAF, GNF)',
                'Wave' => 'SN, CI, ML, BF (XOF)',
                'MTN MoMo' => 'CM, CI, BJ, GH, NG, UG, RW (XAF, XOF, GHS, NGN, UGX, RWF)',
                'Moov Money' => 'CI, BJ, TG, BF (XOF)',
                'Airtel Money' => 'GA, CG, TD, KE, TZ, UG (XAF, KES, TZS, UGX)',
                'M-Pesa' => 'KE, TZ, GH (KES, TZS, GHS)',
                'Free Money' => 'SN (XOF)',
                'T-Money' => 'TG (XOF)',
                'Flooz' => 'TG, BJ (XOF)',
                'Vodacom M-Pesa' => 'CD, TZ, MZ (CDF, TZS, MZN)',
                'Mobilis' => 'DZ (DZD)',
                'EcoCash' => 'ZW (ZWL, USD)',
                'AfriMoney' => 'SL, GN (SLL, GNF)',
                'Hormuud EVC Plus' => 'SO (SOS, USD)',
            ],
        ],
    ],

    // =====================================================================
    // 9. LEGAL & COMPLIANCE
    // =====================================================================

    'legal' => [
        'terms_versions' => [
            'terms' => '2.2 (16 juin 2025)',
            'terms_expats' => '2.2 (16 juin 2025)',
            'terms_clients' => '3.0 (1er fevrier 2026)',
            'terms_affiliate' => '1.0 (27 fevrier 2026)',
            'terms_bloggers' => '1.0 (1er fevrier 2026)',
            'privacy_policy' => '2.2 (16 juin 2025)',
        ],
        'cgu_languages' => '9 langues (fr, en, es, de, ru, pt, zh, hi, ar)',

        'gdpr' => [
            'hard_deletion' => 'Art. 17 RGPD — fonction hardDeleteProvider() avec audit log AVANT suppression',
            'data_purged' => 'users, sos_profiles, kyc_documents, notifications — anonymisation call_sessions/payments',
            'requires_flag' => 'confirmGdprPurge: true requis pour execution',
            'rights' => 'Acces, rectification, effacement, portabilite, opposition, limitation',
        ],

        'dsa' => 'Regulation (EU) 2022/2065 — plateforme "intermediary service" avec mecanismes de signalement',

        'refund_policy' => [
            'before_connection' => 'Remboursement INTEGRAL si annulation avant connexion (avant transmission des coordonnees)',
            'after_connection' => 'NON remboursable une fois la connexion etablie',
            'provider_cancels' => 'Remboursement integral + proposition de re-routage vers un autre prestataire',
            'no_connection_possible' => 'Remboursement integral si aucun prestataire disponible apres tentatives',
            'technical_issue' => 'Remboursement ou re-credit a la discretion de SOS-Expat',
            'consumer_withdrawal' => 'Droit de retractation perdu une fois le service execute (si execution immediate demandee)',
        ],

        'disclaimers' => [
            'SOS-Expat n\'est PAS un cabinet d\'avocats et ne fournit AUCUN conseil juridique, medical, fiscal ou reglementaire',
            'SOS-Expat n\'est PAS partie au contrat entre le client et le prestataire (avocat/expert)',
            'Les avocats restent SEULS responsables de leurs conseils et du respect de la deontologie/lois locales',
            'Les experts locaux fournissent une aide NON reglementee (orientation pratique, contacts, traduction informelle)',
            'AUCUNE garantie sur la qualite, le resultat ou la disponibilite des prestataires',
            'Responsabilite de SOS-Expat LIMITEE au prix total paye pour la reservation concernee',
            'Ne PAS utiliser pour des urgences medicales ou situations mettant la vie en danger',
        ],

        'kyc_aml' => 'Controles KYC/LCB-FT sur tous les paiements',
        'telegram_mandatory' => 'Connexion Telegram obligatoire pour les retraits affilies (double validation)',
    ],

    // =====================================================================
    // 10. BRAND VOICE
    // =====================================================================

    'brand_voice' => [
        'tone' => 'Professionnel mais accessible. Comme un ami expert qui donne des conseils pratiques. Bienveillant, rassurant, concret.',

        'never_say' => [
            'Ne jamais dire "SOS Expat" sans le tiret — c\'est TOUJOURS "SOS-Expat"',
            'Ne jamais dire que SOS-Expat est gratuit (c\'est un service payant)',
            'Ne jamais dire que SOS-Expat donne des conseils juridiques (il CONNECTE avec des avocats)',
            'Ne jamais utiliser un ton alarmiste ou anxiogene',
            'Ne jamais denigrer les ambassades, assurances ou concurrents',
            'Ne jamais promettre des resultats specifiques (chaque situation est unique)',
            'Ne jamais confondre avocat (49EUR ou 55USD/20min) et expert local (19EUR ou 25USD/30min)',
            'Ne jamais dire que c\'est un chatbot ou une IA — ce sont de vrais humains',
        ],

        'always_say' => [
            'Toujours mentionner "197 pays" et "9 langues" quand c\'est pertinent',
            'Toujours preciser "en moins de 5 minutes" pour le temps de mise en relation',
            'Toujours differencier avocat (49EUR ou 55USD/20min) et expert local (19EUR ou 25USD/30min)',
            'Toujours rappeler que le service est disponible 24h/24, 7j/7',
            'Toujours inclure un CTA vers sos-expat.com en fin d\'article (max 1 CTA)',
        ],
    ],

    // =====================================================================
    // 11. CONTENT RULES PER TYPE (anti-redundancy STRICT)
    // =====================================================================

    'content_rules' => [
        'fiches_pays' => 'Guide complet d\'un pays. Vue d\'ensemble large (10 sections : visa, logement, travail, sante, banque, fiscalite, securite, culture, cout de vie, transport). NE PAS approfondir un seul sujet — c\'est le role des articles mots-cles. NE PAS repeter les fiches expat ou vacances.',
        'fiches_expat' => 'Guide expatriation specifique au pays. Visa long sejour, permis de travail, logement, fiscalite, sante, retraite. NE PAS repeter la fiche pays generale. Profondeur pratique pour quelqu\'un qui s\'INSTALLE.',
        'fiches_vacances' => 'Guide vacances specifique au pays. Visa touriste, budget 1-2 semaines, securite, sante voyageur, monnaie, pourboires, transports locaux. NE PAS repeter la fiche expat. Focus COURT SEJOUR.',
        'art_mots_cles' => 'Article APPROFONDI sur 1 sujet precis. 1500-2500 mots. NE PAS etre un guide general du pays. Aller en PROFONDEUR avec donnees chiffrees, etapes concretes, exemples reels, sources citees. Mot-cle principal dans titre + H2.',
        'chatters' => 'Article de recrutement chatter. Avantages du programme ($5 par appel avocat, $3 par appel expert, MLM 2 niveaux, milestones jusqu\'a $4000), missions concretes, temoignages. CTA inscription. NE PAS etre un guide d\'expatriation.',
        'influenceurs' => 'Article de recrutement influenceur. Monetisation audience, commissions ($5/$3 par appel, $5 reduction pour clients), widget, integration facile. CTA inscription. NE PAS etre un guide d\'expatriation.',
        'admin_groupes' => 'Article de recrutement admin groupe WhatsApp/Telegram/Facebook. Monetisation communaute existante, commissions, recrutement de prestataires. CTA inscription.',
        'avocats' => 'Article pour attirer des avocats prestataires. Clientele internationale 197 pays, appels remuneres (30EUR par appel), flexibilite 24/7, pas de cout d\'acquisition client. CTA inscription prestataire.',
        'expats_aidants' => 'Article pour attirer des expatries aidants. Partager son experience, revenu complementaire (10EUR par appel), flexibilite totale, aider des compatriotes. CTA inscription.',
        'comparatifs' => 'Comparaison OBJECTIVE de 2+ services/pays. Tableau comparatif structure (8-12 criteres), pros/cons, verdict argumente. NE PAS etre promotionnel pour SOS-Expat sauf CTA naturel en fin.',
        'affiliation' => 'Comparatif de services avec liens affilies. Banques pour expatries, assurances voyage, transferts d\'argent, VPN, outils. Objectif = conversion affiliation. Objectivite = credibilite.',
        'qr' => 'Reponse courte et directe a une question precise. 300-800 mots. Featured snippet en premier paragraphe (40-60 mots = reponse directe). FAQ 5 questions liees. NE PAS etre un guide long.',
        'news' => 'Actualite expatries/voyageurs. Ton journalistique. Evenement recent avec date, source, impact. NE PAS repeter du contenu evergreen. 800-1200 mots.',
    ],

    // =====================================================================
    // 12. SEO / CTA RULES
    // =====================================================================

    'seo_rules' => [
        'cta_max' => 'Maximum 1 CTA vers SOS-Expat par article (naturel, en fin d\'article)',
        'cta_format' => 'Besoin d\'aide sur place ? Un avocat ou expert local disponible en moins de 5 min via SOS-Expat.',
        'internal_links' => 'Toujours lier vers la fiche pays concernee et les articles thematiques lies. Maillage : fiche pays <-> articles satellites <-> Q/R.',
        'featured_snippet' => 'Premier paragraphe = reponse directe en 40-60 mots (position 0 Google). Commencer par une reformulation du sujet. Structure : definition → chiffre cle → contexte.',
        'no_keyword_stuffing' => 'Densite mot-cle 1-2% maximum, ecrire naturellement',
        'year_mention' => 'Mentionner l\'annee en cours (2026) dans le titre et le contenu quand pertinent',
        'title_length' => 'Titre SEO : 55-65 caracteres. Meta description : 140-155 caracteres.',
        'h2_structure' => 'Minimum 5 sous-titres H2 par article long. Questions en H2 pour les Q/R. H2 doit contenir le mot-cle ou un synonyme.',
        'eeat_signals' => [
            'experience' => 'Inclure des temoignages reels, etudes de cas, exemples vecus par des expatries',
            'expertise' => 'Citer des sources officielles (gouvernement, ONU, OCDE). Donnees chiffrees avec annee. Auteur expert identifie.',
            'authoritativeness' => 'Lier vers des sources .gov, .int, .edu. Mentionner les partenariats et la couverture 197 pays.',
            'trustworthiness' => 'Date de mise a jour visible. Mentions legales claires. HTTPS. Avis verifies. Politique de remboursement transparente.',
        ],
    ],

    // =====================================================================
    // 12b. AEO — ANSWER ENGINE OPTIMIZATION (SearchGPT, Perplexity, Claude)
    // =====================================================================

    'aeo_rules' => [
        'ai_summary' => 'Chaque article DOIT avoir un champ ai_summary : 1 phrase factuelle de max 100 caracteres, reponse directe a l\'intention de recherche. Optimise pour etre cite par les moteurs IA.',
        'concise_answers' => 'Chaque section doit commencer par une phrase-reponse directe AVANT le developpement. Les moteurs IA extraient le debut de chaque section.',
        'structured_data' => 'Toujours inclure FAQ Schema (5-7 Q/R), Article Schema avec dateModified, et Speakable Schema sur le featured snippet + H1.',
        'entity_clarity' => 'Definir clairement chaque entite mentionnee (pays, service, loi) des sa premiere apparition. Les moteurs IA ont besoin de contexte explicite.',
        'citation_worthy' => 'Ecrire des phrases auto-suffisantes et citables : "En 2026, le visa digital nomad en Espagne coute 80EUR et dure 1 an." — pas de pronoms ambigus.',
        'no_fluff' => 'Zero phrase de remplissage. Chaque phrase doit apporter une information nouvelle. Les moteurs IA penalisent le contenu dilue.',
        'source_attribution' => 'Citer la source entre parentheses : "(source: Ministere des Affaires Etrangeres, 2026)" — les moteurs IA valorisent les sources verifiables.',
    ],

    // =====================================================================
    // 12c. SPEAKABLE & SCHEMA MARKUP
    // =====================================================================

    'schema_rules' => [
        'speakable' => 'OBLIGATOIRE sur chaque article : SpeakableSpecification avec cssSelector [".featured-snippet", "h1"]. Optimise pour Google Assistant et assistants vocaux.',
        'faq_schema' => 'FAQPage Schema avec 5-7 questions par article. Questions = vraies requetes Google. Reponses 80-150 mots chacune.',
        'article_schema' => 'Article Schema avec : headline, datePublished, dateModified, author (Organization: SOS-Expat), publisher, image, inLanguage.',
        'breadcrumb_schema' => 'BreadcrumbList Schema sur chaque page : Accueil > Categorie > Pays (si applicable) > Article.',
        'howto_schema' => 'HowTo Schema pour les guides etape par etape (visa, demarches admin). Inclure estimatedCost et totalTime quand applicable.',
        'comparative_schema' => 'ItemList Schema pour les comparatifs avec ListItem pour chaque entite comparee.',
    ],

    // =====================================================================
    // 12d. HREFLANG & MULTI-LANGUE SEO
    // =====================================================================

    'hreflang_rules' => [
        'mandatory' => 'CHAQUE article traduit DOIT avoir des balises hreflang pour les 9 langues + x-default (= fr)',
        'canonical' => 'La version FR est TOUJOURS le canonical principal. Les traductions pointent vers leur propre URL canonique.',
        'url_structure' => 'Format URL : /articles/{slug-fr} (FR), /en/articles/{slug-en} (EN), /es/articulos/{slug-es} (ES), etc.',
        'no_auto_redirect' => 'NE PAS rediriger automatiquement selon la geo IP — Google doit indexer chaque version separement.',
        'sitemap_multi' => 'Sitemap avec <xhtml:link rel="alternate"> pour chaque paire langue/URL.',
    ],

    // =====================================================================
    // 13. INTERACTIVE TOOLS (26 outils, 4 categories)
    // =====================================================================

    'tools' => [
        'total' => 24,
        'access' => 'Gratuit, sans inscription',
        'url' => 'https://sos-expat.com/fr-fr/outils',
        'categories' => [
            'calculate' => [
                'visa-calculator' => 'Calculateur d\'eligibilite visa',
                'cost-of-living' => 'Estimateur cout de la vie',
                'net-salary-expat' => 'Calculateur salaire net apres impots',
                'retirement-simulator' => 'Simulateur retraite a l\'etranger',
                'travel-budget' => 'Planificateur budget voyage',
                'double-taxation' => 'Calculateur double imposition',
                '183-day-rule' => 'Calculateur regle des 183 jours (residence fiscale)',
                'tax-resident-check' => 'Verificateur statut resident fiscal',
            ],
            'compare' => [
                'insurance-comparator' => 'Comparateur assurances expatries',
                'bank-comparator' => 'Comparateur banques internationales',
                'country-recommender' => 'Recommandation de pays selon profil',
                'legal-status-comparator' => 'Comparateur visas et statuts juridiques',
                'nomad-country' => 'Finder destination digital nomad',
            ],
            'generate' => [
                'diploma-recognition' => 'Guide reconnaissance diplomes',
                'call-planner' => 'Planificateur rendez-vous SOS-Expat',
                'departure-checklist' => 'Checklist depart expatriation',
                'admin-checklist' => 'Checklist demarches administratives',
                'packing-list' => 'Liste de preparation demenagement',
                'visa-letter-ai' => 'Generateur lettre de motivation visa (IA)',
                'freelance-contract-ai' => 'Generateur contrat freelance international (IA)',
            ],
            'emergency' => [
                'embassy-finder' => 'Localisateur d\'ambassades dans le monde',
                'passport-theft' => 'Guide perte/vol de passeport',
                'doctors-directory' => 'Annuaire medecins/professionnels de sante',
                'risk-map' => 'Carte des risques securitaires par pays',
            ],
        ],
        'ai_powered' => ['visa-letter-ai', 'freelance-contract-ai'],
    ],

    // =====================================================================
    // 14. SURVEYS (sondages)
    // =====================================================================

    'surveys' => [
        'types' => ['expat', 'vacancier'],
        'languages' => 9,
        'features' => [
            'Votes anonymes par pays',
            'Statistiques en temps reel (total responses, geographic breakdown)',
            'Export CSV',
            'Questions : single choice, multiple choice, scale, open text',
        ],
    ],

    // =====================================================================
    // 15. REVIEW SYSTEM
    // =====================================================================

    'reviews' => [
        'scale' => '1 a 5 etoiles',
        'helpful_votes' => 'Compteur "utile" par avis',
        'moderation' => [
            'statuses' => ['pending', 'published', 'rejected', 'hidden'],
            'verification_badge' => 'Avis verifies marques comme tels',
            'reported_count' => 'Compteur signalements spam/abus',
            'admin_notes' => 'Notes moderateur pour les rejets',
        ],
        'fields' => 'Note, commentaire, type de service, type d\'aide, duree, titre, contenu complet',
        'display' => '20 avis par page avec pagination',
    ],

    // =====================================================================
    // 16. ANTI-FRAUD SYSTEM
    // =====================================================================

    'anti_fraud' => [
        'frontend' => [
            'recaptcha_v3' => 'Verification invisible (pas de prompt utilisateur)',
            'honeypot_field' => 'Champ cache — seuls les bots le remplissent',
            'behavior_tracking' => 'Mouvements souris, frappes clavier, temps de remplissage (min 5 secondes)',
        ],
        'backend' => [
            'ip_detection' => 'Max 3 comptes par IP en 24h, max 5 en 7 jours (hash SHA-256)',
            'email_blocking' => '40+ domaines email jetables bloques (tempmail, yopmail, guerrillamail...)',
            'circular_referral' => 'Detection auto-parrainage',
            'rapid_signup' => 'Max 10 inscriptions rapides par parrain en 24h, max 5 en 1h',
            'activation_guard' => 'Bonus activation $5 SEULEMENT apres 2 appels reels (pas a l\'inscription)',
        ],
        'severity_levels' => ['low', 'medium', 'high', 'critical'],
    ],

    // =====================================================================
    // 17. NOTIFICATION SYSTEM
    // =====================================================================

    'notifications' => [
        'email' => [
            'provider' => 'MailWizz',
            'templates' => 'TR_PRO_*, TR_CLI_*, TR_CHAT_* + langue (ex: TR_PRO_call-completed_FR)',
            'languages' => '9 langues',
        ],
        'telegram' => [
            'bots' => 3,
            'bot_names' => ['@sos_expat_bot (business events)', '@sos_expat_inbox_bot (messages)', '@sos_expat_withdrawals_bot (retraits)'],
            'event_types' => [
                'new_registration', 'call_completed', 'payment_received', 'daily_report',
                'new_provider', 'new_contact_message', 'negative_review', 'security_alert',
                'withdrawal_request', 'captain_application',
            ],
        ],
        'push' => 'Firebase Cloud Messaging (FCM) via PWA — permission a l\'installation',
        'in_app' => 'Notifications temps reel via Firestore listeners + centre notifications',
    ],

    // =====================================================================
    // 18. INFRASTRUCTURE (for technical articles)
    // =====================================================================

    'infrastructure' => [
        'regions' => [
            'europe-west1' => 'Belgique — Core business & APIs publiques (~206 fonctions)',
            'us-central1' => 'Iowa, USA — Affiliate & Marketing (~201 fonctions) — latence Firestore optimale',
            'europe-west3' => 'Francfort — Payments + Twilio PROTEGE (~252 fonctions) — temps reel critique',
        ],
        'firestore' => 'nam7 (Iowa, US) — base de donnees principale',
        'frontend' => 'Cloudflare Pages (auto-deploy GitHub)',
        'functions' => '650+ Cloud Functions Firebase',
        'collections' => '100+ collections Firestore',
        'admin_pages' => '164 pages admin',
        'public_pages' => '50+ pages publiques',
    ],

    // =====================================================================
    // 19. ANNUAIRE (Directory) — Provider Search
    // =====================================================================

    'annuaire' => [
        'description' => 'Annuaire mondial de ressources par pays avec 13 categories',
        'categories' => [
            'urgences', 'ambassade', 'immigration', 'sante', 'logement', 'banque',
            'emploi', 'education', 'transport', 'telecom', 'fiscalite', 'juridique', 'communaute',
        ],
        'features' => [
            'Recherche par pays (200+)',
            'Filtre par nationalite (personnalisation)',
            'Score de confiance par entree',
            'Multi-langue (9 langues)',
            'Historique recherches recentes (max 6)',
        ],
    ],

    // =====================================================================
    // 20. ANTI-CANNIBALIZATION RULES (for content generators)
    // =====================================================================

    // =====================================================================
    // 21. SEARCH INTENT → CONTENT FORMAT (Google ranking factor #1)
    // =====================================================================

    'search_intent' => [
        'informational' => [
            'name' => 'Informationnelle',
            'description' => 'L\'utilisateur veut APPRENDRE ou COMPRENDRE. Ex: "qu\'est-ce qu\'un visa digital nomad", "comment fonctionne la CFE"',
            'format' => [
                'structure' => 'Guide explicatif structure en sections H2 (chaque H2 = une sous-question)',
                'featured_snippet' => 'OBLIGATOIRE — premier paragraphe = definition directe 40-60 mots, commence par reformulation du sujet',
                'longueur' => '1500-3000 mots',
                'elements_html' => [
                    '<h2> pour chaque sous-question (minimum 6)',
                    '<p> premier paragraphe = reponse directe avant le developpement',
                    '<ul>/<ol> listes a puces pour les etapes ou criteres',
                    '<blockquote> pour les encadres "Bon a savoir" ou "Attention"',
                    '<table> si comparaison de donnees (prix, durees, conditions)',
                    '<strong> pour les termes cles et chiffres importants',
                ],
                'faq_count' => 6,
                'cta' => '1 CTA naturel en fin d\'article vers SOS-Expat',
                'tone' => 'Pedagogique, structure, progressif (du simple au complexe)',
            ],
        ],
        'commercial_investigation' => [
            'name' => 'Investigation commerciale',
            'description' => 'L\'utilisateur veut COMPARER avant de decider. Ex: "meilleure assurance expatrie 2026", "Wise vs Revolut pour expatries"',
            'format' => [
                'structure' => 'Comparatif structure avec tableau, pros/cons, et verdict argumente',
                'featured_snippet' => 'OBLIGATOIRE — premier paragraphe = verdict direct ("En 2026, la meilleure assurance expatrie est X pour Y raison")',
                'longueur' => '2000-3500 mots',
                'elements_html' => [
                    '<table> OBLIGATOIRE en haut de page avec <thead>/<tbody> — criteres en lignes, options en colonnes',
                    '<h2> pour chaque option comparee + "Notre verdict" + "Comment choisir"',
                    'Encadres pros/cons pour chaque option (<div class="pros"> et <div class="cons">)',
                    '<strong> pour les prix et les notes',
                    '<ol> pour le classement final (Top 1, Top 2, Top 3)',
                    '<blockquote> pour le verdict et les recommandations par profil',
                ],
                'faq_count' => 5,
                'cta' => '1 CTA naturel ("Besoin d\'aide pour choisir ? Un expert SOS-Expat vous guide")',
                'tone' => 'Objectif, factuel, donnees chiffrees, comparaison honnete',
            ],
        ],
        'transactional' => [
            'name' => 'Transactionnelle',
            'description' => 'L\'utilisateur veut AGIR ou ACHETER maintenant. Ex: "acheter assurance voyage expatrie", "prendre rdv avocat immigration"',
            'format' => [
                'structure' => 'Page orientee action avec prix, etapes, et CTA clair',
                'featured_snippet' => 'OBLIGATOIRE — premier paragraphe = reponse a "combien ca coute" ou "comment faire" en 1 phrase',
                'longueur' => '800-1500 mots (COURT — l\'utilisateur veut agir, pas lire)',
                'elements_html' => [
                    'Encadre PRIX en haut de page (<div class="pricing-box">) avec montant, duree, ce qui est inclus',
                    '<ol> etapes concretes pour passer a l\'action (maximum 5-7 etapes)',
                    '<table> recapitulatif des options si plusieurs formules',
                    '<strong> pour prix et delais',
                    'Encadre confiance : "197 pays, 24/7, 9 langues, avis verifies"',
                    '<blockquote> temoignage ou avis client',
                ],
                'faq_count' => 3,
                'cta' => '2-3 CTA vers SOS-Expat (haut, milieu, fin — l\'utilisateur veut agir)',
                'tone' => 'Direct, rassurant, oriente action, zero jargon',
            ],
        ],
        'local' => [
            'name' => 'Locale',
            'description' => 'L\'utilisateur cherche un service DANS un pays/ville precis. Ex: "avocat francais Bangkok", "medecin francophone Lisbonne"',
            'format' => [
                'structure' => 'Fiche locale avec informations pratiques geographiques',
                'featured_snippet' => 'OBLIGATOIRE — "Pour trouver un {service} a {ville/pays}, voici les options disponibles en 2026"',
                'longueur' => '1000-2000 mots',
                'elements_html' => [
                    '<h2> par type de ressource (ambassade, professionnels prives, organismes officiels)',
                    '<table> avec colonnes : Nom, Adresse, Contact, Langues, Horaires',
                    '<ul> liens utiles officiels (ambassade, consulat, associations)',
                    '<blockquote> conseil pratique local',
                    'Mention de SOS-Expat comme alternative rapide (mise en relation en 5 min)',
                ],
                'faq_count' => 4,
                'cta' => '1 CTA ("Pas le temps de chercher ? SOS-Expat vous connecte en 5 min")',
                'tone' => 'Pratique, local, concret, avec donnees de contact',
            ],
        ],
        'urgency' => [
            'name' => 'Urgence',
            'description' => 'L\'utilisateur a un probleme MAINTENANT. Ex: "passeport vole Thailande que faire", "accident voiture a l\'etranger"',
            'format' => [
                'structure' => 'Guide d\'urgence avec etapes numerotees IMMEDIATEMENT actionnables',
                'featured_snippet' => 'OBLIGATOIRE — "En cas de {urgence} a {pays}, appelez immediatement le {numero}. Voici les 5 etapes a suivre."',
                'longueur' => '800-1500 mots (COURT — urgence = pas le temps de lire)',
                'elements_html' => [
                    '<div class="emergency-box"> en HAUT de page avec numeros d\'urgence (police, ambulance, ambassade)',
                    '<ol> etapes numerotees (maximum 7) — chaque etape = 1 phrase d\'action',
                    '<strong> pour CHAQUE numero de telephone et adresse',
                    '<blockquote class="warning"> pour les erreurs a NE PAS commettre',
                    '<table> numeros utiles (police, ambulance, pompiers, ambassade) si pays specifique',
                    'Lien direct vers l\'ambassade du pays',
                ],
                'faq_count' => 3,
                'cta' => '1 CTA urgent ("Besoin d\'un avocat MAINTENANT ? SOS-Expat : mise en relation en moins de 5 minutes, 24h/24")',
                'tone' => 'Calme, rassurant, DIRECTIF — chaque phrase = une action concrete',
            ],
        ],
        'navigational' => [
            'name' => 'Navigationnelle',
            'description' => 'L\'utilisateur cherche un SITE ou une PAGE specifique. Ex: "SOS-Expat connexion", "sos-expat.com"',
            'format' => [
                'generate' => false,
                'reason' => 'Pas de contenu a generer — ces requetes menent directement a l\'app SOS-Expat',
            ],
        ],
    ],

    // =====================================================================
    // 22. LONG-TAIL CONTENT RULES (requetes specifiques a fort taux de conversion)
    // =====================================================================

    'long_tail_rules' => [
        'definition' => 'Requete de 4+ mots avec intention tres precise. Moins de volume, mais BEAUCOUP plus facile a ranker et meilleur taux de conversion.',
        'avantages' => [
            'Moins de concurrence que les requetes courtes',
            'Intention plus claire = meilleur match de contenu',
            'Meilleur taux de conversion (l\'utilisateur sait ce qu\'il veut)',
            'Plus facile d\'obtenir le featured snippet (position 0)',
        ],
        'regles_generation' => [
            'Le titre DOIT reprendre la requete longue traine EXACTEMENT (ou tres proche)',
            'Le premier paragraphe DOIT repondre directement a la requete en 40-60 mots',
            'Le contenu DOIT etre SPECIFIQUE — pas de generalites, pas de remplissage',
            'Chaque H2 doit approfondir UN aspect de la requete',
            'Les FAQ doivent etre des VARIANTES de la requete principale',
            'Le maillage interne DOIT lier vers la fiche pays + l\'article general sur le sujet',
        ],
        'exemples' => [
            'informational' => 'comment ouvrir un compte bancaire au Portugal en tant que francais sans NIE',
            'commercial' => 'assurance sante digital nomad thailande moins de 100 euros par mois comparatif',
            'transactional' => 'prendre rendez-vous avocat immigration canada en ligne',
            'local' => 'medecin generaliste francophone a Lisbonne quartier Baixa',
            'urgency' => 'passeport francais vole a Bangkok que faire en urgence etapes',
        ],
    ],

    // =====================================================================
    // 23. ANTI-CANNIBALIZATION RULES (for content generators)
    // =====================================================================

    'anti_cannibalization' => [
        'rule_1' => 'FICHE PAYS ≠ FICHE EXPAT ≠ FICHE VACANCES — 3 angles DISTINCTS, jamais de repetition',
        'rule_2' => 'ARTICLE MOT-CLE = 1 sujet precis en PROFONDEUR — jamais un guide general du pays',
        'rule_3' => 'Q/R = reponse COURTE et directe — jamais un article long',
        'rule_4' => 'NEWS = actualite RECENTE avec date — jamais du contenu evergreen',
        'rule_5' => 'COMPARATIF = objectif avec tableau — jamais promotionnel',
        'rule_6' => 'CHATTERS/INFLUENCEURS/ADMINS = recrutement — jamais un guide d\'expatriation',
        'rule_7' => 'AVOCATS/EXPATS AIDANTS = attirer des prestataires — jamais un guide client',
        'rule_8' => 'Si un sujet est deja couvert dans une fiche pays, l\'article mot-cle doit apporter une profondeur SUPPLEMENTAIRE, pas repeter',
        'rule_9' => 'Verifier les articles existants AVANT de generer — si doublon > 50% similarite titre, NE PAS generer',
    ],

];
