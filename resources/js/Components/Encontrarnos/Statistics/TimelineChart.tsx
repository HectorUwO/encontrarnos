import {
    CSSProperties,
    KeyboardEvent,
    MouseEvent,
    useMemo,
    useState,
} from 'react';
import {
    classNames,
    formatMonth,
    formatNumber,
    niceScale,
    useElementWidth,
} from './format';

type Mode = 'year' | 'month';
type Range = 10 | 20 | 'all';

interface Point {
    label: string;
    total: number;
    provisional: boolean;
}

const MARGIN = { top: 14, right: 12, bottom: 30, left: 46 };
const PROVISIONAL_MONTHS = 3;
const RANGES: { value: Range; label: string }[] = [
    { value: 10, label: '10 años' },
    { value: 20, label: '20 años' },
    { value: 'all', label: 'Todo' },
];
const compact = new Intl.NumberFormat('es-MX', { notation: 'compact' });

/** Meses consecutivos desde `start` hasta `end` (ambos «AAAA-MM»). */
function monthsBetween(start: string, end: string) {
    const months: string[] = [];
    let [year, month] = start.split('-').map(Number);
    const [lastYear, lastMonth] = end.split('-').map(Number);

    while (year < lastYear || (year === lastYear && month <= lastMonth)) {
        months.push(`${year}-${String(month).padStart(2, '0')}`);
        month += 1;
        if (month > 12) {
            month = 1;
            year += 1;
        }
    }

    return months;
}

const yearOf = (label: string) => Number(label.slice(0, 4));

const linePath = (
    points: Point[],
    offset: number,
    xOf: (index: number) => number,
    yOf: (value: number) => number,
) =>
    points
        .map(
            (point, index) =>
                `${index === 0 ? 'M' : 'L'}${xOf(offset + index).toFixed(1)} ${yOf(point.total).toFixed(1)}`,
        )
        .join('');

