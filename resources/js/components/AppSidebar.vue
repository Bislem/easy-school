<script setup lang="ts">
import NavMain from '@/components/NavMain.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home } from '@/routes';
import { type NavItem } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    BookMarked,
    BookOpen,
    BriefcaseBusiness,
    Building2,
    CalendarClock,
    CalendarRange,
    ClipboardCheck,
    ClipboardList,
    FileCheck2,
    Files,
    GraduationCap,
    HardDrive,
    IdCard,
    LayoutDashboard,
    ReceiptText,
    Settings,
    Users,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const page = usePage();
const role = page.props.auth.user.role;
const permissions = new Set<string>(
    (page.props.auth.permissions as string[] | undefined) ?? [],
);
const can = (permission: string) => permissions.has(permission);
const hasBackOfficeAccess = permissions.size > 0;
const isPrivateSchool =
    page.props.auth?.tenant?.organization_type === 'private_school';
const academicYears = (page.props.academic_years || []) as Array<{
    id: number;
    name: string;
}>;
const currentAcademicYear = page.props.current_academic_year as {
    id: number;
    name: string;
} | null;
function selectAcademicYear(event: Event) {
    router.post(
        '/admin/academic-years/select',
        { academic_year_id: Number((event.target as HTMLSelectElement).value) },
        { preserveScroll: true },
    );
}
const unreadNotifications = Number(page.props.unread_notifications_count ?? 0);
const notificationsTitle = unreadNotifications
    ? `Notifications (${unreadNotifications})`
    : 'Notifications';

const rawMainNavItems: NavItem[] = [
    {
        title: 'Tableau de bord',
        href: '/dashboard',
        icon: LayoutDashboard,
    },
    ...(hasBackOfficeAccess
        ? [
              {
                  title: 'Étudiants',
                  href: '/admin/students',
                  icon: GraduationCap,
              },
              {
                  title: 'Compte / Abonnement',
                  href: '/admin/account',
                  icon: HardDrive,
              },
              ...(isPrivateSchool
                  ? [
                        {
                            title: 'École privée',
                            icon: Building2,
                            children: [
                                {
                                    title: 'Années scolaires',
                                    href: '/admin/academic-years',
                                    icon: CalendarRange,
                                },
                                {
                                    title: "Campagnes d'inscription",
                                    href: '/admin/inscription-campaigns',
                                    icon: ClipboardList,
                                },
                                {
                                    title: 'Demandes d’inscription',
                                    href: '/school-inscription',
                                    icon: FileCheck2,
                                },
                                {
                                    title: 'Emploi du temps',
                                    href: '/admin/timetable',
                                    icon: CalendarClock,
                                },
                                {
                                    title: 'Présences',
                                    href: '/admin/school-attendance',
                                    icon: ClipboardCheck,
                                },
                                {
                                    title: 'Groupes & niveaux',
                                    href: '/admin/groups',
                                    icon: UsersRound,
                                },
                                {
                                    title: 'Matières',
                                    href: '/admin/subjects',
                                    icon: BookMarked,
                                },
                                {
                                    title: 'Documents scolaires',
                                    href: '/admin/school-documents',
                                    icon: Files,
                                },
                            ],
                        },
                    ]
                  : []),
              {
                  title: 'Centre de formation & langues',
                  icon: BookOpen,
                  children: [
                      {
                          title: 'Formations',
                          href: '/admin/courses',
                          icon: BookOpen,
                      },
                      {
                          title: 'Inscriptions',
                          href: '/admin/enrollment-forms',
                          icon: ClipboardList,
                      },
                      {
                          title: 'Planifications',
                          href: '/admin/planifications',
                          icon: CalendarRange,
                      },
                  ],
              },
              { title: 'Parents', href: '/admin/parents', icon: Users },
              { title: 'Sites', href: '/admin/sites', icon: Building2 },
              { title: 'Salles', href: '/admin/classrooms', icon: Building2 },
              {
                  title: 'Ressources humaines',
                  icon: BriefcaseBusiness,
                  children: [
                      { title: 'Personnel', href: '/admin/users', icon: Users },
                      {
                          title: 'Présences',
                          href: '/admin/attendance',
                          icon: ClipboardCheck,
                      },
                      {
                          title: 'Salaires',
                          href: '/admin/salaries',
                          icon: WalletCards,
                      },
                      {
                          title: 'Paramètres de paie',
                          href: '/admin/salaries/configurations',
                          icon: Settings,
                      },
                      {
                          title: 'Déclarations sociales & fiscales',
                          href: '/admin/salaries/declarations',
                          icon: FileCheck2,
                      },
                  ],
              },
              { title: 'Badges', href: '/admin/badges', icon: IdCard },
              {
                  title: 'Certificats',
                  href: '/admin/certificates',
                  icon: IdCard,
              },
              { title: 'Rapports', href: '/admin/reports', icon: ReceiptText },
              {
                  title: 'Journal d’audit',
                  href: '/admin/audit',
                  icon: ClipboardList,
              },
              {
                  title: 'Finance étudiants',
                  href: '/admin/finance',
                  icon: ReceiptText,
              },
              { title: 'Dépenses', href: '/admin/expenses', icon: ReceiptText },
          ]
        : []),
    ...(role === 'teacher' || role === 'employee'
        ? [
              { title: 'Ma paie', href: '/my/salary', icon: WalletCards },
              { title: 'Ma carte', href: '/my/card', icon: IdCard },
          ]
        : []),
    ...(role === 'student'
        ? [
              { title: 'Mon espace', href: '/portal', icon: GraduationCap },
              {
                  title: 'Ma formation',
                  href: '/portal/formation',
                  icon: BookOpen,
              },
              {
                  title: 'Mon planning',
                  href: '/portal/planning',
                  icon: CalendarRange,
              },
              {
                  title: 'Présences',
                  href: '/portal/attendance',
                  icon: ClipboardCheck,
              },
              {
                  title: 'Mes paiements',
                  href: '/portal/payments',
                  icon: ReceiptText,
              },
              { title: 'Ma carte', href: '/my/card', icon: IdCard },
          ]
        : []),
    ...(role === 'parent'
        ? [
              { title: 'Espace parent', href: '/portal', icon: Users },
              {
                  title: notificationsTitle,
                  href: '/portal/notifications',
                  icon: Bell,
              },
          ]
        : []),
    ...(role === 'teacher'
        ? [
              {
                  title: 'Mes planifications',
                  href: '/admin/planifications',
                  icon: CalendarRange,
              },
              {
                  title: 'Mes étudiants',
                  href: '/portal/students',
                  icon: GraduationCap,
              },
              {
                  title: 'Mon planning',
                  href: '/portal/planning',
                  icon: CalendarRange,
              },
              {
                  title: notificationsTitle,
                  href: '/portal/notifications',
                  icon: Bell,
              },
          ]
        : []),
    ...(hasBackOfficeAccess
        ? [
              {
                  title: "Paramètres de l'école",
                  icon: Settings,
                  children: [
                      {
                          title: 'Configuration',
                          href: '/admin/settings',
                          icon: Settings,
                      },
                      {
                          title: 'Utilisateurs & accès',
                          href: '/admin/settings/access/users',
                          icon: Users,
                      },
                  ],
              },
          ]
        : []),
];

