import { Option, StatisticsProps } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Reveal } from '../motion';
import BarList, { BarItem } from './BarList';
import CompareBars from './CompareBars';
import { ActiveFilters, FilterBar, periodLabel } from './Filters';
import MethodNotes from './MethodNotes';
import MunicipalityTable from './MunicipalityTable';
import ProfileSection from './ProfileSection';
import StateNavigator from './StateNavigator';
import StateOutline, { LocationMap } from './StateOutline';
import TimelineChart from './TimelineChart';
import {
    classNames,
    formatDate,
    formatNumber,
    formatPercent,
    formatRate,
    statesByName,
    yearOptions,
} from './format';
import './statistics.css';
import { useStatisticsNavigation } from './useStatisticsNavigation';

const TOP_MUNICIPALITIES = 10;
const CONCENTRATION_SIZE = 3;

/** Cuánto se aparta una tasa del promedio del país, dicho con palabras. */
function versusNational(rate: number, national: number) {
    if (rate <= 0 || national <= 0) return null;

    const ratio = rate / national;

    if (ratio >= 1.15) return `${formatRate(ratio)} veces el promedio del país`;
    if (ratio <= 0.85) {
        return `${formatPercent(ratio * 100)} del promedio del país`;
    }

    return 'cerca del promedio del país';
}

