import { classNames } from '@/classNames';
import { sentenceCase, titleCase } from '@/format';
import {
    Paginated,
    PersonRequestItem,
    RequestFilters,
    RequestOptions,
} from '@/types';
import { Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChevronLeft,
    ChevronRight,
    LoaderCircle,
    Search,
    SlidersHorizontal,
    UserRound,
    X,
} from 'lucide-react';
import { CSSProperties, useEffect, useRef, useState } from 'react';
import { FadeImage, useVisitPending } from './motion';
import './requests.css';

const SEARCH_DELAY = 350;

const dateTitle = (request: PersonRequestItem) =>
    request.type === 'search' ? 'Desaparición' : 'Localización';

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
    const [filtersOpen, setFiltersOpen] = useState(
        Boolean(filters.state || filters.age || filters.type),
    );
    const pending = useVisitPending();
    const activeFilters = [filters.state, filters.age, filters.type].filter(
        Boolean,
    ).length;
    const latestFilters = useRef(filters);
    latestFilters.current = filters;

    const visit = (changes: Partial<RequestFilters>, page = 1) => {
        const next = { ...latestFilters.current, ...changes };
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

    useEffect(() => {
        const term = query.trim();
        if (term === (latestFilters.current.q ?? '')) return;
        const timer = setTimeout(
            () => visit({ q: term || null }),
            SEARCH_DELAY,
        );
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query]);

    const reset = () => {
        setQuery('');
        visit({ q: null, state: null, age: null, type: null });
    };
    const { current_page: currentPage, last_page: lastPage } = requests.meta;

    return (
        <div className="en-database" id="catalogo">
            <div className="en-database-toolbar">
                <label className="en-database-search">
                    <Search size={22} aria-hidden="true" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Nombre, lugar, descripción o referencia"
                        aria-label="Buscar solicitudes"
                    />
                    {query && (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            aria-label="Borrar búsqueda"
                        >
                            <X size={19} />
                        </button>
                    )}
                </label>
                <button
                    className="en-filter-toggle"
                    type="button"
                    aria-expanded={filtersOpen}
                    aria-controls="request-filters"
                    onClick={() => setFiltersOpen(!filtersOpen)}
                >
                    <SlidersHorizontal size={20} /> Filtros{' '}
                    {activeFilters > 0 && <span>{activeFilters}</span>}
                </button>
            </div>
            <div
                className={classNames('en-collapse', filtersOpen && 'is-open')}
                id="request-filters"
            >
                <div className="en-collapse-inner">
                    <div className="en-database-filters">
                        <label>
                            Tipo de solicitud
                            <select
                                value={filters.type ?? ''}
                                onChange={(event) =>
                                    visit({ type: event.target.value || null })
                                }
                            >
                                <option value="">Todas las solicitudes</option>
                                {options.types.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Estado
                            <select
                                value={filters.state ?? ''}
                                onChange={(event) =>
                                    visit({ state: event.target.value || null })
                                }
                            >
                                <option value="">Todos los estados</option>
                                {options.states.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label>
                            Edad
                            <select
                                value={filters.age ?? ''}
                                onChange={(event) =>
                                    visit({ age: event.target.value || null })
                                }
                            >
                                <option value="">Todas las edades</option>
                                {options.ageRanges.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <button
                            type="button"
                            className="en-plain-button"
                            onClick={reset}
                        >
                            Limpiar filtros
                        </button>
                    </div>
                </div>
            </div>
            <div className="en-database-summary">
                <span role="status" aria-live="polite">
                    {requests.meta.total.toLocaleString('es-MX')}{' '}
                    {requests.meta.total === 1 ? 'solicitud' : 'solicitudes'}
                    {activeFilters > 0 &&
                        ` · ${activeFilters} ${activeFilters === 1 ? 'filtro activo' : 'filtros activos'}`}
                </span>
                {pending && (
                    <span aria-hidden="true">
                        <LoaderCircle className="en-spin" size={15} /> Buscando…
                    </span>
                )}
            </div>
            <div
                className={classNames(
                    'en-database-list',
                    pending && 'en-pending',
                )}
                aria-busy={pending}
            >
                {requests.data.map((request, index) => (
                    <article
                        className="en-person-card"
                        style={{ '--i': index } as CSSProperties}
                        key={request.id}
                    >
                        <div className="en-person-portrait">
                            <RequestPortrait request={request} />
                        </div>
                        <div className="en-person-info">
                            <div className="req-cardhead">
                                <RequestChip type={request.type} />
                                <span className="req-ref">
                                    {request.reference}
                                </span>
                            </div>
                            <h3>
                                {titleCase(request.name) ||
                                    'Persona sin identificar'}
                            </h3>
                            <dl>
                                <div>
                                    <dt>Edad</dt>
                                    <dd>
                                        {request.age === null
                                            ? 'Sin dato'
                                            : `${request.age} años`}
                                    </dd>
                                </div>
                                <div>
                                    <dt>Lugar</dt>
                                    <dd>
                                        {titleCase(request.place) || 'Sin dato'}
                                    </dd>
                                </div>
                                <div>
                                    <dt>{dateTitle(request)}</dt>
                                    <dd>
                                        {request.event_date_label ??
                                            'Sin fecha'}
                                    </dd>
                                </div>
                            </dl>
                            <p>{sentenceCase(request.description)}</p>
                        </div>
                        <Link
                            href={request.url}
                            className="en-person-open"
                            prefetch
                        >
                            Ver ficha <ArrowUpRight size={19} />
                        </Link>
                    </article>
                ))}
                {!requests.data.length && (
                    <div className="en-database-empty">
                        <Search size={30} />
                        <h3>No hay solicitudes con estos filtros</h3>
                        <p>
                            Prueba otro nombre o lugar, o crea una solicitud
                            nueva.
                        </p>
                        <button className="en-primary-button" onClick={reset}>
                            Limpiar búsqueda
                        </button>
                    </div>
                )}
            </div>
            {lastPage > 1 && (
                <nav
                    className="en-pagination"
                    aria-label="Paginación de solicitudes"
                >
                    <button
                        type="button"
                        className="en-secondary-button"
                        disabled={currentPage <= 1}
                        onClick={() => visit({}, currentPage - 1)}
                    >
                        <ChevronLeft size={19} /> Anterior
                    </button>
                    <span>
                        Página {currentPage.toLocaleString('es-MX')} de{' '}
                        {lastPage.toLocaleString('es-MX')}
                    </span>
                    <button
                        type="button"
                        className="en-secondary-button"
                        disabled={currentPage >= lastPage}
                        onClick={() => visit({}, currentPage + 1)}
                    >
                        Siguiente <ChevronRight size={19} />
                    </button>
                </nav>
            )}
        </div>
    );
}
