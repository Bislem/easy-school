import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
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

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, pages),
    setup({ el, App, props, plugin }) {
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
