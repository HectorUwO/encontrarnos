import { classNames } from '@/classNames';
import {
    Option,
    Paginated,
    PersonRecord,
    RecordFilters,
    RecordOptions,
} from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChartNoAxesColumnIncreasing,
    Check,
    ChevronLeft,
    ChevronRight,
    FilePlus2,
    ImagePlus,
    LoaderCircle,
    Search,
    SlidersHorizontal,
    UsersRound,
    X,
} from 'lucide-react';
import { CSSProperties, FormEvent, useEffect, useRef, useState } from 'react';
import { FadeImage, Reveal, useVisitPending } from './motion';
import './public-tools.css';

const actions = [
    {
        href: '/base-de-datos',
        title: 'Buscar en base de datos',
        short: 'Base de datos',
        description: 'Consulta nombres, lugares y descripciones.',
        icon: Search,
    },
    {
        href: '/estadisticas',
        title: 'Estadísticas',
        short: 'Estadísticas',
        description: 'Mapa, histórico y perfil por entidad.',
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        href: '/busqueda-por-fotografia',
        title: 'Búsqueda por fotografía',
        short: 'Fotografía',
        description: 'Busca a partir de una imagen.',
        icon: ImagePlus,
    },
    {
        href: '/solicitudes',
        title: 'Ver o crear solicitudes',
        short: 'Solicitudes',
        description: 'Consulta solicitudes o prepara una nueva.',
        icon: UsersRound,
    },
];

export function ActionGrid() {
    return (
        <Reveal
            stagger
            className="en-action-grid"
            aria-label="Acciones principales"
        >
            {actions.map(({ href, title, description, icon: Icon }, index) => (
                <Link
                    key={href}
                    href={href}
                    prefetch
                    style={{ '--i': index } as CSSProperties}
                    className={classNames(
                        'en-action-card',
                        index === 0 && 'en-action-primary',
                    )}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>
                        <strong>{title}</strong>
                        <small>{description}</small>
                    </span>
                    <ArrowUpRight size={19} aria-hidden="true" />
                </Link>
            ))}
        </Reveal>
    );
}

export function MobileNavigation() {
    const { url } = usePage();
    return (
        <nav className="en-mobile-dock" aria-label="Accesos rápidos">
            {actions.map(({ href, short, icon: Icon }) => (
                <Link
                    href={href}
                    key={href}
                    aria-current={url.startsWith(href) ? 'page' : undefined}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>{short}</span>
                </Link>
            ))}
        </nav>
    );
}

export { actions };

const SEARCH_DELAY = 350;

function useDialog(open: boolean, onClose: () => void) {
    const ref = useRef<HTMLDialogElement>(null);
    useEffect(() => {
        if (!open) return;
        const dialog = ref.current;
        dialog?.showModal();
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previousOverflow;
            dialog?.close();
        };
    }, [open]);
    return {
        ref,
        onCancel: (event: React.SyntheticEvent) => {
            event.preventDefault();
            onClose();
        },
    };
}

const ageLabel = (age: number | null) =>
    age === null ? 'Sin dato' : `${age} años`;

const placeLabel = (record: PersonRecord) =>
    [record.municipality, record.state_label].filter(Boolean).join(', ') ||
    'Sin dato';

const dateTitle = (record: PersonRecord, long = false) =>
    record.type === 'missing_person'
        ? long
            ? 'Fecha de desaparición'
            : 'Desaparición'
        : long
          ? 'Fecha de registro'
          : 'Registro';

