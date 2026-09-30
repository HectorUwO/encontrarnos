import StatisticsDashboard from '@/Components/Encontrarnos/Statistics/StatisticsDashboard';
import PublicLayout from '@/Layouts/PublicLayout';
import { Option, StatisticsProps } from '@/types';
import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';

export default function Estadisticas({
    statistics,
    states,
}: {
    statistics: StatisticsProps;
    states: Option[];
}) {
    return (
        <>
            <Head title="Estadísticas · Encontrarnos" />
            <StatisticsDashboard statistics={statistics} states={states} />
        </>
    );
}

Estadisticas.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
