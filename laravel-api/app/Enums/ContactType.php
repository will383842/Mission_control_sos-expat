<?php

namespace App\Enums;

enum ContactType: string
{
    // Institutionnel
    case Consulat = 'consulat';
    case Association = 'association';
    case Ecole = 'ecole';
    case InstitutCulturel = 'institut_culturel';
    case ChambreCommerce = 'chambre_commerce';

    // Médias & Influence
    case Presse = 'presse';
    case Blog = 'blog';
    case PodcastRadio = 'podcast_radio';
    case Influenceur = 'influenceur';
    case Youtubeur = 'youtubeur';
    case Instagrammeur = 'instagrammeur';

    // Services B2B
    case Avocat = 'avocat';
    case Immobilier = 'immobilier';
    case Assurance = 'assurance';
    case BanqueFintech = 'banque_fintech';
    case Traducteur = 'traducteur';
    case AgenceVoyage = 'agence_voyage';
    case Emploi = 'emploi';

    // Communautés & Lieux
    case CommunauteExpat = 'communaute_expat';
    case GroupeWhatsappTelegram = 'groupe_whatsapp_telegram';
    case CoworkingColiving = 'coworking_coliving';
    case Logement = 'logement';
    case LieuCommunautaire = 'lieu_communautaire';

    // Digital & Technique
    case Backlink = 'backlink';
    case Annuaire = 'annuaire';
    case PlateformeNomad = 'plateforme_nomad';
    case Partenaire = 'partenaire';

    /**
     * All valid values as array for validation rules.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
