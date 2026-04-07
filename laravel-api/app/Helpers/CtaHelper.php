<?php

namespace App\Helpers;

/**
 * Centralized CTA (Call-to-Action) builder for generated articles.
 * All CTA links point to the /prestataires page with the correct locale.
 */
class CtaHelper
{
    // Language → default country code mapping
    private const LOCALE_MAP = [
        'fr' => 'fr', 'en' => 'us', 'de' => 'de', 'es' => 'es',
        'pt' => 'pt', 'ru' => 'ru', 'zh' => 'cn', 'ar' => 'sa', 'hi' => 'in',
    ];

    // CTA labels per language
    private const CTA_LABELS = [
        'fr' => ['title' => "Besoin d'aide sur place ?", 'desc' => 'Un avocat ou expert local disponible en moins de 5 minutes, 24h/24, dans 197 pays.', 'button' => 'Consulter un expert'],
        'en' => ['title' => 'Need help on the ground?', 'desc' => 'A local lawyer or expert available in less than 5 minutes, 24/7, in 197 countries.', 'button' => 'Consult an expert'],
        'de' => ['title' => 'Brauchen Sie Hilfe vor Ort?', 'desc' => 'Ein lokaler Anwalt oder Experte in weniger als 5 Minuten verfügbar, rund um die Uhr, in 197 Ländern.', 'button' => 'Experten kontaktieren'],
        'es' => ['title' => '¿Necesitas ayuda en el lugar?', 'desc' => 'Un abogado o experto local disponible en menos de 5 minutos, 24/7, en 197 países.', 'button' => 'Consultar un experto'],
        'pt' => ['title' => 'Precisa de ajuda no local?', 'desc' => 'Um advogado ou especialista local disponível em menos de 5 minutos, 24/7, em 197 países.', 'button' => 'Consultar um especialista'],
        'ru' => ['title' => 'Нужна помощь на месте?', 'desc' => 'Местный юрист или эксперт доступен менее чем за 5 минут, 24/7, в 197 странах.', 'button' => 'Связаться с экспертом'],
        'zh' => ['title' => '需要当地帮助？', 'desc' => '当地律师或专家5分钟内随时为您服务，覆盖197个国家。', 'button' => '咨询专家'],
        'ar' => ['title' => 'هل تحتاج مساعدة في الموقع؟', 'desc' => 'محامٍ أو خبير محلي متاح في أقل من 5 دقائق، على مدار الساعة، في 197 دولة.', 'button' => 'استشر خبيرًا'],
        'hi' => ['title' => 'क्या आपको स्थानीय मदद चाहिए?', 'desc' => '197 देशों में 24/7, 5 मिनट से कम में स्थानीय वकील या विशेषज्ञ उपलब्ध।', 'button' => 'विशेषज्ञ से संपर्क करें'],
    ];

    /**
     * Build the CTA URL with correct locale.
     */
    public static function url(string $language = 'fr', ?string $countryCode = null): string
    {
        $lang = strtolower(substr($language, 0, 2));
        $country = $countryCode
            ? strtolower(substr($countryCode, 0, 2))
            : (self::LOCALE_MAP[$lang] ?? 'fr');

        return "https://sos-expat.com/{$lang}-{$country}/prestataires";
    }

    /**
     * Build the full CTA HTML block.
     */
    public static function html(string $language = 'fr', ?string $countryCode = null): string
    {
        $url = self::url($language, $countryCode);
        $labels = self::CTA_LABELS[$language] ?? self::CTA_LABELS['fr'];

        return '<div class="cta-box">'
            . '<p><strong>' . $labels['title'] . '</strong></p>'
            . '<p>' . $labels['desc'] . '</p>'
            . '<p><a href="' . $url . '" class="cta-button">' . $labels['button'] . '</a></p>'
            . '</div>';
    }
}
