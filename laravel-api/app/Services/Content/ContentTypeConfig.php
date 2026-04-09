<?php

namespace App\Services\Content;

class ContentTypeConfig
{
    /**
     * Anti-generic title instruction appended to every prompt_suffix.
     * Prevents AI from generating clickbait titles like "Guide Complet", "Guide Ultime", etc.
     */
    private const TITLE_INSTRUCTION = "\n\nTITRE : NE JAMAIS utiliser « Guide Complet », « Guide Ultime », « Tout savoir sur », « Découvrez ». "
        . "Le titre doit être une requête Google naturelle, spécifique, avec le pays/ville et l'année si applicable.";

    /**
     * Get AI configuration for each content type.
     * Different types get different AI models, prompts, and depth.
     */
    public static function get(string $type): array
    {
        $config = match ($type) {
            // PILLAR ARTICLES (fiches pays, guides complets)
            // Maximum quality: GPT-4o for content + Perplexity for research + longest format
            // CITY GUIDES — PILLAR CONTENT (fiches villes — articles piliers comme les fiches pays)
            'guide_city' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 4000,
                'max_words' => 7000,
                'target_words' => 5000,
                'target_words_range' => '4000-7000',
                'length' => 'extra_long',
                'faq_count' => 10,
                'max_tokens_content' => 16384,
                'max_tokens_title' => 100,
                'internal_links' => 8,
                'external_links' => 5,
                'images_count' => 4,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => true,
                'research_depth' => 'deep',
                'quality_threshold' => 90,
                'h2_count' => [8, 12],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "ARTICLE PILIER specifique a la VILLE — doit etre la REFERENCE MONDIALE sur cette ville pour les expatries. "
                    . "Mentionner les QUARTIERS par nom, les prix LOCAUX en devise locale + EUR/USD, "
                    . "les transports LOCAUX (metro, bus, taxi, VTC), les adresses PRECISES. "
                    . "Inclure un tableau comparatif des quartiers (prix, securite, ambiance, transport). "
                    . "S'adresser a TOUTE nationalite d'expatrie, pas uniquement les Francais. "
                    . "Lier vers la fiche pays correspondante pour le contexte national (visa, fiscalite, sante). "
                    . "Cet article est un PILIER — les articles satellites (logement, coworking, ecoles) viendront apres.",
            ],

            // PILLAR ARTICLES (fiches pays, guides complets)
            'guide', 'pillar' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 4000,
                'max_words' => 7000,
                'target_words' => 5000,
                'target_words_range' => '4000-7000',
                'length' => 'extra_long',
                'faq_count' => 12,
                'max_tokens_content' => 16384,
                'max_tokens_title' => 100,
                'internal_links' => 8,
                'external_links' => 5,
                'images_count' => 4,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => true,
                'research_depth' => 'deep',
                'quality_threshold' => 90,
                'h2_count' => [8, 12],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Cet article doit etre la REFERENCE MONDIALE sur ce sujet. "
                    . "Il doit etre plus complet, plus detaille et plus utile que TOUT ce qui existe sur le web. "
                    . "Inclure des donnees chiffrees precises, des tableaux comparatifs, des listes d'etapes, "
                    . "des conseils pratiques uniques, et des avertissements importants.",
            ],

