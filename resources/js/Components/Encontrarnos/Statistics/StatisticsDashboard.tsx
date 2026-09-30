import { Option, StatisticsProps } from '@/types';
import { useMemo, useState } from 'react';
import { Reveal } from '../motion';
import BarList, { BarItem } from './BarList';
import EntityTable from './EntityTable';
import { ActiveFilters, FilterBar } from './Filters';
import MethodNotes from './MethodNotes';
import MexicoMap, { MapMetric } from './MexicoMap';
import ProfileSection from './ProfileSection';
import TimelineChart from './TimelineChart';
import {
    classNames,
    formatDate,
    formatNumber,
    formatPercent,
    formatRate,
    yearOptions,
} from './format';
import './statistics.css';
import { useStatisticsNavigation } from './useStatisticsNavigation';

const RANKING_SIZE = 10;

/** Estadísticas de todo el país; cada entidad abre su propia página. */
export default function StatisticsDashboard({
    statistics,
    states,
}: {
    statistics: StatisticsProps;
    states: Option[];
}) {
    const [metric, setMetric] = useState<MapMetric>('total');
    const [hovered, setHovered] = useState<string | null>(null);
    const [showAll, setShowAll] = useState(false);

    const filters = statistics.has_data
        ? statistics.filters
        : { state: null, from: null, to: null };
    const { loading, urlFor, setPeriod, selectYear, goTo } =
        useStatisticsNavigation(filters);

    const entities = useMemo(
        () => (statistics.has_data ? statistics.entities : []),
        [statistics],
    );
    const ranked = useMemo(
        () =>
            [...entities].sort((a, b) =>
                metric === 'total'
                    ? b.total - a.total
                    : b.per_100k - a.per_100k,
            ),
        [entities, metric],
    );

    if (!statistics.has_data) {
        return (
            <section className="en-stats" aria-labelledby="statistics-title">
                <div className="en-stats-inner">
                    <h2 id="statistics-title">ESTADÍSTICAS</h2>
                    <p className="en-stats-empty">
                        Las estadísticas aún no están disponibles.
                    </p>
                </div>
            </section>
        );
    }

    const {
        meta,
        totals,
        summary,
        unknown_state: unknown,
        timeline,
        profile,
        municipalities,
    } = statistics;
    const hasPeriod = filters.from !== null || filters.to !== null;
    const timelineTotal = timeline.reduce((sum, point) => sum + point.total, 0);
    const openState = (state: string) => urlFor(state);

    const rankingItems: BarItem[] = (
        showAll ? ranked : ranked.slice(0, RANKING_SIZE)
    ).map((entity) => ({
        key: entity.state,
        label: entity.label,
        value: metric === 'total' ? entity.total : entity.per_100k,
        detail:
            metric === 'total'
                ? `${formatRate(entity.per_100k)} por 100 mil`
                : `${formatNumber(entity.total)} registros`,
    }));

    const confidentialItems: BarItem[] = [...entities]
        .filter((entity) => entity.registry_total > 0)
        .sort((a, b) => b.confidential_share - a.confidential_share)
        .slice(0, RANKING_SIZE)
        .map((entity) => ({
            key: entity.state,
            label: entity.label,
            value: entity.confidential_share,
            detail: `${formatNumber(entity.confidential)} de ${formatNumber(entity.registry_total)}`,
        }));

    const municipalityItems: BarItem[] = municipalities.map((municipality) => ({
        key: `${municipality.state}-${municipality.name}`,
        label: municipality.name,
        caption: municipality.state_label ?? undefined,
        value: municipality.total,
        detail: formatPercent(municipality.share),
    }));

    return (
        <section
            className="en-stats"
            id="estadisticas"
            aria-labelledby="statistics-title"
        >
            <div
                className={classNames(
                    'en-stats-inner',
                    loading && 'is-loading',
                )}
            >
                <header className="en-stats-header">
                    <div>
                        <div className="en-section-kicker">
                            <span>02</span> / ESTADÍSTICAS
                        </div>
                        <h2 id="statistics-title">
                            MIRAR LOS DATOS.
                            <br />
                            <em>SEGUIR BUSCANDO.</em>
                        </h2>
                        <p>
                            Cifras del Registro Nacional de Personas
                            Desaparecidas y No Localizadas que reúne esta
                            plataforma: {formatNumber(totals.registry)}{' '}
                            registros
                            {meta.imported_at && (
                                <>
                                    , actualizados al{' '}
                                    {formatDate(meta.imported_at)}
                                </>
                            )}
                            . Explora el mapa y abre la página de cada estado
                            para ver su histórico, sus municipios y su perfil.
                        </p>
                    </div>

                    <FilterBar
                        states={states}
                        state={null}
                        from={filters.from}
                        to={filters.to}
                        years={yearOptions(meta.first_year, meta.last_year)}
                        onState={goTo}
                        onPeriod={setPeriod}
                    />
                </header>

                <ActiveFilters
                    from={filters.from}
                    to={filters.to}
                    onClear={() => setPeriod({ from: null, to: null })}
                />

                <Reveal stagger className="en-stats-kpis" aria-live="polite">
                    <article>
                        <span>
                            {summary.scope}
                            {hasPeriod ? ' · en el periodo' : ''}
                        </span>
                        <strong>{formatNumber(summary.total)}</strong>
                        <small>registros</small>
                    </article>
                    <article>
                        <span>Por cada 100 mil habitantes</span>
                        <strong>{formatRate(summary.per_100k)}</strong>
                        <small>Población: Censo INEGI 2020</small>
                    </article>
                    <article>
                        <span>Año con más registros</span>
                        <strong>{summary.peak_year?.year ?? '—'}</strong>
                        <small>
                            {summary.peak_year
                                ? `${formatNumber(summary.peak_year.total)} con fecha conocida`
                                : 'Sin fechas conocidas'}
                        </small>
                    </article>
                    <article>
                        <span>Registros confidenciales</span>
                        <strong>
                            {formatPercent(summary.confidential_share)}
                        </strong>
                        <small>
                            {formatNumber(summary.confidential)} sin fecha, sexo
                            ni edad
                        </small>
                    </article>
                </Reveal>

                <div className="en-stats-grid">
                    <Reveal
                        as="article"
                        className="en-stats-card en-stats-map-card"
                    >
                        <header className="en-stats-card-head">
                            <div>
                                <h3>Mapa por entidad</h3>
                                <p>
                                    Toca una entidad para abrir su página con el
                                    detalle.
                                </p>
                            </div>
                            <div
                                className="en-stats-segmented"
                                role="group"
                                aria-label="Qué mostrar en el mapa"
                            >
                                <button
                                    type="button"
                                    aria-pressed={metric === 'total'}
                                    onClick={() => setMetric('total')}
                                >
                                    Registros
                                </button>
                                <button
                                    type="button"
                                    aria-pressed={metric === 'per_100k'}
                                    onClick={() => setMetric('per_100k')}
                                >
                                    Por 100 mil hab.
                                </button>
                            </div>
                        </header>
                        <MexicoMap
                            entities={entities}
                            metric={metric}
                            hovered={hovered}
                            onSelect={goTo}
                            onHover={setHovered}
                        />
                        <p className="en-stats-note">
                            {hasPeriod
                                ? 'Solo cuenta registros con fecha de los hechos dentro del periodo elegido.'
                                : 'Cuenta todos los registros de cada entidad, también los confidenciales'}
                            {!hasPeriod &&
                                unknown > 0 &&
                                `; ${formatNumber(unknown)} no indican entidad`}
                            {!hasPeriod && '.'}
                        </p>
                    </Reveal>

                    <Reveal as="article" className="en-stats-card">
                        <header className="en-stats-card-head">
                            <div>
                                <h3>Entidades con más registros</h3>
                                <p>
                                    {metric === 'total'
                                        ? 'Ordenadas por número de registros.'
                                        : 'Ordenadas por registros por cada 100 mil habitantes.'}
                                </p>
                            </div>
                        </header>
                        <BarList
                            items={rankingItems}
                            ordered
                            formatValue={
                                metric === 'total' ? formatNumber : formatRate
                            }
                            activeKey={hovered}
                            hrefFor={openState}
                            onHover={setHovered}
                        />
                        <button
                            type="button"
                            className="en-stats-more"
                            onClick={() => setShowAll(!showAll)}
                        >
                            {showAll
                                ? 'Ver solo las 10 primeras'
                                : 'Ver las 32 entidades'}
                        </button>
                    </Reveal>
                </div>

                <Reveal as="article" className="en-stats-card en-stats-wide">
                    <header className="en-stats-card-head">
                        <div>
                            <h3>Histórico · {summary.scope}</h3>
                            <p>
                                Registros según la fecha de los hechos. Incluye{' '}
                                {formatNumber(timelineTotal)} de{' '}
                                {formatNumber(summary.registry_total)}{' '}
                                registros: los confidenciales no informan fecha
                                y los meses recientes se siguen registrando, por
                                eso los datos más nuevos pueden ser menores.
                            </p>
                        </div>
                    </header>
                    <TimelineChart
                        timeline={timeline}
                        latestMonth={meta.latest_month}
                        peakYear={summary.peak_year?.year ?? null}
                        scope={summary.scope}
                        from={filters.from}
                        to={filters.to}
                        onSelectYear={selectYear}
                    />
                </Reveal>

                <ProfileSection
                    scope={summary.scope}
                    profile={profile}
                    hasPeriod={hasPeriod}
                />

                <div className="en-stats-grid is-even">
                    <Reveal as="article" className="en-stats-card">
                        <header className="en-stats-card-head">
                            <div>
                                <h3>Municipios con más registros</h3>
                                <p>
                                    En todo el país, tal como los reporta cada
                                    autoridad. Cada estado lista los suyos en su
                                    página.
                                </p>
                            </div>
                        </header>
                        {municipalityItems.length > 0 ? (
                            <BarList items={municipalityItems} ordered />
                        ) : (
                            <p className="en-stats-empty">
                                No hay municipios con registros para esta
                                selección.
                            </p>
                        )}
                    </Reveal>

                    <Reveal as="article" className="en-stats-card">
                        <header className="en-stats-card-head">
                            <div>
                                <h3>Dónde pesan más los confidenciales</h3>
                                <p>
                                    Porcentaje de registros confidenciales de
                                    cada entidad. Donde es alto faltan fecha,
                                    sexo y edad de muchos registros, y
                                    compararla con otras entidades es menos
                                    fiable.
                                </p>
                            </div>
                        </header>
                        <BarList
                            items={confidentialItems}
                            ordered
                            formatValue={formatPercent}
                            activeKey={hovered}
                            hrefFor={openState}
                            onHover={setHovered}
                        />
                    </Reveal>
                </div>

                <Reveal as="article" className="en-stats-card en-stats-wide">
                    <header className="en-stats-card-head">
                        <div>
                            <h3>Comparativo por entidad</h3>
                            <p>
                                Ordena por cualquier columna y abre la página de
                                una entidad desde su nombre.
                            </p>
                        </div>
                    </header>
                    <EntityTable entities={entities} hrefFor={openState} />
                </Reveal>

                <MethodNotes totals={totals} comparison="entre entidades" />
            </div>
        </section>
    );
}
