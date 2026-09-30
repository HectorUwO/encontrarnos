import { StatisticsMunicipality } from '@/types';
import { ArrowDown, ArrowUp, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { formatNumber, formatPercent, plainText } from './format';

type SortKey = 'name' | 'total' | 'confidential_share';
type Direction = 'asc' | 'desc';

const PAGE_SIZE = 15;

/** Todos los municipios del estado: se busca por nombre y se ordena por columna. */
export default function MunicipalityTable({
    scope,
    municipalities,
    showConfidential,
    unknown,
}: {
    scope: string;
    municipalities: StatisticsMunicipality[];
    /** Con un periodo elegido los confidenciales no cuentan, así que la columna sobra. */
    showConfidential: boolean;
    /** Registros del estado que no indican municipio. */
    unknown: number;
}) {
    const [query, setQuery] = useState('');
    const [visible, setVisible] = useState(PAGE_SIZE);
    const [sort, setSort] = useState<{ key: SortKey; direction: Direction }>({
        key: 'total',
        direction: 'desc',
    });

    const columns: { key: SortKey; label: string; numeric: boolean }[] = [
        { key: 'name', label: 'Municipio', numeric: false },
        { key: 'total', label: 'Registros', numeric: true },
        ...(showConfidential
            ? [
                  {
                      key: 'confidential_share' as const,
                      label: 'Confidenciales',
                      numeric: true,
                  },
              ]
            : []),
    ];

    const rows = useMemo(() => {
        const needle = plainText(query.trim());
        const matching = needle
            ? municipalities.filter((municipality) =>
                  plainText(municipality.name).includes(needle),
              )
            : municipalities;
        const factor = sort.direction === 'asc' ? 1 : -1;

        return [...matching].sort((a, b) =>
            sort.key === 'name'
                ? factor * a.name.localeCompare(b.name, 'es')
                : factor * (a[sort.key] - b[sort.key]) ||
                  a.name.localeCompare(b.name, 'es'),
        );
    }, [municipalities, query, sort]);

    const maximum = Math.max(1, ...municipalities.map((item) => item.total));
    const shown = rows.slice(0, visible);

    const toggle = (key: SortKey) =>
        setSort((current) => ({
            key,
            direction:
                current.key === key && current.direction === 'desc'
                    ? 'asc'
                    : key === 'name'
                      ? 'asc'
                      : 'desc',
        }));

    return (
        <div className="en-stats-municipalities">
            <label className="en-stats-search">
                <Search size={18} aria-hidden="true" />
                <input
                    type="search"
                    value={query}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setVisible(PAGE_SIZE);
                    }}
                    placeholder="Buscar un municipio"
                    aria-label={`Buscar un municipio de ${scope}`}
                />
            </label>

            {rows.length === 0 ? (
                <p className="en-stats-empty">
                    {municipalities.length === 0
                        ? `No hay municipios de ${scope} con registros para esta selección.`
                        : `Ningún municipio de ${scope} coincide con «${query.trim()}».`}
                </p>
            ) : (
                <div className="en-stats-table-wrap">
                    <table className="en-stats-table">
                        <thead>
                            <tr>
                                {columns.map((column) => (
                                    <th
                                        key={column.key}
                                        scope="col"
                                        className={
                                            column.numeric
                                                ? 'is-numeric'
                                                : undefined
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
                            {shown.map((municipality) => (
                                <tr key={municipality.name}>
                                    <th scope="row">{municipality.name}</th>
                                    <td className="is-numeric">
                                        <span
                                            className="en-stats-cell-bar"
                                            aria-hidden="true"
                                        >
                                            <span
                                                style={{
                                                    width: `${(municipality.total / maximum) * 100}%`,
                                                }}
                                            />
                                        </span>
                                        <span className="en-stats-cell-number">
                                            {formatNumber(municipality.total)}
                                        </span>
                                        <small className="en-stats-cell-share">
                                            {formatPercent(municipality.share)}
                                        </small>
                                    </td>
                                    {showConfidential && (
                                        <td className="is-numeric">
                                            {formatPercent(
                                                municipality.confidential_share,
                                            )}
                                        </td>
                                    )}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            <div className="en-stats-table-foot">
                <span>
                    {rows.length === municipalities.length
                        ? `${formatNumber(municipalities.length)} municipios con registros`
                        : `${formatNumber(rows.length)} de ${formatNumber(municipalities.length)} municipios`}
                    {rows.length > shown.length &&
                        ` · se muestran ${formatNumber(shown.length)}`}
                </span>
                {rows.length > shown.length && (
                    <span className="en-stats-table-actions">
                        <button
                            type="button"
                            className="en-stats-more"
                            onClick={() => setVisible(visible + PAGE_SIZE)}
                        >
                            Mostrar{' '}
                            {Math.min(PAGE_SIZE, rows.length - shown.length)}{' '}
                            más
                        </button>
                        <button
                            type="button"
                            className="en-stats-more"
                            onClick={() => setVisible(rows.length)}
                        >
                            Mostrar todos
                        </button>
                    </span>
                )}
            </div>

            {unknown > 0 && (
                <p className="en-stats-note">
                    {formatNumber(unknown)} registros de {scope} no indican
                    municipio y no aparecen en esta lista, aunque sí cuentan en
                    el total del estado.
                </p>
            )}
        </div>
    );
}
