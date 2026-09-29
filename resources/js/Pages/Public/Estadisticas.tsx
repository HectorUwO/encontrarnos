import { StatisticsPanel } from '@/Components/Encontrarnos/PublicTools';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

export default function Estadisticas() {
    return (
        <PublicLayout>
            <Head title="Estadísticas · Encontrarnos" />
            <StatisticsPanel />
        </PublicLayout>
    );
}
