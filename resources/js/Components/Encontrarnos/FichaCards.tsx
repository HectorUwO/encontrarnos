import { classNames } from '@/classNames';
import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import './ficha-cards.css';

/**
 * Piezas visuales para mostrar los datos de una ficha: tarjetas por tema,
 * mosaicos con icono, píldoras Sí/No y línea de tiempo.
 */

export type CardTone = 'ink' | 'teal' | 'red' | 'gold';

export function FichaCard({
    icon: Icon,
    title,
    tone = 'ink',
    badge,
    wide,
    children,
}: {
    icon: LucideIcon;
    title: string;
    tone?: CardTone;
    badge?: string;
    /** Ocupa todo el ancho cuando la tarjeta está dentro de una cuadrícula. */
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <section
            className={classNames(
                'fx-card',
                `fx-card--${tone}`,
                wide && 'fx-span-2',
            )}
        >
            <header>
                <span className="fx-card-icon" aria-hidden="true">
                    <Icon size={18} />
                </span>
                <h2>{title}</h2>
                {badge && <em>{badge}</em>}
            </header>
            {children}
        </section>
    );
}

export type Tile = {
    label: string;
    value: string | null | undefined;
    icon?: LucideIcon;
    /** Muestra el valor como píldora Sí / No. */
    pill?: boolean;
    /** Valor destacado (números, fechas clave). */
    strong?: boolean;
};

export function FichaTiles({
    tiles,
    columns = 2,
}: {
    tiles: Tile[];
    columns?: 1 | 2 | 3;
}) {
    return (
        <dl className={classNames('fx-tiles', `fx-tiles--${columns}`)}>
            {tiles.map(({ label, value, icon: Icon, pill, strong }) => (
                <div
                    key={label}
                    className={classNames('fx-tile', !value && 'is-empty')}
                >
                    {Icon && (
                        <span className="fx-tile-icon" aria-hidden="true">
                            <Icon size={16} />
                        </span>
                    )}
                    <div>
                        <dt>{label}</dt>
                        <dd
                            className={classNames(
                                strong && 'is-strong',
                                pill &&
                                    value &&
                                    `fx-pill fx-pill--${value === 'Sí' ? 'yes' : 'no'}`,
                            )}
                        >
                            {value || 'Sin dato'}
                        </dd>
                    </div>
                </div>
            ))}
        </dl>
    );
}

export type TimelineItem = {
    label: string;
    date: string | null | undefined;
    note?: string;
    tone?: 'red' | 'teal' | 'ink' | 'gold';
};

/** Recorrido del caso: solo se dibujan los momentos de los que hay fecha. */
export function FichaTimeline({ items }: { items: TimelineItem[] }) {
    const known = items.filter((item) => item.date);

    if (known.length === 0) {
        return <p className="fx-muted">El registro no trae fechas.</p>;
    }

    return (
        <ol className="fx-timeline">
            {known.map((item) => (
                <li
                    key={item.label}
                    className={classNames(`fx-timeline--${item.tone ?? 'ink'}`)}
                >
                    <span aria-hidden="true" />
                    <div>
                        <strong>{item.label}</strong>
                        <time>{item.date}</time>
                        {item.note && <small>{item.note}</small>}
                    </div>
                </li>
            ))}
        </ol>
    );
}

/** Lista de entidades (autoridades a las que se canalizó el caso). */
export function FichaList({ items }: { items: string[] }) {
    return (
        <ul className="fx-list">
            {items.map((item) => (
                <li key={item}>{item}</li>
            ))}
        </ul>
    );
}
