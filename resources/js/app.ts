import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AppLayout from '@/layouts/AppLayout.vue';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import ShowcaseLayout from '@/layouts/ShowcaseLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';
import { setServerToday } from '@/lib/formatters';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    // The public showcase is the account's own site: its titles carry the account's name, not the app's.
    title: (title) =>
        typeof document !== 'undefined' &&
        document.documentElement.hasAttribute('data-showcase')
            ? title
            : title
              ? `${title} - ${appName}`
              : appName,
    layout: (name) => {
        switch (true) {
            case name === 'receipts/Show':
                return null;
            case name === 'auth/Login':
                return AuthSplitLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            case name.startsWith('showcase/'):
                return ShowcaseLayout;
            default:
                return AppLayout;
        }
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();

// This keeps the browser on the server's date, which may be simulated through APP_FAKE_TODAY...
router.on('navigate', (event) => setServerToday(event.detail.page.props.today));
