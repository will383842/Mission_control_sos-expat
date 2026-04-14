import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorNationality() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="nationality"
      label="Nationalités"
      icon="🌍"
      description="Pages nationalité × pays destination pour les 20 nationalités prioritaires. Structure : /fr/aide/{nationalité}/{pays} — Accords bilatéraux, visas, conventions fiscales"
    />
  );
}
