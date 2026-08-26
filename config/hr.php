<?php

return [
    'document_types' => [
        'identity' => 'Pièce d’identité',
        'contract' => 'Contrat de travail',
        'diploma' => 'Diplôme / certificat',
        'cv' => 'CV',
        'medical' => 'Document médical',
        'leave' => 'Justificatif de congé',
        'payroll' => 'Document de paie',
        'bank' => 'Coordonnées bancaires',
        'administrative' => 'Document administratif',
        'evaluation' => 'Évaluation',
        'disciplinary' => 'Document disciplinaire',
        'other' => 'Autre',
    ],
    'record_categories' => [
        'absence' => ['label' => 'Absence', 'types' => ['personal' => 'Absence personnelle', 'unexcused' => 'Absence non justifiée', 'authorized' => 'Autorisation d’absence', 'family_event' => 'Événement familial', 'other' => 'Autre'], 'statuses' => ['pending' => 'À traiter', 'justified' => 'Justifiée', 'unjustified' => 'Non justifiée', 'closed' => 'Clôturée']],
        'contract' => ['label' => 'Contrat', 'types' => ['cdi' => 'CDI', 'cdd' => 'CDD', 'internship' => 'Stage', 'temporary' => 'Temporaire', 'consulting' => 'Prestation', 'amendment' => 'Avenant'], 'statuses' => ['draft' => 'Brouillon', 'active' => 'Actif', 'expired' => 'Expiré', 'terminated' => 'Résilié']],
        'training' => ['label' => 'Formation RH', 'types' => ['internal' => 'Interne', 'external' => 'Externe', 'certification' => 'Certification', 'safety' => 'Sécurité', 'onboarding' => 'Intégration'], 'statuses' => ['planned' => 'Planifiée', 'in_progress' => 'En cours', 'completed' => 'Terminée', 'cancelled' => 'Annulée']],
        'evaluation' => ['label' => 'Évaluation', 'types' => ['annual' => 'Annuelle', 'probation' => 'Fin de période d’essai', 'performance' => 'Performance', 'skills' => 'Compétences', 'other' => 'Autre'], 'statuses' => ['draft' => 'Brouillon', 'scheduled' => 'Planifiée', 'completed' => 'Finalisée', 'acknowledged' => 'Signée par l’employé']],
        'discipline' => ['label' => 'Discipline', 'types' => ['verbal_warning' => 'Rappel verbal', 'written_warning' => 'Avertissement écrit', 'notice' => 'Mise en demeure', 'suspension' => 'Suspension', 'commendation' => 'Fait positif'], 'statuses' => ['open' => 'Ouvert', 'under_review' => 'En examen', 'closed' => 'Clôturé', 'appealed' => 'Contesté']],
        'note' => ['label' => 'Note RH', 'types' => ['general' => 'Générale', 'career' => 'Carrière', 'follow_up' => 'Suivi', 'management' => 'Management', 'confidential' => 'Confidentielle'], 'statuses' => ['active' => 'Active', 'resolved' => 'Traitée', 'archived' => 'Archivée']],
    ],
];
