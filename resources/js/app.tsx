import '../css/app.css';
import './bootstrap';

import { RouteAnnouncer } from '@/Components/Encontrarnos/motion';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Al cambiar de página Inertia regresa arriba con scrollTo; con `scroll-behavior: smooth`
// eso sería un viaje largo y marea. Los enlaces internos (#acciones) siguen siendo suaves.
// Se usa `before` porque una página precargada no emite `start`, y las precargas
// (al pasar el puntero por un enlace) no son visitas: no deben tocar nada.
let restoreSmoothScroll: number | undefined;
router.on('before', (event) => {
    if (event.detail.visit.prefetch) return;
    window.clearTimeout(restoreSmoothScroll);
    document.documentElement.style.scrollBehavior = 'auto';
});
const scheduleRestore = () => {
    window.clearTimeout(restoreSmoothScroll);
    restoreSmoothScroll = window.setTimeout(
        () => document.documentElement.style.removeProperty('scroll-behavior'),
        150,
    );
};
router.on('navigate', scheduleRestore);
router.on('finish', (event) => {
    if (!event.detail.visit.prefetch) scheduleRestore();
});

createInertiaApp({
    // Varias páginas ya incluyen el nombre en su título; no se repite.
    title: (title) =>
        title.includes(appName) ? title : `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob('./Pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <>
                <App {...props} />
                <RouteAnnouncer />
            </>,
        );
    },
    progress: {
        color: '#dd553a',
        delay: 100,
        showSpinner: false,
    },
});
