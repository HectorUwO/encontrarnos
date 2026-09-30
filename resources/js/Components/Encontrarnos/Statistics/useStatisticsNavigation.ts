import { router } from '@inertiajs/react';
import { useState } from 'react';

export interface Period {
    from: number | null;
    to: number | null;
}

const query = ({ from, to }: Period) => ({
    ...(from !== null && { from }),
    ...(to !== null && { to }),
});

/**
 * Navegación de las páginas de estadísticas: cambia el periodo sin recargar
 * todo y abre la página de otro estado (o la del país) conservando el periodo.
 */
export function useStatisticsNavigation(
    current: Period & { state: string | null },
) {
    const [loading, setLoading] = useState(false);

    const urlFor = (state: string | null, period: Period = current) =>
        state === null
            ? route('statistics', query(period))
            : route('statistics.state', { state, ...query(period) });

    const setPeriod = (changes: Partial<Period>) => {
        const next: Period = { from: current.from, to: current.to, ...changes };

        // Si el rango queda al revés, el límite que no se tocó sigue al que sí.
        if (next.from !== null && next.to !== null && next.from > next.to) {
            if ('from' in changes) next.to = next.from;
            else next.from = next.to;
        }

        router.get(
            urlFor(current.state, next),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['statistics'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    };

    // Un segundo clic en el mismo año quita el filtro.
    const selectYear = (year: number) =>
        setPeriod(
            current.from === year && current.to === year
                ? { from: null, to: null }
                : { from: year, to: year },
        );

    const goTo = (state: string | null) => router.visit(urlFor(state));

    return { loading, urlFor, setPeriod, selectYear, goTo };
}
