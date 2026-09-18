import { nextTick, ref, watch } from 'vue';

export type PublicLocale = 'fr' | 'en' | 'ar';

const saved =
    typeof window !== 'undefined'
        ? localStorage.getItem('easy-school-locale')
        : null;
export const publicLocale = ref<PublicLocale>(
    saved === 'en' || saved === 'ar' ? saved : 'fr',
);

const en: Record<string, string> = {
    Accueil: 'Home',
    Modules: 'Modules',
    Tarifs: 'Pricing',
    Contact: 'Contact',
    'Se connecter': 'Sign in',
    Connexion: 'Sign in',
    'Demander une démo': 'Request a demo',
    Démo: 'Demo',
    'Tableau de bord': 'Dashboard',
    Navigation: 'Navigation',
    Ressources: 'Resources',
    Entreprise: 'Company',
    'À propos': 'About',
    Carrières: 'Careers',
    Partenaires: 'Partners',
    Presse: 'Press',
    "Centre d'aide": 'Help center',
    Guides: 'Guides',
    'Tous droits réservés.': 'All rights reserved.',
    Confidentialité: 'Privacy',
    "Conditions d'utilisation": 'Terms of use',
    'La plateforme SaaS complète pour la gestion des établissements scolaires et centres de formation.':
        'The complete SaaS platform for schools and training centers.',
    'PLATEFORME SaaS MULTI-ÉTABLISSEMENTS': 'MULTI-SCHOOL SaaS PLATFORM',
    'Pilotez votre établissement': 'Run your school',
    'en toute simplicité': 'with complete simplicity',
    'Easy School est la plateforme complète pour gérer étudiants, employés, sessions, groupes, formations, finances, RH et plusieurs sites à partir d’un seul espace sécurisé.':
        'Easy School is the complete platform for managing students, staff, sessions, groups, training, finance, HR and multiple locations from one secure workspace.',
    'Voir une démo': 'Watch a demo',
    'Démo jusqu’à 15 jours': 'Demo for up to 15 days',
    'Mise en place rapide': 'Fast setup',
    'Données sécurisées': 'Secure data',
    'Ils nous font confiance': 'Trusted by schools',
    'Une plateforme complète pour chaque acteur de votre établissement':
        'One complete platform for everyone in your school',
    'Gestion des étudiants': 'Student management',
    'Admission, profils, suivi et historique complet.':
        'Admissions, profiles, tracking and complete history.',
    'Dossiers étudiants': 'Student records',
    'Documents, pièces jointes et suivis personnalisés.':
        'Documents, attachments and personalized follow-up.',
    'Gestion RH': 'HR management',
    'Organigramme, rôles, postes et permissions.':
        'Organization, roles, positions and permissions.',
    'Dossiers employés': 'Employee records',
    'Profils, contrats, salaires et évaluations.':
        'Profiles, contracts, salaries and reviews.',
    'Sessions & groupes': 'Sessions & groups',
    'Planification, groupes, créneaux et salles.':
        'Schedules, groups, time slots and rooms.',
    'Formations & cours': 'Training & courses',
    'Programmes, modules et contenus.': 'Programs, modules and content.',
    Inscriptions: 'Enrollments',
    'Gestion des inscriptions et réinscriptions.':
        'Enrollment and re-enrollment management.',
    Présences: 'Attendance',
    'Suivi des présences et absences.': 'Attendance and absence tracking.',
    'Paiements & factures': 'Payments & invoices',
    'Facturation, paiements, relances et reçus.':
        'Billing, payments, reminders and receipts.',
    'Sites / campus multiples': 'Multiple sites / campuses',
    'Gérez plusieurs sites avec des stats par site.':
        'Manage multiple sites with site-level analytics.',
    Certificats: 'Certificates',
    'Génération automatique de certificats.':
        'Automatic certificate generation.',
    Badges: 'Badges',
    'Badges numériques pour étudiants et employés.':
        'Digital badges for students and employees.',
    'Rapports & statistiques': 'Reports & analytics',
    'Tableaux de bord, KPI et rapports avancés.':
        'Dashboards, KPIs and advanced reports.',
    'Emploi du temps': 'Timetable',
    'Planning des cours et ressources.': 'Course and resource scheduling.',
    'Communication parents': 'Parent communication',
    'Messages, annonces et notifications.':
        'Messages, announcements and notifications.',
    'Portail enseignant': 'Teacher portal',
    'Suivi des classes, notes et ressources.': 'Classes, grades and resources.',
    'Mobile parents': 'Parent mobile app',
    'Suivi des enfants et infos en temps réel.':
        'Real-time child tracking and updates.',
    'Suivi en temps réel': 'Live tracking',
    'MULTI-SITES': 'MULTI-SITE',
    'Gérez plusieurs sites depuis': 'Manage multiple sites from',
    'une plateforme centrale': 'one central platform',
    'Easy School vous permet de piloter tous vos établissements avec une vue globale et des paramètres spécifiques à chaque site.':
        'Easy School lets you manage all your schools through a global view and site-specific settings.',
    'Mes établissements': 'My schools',
    Détails: 'Details',
    Actif: 'Active',
    'RESSOURCES HUMAINES': 'HUMAN RESOURCES',
    'Gérez vos ressources humaines': 'Manage your people',
    "et l'administration facilement": 'and administration with ease',
    'Suivez vos employés, gérez les contrats, salaires, absences et évaluations.':
        'Track employees and manage contracts, salaries, absences and reviews.',
    Enseignante: 'Teacher',
    Poste: 'Position',
    Département: 'Department',
    Pédagogie: 'Education',
    "Date d'embauche": 'Hire date',
    'Salaire mensuel': 'Monthly salary',
    'CERTIFICATS & BADGES': 'CERTIFICATES & BADGES',
    'Certificats et badges': 'Certificates and badges',
    'générés automatiquement': 'generated automatically',
    'Créez et délivrez des certificats professionnels en quelques clics.':
        'Create and issue professional certificates in just a few clicks.',
    CERTIFICAT: 'CERTIFICATE',
    'DE RÉUSSITE': 'OF ACHIEVEMENT',
    'Décerné à': 'Awarded to',
    'pour avoir complété avec succès la formation':
        'for successfully completing the course',
    'APPLICATION MOBILE': 'MOBILE APPLICATION',
    'Parents connectés,': 'Connected parents,',
    'enfants suivis': 'supported children',
    "L'application mobile Easy School permet aux parents de suivre la scolarité de leurs enfants en temps réel.":
        'The Easy School mobile app lets parents follow their children’s education in real time.',
    'Bonjour,': 'Hello,',
    Élève: 'Student',
    'Moyenne générale': 'Overall average',
    'Restez informé au quotidien': 'Stay informed every day',
    'Recevez toutes les informations importantes concernant vos enfants.':
        'Receive every important update about your children.',
    'POURQUOI EASY SCHOOL ?': 'WHY EASY SCHOOL?',
    'Une solution puissante et pensée pour votre réussite':
        'A powerful solution designed for your success',
    'Interface moderne': 'Modern interface',
    'Design intuitif et expérience utilisateur exceptionnelle.':
        'Intuitive design and an outstanding user experience.',
    'Une plateforme, plusieurs établissements gérés.':
        'One platform for multiple schools.',
    'Sécurité avancée': 'Advanced security',
    'Données chiffrées et sauvegardes régulières.':
        'Encrypted data and regular backups.',
    'Multi-sites': 'Multi-site',
    'Gérez plusieurs campus avec statistiques par site.':
        'Manage several campuses with per-site analytics.',
    'Modules complets': 'Complete modules',
    'Tous les besoins couverts dans une seule solution.':
        'Every need covered in one solution.',
    Accompagnement: 'Guidance',
    'Support réactif et suivi personnalisé.':
        'Responsive support and personalized guidance.',
    'Prêt à découvrir Easy School ?': 'Ready to discover Easy School?',
    "Demandez une démonstration personnalisée adaptée aux besoins de votre établissement. Démo configurable jusqu'à 15 jours.":
        'Request a personalized demo tailored to your school. Configurable for up to 15 days.',
    'Nous contacter': 'Contact us',
    'DES PLANS QUI GRANDISSENT AVEC VOUS': 'PLANS THAT GROW WITH YOU',
    'Simple à choisir.': 'Simple to choose.',
    'Puissant au quotidien.': 'Powerful every day.',
    'Centralisez votre école sans multiplier les outils. Chaque formule inclut la sécurité multi-tenant, les mises à jour et notre accompagnement.':
        'Centralize your school without multiplying tools. Every plan includes tenant security, updates and guidance.',
    'LE PLUS CHOISI': 'MOST POPULAR',
    'Sur devis': 'Custom quote',
    'Essayer gratuitement': 'Try for free',
    'Besoin d’une configuration particulière ?': 'Need a custom setup?',
    'Multi-campus, migration de données ou accompagnement personnalisé : parlons-en.':
        'Multi-campus, data migration or tailored onboarding: let’s talk.',
    'Construisons une gestion': 'Let’s build simpler',
    'plus simple ensemble.': 'management together.',
    'Une question sur Easy School, un projet de digitalisation ou plusieurs campus à connecter ? Parlez-nous de votre besoin. Notre équipe vous répond avec une solution concrète.':
        'Questions about Easy School, a digital transformation project, or campuses to connect? Tell us what you need and our team will propose a concrete solution.',
    'Conseil humain': 'Human guidance',
    'Un expert vous accompagne': 'An expert supports you',
    'Projet cadré': 'A clear project',
    'Vos besoins avant la technique': 'Your needs come before technology',
    'Pensé pour l’Algérie': 'Built for Algeria',
    'Support local et réactif': 'Responsive local support',
    'Réponse rapide': 'Fast response',
    'Sous un jour ouvré': 'Within one business day',
    'Votre projet': 'Your project',
    'Dites-nous comment vous aider': 'Tell us how we can help',
    'Nom complet *': 'Full name *',
    'Email professionnel *': 'Business email *',
    Téléphone: 'Phone',
    'Établissement / organisation': 'School / organization',
    'Sujet *': 'Subject *',
    'Informations sur Easy School': 'Information about Easy School',
    'Tarifs et abonnement': 'Pricing and subscription',
    Partenariat: 'Partnership',
    Assistance: 'Support',
    'Autre demande': 'Other request',
    'Votre message *': 'Your message *',
    'Envoyer mon message': 'Send my message',
    'UNE ÉQUIPE À VOTRE ÉCOUTE': 'A TEAM THAT LISTENS',
    'Venez nous parler.': 'Come talk to us.',
    Disponibilité: 'Availability',
    'Vous préférez voir la plateforme ?': 'Would you rather see the platform?',
    'DÉMO PERSONNALISÉE & CONFIGURÉE': 'PERSONALIZED & CONFIGURED DEMO',
    'Demandez votre': 'Request your',
    'démo Easy School': 'Easy School demo',
    'Formulaire de demande de démo': 'Demo request form',
    'Établissement / Organisation': 'School / Organization',
    'Nom du responsable': 'Contact name',
    'Fonction du responsable': 'Contact role',
    "Type d'établissement": 'School type',
    'École privée': 'Private school',
    'Centre de formation': 'Training center',
    'École de langues': 'Language school',
    Autre: 'Other',
    Adresse: 'Address',
    'Site web': 'Website',
    'Durée souhaitée': 'Preferred duration',
    'Nombre d’élèves': 'Number of students',
    Enseignants: 'Teachers',
    Employés: 'Employees',
    'Sites / campus': 'Sites / campuses',
    'Modules que vous souhaitez explorer': 'Modules you want to explore',
    'Vos besoins et objectifs': 'Your needs and goals',
    'Envoyer ma demande': 'Send my request',
    'Une plateforme multi-tenant conçue pour gérer plusieurs établissements ou campus, les ressources humaines, les dossiers des étudiants et employés, les sessions, groupes, formations, certificats, badges, statistiques et applications mobiles.':
        'A multi-tenant platform built to manage multiple schools or campuses, HR, student and employee records, sessions, groups, training, certificates, badges, analytics and mobile applications.',
    'Email professionnel': 'Business email',
    '7 jours': '7 days',
    '10 jours': '10 days',
    '15 jours': '15 days',
    'Plannings & emplois du temps': 'Planning & timetables',
    'Multi-sites & campus': 'Multiple sites & campuses',
    'Paiements & finances': 'Payments & finance',
    '“Easy School nous a permis de centraliser toutes nos opérations sur plusieurs sites.”':
        '“Easy School helped us centralize all our operations across multiple sites.”',
    'Sarah K. — Directrice des opérations': 'Sarah K. — Operations Director',
    'Nous concevons des solutions digitales utiles, durables et adaptées aux réalités de votre organisation.':
        'We design useful, sustainable digital solutions adapted to the reality of your organization.',
    Email: 'Email',
    'Alger, Algérie': 'Algiers, Algeria',
    '© 2026 Easy School. Tous droits réservés.':
        '© 2026 Easy School. All rights reserved.',
    "Confidentialité     Conditions d'utilisation": 'Privacy     Terms of use',
    'Nom de votre établissement': 'Your school name',
    'Prénom et nom': 'First and last name',
    'exemple@etablissement.dz': 'example@school.com',
    'Directeur, responsable administratif…': 'Director, administrator…',
    'Adresse complète': 'Full address',
    'Ex: 120': 'E.g. 120',
    'Décrivez vos objectifs, besoins spécifiques ou toute information utile…':
        'Describe your goals, specific needs or any useful information…',
    Essentiel: 'Essential',
    Croissance: 'Growth',
    Réseau: 'Network',
    'Pour lancer la gestion numérique de votre établissement.':
        'For schools beginning their digital management journey.',
    'Pour les écoles qui veulent centraliser toute leur activité.':
        'For schools ready to centralize all their operations.',
    'Une solution sur mesure pour les groupes et multi-campus.':
        'A tailored solution for networks and multiple campuses.',
    'Formations et planning': 'Training and scheduling',
    'Paiements et reçus': 'Payments and receipts',
    'Support par email': 'Email support',
    'Tous les modules Essentiel': 'Every Essential module',
    'Ressources humaines': 'Human resources',
    'Application mobile': 'Mobile application',
    'Rapports avancés': 'Advanced reports',
    'Support prioritaire': 'Priority support',
    'Utilisateurs et sites illimités': 'Unlimited users and sites',
    'Accompagnement au déploiement': 'Deployment guidance',
    'Configuration personnalisée': 'Custom configuration',
    'Support dédié': 'Dedicated support',
    'Configuration guidée': 'Guided setup',
    'Nous configurons votre environnement.': 'We configure your environment.',
    'Parcours personnalisé': 'Personalized journey',
    'Une démonstration ciblée sur vos besoins.':
        'A demonstration focused on your needs.',
    'Modules à la carte': 'Modules your way',
    'Choisissez les modules à explorer.':
        'Choose the modules you want to explore.',
    'Durée maximale 15 jours': 'Up to 15 days',
    'Accès complet à votre rythme.': 'Full access at your own pace.',
    'Un expert vous contacte sous 24h ouvrées.':
        'An expert contacts you within one business day.',
    'Nous planifions la démo à votre convenance.':
        'We schedule the demo at your convenience.',
    'Vous recevez vos accès pour 15 jours maxi.':
        'You receive access for up to 15 days.',
    'Environnement configuré': 'Configured environment',
    'Un espace dédié à votre établissement.':
        'A workspace dedicated to your school.',
    'Démonstration guidée': 'Guided demonstration',
    'Un expert vous accompagne pas à pas.':
        'An expert guides you step by step.',
    'Données réalistes': 'Realistic data',
    'Des données fictives pour vous projeter.':
        'Realistic sample data to help you visualize the experience.',
    'Accès jusqu’à 15 jours': 'Access for up to 15 days',
    'Explorez en toute autonomie.': 'Explore independently.',
    'SaaS multi-tenant': 'Multi-tenant SaaS',
    Blog: 'Blog',
    Documentation: 'Documentation',
    FAQ: 'FAQ',
    mois: 'month',
    an: 'year',
    'Message / Besoins spécifiques': 'Message / Specific needs',
    'Vos informations sont sécurisées et ne seront jamais partagées.':
        'Your information is secure and will never be shared.',
    'Envoyer ma demande de démo': 'Send my demo request',
    'Votre démo, configurée pour vous': 'Your demo, configured for you',
    'Nos experts préparent une démo adaptée à votre établissement et vos priorités.':
        'Our experts prepare a demo tailored to your school and priorities.',
    'Après votre demande': 'After your request',
    'Ce que comprend votre démo': 'What your demo includes',
    'Votre demande a été envoyée': 'Your request has been sent',
    "Notre équipe vérifiera les informations de votre école. Si elle est approuvée, vos identifiants et la date d'expiration seront envoyés par e-mail.":
        'Our team will review your school information. If approved, your login details and expiration date will be sent by email.',
    "Retour à l'accueil": 'Back to home',
    'Votre nom': 'Your name',
    'vous@ecole.dz': 'you@school.com',
    'Nom de votre structure': 'Your organization name',
    'Parlez-nous de votre besoin, du nombre d’élèves et de vos objectifs...':
        'Tell us about your needs, student count and goals...',
    'Portail parents': 'Parent portal',
    'Espace parents': 'Parent portal',
    'Connexion équipe': 'Team sign in',
    'CONÇU POUR LES ÉTABLISSEMENTS D’AUJOURD’HUI': 'BUILT FOR TODAY’S SCHOOLS',
    'Toute votre école.': 'Your entire school.',
    'Enfin au même endroit.': 'Finally in one place.',
    'Easy School relie la scolarité, les équipes, la finance et les parents dans une plateforme claire, sécurisée et pensée pour le quotidien.':
        'Easy School brings academics, teams, finance and parents together in one clear, secure platform built for everyday work.',
    'Découvrir Easy School': 'Discover Easy School',
    'Démo personnalisée': 'Personalized demo',
    'Mise en place accompagnée': 'Guided setup',
    'Support local': 'Local support',
    'Tout est centralisé': 'Everything is centralized',
    'Une donnée, un seul endroit': 'One source of truth',
    'Accès sécurisés': 'Secure access',
    'Selon chaque rôle': 'Tailored to every role',
    '100% en ligne': '100% online',
    'Aucun logiciel à installer': 'Nothing to install',
    'Multi-établissements': 'Multi-school',
    'Une vue claire par site': 'A clear view for every site',
    'Accès maîtrisés': 'Controlled access',
    'Rôles et permissions': 'Roles and permissions',
    'Sur tous vos écrans': 'On every screen',
    'Ordinateur, tablette, mobile': 'Desktop, tablet and mobile',
    'UNE PLATEFORME, UN FIL CONDUCTEUR': 'ONE PLATFORM, ONE CONTINUOUS FLOW',
    'Moins d’outils dispersés.': 'Fewer disconnected tools.',
    'Plus de temps pour l’essentiel.': 'More time for what matters.',
    'Chaque module partage le même contexte. L’information circule sans ressaisie, de l’inscription jusqu’au suivi des parents.':
        'Every module shares the same context. Information moves without duplicate entry, from enrollment through parent follow-up.',
    SCOLARITÉ: 'ACADEMICS',
    'Du premier contact au bulletin': 'From first contact to report card',
    'Centralisez inscriptions, dossiers, classes, notes, absences et documents dans un parcours continu.':
        'Centralize enrollments, records, classes, grades, absences and documents in one continuous journey.',
    'Dossiers élèves': 'Student records',
    'Notes & bulletins': 'Grades & report cards',
    ORGANISATION: 'ORGANIZATION',
    'Une journée scolaire bien orchestrée': 'A school day that runs smoothly',
    'Construisez les emplois du temps, coordonnez les équipes et détectez les conflits avant publication.':
        'Build schedules, coordinate teams and detect conflicts before publishing.',
    'Emplois du temps': 'Timetables',
    'Classes & groupes': 'Classes & groups',
    'Salles & ressources': 'Rooms & resources',
    Annonces: 'Announcements',
    ADMINISTRATION: 'ADMINISTRATION',
    'Les opérations sous contrôle': 'Operations under control',
    'Gérez les équipes, les accès, les paiements et les documents sans multiplier les fichiers.':
        'Manage teams, access, payments and documents without multiplying files.',
    Paiements: 'Payments',
    'Rôles & permissions': 'Roles & permissions',
    Rapports: 'Reports',
    'PILOTAGE MULTI-SITES': 'MULTI-SITE MANAGEMENT',
    'Une vision globale.': 'One global view.',
    'Des réalités locales.': 'Local control.',
    'Comparez les sites, adaptez les accès et conservez des règles propres à chaque établissement.':
        'Compare sites, tailor access and keep rules specific to every school.',
    'Campus central': 'Central campus',
    'Site Est': 'East site',
    'Site Ouest': 'West site',
    'Centre langues': 'Language center',
    'Documents prêts en quelques clics': 'Documents ready in a few clicks',
    'Certificats, badges, bulletins, reçus et justificatifs restent liés au bon dossier.':
        'Certificates, badges, report cards, receipts and supporting documents stay linked to the right record.',
    'UNE MÊME INFORMATION, AU BON MOMENT':
        'THE SAME INFORMATION, AT THE RIGHT TIME',
    'De l’administration aux familles, tout reste aligné.':
        'From administration to families, everyone stays aligned.',
    'Easy School organise le passage de l’information entre chaque acteur. Moins de relances, moins de doubles saisies, et une vue adaptée à chacun.':
        'Easy School organizes how information moves between every stakeholder. Fewer reminders, less duplicate entry and the right view for everyone.',
    'L’administration organise': 'Administration organizes',
    'Paramètres, dossiers, planning, finance et accès sont pilotés depuis un espace central.':
        'Settings, records, schedules, finance and access are managed from one central workspace.',
    'Les équipes collaborent': 'Teams collaborate',
    'Chaque membre retrouve les classes, outils et informations utiles à son rôle.':
        'Every team member gets the classes, tools and information relevant to their role.',
    'Les parents restent informés': 'Parents stay informed',
    'Notes, absences, emploi du temps et annonces sont disponibles dans leur portail web.':
        'Grades, absences, timetables and announcements are available in their web portal.',
    'PORTAIL WEB DISPONIBLE': 'WEB PORTAL AVAILABLE',
    'Les parents suivent.': 'Parents stay informed.',
    'L’école garde le lien.': 'The school stays connected.',
    'Aucune installation nécessaire. Depuis leur navigateur, les parents accèdent aux informations publiées par l’établissement, sur ordinateur, tablette ou téléphone.':
        'No installation required. From their browser, parents access information published by the school on desktop, tablet or phone.',
    'Notes et bulletins': 'Grades and report cards',
    'Absences et justificatifs': 'Absences and supporting documents',
    'Annonces de l’école': 'School announcements',
    'Ouvrir l’espace parents': 'Open the parent portal',
    'Bonjour Samira,': 'Hello Samira,',
    'Vue d’ensemble': 'Overview',
    'Mes enfants': 'My children',
    Absences: 'Absences',
    Notes: 'Grades',
    'Enfant sélectionné': 'Selected child',
    '4e année': '4th year',
    Moyenne: 'Average',
    'À venir': 'Upcoming',
    'Dernières actualités': 'Latest updates',
    'Tout voir': 'View all',
    'Réunion parents–enseignants': 'Parent–teacher meeting',
    'Jeudi, 16:30': 'Thursday, 4:30 PM',
    'Applications mobiles en préparation': 'Mobile apps in development',
    'Le portail web est disponible dès maintenant':
        'The web portal is available now',
    'BIENTÔT SUR': 'COMING SOON TO',
    'SÉCURITÉ PAR CONCEPTION': 'SECURITY BY DESIGN',
    'La bonne information.': 'The right information.',
    'Aux bonnes personnes.': 'For the right people.',
    'Easy School structure les accès autour des rôles, des établissements et des responsabilités. Chacun travaille dans un périmètre clair.':
        'Easy School structures access around roles, schools and responsibilities. Everyone works within a clear scope.',
    'Parler de votre organisation': 'Discuss your organization',
    'Accès précis': 'Precise access',
    'Des rôles et permissions adaptés aux responsabilités de chaque utilisateur.':
        'Roles and permissions matched to each user’s responsibilities.',
    'Données isolées': 'Isolated data',
    'Chaque établissement conserve son propre environnement, ses réglages et ses données.':
        'Every school keeps its own environment, settings and data.',
    'Multi-sites natif': 'Built-in multi-site',
    'Une vue globale avec des périmètres et indicateurs propres à chaque campus.':
        'A global view with scopes and indicators specific to every campus.',
    Traçabilité: 'Traceability',
    'Les actions sensibles et les changements importants restent consultables.':
        'Sensitive actions and important changes remain available for review.',
    'VOTRE ÉCOLE, VOTRE PARCOURS': 'YOUR SCHOOL, YOUR JOURNEY',
    'Prêt à voir ce que votre quotidien pourrait devenir ?':
        'Ready to see what your day-to-day could become?',
    'Nous préparons une démonstration autour de vos priorités, de votre organisation et des modules qui comptent vraiment pour vous.':
        'We prepare a demo around your priorities, organization and the modules that truly matter to you.',
    'Demander ma démonstration': 'Request my demo',
    'Parler à l’équipe': 'Talk to the team',
    'Échange ciblé': 'Focused conversation',
    'Démo configurée': 'Configured demo',
    'Un environnement qui vous ressemble': 'An environment that fits you',
    'Sans engagement': 'No commitment',
    'Explorez avant de décider': 'Explore before deciding',
    Découvrir: 'Discover',
    Fonctionnalités: 'Features',
    Accès: 'Access',
    'Équipe & administration': 'Team & administration',
    'Inscrire un établissement': 'Register a school',
    'La plateforme qui relie la scolarité, l’administration, les équipes et les parents dans un espace sécurisé.':
        'The platform connecting academics, administration, teams and parents in one secure workspace.',
    'Conçu et accompagné en Algérie': 'Built and supported in Algeria',
    'Nous écrire →': 'Write to us →',
    'Plateforme SaaS sécurisée': 'Secure SaaS platform',
    'Choisissez votre espace': 'Choose your workspace',
    'Nous vous dirigerons vers la bonne page de connexion.':
        'We’ll take you to the right sign-in page.',
    'Je suis parent': 'I’m a parent',
    'Suivre la scolarité de mes enfants': 'Follow my children’s school journey',
    'Je fais partie d’une école': 'I’m part of a school',
    'Administration, équipe et élèves': 'Administration, team and students',
    'Accéder au portail parents': 'Open the parent portal',
    'Équipe, administration ou élève': 'Team, administration or student',
};

