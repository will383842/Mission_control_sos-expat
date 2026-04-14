import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorMatching() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="matching"
      label="Matching"
      icon="🎯"
      description="Génération de landing pages de matching expert/client par pays. Structure : /fr/expert/{type}/{pays}"
    />
  );
}
