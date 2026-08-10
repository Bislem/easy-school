import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = import.meta.env.VITE_APP_NAME || 'Gestion scolaire';

const pages = import.meta.glob<DefineComponent>([
    './pages/Dashboard.vue',
    './pages/auth/**/*.vue',
    './pages/settings/Appearance.vue',
    './pages/settings/Password.vue',
    './pages/settings/Profile.vue',
    './pages/settings/TwoFactor.vue',
    './pages/Admin/Users/**/*.vue',
    './pages/Admin/Classrooms/**/*.vue',
    './pages/Admin/Courses/**/*.vue',
    './pages/Admin/Students/**/*.vue',
    './pages/Admin/EnrollmentForms/**/*.vue',
    './pages/Admin/Expenses/**/*.vue',
    './pages/Admin/Salaries/**/*.vue',
    './pages/Admin/TrainingPlans/**/*.vue',
    './pages/Admin/Settings/**/*.vue',
    './pages/Public/**/*.vue',
]);

type SchoolBrand = { primary_color?: string | null } | null | undefined;

function applySchoolBrand(school: SchoolBrand) {
    const color = school?.primary_color || '#f97316';
    const hex = color.replace('#', '');
    const normalized =
        hex.length === 3
            ? hex
                  .split('')
                  .map((character) => character + character)
                  .join('')
            : hex;
    const red = Number.parseInt(normalized.slice(0, 2), 16);
    const green = Number.parseInt(normalized.slice(2, 4), 16);
    const blue = Number.parseInt(normalized.slice(4, 6), 16);
    const foreground =
        Number.isNaN(red + green + blue) ||
        (red * 299 + green * 587 + blue * 114) / 1000 < 150
            ? '#ffffff'
            : '#111827';
    const root = document.documentElement;

    root.style.setProperty('--primary', color);
    root.style.setProperty('--primary-foreground', foreground);
    root.style.setProperty('--ring', color);
    root.style.setProperty('--sidebar-primary', color);
    root.style.setProperty('--sidebar-primary-foreground', foreground);
    root.style.setProperty('--sidebar-ring', color);
    root.style.setProperty('--website-primary', color);
    root.style.setProperty('--chart-1', color);
}

router.on('navigate', (event) => {
    applySchoolBrand(event.detail.page.props.school as SchoolBrand);
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
        applySchoolBrand(props.initialPage.props.school as SchoolBrand);
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#f56100',
    },
});

// Force light mode on page load
initializeTheme();
