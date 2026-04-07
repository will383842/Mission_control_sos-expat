<?php

/**
 * Standardized HTML templates for ALL AI-generated content.
 *
 * These templates ensure visual consistency across ALL content types
 * on sos-expat.com (articles, fiches pays, Q/R, news, comparatifs).
 *
 * IMPORTANT: These templates use classes that are either:
 * - Styled by .prose-blog in the blog CSS (semantic HTML)
 * - In the Tailwind safelist (dynamic classes for callouts)
 *
 * All generators MUST use these exact templates in their prompts.
 */

return [

    // Injected into every AI generation prompt as HTML reference
    'prompt_instructions' => <<<'HTML'

=== TEMPLATES HTML OBLIGATOIRES ===
Utilise EXACTEMENT ces templates HTML dans le contenu genere. Ne modifie PAS les classes.

ENCADRE "Bon a savoir" :
<blockquote class="callout-info">
<p><strong>💡 Bon a savoir</strong></p>
<p>Texte informatif ici...</p>
</blockquote>

ENCADRE "Attention" :
<blockquote class="callout-warning">
<p><strong>⚠️ Attention</strong></p>
<p>Texte d'avertissement ici...</p>
</blockquote>

ENCADRE "Conseil pratique" :
<blockquote class="callout-tip">
<p><strong>✅ Conseil pratique</strong></p>
<p>Texte de conseil ici...</p>
</blockquote>

ENCADRE "Urgence" (pour articles urgence uniquement) :
<div class="emergency-box">
<p><strong>🚨 Numeros d'urgence</strong></p>
<ul>
<li><strong>Police :</strong> {numero}</li>
<li><strong>Ambulance :</strong> {numero}</li>
<li><strong>Ambassade :</strong> {numero}</li>
</ul>
</div>

REPONSE DIRECTE "Featured Snippet" (premier paragraphe, OBLIGATOIRE pour Q/R et tutoriels) :
<div class="featured-snippet">
<p>Reponse directe et factuelle en 40-60 mots maximum. Inclure le mot-cle principal, la reponse concrete et 1 chiffre cle si disponible.</p>
</div>

ENCADRE "En bref" (resume en haut d'article) :
<div class="summary-box">
<p><strong>En bref</strong></p>
<p>Resume factuel 2-3 phrases...</p>
</div>

ENCADRE "Prix / Tarif" (articles transactionnels) :
<div class="pricing-box">
<p><strong>💰 Tarif</strong></p>
<p>Avocat : 49EUR/55USD (20 min) | Expert local : 19EUR/25USD (30 min)</p>
</div>

TABLE COMPARATIVE :
<table>
<thead>
<tr><th>Critere</th><th>Option A</th><th>Option B</th></tr>
</thead>
<tbody>
<tr><td><strong>Prix</strong></td><td>...</td><td>...</td></tr>
<tr><td><strong>Duree</strong></td><td>...</td><td>...</td></tr>
</tbody>
</table>

CTA SOS-EXPAT (1 seul par article, en fin de contenu) :
<div class="cta-box">
<p><strong>Besoin d'aide sur place ?</strong></p>
<p>Un avocat ou expert local disponible en moins de 5 minutes, 24h/24, dans 197 pays.</p>
<p><a href="https://sos-expat.com/{lang}-{country}/prestataires" class="cta-button">Consulter un expert</a></p>
</div>

IMAGE AVEC LEGENDE :
<figure>
<img src="{url}" alt="{description}" loading="lazy" decoding="async" width="1200" height="630">
<figcaption>Photo by <a href="{photographer_url}?utm_source=sos-expat-blog&utm_medium=referral" target="_blank" rel="noopener nofollow">{photographer_name}</a> on <a href="https://unsplash.com/?utm_source=sos-expat-blog&utm_medium=referral" target="_blank" rel="noopener nofollow">Unsplash</a></figcaption>
</figure>

FAQ ACCORDION INLINE (pour les articles avec questions frequentes integrees) :
<details class="faq-item">
<summary><strong>Question frequente ici ?</strong></summary>
<p>Reponse detaillee et actionnable ici. Min 80 mots, max 200 mots.</p>
</details>

LIENS OFFICIELS (sources gouvernementales, ambassades, organismes — max 5) :
<div class="official-links">
<p><strong>🔗 Sources officielles</strong></p>
<ul>
<li><a href="{url}" target="_blank" rel="noopener">Nom de l'organisme officiel</a></li>
</ul>
</div>

DISCLAIMER JURIDIQUE (OBLIGATOIRE pour tous les articles juridiques/fiscaux) :
<div class="disclaimer-box">
<p><strong>⚠️ Avertissement</strong></p>
<p>Cet article est fourni a titre informatif uniquement et ne constitue pas un conseil juridique. Les lois et reglements varient selon les pays et evoluent regulierement. Consultez un professionnel qualifie pour votre situation specifique.</p>
</div>

REGLES HTML :
- JAMAIS de <h1> (le titre H1 est gere par le template de page)
- TOUJOURS loading="lazy" sur les balises <img> (performance et Core Web Vitals)
- Utiliser <h2> pour les sections principales, <h3> pour les sous-sections
- <strong> pour les termes importants et les chiffres
- <a href="..."> pour les liens (internes et externes)
- <ul>/<ol> pour les listes (minimum 3 items)
- JAMAIS de classes Tailwind personnalisees (bg-blue-50, etc.) — utiliser les classes ci-dessus
- DISCLAIMER OBLIGATOIRE en fin d'article pour tout contenu juridique, fiscal ou medical
=== FIN TEMPLATES HTML ===
HTML,

    // CSS classes used in generated content — must be defined in blog app.css
    'safelist_classes' => [
        // Callout types
        'callout-info', 'callout-warning', 'callout-tip',
        // Special boxes
        'emergency-box', 'summary-box', 'pricing-box', 'cta-box', 'cta-button',
        // FAQ + links + legal
        'faq-item', 'official-links', 'disclaimer-box',
        // Featured snippet
        'featured-snippet',
    ],
];
