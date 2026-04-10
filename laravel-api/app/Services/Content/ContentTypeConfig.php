<?php

namespace App\Services\Content;

class ContentTypeConfig
{
    /**
     * Anti-generic title instruction appended to every prompt_suffix.
     * Prevents AI from generating clickbait titles like "Guide Complet", "Guide Ultime", etc.
     */
    private const TITLE_INSTRUCTION = "\n\nTITRE : NE JAMAIS utiliser « Guide Complet », « Guide Ultime », « Tout savoir sur », « Découvrez ». "
        . "Le titre doit être une requête Google naturelle, spécifique, avec le pays/ville et l'année si applicable.\n\n"
        . "QUALITE REDACTIONNELLE ABSOLUE :\n"
        . "- Ecris comme un JOURNALISTE DU NEW YORK TIMES specialise en mobilite internationale — pas comme une IA.\n"
        . "- Chaque phrase doit apporter une INFORMATION NOUVELLE. Zero phrase de remplissage. Zero platitude.\n"
        . "- Utilise des ANECDOTES CONCRETES et des SITUATIONS VECUES pour illustrer (ex: 'Marie, 34 ans, arrivee a Lisbonne en janvier, a decouvert que...').\n"
        . "- Donne des CHIFFRES PRECIS, DATES, et SOURCES pour chaque affirmation factuelle.\n"
        . "- Ecris au PRESENT quand possible. Phrases courtes (max 25 mots). Paragraphes courts (max 4 lignes).\n"
        . "- Ton : un ami expert qui a VECU l'experience et partage ses conseils autour d'un cafe. Chaleureux mais precis.\n"
        . "- INTERDIT : 'Il est important de', 'Il convient de noter', 'Dans cet article', 'N'hesitez pas a', 'En conclusion'. Ces formules trahissent un texte IA.\n"
        . "- INTERDIT : listes generiques de 3 items quand il en faut 7. Phrases passe-partout applicables a n'importe quel pays.\n"
        . "- OBLIGATOIRE : au moins 1 info SURPRENANTE ou PEU CONNUE que le lecteur ne trouvera nulle part ailleurs.\n"
        . "- OBLIGATOIRE : des TRANSITIONS NARRATIVES entre les sections (pas juste des H2 qui se succedent sans lien).\n\n"
        . "OPTIMISATION FEATURED SNIPPET + PEOPLE ALSO ASK (CRITIQUE POUR POSITION 0 GOOGLE) :\n"
        . "- PREMIER PARAGRAPHE AVANT TOUT H2 : 40-60 mots EXACTEMENT.\n"
        . "  Commence par reformuler le sujet avec reponse complete + chiffre cle. Ex : 'Le visa digital nomad en France coute 99€ en 2026, s'obtient en 2-4 semaines et ouvre droit a une residence de 1 an renouvelable. Les revenus minimum exiges sont 32 000€/an.'\n"
        . "- AU MOINS 3 H2 sur 6-8 DOIVENT etre formules comme de vraies requetes People Also Ask :\n"
        . "  * 'Comment [verbe d'action] ?' (ex: 'Comment obtenir un visa digital nomad en France ?')\n"
        . "  * 'Combien coute [sujet] ?'\n"
        . "  * 'Quel [choix] ?'\n"
        . "  * 'Faut-il [obligation] ?'\n"
        . "  * 'Quand [timing] ?'\n"
        . "  * 'Pourquoi [question] ?'\n"
        . "- INTERDIT pour les H2 de questions : formulations declaratives comme 'Les demarches du visa' → DOIT etre 'Comment obtenir le visa ?'\n"
        . "- Chaque question H2 doit trouver sa reponse dans les 60 premiers mots de sa section (position 0 interne).\n\n"
        . "FAQ LONGUES (OBLIGATOIRE POUR AEO CHATGPT/PERPLEXITY/SGE) :\n"
        . "- Chaque reponse FAQ DOIT faire 150-200 mots (minimum 130, maximum 220).\n"
        . "- Structure : reformulation directe (30 mots) + contexte chiffre (60 mots) + exceptions (60 mots) + action (20 mots).\n"
        . "- Au moins 2 donnees chiffrees par reponse.\n"
        . "- Les reponses courtes ne sont PAS citees par les LLMs — la longueur est vitale.\n\n"
        . "- Le lecteur doit se dire : 'Cet article est EXACTEMENT ce qu'il me fallait. Il a repondu a des questions que je ne savais meme pas que j'avais.'";

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
                'prompt_suffix' => "ARTICLE PILIER — cette page doit devenir LA REFERENCE MONDIALE sur cette ville pour les expatries.\n"
                    . "Ecris comme si tu avais VECU 5 ans dans cette ville et que tu guidais un ami qui s'y installe demain.\n"
                    . "OBLIGATOIRE :\n"
                    . "- Nommer les QUARTIERS par nom avec personnalite (ex: 'Cihangir, le Montmartre d'Istanbul — boheme, cafes rooftop, loyers a 800€/mois').\n"
                    . "- Prix LOCAUX en devise locale + EUR/USD pour : loyer studio, repas restaurant, cafe, biere, abonnement metro, coworking.\n"
                    . "- Transports : lignes de metro/bus specifiques, apps locales (Grab, Bolt, etc.), cout moyen d'un trajet.\n"
                    . "- Tableau comparatif de 4-6 quartiers : prix, securite, ambiance, transports, profil ideal (famille/celibataire/digital nomad).\n"
                    . "- Les 3 PIEGES que personne ne mentionne (ex: 'A Bangkok, les appartements au rez-de-chaussee inondent chaque annee en octobre').\n"
                    . "- Les 3 BONS PLANS que seuls les locaux connaissent.\n"
                    . "- S'adresser a TOUTE nationalite d'expatrie, pas uniquement les Francais.\n"
                    . "- Lier vers la fiche pays pour le contexte national (visa, fiscalite, sante).",
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
                'prompt_suffix' => "Cet article doit etre la REFERENCE MONDIALE sur ce sujet — le genre d'article que les consulats eux-memes recommandent.\n"
                    . "OBLIGATOIRE :\n"
                    . "- Commence par un paragraphe-choc qui accroche immediatement (une stat surprenante, une anecdote vecue, un constat contre-intuitif).\n"
                    . "- Au moins 5 DONNEES CHIFFREES PRECISES avec source et annee (ex: 'Le salaire minimum au Portugal est de 820€/mois en 2026 — Ministerio do Trabalho').\n"
                    . "- 2 tableaux <table> : un comparatif (avant/apres, France vs pays, etc.) et un recapitulatif des couts ou demarches.\n"
                    . "- 1 timeline ou liste ordonnee <ol> pour les etapes administratives avec DELAIS REELS.\n"
                    . "- Section 'Ce que personne ne vous dit' avec 3-5 pieges/surprises que seuls les expatries sur place connaissent.\n"
                    . "- Des encadres <strong>Bon a savoir :</strong> apres chaque section clé.\n"
                    . "- Chaque H2 doit etre formule comme une QUESTION que les gens tapent sur Google.\n"
                    . "- S'adresser a TOUTE nationalite d'expatrie, pas uniquement les Francais.",
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
                'prompt_suffix' => "Article qui doit se lire comme un REPORTAGE DE TERRAIN — pas comme une fiche Wikipedia.\n"
                    . "OBLIGATOIRE :\n"
                    . "- Accroche narrative en intro : une situation concrete que le lecteur vit ou vivra (ex: 'Vous venez de recevoir votre visa. Votre vol est dans 3 semaines. Et la, la realite vous rattrape : par ou commencer ?').\n"
                    . "- Au moins 5 donnees chiffrees precises avec source et annee.\n"
                    . "- 1 encadre 'Chiffres cles' en debut d'article avec les 5 donnees essentielles en <strong>.\n"
                    . "- 2-3 H2 formules comme des questions Google naturelles.\n"
                    . "- 1 section 'Erreurs a eviter' avec des consequences REELLES (pas juste 'il faut faire attention').\n"
                    . "- Des comparaisons eclairantes : 'Contrairement a la France ou... en [pays] le systeme fonctionne de maniere radicalement differente'.\n"
                    . "- Conclusion avec un plan d'action en 3-5 etapes concretes.\n"
                    . "- S'adresser a TOUTE nationalite d'expatrie.",
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
                'prompt_suffix' => "Article COMPARATIF de niveau consultant McKinsey — le lecteur doit pouvoir DECIDER apres lecture.\n"
                    . "OBLIGATOIRE :\n"
                    . "- Verdict clair des les 3 premieres lignes : 'Pour un celibataire tech, le Portugal l'emporte. Pour une famille, l'Espagne est imbattable. Voici pourquoi.'\n"
                    . "- Tableau recapitulatif COMPLET en haut d'article (vue d'ensemble de TOUS les criteres cote a cote).\n"
                    . "- 1 tableau detaille PAR critere majeur (cout de la vie, visa, sante, fiscalite, qualite de vie) avec chiffres EXACTS.\n"
                    . "- Chaque critere compare avec des CHIFFRES PRECIS : '1 850€/mois vs 1 500€/mois' — JAMAIS 'plus cher' ou 'moins cher' sans montant.\n"
                    . "- Section 'Verdict par profil' : famille, celibataire, retraite, digital nomad, entrepreneur — chacun avec une recommandation argumentee.\n"
                    . "- Section 'Le detail que tout change' : 1-2 criteres meconnus qui font basculer la decision (ex: le Portugal taxe les crypto a 0%, l'Espagne a 28%).\n"
                    . "- S'adresser a TOUTE nationalite.",
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
                'prompt_suffix' => "Page Q&A optimisee Position 0 Google.\n"
                    . "STRUCTURE :\n"
                    . "- Premier paragraphe : reponse directe et COMPLETE en 40-60 mots (featured snippet). Commence par reformuler le sujet : '[Sujet] coute/est/necessite...'\n"
                    . "- Puis reponse detaillee en 3-5 sections H2 avec chiffres, etapes, sources officielles.\n"
                    . "- Au moins 1 donnee SURPRENANTE que le lecteur ne s'attendait pas a trouver.\n"
                    . "- Chaque reponse doit etre TELLEMENT precise que le lecteur n'a PAS besoin de chercher ailleurs.\n"
                    . "- Ton : expert accessible — comme un ami qui connait le sujet par coeur.",
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
                'prompt_suffix' => "Temoignage IMMERSIF — le lecteur doit VIVRE l'experience de l'expatrie.\n"
                    . "STYLE : Narratif a la premiere personne. Ecris comme un article du magazine 'GEO' ou 'Courrier International'.\n"
                    . "STRUCTURE :\n"
                    . "- Accroche-choc : le moment precis ou tout a bascule ('Le 14 mars, a 6h du matin, devant le bureau de l'immigration de Bangkok, j'ai compris que rien ne se passerait comme prevu.').\n"
                    . "- Le contexte : qui je suis, pourquoi ce pays, ce que j'attendais.\n"
                    . "- Les galeres CONCRETES (pas generiques) : la barriere de la langue, la bureaucratie locale, la solitude, un probleme specifique inattendu.\n"
                    . "- Le declic / la bonne surprise : le moment ou j'ai commence a me sentir chez moi.\n"
                    . "- Mes 5 conseils a ceux qui veulent faire pareil (conseils SPECIFIQUES au pays, pas generiques).\n"
                    . "- NE PAS inventer de donnees chiffrees — rester dans le vecu personnel et emotionnel.\n"
                    . "- Mentionner que le temoignage est inspire de retours d'experience reels.",
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
                'prompt_suffix' => "Page longue traine — cible UNE intention de recherche ultra-precise.\n"
                    . "STRUCTURE :\n"
                    . "- Reponse directe en 40-60 mots (featured snippet position 0) : reformule le sujet + reponse complete.\n"
                    . "- Puis 2-4 sections H2 qui approfondissent l'angle specifique.\n"
                    . "- Chiffres precis, sources, annee — meme pour un article court.\n"
                    . "- Etapes numerotees si c'est une demarche.\n"
                    . "- L'article est COURT mais DENSE : chaque mot compte. Zero remplissage.",
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
                'prompt_suffix' => "TUTORIEL PAS-A-PAS — le lecteur doit pouvoir suivre les etapes les yeux fermes.\n"
                    . "STRUCTURE OBLIGATOIRE :\n"
                    . "- Intro : 'Vous devez [demarche] ? Voici exactement comment faire, etape par etape, avec les delais et couts reels en [annee].'\n"
                    . "- Pre-requis : liste a puces de tout ce qu'il faut AVANT de commencer (documents, montants, conditions).\n"
                    . "- Etapes numerotees en <ol> (5-8 etapes) : chaque etape = 1 ACTION CONCRETE + delai + cout + piege a eviter.\n"
                    . "- Pour chaque etape, donner l'URL ou le lieu EXACT ou la faire (ex: 'Rendez-vous sur service-public.fr > Etrangers > Demande de titre').\n"
                    . "- Encadre <strong>Attention :</strong> apres les etapes critiques.\n"
                    . "- Section 'Les 5 erreurs qui retardent votre dossier' avec consequences reelles.\n"
                    . "- Section 'Combien ca coute au total ?' : tableau recapitulatif de TOUS les frais.\n"
                    . "- FAQ 6 questions pratiques.\n"
                    . "- Chaque etape doit etre actionnable IMMEDIATEMENT — pas de 'renseignez-vous aupres de...'.",
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
                'prompt_suffix' => "Article STATISTIQUE de niveau rapport OCDE — doit devenir LA SOURCE citee par les medias et chercheurs.\n"
                    . "STRUCTURE :\n"
                    . "- Encadre 'Chiffres cles' en ouverture : 5 stats les plus frappantes en <strong>, chacune avec source et annee.\n"
                    . "- Chaque stat DOIT etre citee : '42% des expatries (HSBC Expat Explorer, 2025)'. JAMAIS de stat sans source.\n"
                    . "- Au moins 3 tableaux <table> : evolution temporelle, comparaison entre pays, et breakdown par categorie.\n"
                    . "- Section 'Analyse et tendances' : interprete les donnees comme un economiste (croissance, declin, facteurs explicatifs, projections).\n"
                    . "- Section 'Ce que ces chiffres signifient pour vous' : traduction concrete pour l'expatrie lambda.\n"
                    . "- Comparaisons internationales SYSTEMATIQUES quand possible.\n"
                    . "- Section 'Methodologie et sources' en fin d'article avec TOUTES les sources.\n"
                    . "- FAQ orientees donnees : 'Combien de...', 'Quel pourcentage...', 'Quelle evolution...'.\n"
                    . "- S'adresser a TOUTES les nationalites.",
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
                'prompt_suffix' => "Article URGENCE — l'expatrie qui lit cet article est en CRISE. Il a besoin d'aide MAINTENANT.\n"
                    . "TON : Comme un ami calme et competent qui prend les choses en main. Phrases courtes. Actions concretes. Zero jargon.\n"
                    . "STRUCTURE OBLIGATOIRE :\n"
                    . "1) Encadre URGENCE rouge en haut (<div class='emergency-box'>) : 'Premiers reflexes — faites ceci IMMEDIATEMENT' avec 3-5 actions numerotees.\n"
                    . "2) Numeros d'urgence du pays : police, ambulance, ambassade/consulat, numeros gratuits si existants.\n"
                    . "3) Etapes en <ol> : chaque etape = 1 PHRASE D'ACTION ('Appelez le 191, c'est la police touristique en Thailande. Parlez lentement en anglais.').\n"
                    . "4) Section 'Ce qu'il ne faut SURTOUT PAS faire' avec consequences reelles (ex: 'Ne signez RIEN que vous ne comprenez pas — meme si on vous dit que c'est une formalite').\n"
                    . "5) Section 'Apres l'urgence : les demarches a faire dans les 48h'.\n"
                    . "6) CTA final empathique : 'Besoin d'un expert MAINTENANT ? SOS-Expat.com : un avocat ou expert local au telephone en moins de 5 minutes, 24h/24, 197 pays.'\n"
                    . "- Chaque paragraphe doit RASSURER et donner une ACTION concrete.\n"
                    . "- S'adresser a TOUTE nationalite.",
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
                'prompt_suffix' => "Article d'ACTUALITE — ecris comme un correspondant du Monde ou de la BBC base dans le pays.\n"
                    . "REGLES :\n"
                    . "- Ne JAMAIS recopier de phrases de la source. Reecriture 100% originale avec ton journalistique.\n"
                    . "- Pyramide inversee : l'info essentielle dans les 2 premieres phrases (qui, quoi, quand, ou, pourquoi).\n"
                    . "- Section 'Ce que ca change CONCRETEMENT pour vous' : impact pratique pour l'expatrie/voyageur.\n"
                    . "- Dates d'entree en vigueur clairement mentionnees.\n"
                    . "- Ce qu'il faut faire MAINTENANT (actions concretes si applicables).\n"
                    . "- Court et dense : 600-1200 mots max. Chaque phrase apporte une info nouvelle.",
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
                'prompt_suffix' => "Article d'AFFILIATION — convainc naturellement sans etre pushy.\n"
                    . "TON : Comme un ami qui partage un bon plan qu'il a teste lui-meme. Enthousiaste mais honnete.\n"
                    . "STRUCTURE :\n"
                    . "- Accroche : un probleme concret que le lecteur vit ('Vous connaissez deja des expatries dans votre pays d'accueil. Pourquoi ne pas monetiser cette expertise ?').\n"
                    . "- Les avantages CONCRETS avec chiffres : commissions exactes, exemples de gains possibles, flexibilite.\n"
                    . "- Temoignage fictif mais realiste d'un affilié qui gagne.\n"
                    . "- Les etapes concretes pour demarrer (en 3 minutes).\n"
                    . "- CTA naturel en fin d'article.\n"
                    . "- JAMAIS de promesses irrealistes. Honnete sur l'effort requis.",
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
                'prompt_suffix' => "Article COMPARATIF AFFILIATION — le lecteur doit pouvoir choisir le meilleur service pour SA situation.\n"
                    . "STRUCTURE :\n"
                    . "- Verdict rapide en introduction : 'Pour [profil X], on recommande [service A]. Pour [profil Y], [service B]. Voici notre analyse complete.'\n"
                    . "- Tableau comparatif detaille : prix, avantages, inconvenients, note /10, profil ideal.\n"
                    . "- Test/experience utilisateur pour chaque service : 'Nous avons teste l'inscription en [X] minutes...'.\n"
                    . "- Section 'Pour qui ?' avec recommandations par profil (famille, solo, budget serre, premium).\n"
                    . "- Les liens doivent apparaitre naturellement dans le contexte, pas forces.\n"
                    . "- OBJECTIVITE totale : mentionner les defauts de chaque service.",
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
