import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorEmergency() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="emergency"
      label="Urgences"
      icon="🚨"
      description="Pages d'urgence ultra-courtes par pays. Structure : /fr/urgence/{pays} — 1 LP par pays, CTA immédiat, numéros locaux, réponse <10 secondes"
    />
  );
}
