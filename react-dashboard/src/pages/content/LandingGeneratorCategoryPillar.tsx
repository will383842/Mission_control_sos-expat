import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorCategoryPillar() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="category_pillar"
      label="Piliers catégories"
      icon="🏛️"
      description="Pages piliers thématiques par catégorie de problème × pays. Structure : /fr/aide/{catégorie}/{pays} — Couvre les 22 catégories (immigration, santé, fiscalité…)"
    />
  );
}
