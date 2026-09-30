import { MEXICO_MAP_STATES, MEXICO_MAP_VIEWBOX } from '@/data/mexico-map';
import { StatisticsEntity } from '@/types';
import {
    CSSProperties,
    KeyboardEvent,
    MouseEvent,
    useMemo,
    useRef,
    useState,
} from 'react';
import {
    classIndex,
    classNames,
    formatNumber,
    formatPercent,
    formatRate,
    quantileBreaks,
} from './format';

export type MapMetric = 'total' | 'per_100k';

/** De menos a más: la luminosidad baja de forma pareja para leerse bien también en escala de grises. */
export const MAP_COLORS = [
    '#efe6c4',
    '#e5c97f',
    '#dc9a4e',
    '#c4552f',
    '#7b2417',
];
const EMPTY_COLOR = '#dcd8c8';
const TOOLTIP_HALF_WIDTH = 130;
const TOOLTIP_HEIGHT = 130;

const metricValue = (entity: StatisticsEntity, metric: MapMetric) =>
    metric === 'total' ? entity.total : entity.per_100k;

const formatMetric = (value: number, metric: MapMetric) =>
    metric === 'total' ? formatNumber(value) : formatRate(value);

/** Rangos de la leyenda a partir de los cortes por cuantiles. */
export function legendRanges(entities: StatisticsEntity[], metric: MapMetric) {
    const values = entities
        .map((entity) => metricValue(entity, metric))
        .filter((value) => value > 0);
    if (values.length === 0) return [];

    const breaks = quantileBreaks(values);
    const limits = [Math.min(...values), ...breaks, Math.max(...values)];

    return MAP_COLORS.map((color, index) => ({
        color,
        from: limits[index],
        to: limits[index + 1],
    }));
}

/** Mapa coroplético: cada entidad es un enlace a su página. */
export default function MexicoMap({
    entities,
    metric,
    hovered,
    onSelect,
    onHover,
}: {
    entities: StatisticsEntity[];
    metric: MapMetric;
    hovered: string | null;
    onSelect: (state: string) => void;
    onHover: (state: string | null) => void;
}) {
    const container = useRef<HTMLDivElement>(null);
    const [pointer, setPointer] = useState<{ x: number; y: number } | null>(
        null,
    );

    const byCode = useMemo(
        () => new Map(entities.map((entity) => [entity.code, entity])),
        [entities],
    );
    const breaks = useMemo(
        () =>
            quantileBreaks(
                entities
                    .map((entity) => metricValue(entity, metric))
                    .filter((value) => value > 0),
            ),
        [entities, metric],
    );
    const ranges = useMemo(
        () => legendRanges(entities, metric),
        [entities, metric],
    );

    const colorOf = (entity?: StatisticsEntity) => {
        const value = entity ? metricValue(entity, metric) : 0;
        return value > 0 ? MAP_COLORS[classIndex(value, breaks)] : EMPTY_COLOR;
    };

    const hoveredEntity = entities.find((entity) => entity.state === hovered);
    const shapeOf = (entity?: StatisticsEntity) =>
        MEXICO_MAP_STATES.find((shape) => shape.code === entity?.code);
    const hoveredShape = shapeOf(hoveredEntity);

    const track = (event: MouseEvent) => {
        const box = container.current?.getBoundingClientRect();
        if (box)
            setPointer({
                x: event.clientX - box.left,
                y: event.clientY - box.top,
            });
    };

    const press = (event: KeyboardEvent, state: string) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onSelect(state);
        }
    };

    // Con el teclado no hay cursor: el aviso se ancla al centro de la entidad.
    const tooltipAt = () => {
        const box = container.current?.getBoundingClientRect();
        if (!box) return { x: 0, y: 0, below: false };

        let anchor = pointer;
        if (!anchor && hoveredShape) {
            const scale = box.width / MEXICO_MAP_VIEWBOX.width;
            anchor = {
                x: hoveredShape.centroid[0] * scale,
                y: hoveredShape.centroid[1] * scale,
            };
        }
        if (!anchor) return { x: 0, y: 0, below: false };

        return {
            x: Math.min(
                Math.max(anchor.x, TOOLTIP_HALF_WIDTH),
                Math.max(TOOLTIP_HALF_WIDTH, box.width - TOOLTIP_HALF_WIDTH),
            ),
            y: anchor.y,
            below: anchor.y < TOOLTIP_HEIGHT,
        };
    };
    const tooltip = hoveredEntity ? tooltipAt() : null;

    return (
        <div
            className="en-stats-map"
            ref={container}
            onMouseMove={track}
            onMouseLeave={() => setPointer(null)}
        >
            <svg
                viewBox={`0 0 ${MEXICO_MAP_VIEWBOX.width} ${MEXICO_MAP_VIEWBOX.height}`}
                role="group"
                aria-label="Mapa de México por entidad federativa"
            >
                {MEXICO_MAP_STATES.map((shape, index) => {
                    const entity = byCode.get(shape.code);
                    if (!entity) return null;

                    return (
                        <path
                            key={shape.code}
                            d={shape.path}
                            fill={colorOf(entity)}
                            fillRule="evenodd"
                            className="en-stats-state en-pop"
                            style={{ '--i': index } as CSSProperties}
                            role="link"
                            tabIndex={0}
                            aria-label={`${entity.label}: ${formatMetric(metricValue(entity, metric), metric)} ${metric === 'total' ? 'registros' : 'por cada 100 mil habitantes'}. Abrir su página.`}
                            onMouseEnter={() => onHover(entity.state)}
                            onMouseLeave={() => onHover(null)}
                            onFocus={() => onHover(entity.state)}
                            onBlur={() => onHover(null)}
                            onClick={() => onSelect(entity.state)}
                            onKeyDown={(event) => press(event, entity.state)}
                        />
                    );
                })}
                {hoveredShape &&
                    [true, false].map((halo) => (
                        <path
                            key={halo ? 'halo' : 'line'}
                            d={hoveredShape.path}
                            fill="none"
                            fillRule="evenodd"
                            className={classNames(
                                'en-stats-outline',
                                halo && 'is-halo',
                            )}
                            aria-hidden="true"
                        />
                    ))}
            </svg>

            {hoveredEntity && tooltip && (
                <div
                    className={classNames(
                        'en-stats-tooltip',
                        tooltip.below && 'is-below',
                    )}
                    role="status"
                    style={{ left: tooltip.x, top: tooltip.y }}
                >
                    <strong>{hoveredEntity.label}</strong>
                    <span>
                        {formatNumber(hoveredEntity.total)} registros ·{' '}
                        {formatRate(hoveredEntity.per_100k)} por 100 mil hab.
                    </span>
                    <small>
                        Lugar {hoveredEntity.rank} por registros ·{' '}
                        {hoveredEntity.rate_rank} por tasa
                    </small>
                    <small>
                        {formatPercent(hoveredEntity.confidential_share)}{' '}
                        confidenciales · clic para abrir su página
                    </small>
                </div>
            )}

            <ul className="en-stats-legend" aria-label="Leyenda del mapa">
                {ranges.map((range) => (
                    <li key={range.color}>
                        <span
                            style={{ background: range.color }}
                            aria-hidden="true"
                        />
                        {formatMetric(range.from, metric)} –{' '}
                        {formatMetric(range.to, metric)}
                    </li>
                ))}
                <li>
                    <span
                        style={{ background: EMPTY_COLOR }}
                        aria-hidden="true"
                    />
                    Sin registros
                </li>
            </ul>
        </div>
    );
}
