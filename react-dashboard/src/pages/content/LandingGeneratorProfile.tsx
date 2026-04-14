import React from 'react';
import LandingGeneratorAudiencePage from './LandingGeneratorAudiencePage';

export default function LandingGeneratorProfile() {
  return (
    <LandingGeneratorAudiencePage
      audienceType="profile"
      label="Profils expatriés"
      icon="🧑‍💻"
      description="Pages ciblant les 7 profils expatriés × pays. Structure : /fr/aide/{profil}/{pays} — digital nomade, retraité, famille, entrepreneur, étudiant, investisseur, expatrié"
    />
  );
}
