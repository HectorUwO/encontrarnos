import { StatisticsEntity } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowDown, ArrowUp } from 'lucide-react';
import { useMemo, useState } from 'react';
import { formatNumber, formatPercent, formatRate } from './format';

type SortKey = 'label' | 'total' | 'per_100k' | 'confidential_share';
type Direction = 'asc' | 'desc';

const columns: { key: SortKey; label: string; numeric: boolean }[] = [
    { key: 'label', label: 'Entidad', numeric: false },
    { key: 'total', label: 'Registros', numeric: true },
    { key: 'per_100k', label: 'Por 100 mil hab.', numeric: true },
    { key: 'confidential_share', label: 'Confidenciales', numeric: true },
];

/** Comparativo de las 32 entidades, ordenable por cualquier columna; cada nombre abre su página. */
export default function EntityTable({
    entities,
    hrefFor,
}: {
    entities: StatisticsEntity[];
    hrefFor: (state: string) => string;
}) {
    const [sort, setSort] = useState<{ key: SortKey; direction: Direction }>({
        key: 'total',
        direction: 'desc',
    });

    const rows = useMemo(() => {
        const factor = sort.direction === 'asc' ? 1 : -1;
        return [...entities].sort((a, b) =>
            sort.key === 'label'
                ? factor * a.label.localeCompare(b.label, 'es')
                : factor * (a[sort.key] - b[sort.key]) ||
                  a.label.localeCompare(b.label, 'es'),
        );
    }, [entities, sort]);

    const maximum = Math.max(1, ...entities.map((entity) => entity.total));

    const toggle = (key: SortKey) =>
        setSort((current) => ({
            key,
            direction:
                current.key === key && current.direction === 'desc'
                    ? 'asc'
                    : key === 'label'
                      ? 'asc'
                      : 'desc',
        }));

    return (
        <div className="en-stats-table-wrap">
            <table className="en-stats-table">
                <thead>
                    <tr>
                        {columns.map((column) => (
                            <th
                                key={column.key}
                                scope="col"
                                className={
                                    column.numeric ? 'is-numeric' : undefined
                                }
                                aria-sort={
                                    sort.key === column.key
                                        ? sort.direction === 'asc'
                                            ? 'ascending'
                                            : 'descending'
                                        : 'none'
                                }
                            >
                                <button
                                    type="button"
                                    onClick={() => toggle(column.key)}
                                >
                                    {column.label}
                                    {sort.key === column.key &&
                                        (sort.direction === 'asc' ? (
                                            <ArrowUp
                                                size={14}
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <ArrowDown
                                                size={14}
                                                aria-hidden="true"
                                            />
                                        ))}
                                </button>
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((entity) => (
                        <tr key={entity.state}>
                            <th scope="row">
                                <Link href={hrefFor(entity.state)}>
                                    {entity.label}
                                </Link>
                            </th>
                            <td className="is-numeric">
                                <span
                                    className="en-stats-cell-bar"
                                    aria-hidden="true"
                                >
                                    <span
                                        style={{
                                            width: `${(entity.total / maximum) * 100}%`,
                                        }}
                                    />
                                </span>
                                <span className="en-stats-cell-number">
                                    {formatNumber(entity.total)}
                                </span>
                            </td>
                            <td className="is-numeric">
                                {formatRate(entity.per_100k)}
                            </td>
                            <td className="is-numeric">
                                {formatPercent(entity.confidential_share)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
