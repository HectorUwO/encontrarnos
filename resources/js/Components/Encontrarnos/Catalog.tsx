import { classNames } from '@/classNames';
import { Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    LucideIcon,
    Search,
    UserRound,
    X,
} from 'lucide-react';
import { CSSProperties, ReactNode, useEffect, useRef, useState } from 'react';
import './catalog.css';
import { FadeImage } from './motion';

/**
 * Piezas compartidas por los catálogos (personas desaparecidas y solicitudes):
 * buscador, filtros, chips de filtros activos, tarjeta con foto vertical y
 * paginación.
 */

export function CatalogSearch({
    value,
    onChange,
    placeholder,
    label,
    hint,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
    label: string;
    hint?: string;
}) {
    return (
        <div className="rc-search">
            <label>
                <Search size={22} aria-hidden="true" />
                <input
                    value={value}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder={placeholder}
                    aria-label={label}
                    autoComplete="off"
                    spellCheck={false}
                />
                {value && (
                    <button
                        type="button"
                        onClick={() => onChange('')}
                        aria-label="Borrar búsqueda"
                    >
                        <X size={19} />
                    </button>
                )}
            </label>
            {hint && <small>{hint}</small>}
        </div>
    );
}

export type Chip = { key: string; label: string; onRemove: () => void };

export function FilterChips({
    chips,
    onClear,
}: {
    chips: Chip[];
    onClear: () => void;
}) {
    if (chips.length === 0) return null;

    return (
        <ul className="rc-chips" aria-label="Filtros activos">
            {chips.map((chip) => (
                <li key={chip.key}>
                    <span>{chip.label}</span>
                    <button
                        type="button"
                        onClick={chip.onRemove}
                        aria-label={`Quitar filtro: ${chip.label}`}
                    >
                        <X size={14} />
                    </button>
                </li>
            ))}
            <li className="rc-chips-clear">
                <button type="button" onClick={onClear}>
                    Limpiar todo
                </button>
            </li>
        </ul>
    );
}

export function FilterGroup({
    title,
    icon: Icon,
    badge,
    wide,
    children,
}: {
    title: string;
    icon: LucideIcon;
    badge?: string;
    /** Ocupa todo el ancho del panel de filtros. */
    wide?: boolean;
    children: ReactNode;
}) {
    return (
        <fieldset className={classNames('rc-group', wide && 'rc-group--wide')}>
            <legend>
                <Icon size={16} aria-hidden="true" /> {title}
                {badge && <em>{badge}</em>}
            </legend>
            {children}
        </fieldset>
    );
}

export function Field({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="rc-field">
            <span>{label}</span>
            {children}
        </label>
    );
}

/** Botones excluyentes; volver a pulsar el activo lo quita. */
export function Segmented({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string | null;
    options: { value: string; label: string }[];
    onChange: (value: string | null) => void;
}) {
    return (
        <div className="rc-field">
            <span id={`seg-${label}`}>{label}</span>
            <div
                className="rc-segmented"
                role="radiogroup"
                aria-labelledby={`seg-${label}`}
            >
                {options.map((option) => (
                    <button
                        key={option.value}
                        type="button"
                        role="radio"
                        aria-checked={value === option.value}
                        className={classNames(
                            value === option.value && 'is-on',
                        )}
                        onClick={() =>
                            onChange(
                                value === option.value ? null : option.value,
                            )
                        }
                    >
                        {option.label}
                    </button>
                ))}
            </div>
        </div>
    );
}

export function Toggle({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label className={classNames('rc-toggle', checked && 'is-on')}>
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
            />
            <span aria-hidden="true" />
            {label}
        </label>
    );
}

/**
 * Campo de texto o número que avisa del cambio cuando la persona deja de
 * escribir, para no consultar en cada tecla.
 */
export function DebouncedInput({
    value,
    onCommit,
    delay = 450,
    ...props
}: {
    value: string;
    onCommit: (value: string) => void;
    delay?: number;
} & Omit<React.InputHTMLAttributes<HTMLInputElement>, 'value' | 'onChange'>) {
    const [text, setText] = useState(value);
    const committed = useRef(value);

    // Si el filtro cambia desde fuera (chips, «limpiar»), el campo lo sigue.
    useEffect(() => {
        setText(value);
        committed.current = value;
    }, [value]);

    useEffect(() => {
        if (text === committed.current) return;
        const timer = setTimeout(() => {
            committed.current = text;
            onCommit(text);
        }, delay);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text]);

    return (
        <input
            {...props}
            value={text}
            onChange={(event) => setText(event.target.value)}
        />
    );
}

export function PersonCard({
    href,
    index,
    photo,
    hasPhoto,
    photoAlt,
    chip,
    reference,
    title,
    facts,
    excerpt,
}: {
    href: string;
    index: number;
    photo: string | null;
    hasPhoto: boolean;
    photoAlt: string;
    chip: ReactNode;
    reference: string;
    title: string;
    facts: [string, string][];
    excerpt: string;
}) {
    return (
        <article className="rc-card" style={{ '--i': index } as CSSProperties}>
            <Link
                href={href}
                className={classNames('rc-photo', !hasPhoto && 'is-empty')}
                prefetch
                tabIndex={-1}
                aria-hidden="true"
            >
                {photo ? (
                    <FadeImage
                        src={photo}
                        alt={photoAlt}
                        loading="lazy"
                        decoding="async"
                    />
                ) : (
                    <UserRound size={48} strokeWidth={1.3} />
                )}
            </Link>
            <div className="rc-body">
                <div className="rc-head">
                    {chip}
                    <span>{reference}</span>
                </div>
                <h3>
                    <Link href={href} prefetch>
                        {title}
                    </Link>
                </h3>
                <dl className="rc-facts">
                    {facts.map(([label, value]) => (
                        <div key={label}>
                            <dt>{label}</dt>
                            <dd>{value}</dd>
                        </div>
                    ))}
                </dl>
                <p className="rc-excerpt">{excerpt}</p>
                <Link href={href} className="rc-open" prefetch>
                    Ver ficha <ArrowUpRight size={18} />
                </Link>
            </div>
        </article>
    );
}

export function Pager({
    current,
    last,
    onPage,
}: {
    current: number;
    last: number;
    onPage: (page: number) => void;
}) {
    if (last <= 1) return null;

    return (
        <nav className="rc-pager" aria-label="Paginación de resultados">
            <button
                type="button"
                disabled={current <= 1}
                onClick={() => onPage(current - 1)}
            >
                <ChevronLeft size={19} /> Anterior
            </button>
            <span>
                Página {current.toLocaleString('es-MX')} de{' '}
                {last.toLocaleString('es-MX')}
            </span>
            <button
                type="button"
                disabled={current >= last}
                onClick={() => onPage(current + 1)}
            >
                Siguiente <ChevronRight size={19} />
            </button>
        </nav>
    );
}