            // NORMAL ARTICLES (thematiques, pratiques)
            'article' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.7,
                'min_words' => 2000,
                'max_words' => 3000,
                'target_words' => 2500,
                'target_words_range' => '2000-3000',
                'length' => 'long',
                'faq_count' => 8,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 6,
                'external_links' => 3,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 85,
                'h2_count' => [6, 8],
                'include_charts_data' => false,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article informatif et pratique de haute qualite. "
                    . "OBLIGATOIRE : inclure au moins 3 donnees chiffrees precises et verifiees (montants, delais, pourcentages, statistiques officielles). "
                    . "Chaque section H2 doit apporter une valeur concrete. "
                    . "Un encadre 'Chiffres cles' avec les donnees essentielles en <strong>.",
            ],

            // COMPARATIVES (tableaux, donnees structurees)
            'comparative' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.5,
                'min_words' => 2500,
                'max_words' => 4000,
                'target_words' => 3000,
                'target_words_range' => '2500-4000',
                'length' => 'long',
                'faq_count' => 6,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 5,
                'external_links' => 4,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => false,
                'research_depth' => 'deep',
                'quality_threshold' => 85,
                'h2_count' => [5, 8],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article COMPARATIF avec OBLIGATOIREMENT : "
                    . "1) Au moins 2 tableaux <table> comparatifs detailles avec <thead> et <tbody>, "
                    . "2) Des donnees chiffrees precises pour chaque entite comparee, "
                    . "3) Un bloc 'Chiffres cles' avec les donnees importantes en <strong>, "
                    . "4) Un resume 'Verdict' en fin d'article avec recommandations par profil d'expatrie.",
            ],

            // Q&A (reponses directes, featured snippets)
            'qa' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.5,
                'min_words' => 800,
                'max_words' => 2000,
                'target_words' => 1200,
                'target_words_range' => '800-2000',
                'length' => 'medium',
                'faq_count' => 0,
                'max_tokens_content' => 3000,
                'max_tokens_title' => 80,
                'internal_links' => 3,
                'external_links' => 2,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => false,
                'research_depth' => 'light',
                'quality_threshold' => 80,
                'h2_count' => [3, 5],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => "Page Q&A avec reponse directe de 40-60 mots (featured snippet) "
                    . "suivie d'une reponse detaillee structuree.",
            ],

            // TESTIMONIALS (témoignages d'expatriés, social proof)
            'testimonial' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.8,
                'min_words' => 1200,
                'max_words' => 2500,
                'target_words' => 1800,
                'target_words_range' => '1200-2500',
                'length' => 'medium',
                'faq_count' => 4,
                'max_tokens_content' => 5000,
                'max_tokens_title' => 90,
                'internal_links' => 4,
                'external_links' => 1,
                'images_count' => 1,
                'featured_snippet' => false,
                'comparison_table' => false,
                'numbered_steps' => false,
                'research_depth' => 'light',
                'quality_threshold' => 75,
                'h2_count' => [4, 6],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => "Témoignage inspiré de cas réels d'expatriés. "
                    . "Style narratif à la première personne. "
                    . "Inclure : contexte de départ, défis rencontrés, bonnes surprises, conseils concrets pour ceux qui veulent faire pareil. "
                    . "NE PAS inventer de données chiffrées précises, rester dans le vécu personnel. "
                    . "Mentionner que le témoignage est inspiré de retours d'expérience réels.",
            ],

            // Q&A NEEDS (longue traîne — intentions de recherche précises)
            'qa_needs' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.5,
                'min_words' => 600,
                'max_words' => 1500,
                'target_words' => 900,
                'target_words_range' => '600-1500',
                'length' => 'short',
                'faq_count' => 0,
                'max_tokens_content' => 2500,
                'max_tokens_title' => 80,
                'internal_links' => 2,
                'external_links' => 1,
                'images_count' => 0,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'light',
                'quality_threshold' => 75,
                'h2_count' => [2, 4],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => "Page longue traîne optimisée pour une intention de recherche très précise. "
                    . "Réponse directe en 40-60 mots (featured snippet position 0) puis développement court. "
                    . "Structurer autour de l'intention exacte de l'internaute.",
            ],

            // TUTORIAL (guides pratiques pas-à-pas pour démarches expatriés)
            'tutorial' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 1500,
                'max_words' => 3000,
                'target_words' => 2000,
                'target_words_range' => '1500-3000',
                'length' => 'medium',
                'faq_count' => 6,
                'max_tokens_content' => 6000,
                'max_tokens_title' => 100,
                'internal_links' => 5,
                'external_links' => 3,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 85,
                'h2_count' => [4, 7],
                'include_charts_data' => false,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Guide pratique pas-à-pas sur une démarche administrative ou pratique pour expatrié. "
                    . "Structure OBLIGATOIRE : introduction (contexte + pourquoi ce guide), pré-requis, "
                    . "étapes numérotées en <ol> (min 5, max 8 étapes) avec sous-détails concrets, "
                    . "délais et coûts réels, erreurs fréquentes à éviter, FAQ 6 questions. "
                    . "Chaque étape doit être actionnable immédiatement. "
                    . "Données chiffrées vérifiées (délais officiels, montants réels). "
                    . "CTA vers SOS-Expat.com pour aide personnalisée.",
            ],

            // STATISTICS (articles data-driven à partir de datasets recherchés)
            // research_depth = 'none' : les stats sont déjà recherchées via Perplexity en amont
            'statistics' => [
                'model' => 'gpt-4o',
                'research_model' => null,
                'temperature' => 0.5,
                'min_words' => 2500,
                'max_words' => 4000,
                'target_words' => 3000,
                'target_words_range' => '2500-4000',
                'length' => 'long',
                'faq_count' => 6,
                'max_tokens_content' => 10000,
                'max_tokens_title' => 100,
                'internal_links' => 8,
                'external_links' => 6,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => false,
                'research_depth' => 'none',
                'quality_threshold' => 85,
                'h2_count' => [6, 10],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article STATISTIQUE data-driven. OBLIGATIONS :\n"
                    . "1) Chaque statistique DOIT etre citee avec sa source entre parentheses (Organisation, Annee).\n"
                    . "2) Au moins 2 tableaux <table> avec <thead>/<tbody> comparant les donnees par annee ou par pays.\n"
                    . "3) Un encadre 'Chiffres cles' en debut d'article avec les 5 statistiques les plus importantes en <strong>.\n"
                    . "4) Section 'Analyse et tendances' interpretant les donnees (croissance, declin, facteurs explicatifs).\n"
                    . "5) Section 'Methodologie et sources' en fin d'article listant TOUTES les sources avec URLs.\n"
                    . "6) S'adresser a TOUTES les nationalites, pas uniquement les Francais.\n"
                    . "7) Inclure des comparaisons internationales quand les donnees le permettent.\n"
                    . "8) FAQ orientees 'donnees' (ex: 'Combien de...', 'Quel pourcentage...', 'Quelle evolution...').\n"
                    . "OBJECTIF : devenir la REFERENCE citee par les medias, chercheurs et institutions sur ce sujet.",
            ],

            // PAIN POINT (souffrances/urgences expatriés — intent urgency)
            // Cible les gens qui ONT un problème MAINTENANT et cherchent une solution IMMÉDIATE
            'pain_point' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 800,
                'max_words' => 1500,
                'target_words' => 1200,
                'target_words_range' => '800-1500',
                'length' => 'short',
                'faq_count' => 4,
                'max_tokens_content' => 4000,
                'max_tokens_title' => 100,
                'internal_links' => 4,
                'external_links' => 2,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 80,
                'h2_count' => [3, 5],
                'include_charts_data' => false,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article SOUFFRANCE / URGENCE pour expatrie en detresse.\n"
                    . "L'utilisateur a un probleme MAINTENANT et cherche une solution IMMEDIATE.\n"
                    . "Structure OBLIGATOIRE :\n"
                    . "1) Encadre URGENCE en haut (<div class='emergency-box'>) avec les premiers reflexes (3-5 actions immediates).\n"
                    . "2) Etapes numerotees en <ol> — chaque etape = 1 action concrete et actionnable.\n"
                    . "3) Numeros et contacts utiles (ambassade, police locale, numeros d'urgence du pays).\n"
                    . "4) Section 'Erreurs a ne PAS commettre' avec les pieges courants.\n"
                    . "5) CTA fort vers SOS-Expat : 'Besoin d'aide MAINTENANT ? SOS-Expat.com : mise en relation avec un expert en 5 min, 24h/24, 197 pays'.\n"
                    . "Ton empathique mais directif. Phrases courtes. Zero jargon.\n"
                    . "Chaque paragraphe doit rassurer ET donner une action concrete.\n"
                    . "S'adresser a TOUTE nationalite d'expatrie, pas uniquement les Francais.",
            ],

            // NEWS (articles d'actualité réécrits depuis RSS — informationnels)
            'news' => [
                'model' => 'gpt-4o',
                'research_model' => null,
                'temperature' => 0.6,
                'min_words' => 600,
                'max_words' => 1200,
                'target_words' => 800,
                'target_words_range' => '600-1200',
                'length' => 'short',
                'faq_count' => 3,
                'max_tokens_content' => 3000,
                'max_tokens_title' => 90,
                'internal_links' => 3,
                'external_links' => 2,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => false,
                'research_depth' => 'none',
                'quality_threshold' => 75,
                'h2_count' => [3, 5],
                'include_charts_data' => false,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article d'actualite expatriation reecrit a partir d'une source RSS. "
                    . "Ne JAMAIS recopier de phrases de la source. Reecriture 100% originale. "
                    . "Inclure le contexte pour les expatries (impact concret). "
                    . "Court et factuel (600-1200 mots max).",
            ],

            // OUTREACH (affiliation : chatters, blogueurs, admin groups)
            // Objectif : convaincre des candidats potentiels de rejoindre le programme
            // research_depth = 'none' : GPT connaît le programme, Perplexity inutile ici
            'outreach' => [
                'model' => 'gpt-4o',
                'research_model' => null,          // pas de recherche externe
                'temperature' => 0.8,
                'min_words' => 800,
                'max_words' => 1500,
                'target_words' => 1000,
                'target_words_range' => '800-1500',
                'length' => 'short',
                'faq_count' => 4,
                'max_tokens_content' => 3500,
                'max_tokens_title' => 80,
                'internal_links' => 3,
                'external_links' => 1,
                'images_count' => 1,
                'featured_snippet' => false,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'none',        // skip Perplexity — GPT génère de sa propre connaissance
                'quality_threshold' => 75,
                'h2_count' => [3, 5],
                'include_charts_data' => false,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article d'affiliation / outreach. "
                    . "Objectif : convaincre le lecteur de rejoindre le programme SOS-Expat. "
                    . "Ton enthousiaste mais honnête. Mettre en avant les avantages concrets (commissions, flexibilité, communauté). "
                    . "Inclure un CTA clair en fin d'article.",
            ],

            // AFFILIATION (landing pages conversion avec liens affiliés)
            'affiliation' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 1000,
                'max_words' => 2500,
                'target_words' => 1500,
                'target_words_range' => '1000-2500',
                'length' => 'medium',
                'faq_count' => 5,
                'max_tokens_content' => 5000,
                'max_tokens_title' => 90,
                'internal_links' => 3,
                'external_links' => 5,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => false,
                'research_depth' => 'standard',
                'quality_threshold' => 80,
                'h2_count' => [4, 7],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article d'affiliation comparatif et orienté conversion. "
                    . "Comparer objectivement les services (prix, avantages, inconvénients). "
                    . "Les liens affiliés doivent apparaître naturellement dans le contexte. "
                    . "Inclure un tableau comparatif détaillé et des retours d'expérience concrets.",
            ],

            // DEFAULT
            default => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.7,
                'min_words' => 2000,
                'max_words' => 3000,
                'target_words' => 2500,
                'target_words_range' => '2000-3000',
                'length' => 'long',
                'faq_count' => 8,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 6,
                'external_links' => 3,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 85,
                'h2_count' => [6, 8],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => '',
            ],
        };

        // Append anti-generic-title instruction to every prompt_suffix
        if (!empty($config['prompt_suffix'])) {
            $config['prompt_suffix'] .= self::TITLE_INSTRUCTION;
        }

        return $config;
    }

    /**
     * Apply search intent overrides to the base content type config.
     * Intent changes the content FORMAT without changing the content TYPE.
     */
    public static function withIntent(string $type, ?string $intent): array
    {
        $config = self::get($type);

        if (!$intent) {
            return $config;
        }

        return match ($intent) {
            'informational' => array_merge($config, [
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'faq_count' => max($config['faq_count'], 6),
                'h2_count' => [6, 10],
                'prompt_suffix' => ($config['prompt_suffix'] ?? '') . "\n\nINTENTION INFORMATIONNELLE : L'utilisateur veut APPRENDRE. "
                    . "Structure pedagogique : du simple au complexe. "
                    . "Chaque H2 = une sous-question. Premier paragraphe = definition directe 40-60 mots. "
                    . "Inclure des listes a puces, encadres 'Bon a savoir', et donnees chiffrees.",
            ]),
            'commercial_investigation' => array_merge($config, [
                'featured_snippet' => true,
                'comparison_table' => true,
                'include_charts_data' => true,
                'faq_count' => max($config['faq_count'], 5),
                'h2_count' => [5, 8],
                'prompt_suffix' => ($config['prompt_suffix'] ?? '') . "\n\nINTENTION INVESTIGATION COMMERCIALE : L'utilisateur veut COMPARER avant de decider. "
                    . "OBLIGATOIRE : tableau comparatif <table> avec <thead>/<tbody> EN HAUT de l'article. "
                    . "Colonnes = options comparees. Lignes = criteres (prix, couverture, avantages, inconvenients, note). "
                    . "Puis section pros/cons pour chaque option. Puis verdict argumente. "
                    . "Premier paragraphe = verdict direct ('En 2026, le meilleur X est Y pour Z raison').",
            ]),
            'transactional' => array_merge($config, [
                'min_words' => min($config['min_words'], 1200),
                'max_words' => min($config['max_words'], 1800),
                'target_words' => min($config['target_words'] ?? 1500, 1500),
                'target_words_range' => '800-1500',
                'length' => 'short',
                'featured_snippet' => true,
                'faq_count' => 3,
                'h2_count' => [3, 5],
                'prompt_suffix' => ($config['prompt_suffix'] ?? '') . "\n\nINTENTION TRANSACTIONNELLE : L'utilisateur veut AGIR maintenant. "
                    . "COURT (800-1500 mots). Encadre PRIX en haut (<div class='pricing-box'>). "
                    . "Etapes concretes en <ol> (max 5-7). Encadre confiance (197 pays, 24/7, avis). "
                    . "2-3 CTA vers SOS-Expat. Zero jargon.",
            ]),
            'local' => array_merge($config, [
                'featured_snippet' => true,
                'comparison_table' => true,
                'faq_count' => 4,
                'h2_count' => [4, 6],
                'prompt_suffix' => ($config['prompt_suffix'] ?? '') . "\n\nINTENTION LOCALE : L'utilisateur cherche un service dans un lieu precis. "
                    . "Tableau <table> avec Nom, Adresse, Contact, Langues, Horaires. "
                    . "Listes de ressources officielles (ambassade, consulat). "
                    . "SOS-Expat comme alternative rapide ('mise en relation en 5 min').",
            ]),
            'urgency' => array_merge($config, [
                'min_words' => min($config['min_words'], 1200),
                'max_words' => min($config['max_words'], 1500),
                'target_words' => min($config['target_words'] ?? 1200, 1200),
                'target_words_range' => '800-1500',
                'length' => 'short',
                'featured_snippet' => true,
                'numbered_steps' => true,
                'faq_count' => 3,
                'h2_count' => [3, 5],
                'prompt_suffix' => ($config['prompt_suffix'] ?? '') . "\n\nINTENTION URGENCE : L'utilisateur a un probleme MAINTENANT. "
                    . "Encadre URGENCE en haut (<div class='emergency-box'>) avec numeros (police, ambulance, ambassade). "
                    . "Etapes numerotees en <ol> — chaque etape = 1 phrase d'action. "
                    . "CTA urgent ('Besoin d'un avocat MAINTENANT ? SOS-Expat : 5 min, 24h/24'). "
                    . "Ton calme, directif, chaque phrase = une action concrete.",
            ]),
            default => $config,
        };
    }
}