function Portrait({
    record,
    large = false,
}: {
    record: PersonRecord;
    large?: boolean;
}) {
    return (
        <FadeImage
            src={large ? record.portrait_large : record.portrait}
            alt={
                record.has_photo
                    ? `Fotografía de la ficha ${record.folio}`
                    : 'Silueta de una persona'
            }
            className={record.has_photo ? 'en-photo' : undefined}
            loading="lazy"
            decoding="async"
        />
    );
}

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
    const [filtersOpen, setFiltersOpen] = useState(
        Boolean(filters.state || filters.age || filters.type),
    );
    // La ficha se conserva mientras el diálogo se cierra: si no, se vaciaría a media animación.
    const [selected, setSelected] = useState<PersonRecord | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);
    const dialog = useDialog(dialogOpen, () => setDialogOpen(false));
    const pending = useVisitPending();
    const openRecord = (record: PersonRecord) => {
        setSelected(record);
        setDialogOpen(true);
    };
    const activeFilters = [filters.state, filters.age, filters.type].filter(
        Boolean,
    ).length;
    const latestFilters = useRef(filters);
    latestFilters.current = filters;

    const visit = (changes: Partial<RecordFilters>, page = 1) => {
        const next = { ...latestFilters.current, ...changes };
        const parameters = Object.fromEntries(
            Object.entries({ ...next, page: page > 1 ? page : null }).filter(
                ([, value]) => value,
            ),
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
    const { current_page: currentPage, last_page: lastPage } = records.meta;

    return (
        <div className="en-database">
            <div className="en-database-toolbar">
                <label className="en-database-search">
                    <Search size={22} aria-hidden="true" />
                    <input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Nombre, lugar o descripción"
                        aria-label="Buscar en la base de datos"
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
                    aria-controls="database-filters"
                    onClick={() => setFiltersOpen(!filtersOpen)}
                >
                    <SlidersHorizontal size={20} /> Filtros{' '}
                    {activeFilters > 0 && <span>{activeFilters}</span>}
                </button>
            </div>
            <div
                className={classNames('en-collapse', filtersOpen && 'is-open')}
                id="database-filters"
            >
                <div className="en-collapse-inner">
                    <div className="en-database-filters">
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
                        <label>
                            Tipo de registro
                            <select
                                value={filters.type ?? ''}
                                onChange={(event) =>
                                    visit({ type: event.target.value || null })
                                }
                            >
                                <option value="">Todos los registros</option>
                                {options.types.map((item) => (
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
                    {records.meta.total.toLocaleString('es-MX')}{' '}
                    {records.meta.total === 1 ? 'resultado' : 'resultados'}
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
                {records.data.map((record, index) => (
                    <article
                        className="en-person-card"
                        style={{ '--i': index } as CSSProperties}
                        key={record.folio}
                    >
                        <div className="en-person-portrait">
                            <Portrait record={record} />
                        </div>
                        <div className="en-person-info">
                            <span className="en-person-type">
                                {record.type_label}
                            </span>
                            <h3>{record.name}</h3>
                            <dl>
                                <div>
                                    <dt>Edad</dt>
                                    <dd>{ageLabel(record.age)}</dd>
                                </div>
                                <div>
                                    <dt>Lugar</dt>
                                    <dd>{placeLabel(record)}</dd>
                                </div>
                                <div>
                                    <dt>{dateTitle(record)}</dt>
                                    <dd>
                                        {record.event_date_label ?? 'Sin fecha'}
                                    </dd>
                                </div>
                            </dl>
                            <p>
                                {record.description ??
                                    'Aún no hay una descripción física registrada.'}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="en-person-open"
                            onClick={() => openRecord(record)}
                        >
                            Ver ficha <ArrowUpRight size={19} />
                        </button>
                    </article>
                ))}
                {!records.data.length && (
                    <div className="en-database-empty">
                        <Search size={30} />
                        <h3>No hay resultados con estos filtros</h3>
                        <p>Prueba otro nombre, lugar o rango de edad.</p>
                        <button className="en-primary-button" onClick={reset}>
                            Limpiar búsqueda
                        </button>
                    </div>
                )}
            </div>
            {lastPage > 1 && (
                <nav
                    className="en-pagination"
                    aria-label="Paginación de resultados"
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
            <dialog
                {...dialog}
                className="en-dialog"
                aria-labelledby="record-dialog-title"
                onClick={(event) => {
                    if (event.target === event.currentTarget)
                        setDialogOpen(false);
                }}
            >
                {selected && (
                    <>
                        <div className="en-dialog-header">
                            <span>Ficha de registro</span>
                            <button
                                type="button"
                                onClick={() => setDialogOpen(false)}
                                aria-label="Cerrar ficha"
                            >
                                <X size={23} />
                            </button>
                        </div>
                        <div className="en-dialog-content">
                            <div
                                className={
                                    selected.has_photo
                                        ? 'en-detail-portrait en-detail-portrait--photo'
                                        : 'en-detail-portrait'
                                }
                            >
                                <Portrait
                                    key={selected.folio}
                                    record={selected}
                                    large
                                />
                            </div>
                            <span className="en-person-type">
                                {selected.type_label}
                                {selected.status_label &&
                                    ` · ${selected.status_label}`}
                            </span>
                            <h2 id="record-dialog-title">{selected.name}</h2>
                            <dl className="en-detail-data">
                                <div>
                                    <dt>Edad</dt>
                                    <dd>{ageLabel(selected.age)}</dd>
                                </div>
                                <div>
                                    <dt>Lugar</dt>
                                    <dd>{placeLabel(selected)}</dd>
                                </div>
                                <div>
                                    <dt>{dateTitle(selected, true)}</dt>
                                    <dd>
                                        {selected.event_date_label ??
                                            'Sin fecha'}
                                    </dd>
                                </div>
                            </dl>
                            {selected.traits.length > 0 && (
                                <>
                                    <h3>Rasgos</h3>
                                    <dl className="en-detail-data">
                                        {selected.traits.map((trait) => (
                                            <div key={trait.label}>
                                                <dt>{trait.label}</dt>
                                                <dd>{trait.value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                </>
                            )}
                            {selected.distinguishing_marks && (
                                <>
                                    <h3>Señas particulares</h3>
                                    <p>{selected.distinguishing_marks}</p>
                                </>
                            )}
                            {selected.clothing && (
                                <>
                                    <h3>Prendas de vestir</h3>
                                    <p>{selected.clothing}</p>
                                </>
                            )}
                            {!selected.traits.length &&
                                !selected.distinguishing_marks &&
                                !selected.clothing && (
                                    <>
                                        <h3>Descripción</h3>
                                        <p>
                                            {selected.description ??
                                                'Aún no hay una descripción física registrada.'}
                                        </p>
                                    </>
                                )}
                            {selected.authority && (
                                <>
                                    <h3>Autoridad responsable</h3>
                                    <p>
                                        Para aportar información, comunícate con
                                        la autoridad que registró la ficha:{' '}
                                        {selected.authority}.
                                    </p>
                                </>
                            )}
                        </div>
                    </>
                )}
            </dialog>
        </div>
    );
}

const requestTypes: Option[] = [
    { value: 'search', label: 'Búsqueda de una persona' },
    { value: 'identification', label: 'Identificación de una persona' },
];

export function RequestComposer({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const dialog = useDialog(open, onClose);
    const [preview, setPreview] = useState(false);
    const [type, setType] = useState(requestTypes[0].value);
    const [name, setName] = useState('');
    const [place, setPlace] = useState('');
    const [description, setDescription] = useState('');
    const [contact, setContact] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [photoUrl, setPhotoUrl] = useState('');
    const [error, setError] = useState('');
    const [serverErrors, setServerErrors] = useState<Record<string, string>>(
        {},
    );
    const [sending, setSending] = useState(false);
    const [sent, setSent] = useState(false);
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        if (!photo) {
            setPhotoUrl('');
            return;
        }
        const url = URL.createObjectURL(photo);
        setPhotoUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [photo]);
    useEffect(() => {
        if (open && preview) heading.current?.focus();
    }, [open, preview]);
    const submit = (event: FormEvent) => {
        event.preventDefault();
        setServerErrors({});
        setPreview(true);
    };
    const send = () => {
        setSending(true);
        router.post(
            route('requests.store'),
            { type, name, place, description, contact_email: contact, photo },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setServerErrors({});
                    setSent(true);
                },
                onError: (errors) => setServerErrors(errors),
                onFinish: () => setSending(false),
            },
        );
    };
    const close = () => {
        if (sent) {
            setSent(false);
            setPreview(false);
            setType(requestTypes[0].value);
            setName('');
            setPlace('');
            setDescription('');
            setContact('');
            setPhoto(null);
        }
        onClose();
    };
    return (
        <dialog
            {...dialog}
            className="en-dialog en-request-dialog"
            aria-labelledby="request-dialog-title"
            onClick={(event) => {
                if (event.target === event.currentTarget) close();
            }}
        >
            <div className="en-dialog-header">
                <span>
                    <FilePlus2 size={19} /> Nueva solicitud
                </span>
                <button
                    type="button"
                    onClick={close}
                    aria-label="Cerrar solicitud"
                >
                    <X size={23} />
                </button>
            </div>
            <div className="en-dialog-content">
                <h2 ref={heading} tabIndex={-1} id="request-dialog-title">
                    {sent
                        ? 'Solicitud recibida'
                        : preview
                          ? 'Vista previa de tu solicitud'
                          : 'Crear solicitud'}
                </h2>
                {sent ? (
                    <div className="en-request-preview">
                        <p className="en-demo-note">
                            <Check size={19} /> Recibimos tu solicitud. Será
                            revisada antes de publicarse.
                        </p>
                        <div className="en-dialog-actions">
                            <button
                                type="button"
                                className="en-primary-button"
                                onClick={close}
                            >
                                Cerrar
                            </button>
                        </div>
                    </div>
                ) : preview ? (
                    <div className="en-request-preview">
                        <p className="en-demo-note">
                            <Check size={19} /> Tu borrador está listo para
                            revisar.
                        </p>
                        {photoUrl && (
                            <img
                                className="en-draft-photo"
                                src={photoUrl}
                                alt="Fotografía de la solicitud"
                            />
                        )}
                        <span className="en-person-type">
                            {requestTypes.find((item) => item.value === type)
                                ?.label ?? type}
                        </span>
                        <h3>{name || 'Persona por identificar'}</h3>
                        <dl className="en-detail-data">
                            <div>
                                <dt>Lugar</dt>
                                <dd>{place}</dd>
                            </div>
                            <div>
                                <dt>Contacto</dt>
                                <dd>{contact}</dd>
                            </div>
                        </dl>
                        <h3>Descripción</h3>
                        <p className="en-draft-description">{description}</p>
                        {Object.values(serverErrors).map((message) => (
                            <p
                                role="alert"
                                className="en-form-error"
                                key={message}
                            >
                                {message}
                            </p>
                        ))}
                        <div className="en-dialog-actions">
                            <button
                                type="button"
                                className="en-secondary-button"
                                onClick={() => setPreview(false)}
                            >
                                Editar solicitud
                            </button>
                            <button
                                type="button"
                                className="en-primary-button"
                                disabled={sending}
                                aria-busy={sending}
                                onClick={send}
                            >
                                {sending ? 'Enviando…' : 'Enviar solicitud'}{' '}
                                {sending ? (
                                    <LoaderCircle
                                        className="en-spin"
                                        size={19}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <ArrowUpRight size={19} />
                                )}
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <p>
                            Agrega la información que tengas. Podrás revisar la
                            solicitud antes de enviarla.
                        </p>
                        <form onSubmit={submit} className="en-request-form">
                            <label>
                                Tipo de solicitud
                                <select
                                    value={type}
                                    onChange={(event) =>
                                        setType(event.target.value)
                                    }
                                >
                                    {requestTypes.map((item) => (
                                        <option
                                            key={item.value}
                                            value={item.value}
                                        >
                                            {item.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label>
                                Nombre de la persona{' '}
                                <small>(si lo conoces)</small>
                                <input
                                    autoComplete="off"
                                    value={name}
                                    onChange={(event) =>
                                        setName(event.target.value)
                                    }
                                    placeholder="Nombre completo"
                                    maxLength={120}
                                />
                            </label>
                            <label>
                                Lugar <small>(obligatorio)</small>
                                <input
                                    required
                                    value={place}
                                    onChange={(event) =>
                                        setPlace(event.target.value)
                                    }
                                    placeholder="Municipio y estado"
                                    maxLength={150}
                                />
                            </label>
                            <label>
                                Descripción <small>(obligatorio)</small>
                                <textarea
                                    required
                                    rows={4}
                                    value={description}
                                    onChange={(event) =>
                                        setDescription(event.target.value)
                                    }
                                    placeholder="Descripción física, señas particulares y datos que ayuden a identificar a la persona."
                                    maxLength={3000}
                                />
                            </label>
                            <label>
                                Correo de contacto <small>(obligatorio)</small>
                                <input
                                    type="email"
                                    autoComplete="email"
                                    required
                                    value={contact}
                                    onChange={(event) =>
                                        setContact(event.target.value)
                                    }
                                    placeholder="tu@correo.com"
                                    maxLength={254}
                                />
                            </label>
                            <label>
                                Fotografía{' '}
                                <small>
                                    (opcional · JPG, PNG o WEBP · hasta 10 MB)
                                </small>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];
                                        if (
                                            file &&
                                            (![
                                                'image/jpeg',
                                                'image/png',
                                                'image/webp',
                                            ].includes(file.type) ||
                                                file.size > 10 * 1024 * 1024)
                                        ) {
                                            setError(
                                                'Elige un archivo JPG, PNG o WEBP de hasta 10 MB.',
                                            );
                                            event.target.value = '';
                                            setPhoto(null);
                                        } else {
                                            setError('');
                                            setPhoto(file || null);
                                        }
                                    }}
                                />
                            </label>
                            {error && (
                                <p role="alert" className="en-form-error">
                                    {error}
                                </p>
                            )}
                            {photoUrl && (
                                <img
                                    className="en-draft-photo"
                                    src={photoUrl}
                                    alt="Vista previa de la fotografía"
                                />
                            )}
                            <button
                                type="submit"
                                className="en-primary-button"
                                disabled={Boolean(error)}
                            >
                                Revisar solicitud <ArrowUpRight size={19} />
                            </button>
                        </form>
                    </>
                )}
            </div>
        </dialog>
    );
}
