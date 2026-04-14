import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorClients() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="clients"
      label="Clients"
      icon="👤"
      description="Génération de landing pages par problème d'expatriation × pays × template. Structure : /fr/aide/{problème}/{pays}"
    />
  );
}