/** Página de un estado: sus cifras, su histórico, sus municipios y cómo se compara con el país. */
export default function StateDashboard({
    statistics,
    states,
}: {
    statistics: StatisticsProps;
    states: Option[];
}) {
    const filters = statistics.has_data
        ? statistics.filters
        : { state: null, from: null, to: null };
    const { loading, urlFor, setPeriod, selectYear, goTo } =
        useStatisticsNavigation(filters);

    const entity = statistics.has_data
        ? statistics.entities.find((item) => item.state === filters.state)
        : undefined;

    if (!statistics.has_data || !entity) {
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
        entities,
        timeline,
        profile,
        municipalities,
        unknown_municipality: unknownMunicipality,
    } = statistics;
    const { national } = summary;
    const hasPeriod = filters.from !== null || filters.to !== null;
    const period = periodLabel(filters.from, filters.to);
    const versus = versusNational(summary.per_100k, national.per_100k);
    const timelineTotal = timeline.reduce((sum, point) => sum + point.total, 0);

    const sorted = statesByName(states);
    const position = sorted.findIndex((item) => item.value === entity.state);
    const previous = sorted[(position + sorted.length - 1) % sorted.length];
    const next = sorted[(position + 1) % sorted.length];

    const municipalityItems: BarItem[] = municipalities
        .slice(0, TOP_MUNICIPALITIES)
        .map((municipality) => ({
            key: municipality.name,
            label: municipality.name,
            value: municipality.total,
            detail: formatPercent(municipality.share),
        }));

    const populationShare = (summary.population / national.population) * 100;

    // Cuánto del estado cabe en sus municipios con más registros.
    const leading = municipalities.slice(0, CONCENTRATION_SIZE);
    const concentration =
        municipalities.length > CONCENTRATION_SIZE
            ? {
                  share: leading.reduce((sum, item) => sum + item.share, 0),
                  names: new Intl.ListFormat('es', {
                      style: 'long',
                      type: 'conjunction',
                  }).format(leading.map((item) => item.name)),
              }
            : null;

    return (
        <section
            className="en-stats en-state"
            id="estadisticas"
            aria-labelledby="statistics-title"
        >
            <div
                className={classNames(
                    'en-stats-inner',
                    loading && 'is-loading',
                )}
            >
                <header className="en-state-hero">
                    <div className="en-state-hero-text">
                        <div className="en-state-topline">
                            <nav aria-label="Ruta" className="en-state-crumbs">
                                <Link href={urlFor(null)}>Estadísticas</Link>
                                <span aria-hidden="true">/</span>
                                <span aria-current="page">{summary.scope}</span>
                            </nav>
                            <div className="en-state-pager">
                                <Link
                                    href={urlFor(previous.value)}
                                    aria-label={`Estado anterior: ${previous.label}`}
                                >
                                    <ArrowLeft size={16} aria-hidden="true" />
                                    {previous.label}
                                </Link>
                                <Link
                                    href={urlFor(next.value)}
                                    aria-label={`Estado siguiente: ${next.label}`}
                                >
                                    {next.label}
                                    <ArrowRight size={16} aria-hidden="true" />
                                </Link>
                            </div>
                        </div>

                        <h2 id="statistics-title">{summary.scope}</h2>
                        <p className="en-state-lede">
                            {hasPeriod ? (
                                <>
                                    En {period}, {summary.scope} suma{' '}
                                    <strong>
                                        {formatNumber(summary.total)}
                                    </strong>{' '}
                                    registros con fecha de los hechos:{' '}
                                    {formatPercent(summary.share)} de los del
                                    país, el lugar {summary.rank} de 32.
                                </>
                            ) : (
                                <>
                                    {summary.scope} suma{' '}
                                    <strong>
                                        {formatNumber(summary.total)}
                                    </strong>{' '}
                                    registros en el Registro Nacional:{' '}
                                    {formatPercent(summary.share)} de los del
                                    país, el lugar {summary.rank} de 32.
                                </>
                            )}{' '}
                            Por cada 100 mil habitantes son{' '}
                            {formatRate(summary.per_100k)}
                            {versus && (
                                <>
                                    , {versus} ({formatRate(national.per_100k)})
                                </>
                            )}
                            .
                        </p>

                        <FilterBar
                            states={states}
                            state={filters.state}
                            from={filters.from}
                            to={filters.to}
                            years={yearOptions(meta.first_year, meta.last_year)}
                            onState={goTo}
                            onPeriod={setPeriod}
                        />
                    </div>

                    <figure className="en-state-figure">
                        <StateOutline
                            code={entity.code}
                            label={summary.scope}
                        />
                        <LocationMap code={entity.code} label={summary.scope} />
                        <figcaption>
                            Entidad {String(entity.code).padStart(2, '0')} ·
                            clave INEGI
                            {meta.imported_at && (
                                <> · datos al {formatDate(meta.imported_at)}</>
                            )}
                        </figcaption>
                    </figure>
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
                        <small>
                            registros · lugar {summary.rank} de 32 ·{' '}
                            {formatPercent(summary.share)} del país
                        </small>
                    </article>
                    <article>
                        <span>Por cada 100 mil habitantes</span>
                        <strong>{formatRate(summary.per_100k)}</strong>
                        <small>
                            Lugar {summary.rate_rank} de 32 · país:{' '}
                            {formatRate(national.per_100k)}
                        </small>
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
                            País: {formatPercent(national.confidential_share)} ·{' '}
                            {formatNumber(summary.confidential)} sin fecha, sexo
                            ni edad
                        </small>
                    </article>
                </Reveal>

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

                <div className="en-stats-grid is-even">
                    <Reveal as="article" className="en-stats-card">
                        <header className="en-stats-card-head">
                            <div>
                                <h3>Municipios con más registros</h3>
                                <p>
                                    Los{' '}
                                    {Math.min(
                                        TOP_MUNICIPALITIES,
                                        municipalityItems.length,
                                    )}{' '}
                                    primeros de {summary.scope}, tal como los
                                    reporta cada autoridad.
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
                                <h3>Comparado con el país</h3>
                                <p>
                                    {summary.scope} frente a México, con las
                                    mismas reglas de conteo.
                                </p>
                            </div>
                        </header>
                        <CompareBars
                            comparisons={[
                                {
                                    title: 'Registros por cada 100 mil habitantes',
                                    format: formatRate,
                                    bars: [
                                        {
                                            label: summary.scope,
                                            value: summary.per_100k,
                                            highlight: true,
                                        },
                                        {
                                            label: 'México',
                                            value: national.per_100k,
                                        },
                                    ],
                                    note: versus
                                        ? `${summary.scope}: ${versus}.`
                                        : undefined,
                                },
                                {
                                    title: 'Población y registros',
                                    format: formatPercent,
                                    bars: [
                                        {
                                            label: 'De la población',
                                            value: populationShare,
                                        },
                                        {
                                            label: 'De los registros',
                                            value: summary.share,
                                            highlight: true,
                                        },
                                    ],
                                    note: `${summary.scope} tiene ${formatPercent(populationShare)} de la población del país y ${formatPercent(summary.share)} de sus registros${hasPeriod ? ' en el periodo' : ''}.`,
                                },
                                {
                                    title: 'Registros confidenciales',
                                    format: formatPercent,
                                    bars: [
                                        {
                                            label: summary.scope,
                                            value: summary.confidential_share,
                                            highlight: true,
                                        },
                                        {
                                            label: 'México',
                                            value: national.confidential_share,
                                        },
                                    ],
                                    note: 'Se cuentan todos los periodos. Donde pesan más faltan la fecha, el sexo y la edad de muchos registros.',
                                },
                            ]}
                        />
                        {concentration && (
                            <section className="en-stats-fact">
                                <strong>
                                    {formatPercent(concentration.share)}
                                </strong>
                                <p>
                                    de los registros de {summary.scope} están en
                                    sus {CONCENTRATION_SIZE} municipios con más
                                    registros: {concentration.names}.
                                </p>
                            </section>
                        )}
                    </Reveal>
                </div>

                <Reveal as="article" className="en-stats-card en-stats-wide">
                    <header className="en-stats-card-head">
                        <div>
                            <h3>Todos los municipios de {summary.scope}</h3>
                            <p>
                                Busca un municipio u ordena por cualquier
                                columna.
                                {hasPeriod
                                    ? ' Con un periodo elegido solo cuentan los registros con fecha de los hechos.'
                                    : ''}
                            </p>
                        </div>
                    </header>
                    <MunicipalityTable
                        scope={summary.scope}
                        municipalities={municipalities}
                        showConfidential={!hasPeriod}
                        unknown={unknownMunicipality}
                    />
                </Reveal>

                <ProfileSection
                    scope={summary.scope}
                    profile={profile}
                    hasPeriod={hasPeriod}
                />

                <Reveal as="article" className="en-stats-card en-stats-wide">
                    <header className="en-stats-card-head">
                        <div>
                            <h3>Otros estados</h3>
                            <p>
                                Abre la página de cualquier entidad; se conserva
                                el periodo que elegiste. El número es el total
                                de registros{hasPeriod ? ' del periodo' : ''}.
                            </p>
                        </div>
                    </header>
                    <StateNavigator
                        states={states}
                        entities={entities}
                        current={entity.state}
                        hrefFor={(state) => urlFor(state)}
                    />
                </Reveal>

                <MethodNotes totals={totals} comparison="entre entidades" />
            </div>
        </section>
    );
}
