import { classNames } from '@/classNames';
import { sentenceCase, titleCase } from '@/format';
import { Paginated, PersonRecord, RecordFilters, RecordOptions } from '@/types';
import { router } from '@inertiajs/react';
import {
    CalendarRange,
    FileText,
    LoaderCircle,
    MapPin,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    UserRound,
} from 'lucide-react';
import { useRef, useState } from 'react';
import {
    CatalogSearch,
    DebouncedInput,
    Field,
    Chip as FilterChip,
    FilterChips,
    FilterGroup,
    Pager,
    PersonCard,
    Segmented,
    Toggle,
} from './Catalog';
import { Chip } from './Ficha';
import { useVisitPending } from './motion';

const SEARCH_DELAY = 350;

const ADVANCED_KEYS = [
    'state',
    'age',
    'age_from',
    'age_to',
    'sex',
    'status',
    'photo',
    'from',
    'to',
    'municipality',
    'authority',
    'nationality',
    'disability',
    'registry',
] as const;

const dateLabel = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

const ageLabel = (age: number | null) =>
    age === null ? 'Sin dato' : `${age} ${age === 1 ? 'año' : 'años'}`;

export function RecordBrowser({
    records,
    filters,
    options,
}: {
    records: Paginated<PersonRecord>;
    filters: RecordFilters;
    options: RecordOptions;
}) {
    const [query, setQuery] = useState(filters.q ?? '');
    const advancedCount = ADVANCED_KEYS.filter((key) => filters[key]).length;
    const [open, setOpen] = useState(advancedCount > 0);
    const pending = useVisitPending();
    const latest = useRef(filters);
    latest.current = filters;
    const timer = useRef<ReturnType<typeof setTimeout>>();

    const visit = (changes: Partial<RecordFilters>, page = 1) => {
        const next = { ...latest.current, ...changes };
        const parameters = Object.fromEntries(
            Object.entries({ ...next, page: page > 1 ? page : null })
                .filter(
                    ([, value]) =>
                        value !== null && value !== '' && value !== false,
                )
                .map(([key, value]) => [key, value === true ? 1 : value]),
        );
        router.get(route('records'), parameters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['records', 'filters'],
            onSuccess: () => {
                if (page > 1) {
                    document
                        .getElementById('registros')
                        ?.scrollIntoView({ behavior: 'smooth' });
                }
            },
        });
    };

    const onSearch = (value: string) => {
        setQuery(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(
            () => visit({ q: value.trim() || null }),
            SEARCH_DELAY,
        );
    };

    const label = (list: { value: string; label: string }[], value: string) =>
        list.find((item) => item.value === value)?.label ?? value;

    const chips: FilterChip[] = [];
    const add = (key: keyof RecordFilters, text: string | null) => {
        if (text) {
            chips.push({
                key,
                label: text,
                onRemove: () =>
                    visit({ [key]: null } as Partial<RecordFilters>),
            });
        }
    };
    add('state', filters.state && label(options.states, filters.state));
    add('age', filters.age && label(options.ageRanges, filters.age));
    add(
        'age_from',
        filters.age_from !== null ? `Edad desde ${filters.age_from}` : null,
    );
    add(
        'age_to',
        filters.age_to !== null ? `Edad hasta ${filters.age_to}` : null,
    );
    add('sex', filters.sex && label(options.sexes, filters.sex));
    add('status', filters.status && label(options.statuses, filters.status));
    add('from', filters.from && `Desde ${dateLabel(filters.from)}`);
    add('to', filters.to && `Hasta ${dateLabel(filters.to)}`);
    add(
        'municipality',
        filters.municipality && `Municipio: ${filters.municipality}`,
    );
    add('authority', filters.authority && `Autoridad: ${filters.authority}`);
    add(
        'nationality',
        filters.nationality &&
            label(options.nationalities, filters.nationality),
    );
    add('photo', filters.photo ? 'Con fotografía' : null);
    add('disability', filters.disability ? 'Con discapacidad' : null);
    add(
        'registry',
        filters.registry && options.registry
            ? `Registro: ${label(options.registry, filters.registry)}`
            : null,
    );

    const clearAll = () => {
        setQuery('');
        clearTimeout(timer.current);
        visit({
            q: null,
            state: null,
            age: null,
            age_from: null,
            age_to: null,
            sex: null,
            status: null,
            photo: null,
            from: null,
            to: null,
            municipality: null,
            authority: null,
            nationality: null,
            disability: null,
            registry: null,
        });
    };

    const { current_page: currentPage, last_page: lastPage } = records.meta;
    const total = records.meta.total;

    return (
        <div className="rc" id="registros">
            <div className="rc-toolbar">
                <CatalogSearch
                    value={query}
                    onChange={onSearch}
                    placeholder="Nombre, folio (EN-000123), municipio o descripción"
                    label="Buscar en la base de datos"
                    hint="Puedes escribir varias palabras: se buscan todas, sin importar el orden ni los acentos."
                />
                <button
                    type="button"
                    className="rc-filter-button"
                    aria-expanded={open}
                    aria-controls="record-filters"
                    onClick={() => setOpen(!open)}
                >
                    <SlidersHorizontal size={20} /> Filtros
                    {advancedCount > 0 && <b>{advancedCount}</b>}
                </button>
            </div>

            <div
                className={classNames('rc-panel', open && 'is-open')}
                id="record-filters"
            >
                <div>
                    <div className="rc-groups">
                        <FilterGroup title="Persona" icon={UserRound}>
                            <Segmented
                                label="Sexo"
                                value={filters.sex}
                                options={options.sexes.filter(
                                    (item) => item.value !== 'unknown',
                                )}
                                onChange={(value) => visit({ sex: value })}
                            />
                            <Field label="Edad">
                                <select
                                    value={filters.age ?? ''}
                                    onChange={(event) =>
                                        visit({
                                            age: event.target.value || null,
                                            age_from: null,
                                            age_to: null,
                                        })
                                    }
                                >
                                    <option value="">Todas las edades</option>
                                    {options.ageRanges.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <div className="rc-pair">
                                <Field label="Desde (años)">
                                    <DebouncedInput
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        max={110}
                                        value={String(filters.age_from ?? '')}
                                        onCommit={(value) =>
                                            visit({
                                                age: null,
                                                age_from:
                                                    value === ''
                                                        ? null
                                                        : Number(value),
                                            })
                                        }
                                    />
                                </Field>
                                <Field label="Hasta (años)">
                                    <DebouncedInput
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        max={110}
                                        value={String(filters.age_to ?? '')}
                                        onCommit={(value) =>
                                            visit({
                                                age: null,
                                                age_to:
                                                    value === ''
                                                        ? null
                                                        : Number(value),
                                            })
                                        }
                                    />
                                </Field>
                            </div>
                            <Field label="Nacionalidad">
                                <select
                                    value={filters.nationality ?? ''}
                                    onChange={(event) =>
                                        visit({
                                            nationality:
                                                event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Todas</option>
                                    {options.nationalities.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </FilterGroup>

                        <FilterGroup title="Lugar" icon={MapPin}>
                            <Field label="Estado">
                                <select
                                    value={filters.state ?? ''}
                                    onChange={(event) =>
                                        visit({
                                            state: event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Todos los estados</option>
                                    {options.states.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field label="Municipio">
                                <DebouncedInput
                                    type="search"
                                    placeholder="Ej. Tepic"
                                    value={filters.municipality ?? ''}
                                    onCommit={(value) =>
                                        visit({
                                            municipality: value.trim() || null,
                                        })
                                    }
                                />
                            </Field>
                        </FilterGroup>

                        <FilterGroup title="Desaparición" icon={CalendarRange}>
                            <Segmented
                                label="Estatus"
                                value={filters.status}
                                options={options.statuses}
                                onChange={(value) => visit({ status: value })}
                            />
                            <div className="rc-pair">
                                <Field label="Desde">
                                    <input
                                        type="date"
                                        value={filters.from ?? ''}
                                        max={filters.to ?? undefined}
                                        onChange={(event) =>
                                            visit({
                                                from:
                                                    event.target.value || null,
                                            })
                                        }
                                    />
                                </Field>
                                <Field label="Hasta">
                                    <input
                                        type="date"
                                        value={filters.to ?? ''}
                                        min={filters.from ?? undefined}
                                        onChange={(event) =>
                                            visit({
                                                to: event.target.value || null,
                                            })
                                        }
                                    />
                                </Field>
                            </div>
                        </FilterGroup>

                        <FilterGroup title="Ficha" icon={FileText}>
                            <Field label="Autoridad responsable">
                                <DebouncedInput
                                    type="search"
                                    placeholder="Ej. Fiscalía de Jalisco"
                                    value={filters.authority ?? ''}
                                    onCommit={(value) =>
                                        visit({
                                            authority: value.trim() || null,
                                        })
                                    }
                                />
                            </Field>
                            <Toggle
                                label="Solo con fotografía"
                                checked={Boolean(filters.photo)}
                                onChange={(checked) =>
                                    visit({ photo: checked ? true : null })
                                }
                            />
                            <Toggle
                                label="Con discapacidad"
                                checked={Boolean(filters.disability)}
                                onChange={(checked) =>
                                    visit({ disability: checked ? true : null })
                                }
                            />
                        </FilterGroup>

                        {options.registry && (
                            <FilterGroup
                                title="Registro"
                                icon={ShieldCheck}
                                badge="SOLO ADMIN"
                                wide
                            >
                                <Segmented
                                    label="Publicación según el registro"
                                    value={filters.registry}
                                    options={options.registry}
                                    onChange={(value) =>
                                        visit({ registry: value })
                                    }
                                />
                            </FilterGroup>
                        )}
                    </div>
                </div>
            </div>

            <FilterChips chips={chips} onClear={clearAll} />

            <div className="rc-summary">
                <span role="status" aria-live="polite">
                    <strong>{total.toLocaleString('es-MX')}</strong>{' '}
                    {total === 1 ? 'resultado' : 'resultados'}
                    {chips.length > 0 &&
                        ` · ${chips.length} ${chips.length === 1 ? 'filtro activo' : 'filtros activos'}`}
                </span>
                <div className="rc-summary-tools">
                    {pending && (
                        <span className="rc-loading" aria-hidden="true">
                            <LoaderCircle className="en-spin" size={15} />{' '}
                            Buscando…
                        </span>
                    )}
                    <label>
                        Ordenar por
                        <select
                            value={filters.sort ?? 'recent'}
                            onChange={(event) =>
                                visit({
                                    sort:
                                        event.target.value === 'recent'
                                            ? null
                                            : event.target.value,
                                })
                            }
                        >
                            {options.sorts.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>
            </div>

            <div
                className={classNames('rc-list', pending && 'is-pending')}
                aria-busy={pending}
            >
                {records.data.map((record, index) => (
                    <PersonCard
                        key={record.folio}
                        index={index}
                        href={record.url}
                        photo={record.has_photo ? record.portrait : null}
                        hasPhoto={record.has_photo}
                        photoAlt={`Fotografía de la ficha ${record.folio}`}
                        chip={
                            <Chip
                                tone={
                                    record.status_label === 'No localizada'
                                        ? 'not_located'
                                        : 'missing'
                                }
                            >
                                {record.status_label ?? record.type_label}
                            </Chip>
                        }
                        reference={record.folio}
                        title={titleCase(record.name)}
                        facts={[
                            ['Edad', ageLabel(record.age)],
                            [
                                'Lugar',
                                [
                                    titleCase(record.municipality),
                                    record.state_label,
                                ]
                                    .filter(Boolean)
                                    .join(', ') || 'Sin dato',
                            ],
                            [
                                'Desaparición',
                                record.event_date_label ?? 'Sin fecha',
                            ],
                        ]}
                        excerpt={
                            record.description
                                ? sentenceCase(record.description)
                                : 'Aún no hay una descripción física registrada.'
                        }
                    />
                ))}
                {records.data.length === 0 && (
                    <div className="rc-empty">
                        <Search size={34} />
                        <h3>No hay fichas con estos filtros</h3>
                        <p>
                            Prueba con menos palabras, quita algún filtro o
                            busca solo por el nombre.
                        </p>
                        <button
                            type="button"
                            className="en-primary-button"
                            onClick={clearAll}
                        >
                            Limpiar búsqueda
                        </button>
                    </div>
                )}
            </div>

            <Pager
                current={currentPage}
                last={lastPage}
                onPage={(page) => visit({}, page)}
            />
        </div>
    );
}
