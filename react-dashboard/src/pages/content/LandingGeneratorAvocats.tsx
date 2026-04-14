import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorAvocats() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="lawyers"
      label="Avocats"
      icon="⚖️"
      description="Génération de landing pages de recrutement pour avocats partenaires. Structure : /fr/devenir-partenaire/{template}/{pays}"
    />
  );
}