export default function TimelineChart({
    timeline,
    latestMonth,
    peakYear,
    scope,
    from,
    to,
    onSelectYear,
}: {
    timeline: { month: string; total: number }[];
    latestMonth: string | null;
    peakYear: number | null;
    scope: string;
    /** Periodo elegido en los filtros; se resalta sobre la historia completa. */
    from: number | null;
    to: number | null;
    onSelectYear: (year: number) => void;
}) {
    const [mode, setMode] = useState<Mode>('year');
    const [range, setRange] = useState<Range>(20);
    const [hover, setHover] = useState<number | null>(null);
    const [wrapper, width] = useElementWidth<HTMLDivElement>();

    const { points, hidden, startYear } = useMemo(() => {
        const empty = { points: [] as Point[], hidden: 0, startYear: 0 };
        if (timeline.length === 0 || !latestMonth) return empty;

        const lastYear = yearOf(latestMonth);
        const earliest = timeline[0].month;
        const cutoff = `${lastYear - (range === 'all' ? 1000 : range) + 1}-01`;
        const start = cutoff > earliest ? cutoff : earliest;
        const visible = timeline.filter((point) => point.month >= start);
        const hidden = timeline.reduce(
            (sum, point) => (point.month < start ? sum + point.total : sum),
            0,
        );

        if (mode === 'year') {
            const totals = new Map<number, number>();
            visible.forEach(({ month, total }) =>
                totals.set(
                    yearOf(month),
                    (totals.get(yearOf(month)) ?? 0) + total,
                ),
            );
            const first = yearOf(start);
            const partial = latestMonth.slice(5) !== '12';

            return {
                hidden,
                startYear: first,
                points: Array.from(
                    { length: lastYear - first + 1 },
                    (_, index): Point => ({
                        label: String(first + index),
                        total: totals.get(first + index) ?? 0,
                        provisional: partial && first + index === lastYear,
                    }),
                ),
            };
        }

        const totals = new Map(
            visible.map(({ month, total }) => [month, total]),
        );
        const months = monthsBetween(start, latestMonth);

        return {
            hidden,
            startYear: yearOf(start),
            points: months.map((month, index): Point => ({
                label: month,
                total: totals.get(month) ?? 0,
                provisional: index >= months.length - PROVISIONAL_MONTHS,
            })),
        };
    }, [timeline, latestMonth, mode, range]);

    if (points.length === 0) {
        return (
            <p className="en-stats-empty">
                No hay registros con fecha para esta selección.
            </p>
        );
    }

    const hasPeriod = from !== null || to !== null;
    const inPeriod = (year: number) =>
        (from === null || year >= from) && (to === null || year <= to);

    const height = width < 520 ? 240 : 300;
    const innerWidth = Math.max(0, width - MARGIN.left - MARGIN.right);
    const innerHeight = height - MARGIN.top - MARGIN.bottom;
    const scale = niceScale(Math.max(...points.map((point) => point.total)));
    const band = innerWidth / points.length;
    const xOf = (index: number) => MARGIN.left + index * band + band / 2;
    const yOf = (value: number) =>
        MARGIN.top + innerHeight - (value / scale.max) * innerHeight;

    const titleOf = (point: Point) =>
        mode === 'year' ? point.label : formatMonth(point.label);

    // Etiquetas del eje: cada cierto número de años, o cada enero.
    const labelEvery = Math.max(
        1,
        Math.ceil(points.length / Math.max(2, Math.floor(innerWidth / 52))),
    );
    const showsLabel = (point: Point, index: number) =>
        mode === 'year'
            ? index % labelEvery === 0
            : point.label.endsWith('-01') &&
              yearOf(point.label) % Math.max(1, Math.ceil(labelEvery / 12)) ===
                  0;

    const indexAt = (event: MouseEvent<SVGSVGElement>) => {
        const box = event.currentTarget.getBoundingClientRect();
        const index = Math.floor(
            (event.clientX - box.left - MARGIN.left) / band,
        );
        return index >= 0 && index < points.length ? index : null;
    };

    const choose = (index: number | null) => {
        if (mode === 'year' && index !== null)
            onSelectYear(Number(points[index].label));
    };

    const pressBar = (event: KeyboardEvent, index: number) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            choose(index);
        }
    };

    const active = hover === null ? null : points[hover];
    const solidEnd = points.findIndex((point) => point.provisional);
    const dashedStart = Math.max(0, solidEnd - 1);
    const outline = linePath(points, 0, xOf, yOf);
    const areaPath = `${outline}L${xOf(points.length - 1).toFixed(1)} ${yOf(0)}L${xOf(0).toFixed(1)} ${yOf(0)}Z`;
    const solidLine =
        solidEnd === -1
            ? outline
            : linePath(points.slice(0, solidEnd + 1), 0, xOf, yOf);
    const dashedLine =
        solidEnd === -1
            ? ''
            : linePath(points.slice(dashedStart), dashedStart, xOf, yOf);

    // Franja del periodo elegido (solo en la vista mensual).
    const bandStart = points.findIndex((point) =>
        inPeriod(yearOf(point.label)),
    );
    const bandEnd = points.reduce(
        (last, point, index) => (inPeriod(yearOf(point.label)) ? index : last),
        -1,
    );

    const reset = () => setHover(null);

    return (
        <div className="en-stats-timeline">
            <div className="en-stats-controls">
                <div
                    className="en-stats-segmented"
                    role="group"
                    aria-label="Agrupar el histórico"
                >
                    {(
                        [
                            ['year', 'Por año'],
                            ['month', 'Por mes'],
                        ] as [Mode, string][]
                    ).map(([value, label]) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={mode === value}
                            onClick={() => {
                                setMode(value);
                                reset();
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>
                <div
                    className="en-stats-segmented"
                    role="group"
                    aria-label="Periodo que se muestra"
                >
                    {RANGES.map(({ value, label }) => (
                        <button
                            key={value}
                            type="button"
                            aria-pressed={range === value}
                            onClick={() => {
                                setRange(value);
                                reset();
                            }}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="en-stats-chart" ref={wrapper}>
                {width > 0 && (
                    <svg
                        width={width}
                        height={height}
                        role="group"
                        aria-label={`Histórico de registros de ${scope} ${mode === 'year' ? 'por año' : 'por mes'}${peakYear ? `. El año con más registros es ${peakYear}` : ''}.`}
                        className={classNames(
                            mode === 'year' && 'is-clickable',
                        )}
                        onMouseMove={(event) => setHover(indexAt(event))}
                        onMouseLeave={reset}
                        onClick={(event) => choose(indexAt(event))}
                    >
                        <defs>
                            <pattern
                                id="en-stats-hatch"
                                width="6"
                                height="6"
                                patternUnits="userSpaceOnUse"
                                patternTransform="rotate(45)"
                            >
                                <rect width="6" height="6" fill="#4c8580" />
                                <rect width="2.5" height="6" fill="#a9c9c2" />
                            </pattern>
                            <linearGradient
                                id="en-stats-area"
                                x1="0"
                                x2="0"
                                y1="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="#1c5d6b"
                                    stopOpacity="0.5"
                                />
                                <stop
                                    offset="100%"
                                    stopColor="#1c5d6b"
                                    stopOpacity="0.03"
                                />
                            </linearGradient>
                        </defs>

                        {scale.ticks.map((tick) => (
                            <g key={tick}>
                                <line
                                    className="en-stats-gridline"
                                    x1={MARGIN.left}
                                    x2={width - MARGIN.right}
                                    y1={yOf(tick)}
                                    y2={yOf(tick)}
                                />
                                <text
                                    className="en-stats-axis"
                                    x={MARGIN.left - 8}
                                    y={yOf(tick) + 4}
                                    textAnchor="end"
                                >
                                    {tick === 0 ? '0' : compact.format(tick)}
                                </text>
                            </g>
                        ))}

                        {mode === 'year' ? (
                            points.map((point, index) => {
                                const barWidth = Math.max(1.5, band * 0.68);

                                return (
                                    <rect
                                        key={point.label}
                                        x={xOf(index) - barWidth / 2}
                                        y={yOf(point.total)}
                                        width={barWidth}
                                        height={Math.max(
                                            0,
                                            yOf(0) - yOf(point.total),
                                        )}
                                        rx={1.5}
                                        style={
                                            { '--i': index } as CSSProperties
                                        }
                                        className={classNames(
                                            'en-stats-bar',
                                            'en-rise',
                                            peakYear !== null &&
                                                point.label ===
                                                    String(peakYear) &&
                                                'is-peak',
                                            point.provisional &&
                                                'is-provisional',
                                            hasPeriod &&
                                                !inPeriod(
                                                    yearOf(point.label),
                                                ) &&
                                                'is-muted',
                                            hover === index && 'is-active',
                                        )}
                                        role="button"
                                        tabIndex={0}
                                        aria-label={`${point.label}: ${formatNumber(point.total)} registros${point.provisional ? ' (año en curso)' : ''}. Elegir este año.`}
                                        onFocus={() => setHover(index)}
                                        onBlur={reset}
                                        onKeyDown={(event) =>
                                            pressBar(event, index)
                                        }
                                    />
                                );
                            })
                        ) : (
                            <>
                                {hasPeriod && bandStart !== -1 && (
                                    <rect
                                        className="en-stats-period"
                                        x={MARGIN.left + bandStart * band}
                                        y={MARGIN.top}
                                        width={(bandEnd - bandStart + 1) * band}
                                        height={innerHeight}
                                    />
                                )}
                                <g className="en-wipe">
                                    <path
                                        d={areaPath}
                                        fill="url(#en-stats-area)"
                                    />
                                    <path
                                        d={solidLine}
                                        className="en-stats-line"
                                    />
                                    {dashedLine && (
                                        <path
                                            d={dashedLine}
                                            className="en-stats-line is-provisional"
                                        />
                                    )}
                                </g>
                            </>
                        )}

                        {points.map((point, index) =>
                            showsLabel(point, index) ? (
                                <text
                                    key={point.label}
                                    className="en-stats-axis"
                                    x={xOf(index)}
                                    y={height - 10}
                                    textAnchor="middle"
                                >
                                    {mode === 'year'
                                        ? point.label
                                        : point.label.slice(0, 4)}
                                </text>
                            ) : null,
                        )}

                        {active && hover !== null && (
                            <g>
                                <line
                                    className="en-stats-cursor"
                                    x1={xOf(hover)}
                                    x2={xOf(hover)}
                                    y1={MARGIN.top}
                                    y2={yOf(0)}
                                />
                                {mode === 'month' && (
                                    <circle
                                        className="en-stats-dot"
                                        cx={xOf(hover)}
                                        cy={yOf(active.total)}
                                        r={4}
                                    />
                                )}
                            </g>
                        )}
                    </svg>
                )}

                {active && hover !== null && (
                    <div
                        className="en-stats-tooltip is-chart"
                        role="status"
                        style={{
                            left: Math.min(
                                Math.max(xOf(hover), 90),
                                Math.max(90, width - 90),
                            ),
                            top: Math.max(0, yOf(active.total) - 8),
                        }}
                    >
                        <strong>{titleOf(active)}</strong>
                        <span>{formatNumber(active.total)} registros</span>
                        {active.provisional && (
                            <small>
                                {mode === 'year'
                                    ? 'Año en curso'
                                    : 'Mes reciente: aún se está registrando'}
                            </small>
                        )}
                        {mode === 'year' && (
                            <small>Clic para ver solo este año</small>
                        )}
                    </div>
                )}
            </div>

            <ul className="en-stats-legend-note">
                <li>
                    <span className="is-solid" aria-hidden="true" />
                    Registros con fecha de los hechos
                </li>
                <li>
                    <span className="is-provisional" aria-hidden="true" />
                    {mode === 'year'
                        ? 'Año en curso'
                        : 'Últimos meses: todavía se están registrando'}
                </li>
                {mode === 'year' && hasPeriod && (
                    <li>
                        <span className="is-muted" aria-hidden="true" />
                        Fuera del periodo elegido
                    </li>
                )}
            </ul>

            {hidden > 0 && (
                <p className="en-stats-note">
                    Hay {formatNumber(hidden)} registros con fecha anterior a{' '}
                    {startYear} que no se dibujan aquí. Elige «Todo» para
                    verlos.
                </p>
            )}
        </div>
    );
}