const routePermissions: Record<string, string> = {
    '/admin/students': 'students.view',
    '/admin/account': 'users.view',
    '/admin/academic-years': 'academic_years.view',
    '/admin/inscription-campaigns': 'enrollments.view',
    '/school-inscription': 'enrollments.view',
    '/admin/timetable': 'timetables.view',
    '/admin/school-attendance': 'student_attendance.view',
    '/admin/groups': 'groups.view',
    '/admin/subjects': 'groups.view',
    '/admin/school-documents': 'administrative_documents.view',
    '/admin/courses': 'groups.view',
    '/admin/enrollment-forms': 'enrollments.view',
    '/admin/planifications': 'timetables.view',
    '/admin/parents': 'parents.view',
    '/admin/sites': 'groups.view',
    '/admin/classrooms': 'groups.view',
    '/admin/users': 'users.view',
    '/admin/attendance': 'staff_attendance.view',
    '/admin/salaries': 'salaries.view',
    '/admin/badges': 'badges.view',
    '/admin/certificates': 'certificates.view',
    '/admin/reports': 'reports.view',
    '/admin/audit': 'audit.view',
    '/admin/finance': 'payments.view',
    '/admin/expenses': 'expenses.view',
    '/admin/settings': 'users.view',
    '/admin/settings/access/users': 'roles.view',
};

function filterAuthorized(items: NavItem[]): NavItem[] {
    return items.flatMap((item) => {
        const children = item.children
            ? filterAuthorized(item.children)
            : undefined;
        const href = typeof item.href === 'string' ? item.href : undefined;
        const required = href ? routePermissions[href] : undefined;
        if (required && !can(required)) return [];
        if (!href && item.children && !children?.length) return [];
        return [{ ...item, children }];
    });
}

const mainNavItems = filterAuthorized(rawMainNavItems);
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="school-sidebar">
        <SidebarHeader class="border-b border-sidebar-border/70 p-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-12 rounded-xl bg-white/5 text-white shadow-sm ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white hover:shadow-md data-[state=open]:bg-white/10"
                    >
                        <Link :href="home()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
                <SidebarMenuItem
                    v-if="
                        hasBackOfficeAccess &&
                        isPrivateSchool &&
                        academicYears.length
                    "
                    class="group-data-[collapsible=icon]:hidden"
                >
                    <select
                        class="w-full rounded-lg border border-white/15 bg-white/10 px-2 py-2 text-xs text-white"
                        :value="currentAcademicYear?.id"
                        @change="selectAcademicYear"
                    >
                        <option
                            v-for="year in academicYears"
                            :key="year.id"
                            :value="year.id"
                            class="text-slate-900"
                        >
                            {{ year.name }}
                        </option>
                    </select>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>
    </Sidebar>
    <slot />
</template>
