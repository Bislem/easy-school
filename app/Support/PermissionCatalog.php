<?php

namespace App\Support;

final class PermissionCatalog
{
    /** @return array<string, string> */
    public static function all(): array
    {
        return [
            'employees.view' => 'Consulter les employés', 'employees.create' => 'Créer des employés', 'employees.update' => 'Modifier les employés', 'employees.delete' => 'Supprimer des employés', 'employees.export' => 'Exporter les employés',
            'salaries.view' => 'Consulter les salaires', 'salaries.manage' => 'Gérer les salaires', 'salaries.approve' => 'Approuver les salaires', 'salaries.export' => 'Exporter les salaires',
            'payslips.view' => 'Consulter les fiches de paie', 'payslips.manage' => 'Gérer les fiches de paie',
            'hr_records.view' => 'Consulter les dossiers RH', 'hr_records.manage' => 'Gérer les dossiers RH',
            'payments.view' => 'Consulter les paiements', 'payments.collect' => 'Encaisser des paiements', 'payments.cancel' => 'Annuler des paiements', 'payments.refund' => 'Rembourser des paiements', 'payments.export' => 'Exporter les paiements',
            'school_fees.view' => 'Consulter les frais scolaires', 'school_fees.manage' => 'Gérer les frais scolaires',
            'expenses.view' => 'Consulter les dépenses', 'expenses.create' => 'Créer des dépenses', 'expenses.update' => 'Modifier les dépenses', 'expenses.delete' => 'Supprimer des dépenses', 'expenses.approve' => 'Approuver les dépenses',
            'financial_reports.view' => 'Consulter les rapports financiers', 'financial_reports.export' => 'Exporter les rapports financiers',
            'students.view' => 'Consulter les élèves', 'students.create' => 'Créer des élèves', 'students.update' => 'Modifier les élèves', 'students.delete' => 'Supprimer des élèves', 'students.export' => 'Exporter les élèves',
            'parents.view' => 'Consulter les parents', 'parents.create' => 'Créer des parents', 'parents.update' => 'Modifier les parents', 'parents.delete' => 'Supprimer des parents',
            'enrollments.view' => 'Consulter les inscriptions', 'enrollments.manage' => 'Gérer les inscriptions',
            'groups.view' => 'Consulter les groupes', 'groups.manage' => 'Gérer les groupes', 'assigned_groups.view' => 'Consulter les groupes affectés',
            'timetables.view' => 'Consulter les emplois du temps', 'timetables.manage' => 'Gérer les emplois du temps',
            'student_attendance.view' => 'Consulter les présences élèves', 'student_attendance.record' => 'Saisir les présences élèves', 'student_attendance.justify' => 'Justifier les absences élèves',
            'staff_attendance.view' => 'Consulter les présences du personnel', 'staff_attendance.record' => 'Saisir les présences du personnel', 'staff_attendance.justify' => 'Justifier les absences du personnel',
            'absences.view' => 'Consulter les absences', 'absences.manage' => 'Gérer les absences',
            'discipline.view' => 'Consulter le suivi disciplinaire', 'discipline.manage' => 'Gérer le suivi disciplinaire',
            'grades.view' => 'Consulter les notes', 'grades.manage' => 'Gérer les notes',
            'homework.view' => 'Consulter les devoirs', 'homework.manage' => 'Gérer les devoirs',
            'observations.view' => 'Consulter les observations', 'observations.manage' => 'Gérer les observations',
            'administrative_documents.view' => 'Consulter les documents administratifs', 'administrative_documents.manage' => 'Gérer les documents administratifs',
            'roles.view' => 'Consulter les rôles', 'roles.manage' => 'Gérer les rôles',
            'users.view' => 'Consulter les utilisateurs', 'users.manage' => 'Gérer les utilisateurs',
            'academic_years.view' => 'Consulter les années scolaires', 'academic_years.manage' => 'Gérer les années scolaires',
            'badges.view' => 'Consulter les badges', 'badges.manage' => 'Gérer les badges',
            'certificates.view' => 'Consulter les certificats', 'certificates.manage' => 'Gérer les certificats',
            'reports.view' => 'Consulter les rapports', 'reports.export' => 'Exporter les rapports', 'audit.view' => "Consulter le journal d'audit",
        ];
    }

    public static function normalize(string $key): string
    {
        return self::aliases()[$key] ?? $key;
    }

    /** @return array<string, string> */
    private static function aliases(): array
    {
        return [
            'staff.viewAny' => 'employees.view', 'staff.view' => 'employees.view', 'staff.create' => 'employees.create', 'staff.update' => 'employees.update', 'staff.changeStatus' => 'employees.update', 'staff.manageTypes' => 'employees.update',
            'attendance.view' => 'staff_attendance.view', 'attendance.students.manage' => 'student_attendance.record', 'attendance.teachers.manage' => 'staff_attendance.record', 'attendance.employees.manage' => 'staff_attendance.record', 'attendance.validate' => 'staff_attendance.justify', 'attendance.correct_locked' => 'staff_attendance.justify', 'attendance.reports.view' => 'staff_attendance.view',
            'timetable.view' => 'timetables.view', 'timetable.manage' => 'timetables.manage',
            'school_attendance.view' => 'student_attendance.view', 'school_attendance.manage' => 'student_attendance.record',
            'academic_year.view' => 'academic_years.view', 'academic_year.create' => 'academic_years.manage', 'academic_year.update' => 'academic_years.manage', 'academic_year.activate' => 'academic_years.manage', 'academic_year.close' => 'academic_years.manage', 'academic_year.archive' => 'academic_years.manage',
            'badges.print' => 'badges.manage', 'badges.reissue' => 'badges.manage',
            'certificates.issue' => 'certificates.manage', 'certificates.print' => 'certificates.manage',
        ];
    }
}
