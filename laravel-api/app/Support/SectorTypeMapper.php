<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Mappe le secteur (champ scraper pour content_contacts) vers un contact_type
 * reconnu par le Backlink Engine (SYNCABLE_TYPES de BacklinkEngineWebhookService).
 *
 * Extrait de ContentContactObserver::resolveType() pour être partagé avec :
 * - La commande de migration MigrateContactsToInfluenceurs (P1)
 * - Les scrapers content qui écrivent maintenant dans influenceurs (P2)
 * - La commande ResyncBacklinkEngine (P3)
 *
 * Single source of truth : un seul endroit pour le mapping sector → contact_type.
 */
class SectorTypeMapper
{
    /**
     * Mapping secteur (content_contacts.sector) → contact_type (Influenceur.contact_type).
     * Les contact_type retournés sont tous dans SYNCABLE_TYPES côté bl-app.
     */
    private const MAP = [
        'media'      => 'presse',
        'assurance'  => 'assurance',
        'sante'      => 'partenaire',
        'emploi'     => 'emploi',
        'education'  => 'ecole',
        'fiscalite'  => 'partenaire',
        'social'     => 'communaute_expat',
        'immobilier' => 'immobilier',
        'traduction' => 'traducteur',
        'voyage'     => 'agence_voyage',
        'banque'     => 'banque_fintech',
    ];

    /**
     * Secteurs inconnus sont mappés à 'partenaire' (catch-all B2B, syncable).
     * Log info pour détecter de nouveaux secteurs à mapper.
     */
    public static function resolve(?string $sector): string
    {
        $key = strtolower(trim($sector ?? ''));

        if ($sector && !isset(self::MAP[$key])) {
            Log::info('SectorTypeMapper: sector inconnu mappé à partenaire', ['sector' => $sector]);
        }

        return self::MAP[$key] ?? 'partenaire';
    }
}
