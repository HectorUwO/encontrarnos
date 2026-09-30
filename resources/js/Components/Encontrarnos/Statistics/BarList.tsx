import { Link } from '@inertiajs/react';
import { CSSProperties } from 'react';
import { formatNumber } from './format';

export interface BarItem {
    key: string;
    label: string;
    value: number;
    /** Texto secundario a la derecha del valor, por ejemplo un porcentaje. */
    detail?: string;
    /** Texto pequeño debajo de la etiqueta. */
    caption?: string;
}

/** Lista de barras horizontales; si recibe `hrefFor`, cada fila es un enlace. */
export default function BarList({
    items,
    formatValue = formatNumber,
    ordered = false,
    activeKey = null,
    hrefFor,
    onHover,
}: {
    items: BarItem[];
    formatValue?: (value: number) => string;
    ordered?: boolean;
    activeKey?: string | null;
    hrefFor?: (key: string) => string;
    onHover?: (key: string | null) => void;
}) {
    const maximum = Math.max(1, ...items.map((item) => item.value));

    // Todas las filas reservan el mismo ancho para el valor: así las barras
    // empiezan y terminan en el mismo sitio aunque los números sean distintos.
    const valueWidth = Math.max(
        1,
        ...items.map((item) =>
            Math.max(
                formatValue(item.value).length,
                Math.ceil((item.detail?.length ?? 0) * 0.8),
            ),
        ),
    );

    return (
        <ol
            className="en-stats-bars"
            style={{ '--bar-value-width': `${valueWidth}ch` } as CSSProperties}
        >
            {items.map((item, index) => {
                const content = (
                    <>
                        {ordered && (
                            <span className="en-stats-rank">{index + 1}</span>
                        )}
                        <span className="en-stats-bar-label">
                            {item.label}
                            {item.caption && <small>{item.caption}</small>}
                        </span>
                        <span className="en-stats-bar-value">
                            <strong>{formatValue(item.value)}</strong>
                            {item.detail && <small>{item.detail}</small>}
                        </span>
                        <span className="en-stats-bar-track" aria-hidden="true">
                            <span
                                className="en-grow"
                                style={
                                    {
                                        width: `${(item.value / maximum) * 100}%`,
                                        '--i': index,
                                    } as CSSProperties
                                }
                            />
                        </span>
                    </>
                );

                return (
                    <li
                        key={item.key}
                        className={
                            activeKey === item.key ? 'is-active' : undefined
                        }
                    >
                        {hrefFor ? (
                            <Link
                                href={hrefFor(item.key)}
                                className="en-stats-bar-row"
                                onMouseEnter={() => onHover?.(item.key)}
                                onMouseLeave={() => onHover?.(null)}
                                onFocus={() => onHover?.(item.key)}
                                onBlur={() => onHover?.(null)}
                            >
                                {content}
                            </Link>
                        ) : (
                            <div className="en-stats-bar-row">{content}</div>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