const ar: Record<string, string> = {
    Accueil: 'الرئيسية',
    Modules: 'الوحدات',
    Tarifs: 'الأسعار',
    Contact: 'اتصل بنا',
    'Se connecter': 'تسجيل الدخول',
    Connexion: 'تسجيل الدخول',
    'Demander une démo': 'اطلب عرضاً تجريبياً',
    Démo: 'تجربة',
    'Tableau de bord': 'لوحة التحكم',
    Navigation: 'التنقل',
    Ressources: 'الموارد',
    Entreprise: 'الشركة',
    'À propos': 'من نحن',
    Carrières: 'الوظائف',
    Partenaires: 'الشركاء',
    Presse: 'الصحافة',
    "Centre d'aide": 'مركز المساعدة',
    Guides: 'الأدلة',
    'Tous droits réservés.': 'جميع الحقوق محفوظة.',
    Confidentialité: 'الخصوصية',
    "Conditions d'utilisation": 'شروط الاستخدام',
    'La plateforme SaaS complète pour la gestion des établissements scolaires et centres de formation.':
        'منصة سحابية متكاملة لإدارة المدارس ومراكز التكوين.',
    'PLATEFORME SaaS MULTI-ÉTABLISSEMENTS': 'منصة سحابية متعددة المؤسسات',
    'Pilotez votre établissement': 'أدر مؤسستك',
    'en toute simplicité': 'بكل سهولة',
    'Easy School est la plateforme complète pour gérer étudiants, employés, sessions, groupes, formations, finances, RH et plusieurs sites à partir d’un seul espace sécurisé.':
        'Easy School منصة متكاملة لإدارة الطلبة والموظفين والحصص والمجموعات والتكوينات والمالية والموارد البشرية وعدة فروع من فضاء آمن واحد.',
    'Voir une démo': 'شاهد العرض',
    'Démo jusqu’à 15 jours': 'تجربة لمدة تصل إلى 15 يوماً',
    'Mise en place rapide': 'إعداد سريع',
    'Données sécurisées': 'بيانات آمنة',
    'Ils nous font confiance': 'يثقون بنا',
    'Une plateforme complète pour chaque acteur de votre établissement':
        'منصة متكاملة لكل فرد في مؤسستك',
    'Gestion des étudiants': 'إدارة الطلبة',
    'Admission, profils, suivi et historique complet.':
        'التسجيل والملفات والمتابعة والسجل الكامل.',
    'Dossiers étudiants': 'ملفات الطلبة',
    'Documents, pièces jointes et suivis personnalisés.':
        'الوثائق والمرفقات والمتابعة المخصصة.',
    'Gestion RH': 'إدارة الموارد البشرية',
    'Organigramme, rôles, postes et permissions.':
        'الهيكل والأدوار والمناصب والصلاحيات.',
    'Dossiers employés': 'ملفات الموظفين',
    'Profils, contrats, salaires et évaluations.':
        'الملفات والعقود والرواتب والتقييمات.',
    'Sessions & groupes': 'الحصص والمجموعات',
    'Planification, groupes, créneaux et salles.':
        'التخطيط والمجموعات والمواقيت والقاعات.',
    'Formations & cours': 'التكوينات والدروس',
    'Programmes, modules et contenus.': 'البرامج والوحدات والمحتوى.',
    Inscriptions: 'التسجيلات',
    'Gestion des inscriptions et réinscriptions.':
        'إدارة التسجيل وإعادة التسجيل.',
    Présences: 'الحضور',
    'Suivi des présences et absences.': 'متابعة الحضور والغياب.',
    'Paiements & factures': 'المدفوعات والفواتير',
    'Facturation, paiements, relances et reçus.':
        'الفوترة والدفع والتذكير والوصولات.',
    'Sites / campus multiples': 'فروع متعددة',
    'Gérez plusieurs sites avec des stats par site.':
        'إدارة عدة فروع مع إحصائيات لكل فرع.',
    Certificats: 'الشهادات',
    'Génération automatique de certificats.': 'إنشاء الشهادات تلقائياً.',
    Badges: 'البطاقات',
    'Badges numériques pour étudiants et employés.':
        'بطاقات رقمية للطلبة والموظفين.',
    'Rapports & statistiques': 'التقارير والإحصائيات',
    'Tableaux de bord, KPI et rapports avancés.':
        'لوحات قيادة ومؤشرات وتقارير متقدمة.',
    'Emploi du temps': 'جدول التوقيت',
    'Planning des cours et ressources.': 'تخطيط الدروس والموارد.',
    'Communication parents': 'التواصل مع الأولياء',
    'Messages, annonces et notifications.': 'الرسائل والإعلانات والإشعارات.',
    'Portail enseignant': 'بوابة الأستاذ',
    'Suivi des classes, notes et ressources.':
        'متابعة الأقسام والنقاط والموارد.',
    'Mobile parents': 'تطبيق الأولياء',
    'Suivi des enfants et infos en temps réel.':
        'متابعة الأبناء والمعلومات فورياً.',
    'Suivi en temps réel': 'متابعة مباشرة',
    'MULTI-SITES': 'فروع متعددة',
    'Gérez plusieurs sites depuis': 'أدر عدة فروع من',
    'une plateforme centrale': 'منصة مركزية واحدة',
    'Easy School vous permet de piloter tous vos établissements avec une vue globale et des paramètres spécifiques à chaque site.':
        'يتيح لك Easy School إدارة جميع مؤسساتك برؤية شاملة وإعدادات خاصة بكل فرع.',
    'Mes établissements': 'مؤسساتي',
    Détails: 'التفاصيل',
    Actif: 'نشط',
    'RESSOURCES HUMAINES': 'الموارد البشرية',
    'Gérez vos ressources humaines': 'أدر مواردك البشرية',
    "et l'administration facilement": 'والإدارة بسهولة',
    'Suivez vos employés, gérez les contrats, salaires, absences et évaluations.':
        'تابع موظفيك وأدر العقود والرواتب والغيابات والتقييمات.',
    Enseignante: 'أستاذة',
    Poste: 'المنصب',
    Département: 'القسم',
    Pédagogie: 'البيداغوجيا',
    "Date d'embauche": 'تاريخ التوظيف',
    'Salaire mensuel': 'الراتب الشهري',
    'CERTIFICATS & BADGES': 'الشهادات والبطاقات',
    'Certificats et badges': 'شهادات وبطاقات',
    'générés automatiquement': 'تُنشأ تلقائياً',
    'Créez et délivrez des certificats professionnels en quelques clics.':
        'أنشئ وامنح شهادات احترافية ببضع نقرات.',
    CERTIFICAT: 'شهادة',
    'DE RÉUSSITE': 'نجاح',
    'Décerné à': 'ممنوحة إلى',
    'pour avoir complété avec succès la formation': 'لإتمام التكوين بنجاح',
    'APPLICATION MOBILE': 'تطبيق الهاتف',
    'Parents connectés,': 'أولياء متصلون،',
    'enfants suivis': 'وأبناء تحت المتابعة',
    "L'application mobile Easy School permet aux parents de suivre la scolarité de leurs enfants en temps réel.":
        'يسمح تطبيق Easy School للأولياء بمتابعة دراسة أبنائهم فورياً.',
    'Bonjour,': 'مرحباً،',
    Élève: 'تلميذ',
    'Moyenne générale': 'المعدل العام',
    'Restez informé au quotidien': 'ابقَ على اطلاع يومياً',
    'Recevez toutes les informations importantes concernant vos enfants.':
        'استلم كل المعلومات المهمة المتعلقة بأبنائك.',
    'POURQUOI EASY SCHOOL ?': 'لماذا EASY SCHOOL؟',
    'Une solution puissante et pensée pour votre réussite':
        'حل قوي مصمم لنجاحك',
    'Interface moderne': 'واجهة عصرية',
    'Design intuitif et expérience utilisateur exceptionnelle.':
        'تصميم بديهي وتجربة استخدام مميزة.',
    'Une plateforme, plusieurs établissements gérés.':
        'منصة واحدة لإدارة عدة مؤسسات.',
    'Sécurité avancée': 'أمان متقدم',
    'Données chiffrées et sauvegardes régulières.':
        'بيانات مشفرة ونسخ احتياطية منتظمة.',
    'Multi-sites': 'فروع متعددة',
    'Gérez plusieurs campus avec statistiques par site.':
        'أدر عدة فروع بإحصائيات مستقلة.',
    'Modules complets': 'وحدات متكاملة',
    'Tous les besoins couverts dans une seule solution.':
        'كل الاحتياجات في حل واحد.',
    Accompagnement: 'مرافقة',
    'Support réactif et suivi personnalisé.': 'دعم سريع ومتابعة مخصصة.',
    'Prêt à découvrir Easy School ?': 'هل أنت مستعد لاكتشاف Easy School؟',
    "Demandez une démonstration personnalisée adaptée aux besoins de votre établissement. Démo configurable jusqu'à 15 jours.":
        'اطلب تجربة مخصصة لاحتياجات مؤسستك لمدة تصل إلى 15 يوماً.',
    'Nous contacter': 'اتصل بنا',
    'DES PLANS QUI GRANDISSENT AVEC VOUS': 'خطط تنمو معكم',
    'Simple à choisir.': 'سهلة الاختيار.',
    'Puissant au quotidien.': 'قوية كل يوم.',
    'Centralisez votre école sans multiplier les outils. Chaque formule inclut la sécurité multi-tenant, les mises à jour et notre accompagnement.':
        'وحّد إدارة مدرستك دون تعدد الأدوات. تشمل كل خطة الأمان والتحديثات والمرافقة.',
    'LE PLUS CHOISI': 'الأكثر اختياراً',
    'Sur devis': 'حسب الطلب',
    'Essayer gratuitement': 'جرّب مجاناً',
    'Besoin d’une configuration particulière ?': 'تحتاج إعداداً خاصاً؟',
    'Multi-campus, migration de données ou accompagnement personnalisé : parlons-en.':
        'فروع متعددة أو نقل بيانات أو مرافقة مخصصة: تواصل معنا.',
    'UNE ÉQUIPE À VOTRE ÉCOUTE': 'فريق يستمع إليكم',
    'Construisons une gestion': 'لنبنِ إدارة',
    'plus simple ensemble.': 'أسهل معاً.',
    'Une question sur Easy School, un projet de digitalisation ou plusieurs campus à connecter ? Parlez-nous de votre besoin. Notre équipe vous répond avec une solution concrète.':
        'لديك سؤال أو مشروع رقمنة أو فروع تريد ربطها؟ أخبرنا باحتياجاتك وسيقترح فريقنا حلاً عملياً.',
    'Conseil humain': 'استشارة بشرية',
    'Un expert vous accompagne': 'خبير يرافقك',
    'Projet cadré': 'مشروع واضح',
    'Vos besoins avant la technique': 'احتياجاتك قبل التقنية',
    'Pensé pour l’Algérie': 'مصمم للجزائر',
    'Support local et réactif': 'دعم محلي سريع',
    'Réponse rapide': 'رد سريع',
    'Sous un jour ouvré': 'خلال يوم عمل',
    'Votre projet': 'مشروعك',
    'Dites-nous comment vous aider': 'أخبرنا كيف نساعدك',
    'Nom complet *': 'الاسم الكامل *',
    'Email professionnel *': 'البريد المهني *',
    Téléphone: 'الهاتف',
    'Établissement / organisation': 'المؤسسة / المنظمة',
    'Sujet *': 'الموضوع *',
    'Informations sur Easy School': 'معلومات حول Easy School',
    'Tarifs et abonnement': 'الأسعار والاشتراك',
    Partenariat: 'شراكة',
    Assistance: 'الدعم',
    'Autre demande': 'طلب آخر',
    'Votre message *': 'رسالتك *',
    'Envoyer mon message': 'أرسل رسالتي',
    'Venez nous parler.': 'تحدثوا إلينا.',
    Disponibilité: 'أوقات العمل',
    'Vous préférez voir la plateforme ?': 'تفضل مشاهدة المنصة؟',
    'DÉMO PERSONNALISÉE & CONFIGURÉE': 'تجربة مخصصة ومهيأة',
    'Demandez votre': 'اطلب',
    'démo Easy School': 'تجربة Easy School',
    'Formulaire de demande de démo': 'استمارة طلب التجربة',
    'Établissement / Organisation': 'المؤسسة / المنظمة',
    'Nom du responsable': 'اسم المسؤول',
    'Fonction du responsable': 'صفة المسؤول',
    "Type d'établissement": 'نوع المؤسسة',
    'École privée': 'مدرسة خاصة',
    'Centre de formation': 'مركز تكوين',
    'École de langues': 'مدرسة لغات',
    Autre: 'أخرى',
    Adresse: 'العنوان',
    'Site web': 'الموقع الإلكتروني',
    'Durée souhaitée': 'المدة المطلوبة',
    'Nombre d’élèves': 'عدد الطلبة',
    Enseignants: 'الأساتذة',
    Employés: 'الموظفون',
    'Sites / campus': 'الفروع',
    'Modules que vous souhaitez explorer': 'الوحدات التي تريد تجربتها',
    'Vos besoins et objectifs': 'احتياجاتك وأهدافك',
    'Envoyer ma demande': 'أرسل طلبي',
    'Une plateforme multi-tenant conçue pour gérer plusieurs établissements ou campus, les ressources humaines, les dossiers des étudiants et employés, les sessions, groupes, formations, certificats, badges, statistiques et applications mobiles.':
        'منصة متعددة المؤسسات لإدارة المدارس والفروع والموارد البشرية وملفات الطلبة والموظفين والحصص والتكوينات والشهادات والتطبيقات.',
    'Email professionnel': 'البريد المهني',
    '7 jours': '7 أيام',
    '10 jours': '10 أيام',
    '15 jours': '15 يوماً',
    'Plannings & emplois du temps': 'التخطيط وجداول التوقيت',
    'Multi-sites & campus': 'فروع ومواقع متعددة',
    'Paiements & finances': 'المدفوعات والمالية',
    '“Easy School nous a permis de centraliser toutes nos opérations sur plusieurs sites.”':
        '«ساعدنا Easy School على توحيد كل عملياتنا عبر عدة فروع.»',
    'Sarah K. — Directrice des opérations': 'سارة ك. — مديرة العمليات',
    'Nous concevons des solutions digitales utiles, durables et adaptées aux réalités de votre organisation.':
        'نصمم حلولاً رقمية مفيدة ومستدامة ومناسبة لواقع مؤسستك.',
    Email: 'البريد الإلكتروني',
    'Alger, Algérie': 'الجزائر العاصمة، الجزائر',
    '© 2026 Easy School. Tous droits réservés.':
        '© 2026 Easy School. جميع الحقوق محفوظة.',
    "Confidentialité     Conditions d'utilisation":
        'الخصوصية     شروط الاستخدام',
    'Nom de votre établissement': 'اسم مؤسستك',
    'Prénom et nom': 'الاسم واللقب',
    'exemple@etablissement.dz': 'example@school.dz',
    'Directeur, responsable administratif…': 'مدير، مسؤول إداري…',
    'Adresse complète': 'العنوان الكامل',
    'Ex: 120': 'مثال: 120',
    'Décrivez vos objectifs, besoins spécifiques ou toute information utile…':
        'اشرح أهدافك واحتياجاتك الخاصة وأي معلومات مفيدة…',
    Essentiel: 'الأساسية',
    Croissance: 'النمو',
    Réseau: 'الشبكة',
    'Pour lancer la gestion numérique de votre établissement.':
        'لبدء الإدارة الرقمية لمؤسستك.',
    'Pour les écoles qui veulent centraliser toute leur activité.':
        'للمدارس التي تريد توحيد كل عملياتها.',
    'Une solution sur mesure pour les groupes et multi-campus.':
        'حل مخصص للمجموعات والفروع المتعددة.',
    'Formations et planning': 'التكوينات والجدولة',
    'Paiements et reçus': 'المدفوعات والوصولات',
    'Support par email': 'دعم عبر البريد',
    'Tous les modules Essentiel': 'كل وحدات الخطة الأساسية',
    'Ressources humaines': 'الموارد البشرية',
    'Application mobile': 'تطبيق الهاتف',
    'Rapports avancés': 'تقارير متقدمة',
    'Support prioritaire': 'دعم ذو أولوية',
    'Utilisateurs et sites illimités': 'مستخدمون وفروع دون حدود',
    'Accompagnement au déploiement': 'مرافقة عند الإطلاق',
    'Configuration personnalisée': 'إعدادات مخصصة',
    'Support dédié': 'دعم مخصص',
    'Configuration guidée': 'إعداد موجّه',
    'Nous configurons votre environnement.': 'نقوم بتهيئة بيئتك.',
    'Parcours personnalisé': 'مسار مخصص',
    'Une démonstration ciblée sur vos besoins.': 'عرض موجه لاحتياجاتك.',
    'Modules à la carte': 'وحدات حسب اختيارك',
    'Choisissez les modules à explorer.': 'اختر الوحدات التي تريد تجربتها.',
    'Durée maximale 15 jours': 'مدة تصل إلى 15 يوماً',
    'Accès complet à votre rythme.': 'وصول كامل بوتيرتك.',
    'Un expert vous contacte sous 24h ouvrées.':
        'يتواصل معك خبير خلال يوم عمل.',
    'Nous planifions la démo à votre convenance.':
        'نبرمج العرض في الوقت المناسب لك.',
    'Vous recevez vos accès pour 15 jours maxi.':
        'تتلقى بيانات دخول لمدة تصل إلى 15 يوماً.',
    'Environnement configuré': 'بيئة مهيأة',
    'Un espace dédié à votre établissement.': 'فضاء مخصص لمؤسستك.',
    'Démonstration guidée': 'عرض موجّه',
    'Un expert vous accompagne pas à pas.': 'يرافقك خبير خطوة بخطوة.',
    'Données réalistes': 'بيانات واقعية',
    'Des données fictives pour vous projeter.':
        'بيانات تجريبية واقعية لتصور التجربة.',
    'Accès jusqu’à 15 jours': 'وصول لمدة تصل إلى 15 يوماً',
    'Explorez en toute autonomie.': 'استكشف بكل حرية.',
    'SaaS multi-tenant': 'منصة سحابية متعددة المؤسسات',
    Blog: 'المدونة',
    Documentation: 'التوثيق',
    FAQ: 'الأسئلة الشائعة',
    mois: 'شهر',
    an: 'سنة',
    'Message / Besoins spécifiques': 'الرسالة / الاحتياجات الخاصة',
    'Vos informations sont sécurisées et ne seront jamais partagéاes.':
        'معلوماتك آمنة ولن تتم مشاركتها.',
    'Envoyer ma demande de démo': 'أرسل طلب التجربة',
    'Votre démo, configurée pour vous': 'تجربتك مهيأة لاحتياجاتك',
    'Nos experts préparent une démo adaptée à votre établissement et vos priorités.':
        'يحضّر خبراؤنا تجربة مناسبة لمؤسستك وأولوياتك.',
    'Après votre demande': 'بعد طلبك',
    'Ce que comprend votre démo': 'ماذا تشمل التجربة',
    'Votre demande a été envoyée': 'تم إرسال طلبك',
    "Notre équipe vérifiera les informations de votre école. Si elle est approuvée, vos identifiants et la date d'expiration seront envoyés par e-mail.":
        'سيراجع فريقنا معلومات مؤسستك، وستصلك بيانات الدخول عبر البريد بعد الموافقة.',
    "Retour à l'accueil": 'العودة إلى الرئيسية',
    'Votre nom': 'اسمك',
    'vous@ecole.dz': 'you@school.dz',
    'Nom de votre structure': 'اسم مؤسستك',
    'Parlez-nous de votre besoin, du nombre d’élèves et de vos objectifs...':
        'حدثنا عن احتياجاتك وعدد الطلبة وأهدافك...',
    'Portail parents': 'بوابة الأولياء',
    'Espace parents': 'فضاء الأولياء',
    'Connexion équipe': 'دخول الفريق',
    'CONÇU POUR LES ÉTABLISSEMENTS D’AUJOURD’HUI': 'مصمم لمؤسسات اليوم',
    'Toute votre école.': 'مؤسستك كاملة.',
    'Enfin au même endroit.': 'أخيراً في مكان واحد.',
    'Easy School relie la scolarité, les équipes, la finance et les parents dans une plateforme claire, sécurisée et pensée pour le quotidien.':
        'تجمع Easy School الدراسة والفرق والمالية والأولياء في منصة واضحة وآمنة مصممة للعمل اليومي.',
    'Découvrir Easy School': 'اكتشف Easy School',
    'Démo personnalisée': 'عرض مخصص',
    'Mise en place accompagnée': 'إعداد بمرافقة',
    'Support local': 'دعم محلي',
    'Tout est centralisé': 'كل شيء مركزي',
    'Une donnée, un seul endroit': 'معلومة واحدة في مكان واحد',
    'Accès sécurisés': 'دخول آمن',
    'Selon chaque rôle': 'حسب كل دور',
    '100% en ligne': 'متصل 100٪',
    'Aucun logiciel à installer': 'دون تثبيت برامج',
    'Multi-établissements': 'متعدد المؤسسات',
    'Une vue claire par site': 'رؤية واضحة لكل فرع',
    'Accès maîtrisés': 'صلاحيات مضبوطة',
    'Rôles et permissions': 'الأدوار والصلاحيات',
    'Sur tous vos écrans': 'على كل شاشاتكم',
    'Ordinateur, tablette, mobile': 'حاسوب ولوحة وهاتف',
    'UNE PLATEFORME, UN FIL CONDUCTEUR': 'منصة واحدة ومسار متكامل',
    'Moins d’outils dispersés.': 'أدوات متفرقة أقل.',
    'Plus de temps pour l’essentiel.': 'وقت أكثر لما يهم.',
    'Chaque module partage le même contexte. L’information circule sans ressaisie, de l’inscription jusqu’au suivi des parents.':
        'تتشارك كل الوحدات نفس السياق، وتنتقل المعلومة دون إعادة إدخال من التسجيل إلى متابعة الأولياء.',
    SCOLARITÉ: 'الدراسة',
    'Du premier contact au bulletin': 'من أول تواصل إلى كشف النقاط',
    'Centralisez inscriptions, dossiers, classes, notes, absences et documents dans un parcours continu.':
        'اجمع التسجيلات والملفات والأقسام والنقاط والغيابات والوثائق في مسار واحد.',
    'Dossiers élèves': 'ملفات التلاميذ',
    'Notes & bulletins': 'النقاط وكشوف النتائج',
    ORGANISATION: 'التنظيم',
    'Une journée scolaire bien orchestrée': 'يوم دراسي منظم بإحكام',
    'Construisez les emplois du temps, coordonnez les équipes et détectez les conflits avant publication.':
        'أنشئ جداول التوقيت ونسّق الفرق واكتشف التعارضات قبل النشر.',
    'Emplois du temps': 'جداول التوقيت',
    'Classes & groupes': 'الأقسام والمجموعات',
    'Salles & ressources': 'القاعات والموارد',
    Annonces: 'الإعلانات',
    ADMINISTRATION: 'الإدارة',
    'Les opérations sous contrôle': 'عمليات تحت السيطرة',
    'Gérez les équipes, les accès, les paiements et les documents sans multiplier les fichiers.':
        'أدر الفرق والصلاحيات والمدفوعات والوثائق دون تشتيت الملفات.',
    Paiements: 'المدفوعات',
    'Rôles & permissions': 'الأدوار والصلاحيات',
    Rapports: 'التقارير',
    'PILOTAGE MULTI-SITES': 'إدارة متعددة الفروع',
    'Une vision globale.': 'رؤية شاملة.',
    'Des réalités locales.': 'وتحكم محلي.',
    'Comparez les sites, adaptez les accès et conservez des règles propres à chaque établissement.':
        'قارن الفروع واضبط الصلاحيات واحتفظ بقواعد خاصة لكل مؤسسة.',
    'Campus central': 'الفرع المركزي',
    'Site Est': 'الفرع الشرقي',
    'Site Ouest': 'الفرع الغربي',
    'Centre langues': 'مركز اللغات',
    'Documents prêts en quelques clics': 'وثائق جاهزة ببضع نقرات',
    'Certificats, badges, bulletins, reçus et justificatifs restent liés au bon dossier.':
        'تبقى الشهادات والبطاقات والكشوف والوصولات والمبررات مرتبطة بالملف الصحيح.',
    'UNE MÊME INFORMATION, AU BON MOMENT': 'نفس المعلومة في الوقت المناسب',
    'De l’administration aux familles, tout reste aligné.':
        'من الإدارة إلى العائلات، الجميع على اطلاع.',
    'Easy School organise le passage de l’information entre chaque acteur. Moins de relances, moins de doubles saisies, et une vue adaptée à chacun.':
        'تنظم Easy School انتقال المعلومات بين الجميع، مع تذكيرات وإدخالات مكررة أقل وواجهة مناسبة لكل شخص.',
    'L’administration organise': 'الإدارة تنظّم',
    'Paramètres, dossiers, planning, finance et accès sont pilotés depuis un espace central.':
        'تُدار الإعدادات والملفات والتخطيط والمالية والصلاحيات من فضاء مركزي.',
    'Les équipes collaborent': 'الفرق تتعاون',
    'Chaque membre retrouve les classes, outils et informations utiles à son rôle.':
        'يجد كل عضو الأقسام والأدوات والمعلومات المناسبة لدوره.',
    'Les parents restent informés': 'الأولياء على اطلاع',
    'Notes, absences, emploi du temps et annonces sont disponibles dans leur portail web.':
        'تتوفر النقاط والغيابات وجدول التوقيت والإعلانات في بوابتهم الإلكترونية.',
    'PORTAIL WEB DISPONIBLE': 'البوابة الإلكترونية متاحة',
    'Les parents suivent.': 'الأولياء يتابعون.',
    'L’école garde le lien.': 'والمؤسسة تبقى قريبة.',
    'Aucune installation nécessaire. Depuis leur navigateur, les parents accèdent aux informations publiées par l’établissement, sur ordinateur, tablette ou téléphone.':
        'لا حاجة إلى تثبيت أي تطبيق. يدخل الأولياء من المتصفح إلى المعلومات المنشورة عبر الحاسوب أو اللوحة أو الهاتف.',
    'Notes et bulletins': 'النقاط وكشوف النتائج',
    'Absences et justificatifs': 'الغيابات والمبررات',
    'Annonces de l’école': 'إعلانات المؤسسة',
    'Ouvrir l’espace parents': 'فتح فضاء الأولياء',
    'Bonjour Samira,': 'مرحباً سميرة،',
    'Vue d’ensemble': 'نظرة عامة',
    'Mes enfants': 'أبنائي',
    Absences: 'الغيابات',
    Notes: 'النقاط',
    'Enfant sélectionné': 'التلميذ المحدد',
    '4e année': 'السنة الرابعة',
    Moyenne: 'المعدل',
    'À venir': 'قريباً',
    'Dernières actualités': 'آخر الأخبار',
    'Tout voir': 'عرض الكل',
    'Réunion parents–enseignants': 'اجتماع الأولياء والأساتذة',
    'Jeudi, 16:30': 'الخميس، 16:30',
    'Applications mobiles en préparation': 'تطبيقات الهاتف قيد التحضير',
    'Le portail web est disponible dès maintenant':
        'البوابة الإلكترونية متاحة الآن',
    'BIENTÔT SUR': 'قريباً على',
    'SÉCURITÉ PAR CONCEPTION': 'الأمان أساس التصميم',
    'La bonne information.': 'المعلومة الصحيحة.',
    'Aux bonnes personnes.': 'للأشخاص المناسبين.',
    'Easy School structure les accès autour des rôles, des établissements et des responsabilités. Chacun travaille dans un périmètre clair.':
        'تنظم Easy School الدخول حسب الأدوار والمؤسسات والمسؤوليات، ليعمل كل شخص ضمن نطاق واضح.',
    'Parler de votre organisation': 'تحدث عن مؤسستك',
    'Accès précis': 'صلاحيات دقيقة',
    'Des rôles et permissions adaptés aux responsabilités de chaque utilisateur.':
        'أدوار وصلاحيات ملائمة لمسؤوليات كل مستخدم.',
    'Données isolées': 'بيانات معزولة',
    'Chaque établissement conserve son propre environnement, ses réglages et ses données.':
        'تحتفظ كل مؤسسة ببيئتها وإعداداتها وبياناتها الخاصة.',
    'Multi-sites natif': 'تعدد فروع مدمج',
    'Une vue globale avec des périmètres et indicateurs propres à chaque campus.':
        'رؤية شاملة مع نطاقات ومؤشرات خاصة بكل فرع.',
    Traçabilité: 'التتبع',
    'Les actions sensibles et les changements importants restent consultables.':
        'تبقى العمليات الحساسة والتغييرات المهمة قابلة للمراجعة.',
    'VOTRE ÉCOLE, VOTRE PARCOURS': 'مؤسستك، مسارك',
    'Prêt à voir ce que votre quotidien pourrait devenir ?':
        'هل أنت مستعد لرؤية كيف يمكن أن يصبح يومك؟',
    'Nous préparons une démonstration autour de vos priorités, de votre organisation et des modules qui comptent vraiment pour vous.':
        'نحضّر عرضاً حول أولوياتكم وتنظيمكم والوحدات التي تهمكم فعلاً.',
    'Demander ma démonstration': 'اطلب عرضي',
    'Parler à l’équipe': 'تحدث مع الفريق',
    'Échange ciblé': 'نقاش مركز',
    'Démo configurée': 'عرض مهيأ',
    'Un environnement qui vous ressemble': 'بيئة تناسبكم',
    'Sans engagement': 'دون التزام',
    'Explorez avant de décider': 'استكشف قبل أن تقرر',
    Découvrir: 'اكتشف',
    Fonctionnalités: 'الميزات',
    Accès: 'الدخول',
    'Équipe & administration': 'الفريق والإدارة',
    'Inscrire un établissement': 'تسجيل مؤسسة',
    'La plateforme qui relie la scolarité, l’administration, les équipes et les parents dans un espace sécurisé.':
        'المنصة التي تربط الدراسة والإدارة والفرق والأولياء في فضاء آمن.',
    'Conçu et accompagné en Algérie': 'مصمم ومدعوم في الجزائر',
    'Nous écrire →': 'راسلنا ←',
    'Plateforme SaaS sécurisée': 'منصة سحابية آمنة',
    'Choisissez votre espace': 'اختر فضاءك',
    'Nous vous dirigerons vers la bonne page de connexion.':
        'سنوجهك إلى صفحة الدخول المناسبة.',
    'Je suis parent': 'أنا ولي',
    'Suivre la scolarité de mes enfants': 'متابعة دراسة أبنائي',
    'Je fais partie d’une école': 'أنا من طاقم المؤسسة',
    'Administration, équipe et élèves': 'الإدارة والفريق والتلاميذ',
    'Accéder au portail parents': 'الدخول إلى بوابة الأولياء',
    'Équipe, administration ou élève': 'الفريق أو الإدارة أو التلميذ',
};

