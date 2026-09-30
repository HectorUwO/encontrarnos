import StateDashboard from '@/Components/Encontrarnos/Statistics/StateDashboard';
import PublicLayout from '@/Layouts/PublicLayout';
import { Option, StatisticsProps } from '@/types';
import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function EstadisticasEstado({
    statistics,
    states,
}: {
    statistics: StatisticsProps;
    states: Option[];
}) {
    const scope = statistics.has_data ? statistics.summary.scope : 'Estado';

    return (
        <>
            <Head title={`${scope} · Estadísticas · Encontrarnos`} />
            <StateDashboard
                // Al pasar de un estado a otro se empieza de cero: búsqueda, orden, etc.
                key={statistics.has_data ? statistics.filters.state : 'none'}
                statistics={statistics}
                states={states}
            />
        </>
    );
}

EstadisticasEstado.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
