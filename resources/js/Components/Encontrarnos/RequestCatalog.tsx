import { classNames } from '@/classNames';
import { sentenceCase, titleCase } from '@/format';
import {
    Paginated,
    PersonRequestItem,
    RequestFilters,
    RequestOptions,
} from '@/types';
import { router } from '@inertiajs/react';
import {
    LoaderCircle,
    MapPin,
    Search,
    SlidersHorizontal,
    UserRound,
} from 'lucide-react';
import { useRef, useState } from 'react';
import {
    CatalogSearch,
    Field,
    Chip as FilterChip,
    FilterChips,
    FilterGroup,
    Pager,
    PersonCard,
    Segmented,
} from './Catalog';
import { FadeImage, useVisitPending } from './motion';
import './requests.css';

const SEARCH_DELAY = 350;

export function RequestChip({ type }: { type: PersonRequestItem['type'] }) {
    return (
        <span className={classNames('req-chip', `req-chip--${type}`)}>
            {type === 'search' ? 'Búsqueda' : 'Identificación'}
        </span>
    );
}

export function RequestPortrait({
    request,
    large = false,
}: {
    request: PersonRequestItem;
    large?: boolean;
}) {
    const src = large ? request.photo : request.photo_thumb;

    return src ? (
        <FadeImage
            src={src}
            alt={`Fotografía de la solicitud ${request.reference}`}
            className="en-photo"
            loading="lazy"
            decoding="async"
        />
    ) : (
        <span className="req-noimage" role="img" aria-label="Sin fotografía">
            <UserRound size={44} strokeWidth={1.5} />
        </span>
    );
}

export function RequestBrowser({
    requests,
    filters,
    options,
}: {
    requests: Paginated<PersonRequestItem>;
    filters: RequestFilters;
    options: RequestOptions;
}) {
    const [query, setQuery] = useState(filters.q ?? '');
    const activeCount = [filters.state, filters.age, filters.type].filter(
        Boolean,
    ).length;
    const [open, setOpen] = useState(activeCount > 0);
    const pending = useVisitPending();
    const latest = useRef(filters);
    latest.current = filters;
    const timer = useRef<ReturnType<typeof setTimeout>>();

    const visit = (changes: Partial<RequestFilters>, page = 1) => {
        const next = { ...latest.current, ...changes };
        const parameters = Object.fromEntries(
            Object.entries({ ...next, page: page > 1 ? page : null }).filter(
                ([, value]) => value,
            ),
        );
        router.get(route('requests'), parameters, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['requests', 'filters'],
            onSuccess: () => {
                if (page > 1) {
                    document
                        .getElementById('catalogo')
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
    (
        [
            ['type', options.types],
            ['state', options.states],
            ['age', options.ageRanges],
        ] as const
    ).forEach(([key, list]) => {
        const value = filters[key];
        if (value) {
            chips.push({
                key,
                label: label(list, value),
                onRemove: () =>
                    visit({ [key]: null } as Partial<RequestFilters>),
            });
        }
    });

    const clearAll = () => {
        setQuery('');
        clearTimeout(timer.current);
        visit({ q: null, state: null, age: null, type: null });
    };

    const { current_page: currentPage, last_page: lastPage } = requests.meta;
    const total = requests.meta.total;

    return (
        <div className="rc" id="catalogo">
            <div className="rc-toolbar">
                <CatalogSearch
                    value={query}
                    onChange={onSearch}
                    placeholder="Nombre, lugar, descripción o referencia (SOL-000012)"
                    label="Buscar solicitudes"
                />
                <button
                    type="button"
                    className="rc-filter-button"
                    aria-expanded={open}
                    aria-controls="request-filters"
                    onClick={() => setOpen(!open)}
                >
                    <SlidersHorizontal size={20} /> Filtros
                    {activeCount > 0 && <b>{activeCount}</b>}
                </button>
            </div>

            <div
                className={classNames('rc-panel', open && 'is-open')}
                id="request-filters"
            >
                <div>
                    <div className="rc-groups rc-groups--three">
                        <FilterGroup title="Solicitud" icon={UserRound}>
                            <Segmented
                                label="Tipo"
                                value={filters.type}
                                options={options.types}
                                onChange={(value) => visit({ type: value })}
                            />
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
                        </FilterGroup>
                        <FilterGroup title="Persona" icon={UserRound}>
                            <Field label="Edad">
                                <select
                                    value={filters.age ?? ''}
                                    onChange={(event) =>
                                        visit({
                                            age: event.target.value || null,
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
                        </FilterGroup>
                    </div>
                </div>
            </div>

            <FilterChips chips={chips} onClear={clearAll} />

            <div className="rc-summary">
                <span role="status" aria-live="polite">
                    <strong>{total.toLocaleString('es-MX')}</strong>{' '}
                    {total === 1 ? 'solicitud' : 'solicitudes'}
                    {chips.length > 0 &&
                        ` · ${chips.length} ${chips.length === 1 ? 'filtro activo' : 'filtros activos'}`}
                </span>
                {pending && (
                    <span className="rc-loading" aria-hidden="true">
                        <LoaderCircle className="en-spin" size={15} /> Buscando…
                    </span>
                )}
            </div>

            <div
                className={classNames('rc-list', pending && 'is-pending')}
                aria-busy={pending}
            >
                {requests.data.map((request, index) => (
                    <PersonCard
                        key={request.id}
                        index={index}
                        href={request.url}
                        photo={request.photo_thumb}
                        hasPhoto={Boolean(request.photo_thumb)}
                        photoAlt={`Fotografía de la solicitud ${request.reference}`}
                        chip={<RequestChip type={request.type} />}
                        reference={request.reference}
                        title={
                            titleCase(request.name) || 'Persona sin identificar'
                        }
                        facts={[
                            [
                                'Edad',
                                request.age === null
                                    ? 'Sin dato'
                                    : `${request.age} años`,
                            ],
                            ['Lugar', titleCase(request.place) || 'Sin dato'],
                            [
                                request.type === 'search'
                                    ? 'Desaparición'
                                    : 'Localización',
                                request.event_date_label ?? 'Sin fecha',
                            ],
                        ]}
                        excerpt={sentenceCase(request.description)}
                    />
                ))}
                {requests.data.length === 0 && (
                    <div className="rc-empty">
                        <Search size={34} />
                        <h3>No hay solicitudes con estos filtros</h3>
                        <p>
                            Prueba otro nombre o lugar, o crea una solicitud
                            nueva.
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