const dictionaries = { en, ar };
const originals = new WeakMap<Node, string>();

export function setPublicLocale(locale: PublicLocale) {
    publicLocale.value = locale;
    localStorage.setItem('easy-school-locale', locale);
}

export function translatePublicRoot(
    root: HTMLElement,
    locale = publicLocale.value,
) {
    document.documentElement.lang = locale;
    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
    const dictionary = locale === 'fr' ? null : dictionaries[locale];
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node: Node | null;
    while ((node = walker.nextNode())) {
        if (node.parentElement?.closest('[data-no-translate]')) continue;
        if (!originals.has(node)) originals.set(node, node.textContent || '');
        const source = originals.get(node) || '';
        const value = source.trim();
        node.textContent = dictionary?.[value]
            ? source.replace(value, dictionary[value])
            : source;
    }
    root.querySelectorAll<HTMLElement>(
        '[placeholder],[aria-label],[title]',
    ).forEach((element) => {
        if (element.closest('[data-no-translate]')) return;
        for (const attribute of ['placeholder', 'aria-label', 'title']) {
            const current = element.getAttribute(attribute);
            if (!current) continue;
            const key = `locale${attribute.replace('-', '')}`;
            if (!element.dataset[key]) element.dataset[key] = current;
            const source = element.dataset[key] || current;
            element.setAttribute(attribute, dictionary?.[source] || source);
        }
    });
}

export function watchPublicLocale(root: () => HTMLElement | null) {
    watch(publicLocale, async () => {
        await nextTick();
        const element = root();
        if (element) translatePublicRoot(element);
    });
}
