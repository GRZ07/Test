// main.js

import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query'; // Import QueryClient
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// Initialize the QueryClient with default options
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            staleTime: 5000, // Data is fresh for 5 seconds
            cacheTime: 10000, // Unused data is cached for 10 seconds
            refetchOnWindowFocus: false, // Do not refetch on window focus
            retry: 1, // Retry failed queries once
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(VueQueryPlugin, { queryClient }) // Provide QueryClient here
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
