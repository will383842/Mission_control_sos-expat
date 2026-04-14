import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorHelpers() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="helpers"
      label="Helpers"
      icon="🧳"
      description="Génération de landing pages de recrutement pour expats aidants. Structure : /fr/expats-aidants/{template}/{pays}"
    />
  );
}
