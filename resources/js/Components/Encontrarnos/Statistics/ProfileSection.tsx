import { RegistryStatistics, StatisticsGroup } from '@/types';
import { Reveal } from '../motion';
import BarList, { BarItem } from './BarList';
import { formatNumber, formatPercent } from './format';

/** Sexo, edad y estatus de los registros que los informan. */
export default function ProfileSection({
    scope,
    profile,
    hasPeriod,
}: {
    scope: string;
    profile: RegistryStatistics['profile'];
    hasPeriod: boolean;
}) {
    const shareOfKnown = (count: number) =>
        profile.known > 0 ? formatPercent((count / profile.known) * 100) : '—';
    const items = (groups: StatisticsGroup[]): BarItem[] =>
        groups
            .filter((group) => group.count > 0 || group.value !== null)
            .map((group) => ({
                key: group.value ?? 'unknown',
                label: group.label,
                value: group.count,
                detail: shareOfKnown(group.count),
            }));

    return (
        <div className="en-stats-profile">
            <header>
                <h3>Perfil de las personas · {scope}</h3>
                <p>
                    Basado en {formatNumber(profile.known)} registros que
                    informan sexo, edad y estatus
                    {hasPeriod ? ' dentro del periodo' : ''}. Los confidenciales
                    no se incluyen.
                </p>
            </header>
            <div className="en-stats-profile-grid">
                <Reveal as="article" index={0} className="en-stats-card">
                    <h4>Sexo</h4>
                    <BarList items={items(profile.sex)} />
                </Reveal>
                <Reveal as="article" index={1} className="en-stats-card">
                    <h4>Edad al momento de los hechos</h4>
                    <BarList items={items(profile.age)} />
                </Reveal>
                <Reveal as="article" index={2} className="en-stats-card">
                    <h4>Estatus</h4>
                    <BarList items={items(profile.status)} />
                </Reveal>
            </div>
        </div>
    );
}
