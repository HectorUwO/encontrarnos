import { Option } from '@/types';
import { X } from 'lucide-react';
import { statesByName } from './format';
import { Period } from './useStatisticsNavigation';

export const periodLabel = (from: number | null, to: number | null) => {
    if (from !== null && to !== null) {
        return from === to ? String(from) : `${from} – ${to}`;
    }

    return from !== null ? `Desde ${from}` : `Hasta ${to}`;
};

/** Selectores de entidad y periodo; elegir una entidad abre su página. */
export function FilterBar({
    states,
    state,
    from,
    to,
    years,
    onState,
    onPeriod,
}: Period & {
    states: Option[];
    state: string | null;
    years: number[];
    onState: (state: string | null) => void;
    onPeriod: (changes: Partial<Period>) => void;
}) {
    const year = (value: string) => (value ? Number(value) : null);

    return (
        <form
            className="en-stats-filters"
            onSubmit={(event) => event.preventDefault()}
        >
            <label>
                Entidad
                <select
                    value={state ?? ''}
                    onChange={(event) => onState(event.target.value || null)}
                >
                    <option value="">Todo el país</option>
                    {statesByName(states).map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.label}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Desde
                <select
                    value={from ?? ''}
                    onChange={(event) =>
                        onPeriod({ from: year(event.target.value) })
                    }
                >
                    <option value="">Siempre</option>
                    {years.map((item) => (
                        <option key={item} value={item}>
                            {item}
                        </option>
                    ))}
                </select>
            </label>
            <label>
                Hasta
                <select
                    value={to ?? ''}
                    onChange={(event) =>
                        onPeriod({ to: year(event.target.value) })
                    }
                >
                    <option value="">Hoy</option>
                    {years.map((item) => (
                        <option key={item} value={item}>
                            {item}
                        </option>
                    ))}
                </select>
            </label>
        </form>
    );
}

/** Periodo elegido, con una nota de qué cuenta cuando se filtra por fecha. */
export function ActiveFilters({
    from,
    to,
    onClear,
}: Period & { onClear: () => void }) {
    if (from === null && to === null) return null;

    const label = periodLabel(from, to);

    return (
        <div className="en-stats-active">
            <div
                className="en-stats-chips"
                role="group"
                aria-label="Filtros activos"
            >
                <span>Viendo</span>
                <button
                    type="button"
                    className="en-stats-chip"
                    aria-label={`Quitar el filtro de periodo: ${label}`}
                    onClick={onClear}
                >
                    {label}
                    <X size={14} aria-hidden="true" />
                </button>
            </div>
            <p className="en-stats-callout" role="note">
                Al filtrar por periodo solo se cuentan los registros con fecha
                de los hechos: los confidenciales no la informan y quedan fuera
                de estas cifras.
            </p>
        </div>
    );
}
