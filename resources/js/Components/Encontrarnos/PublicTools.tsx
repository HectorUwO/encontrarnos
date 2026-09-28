import {
    ArrowUpRight,
    ChartNoAxesColumnIncreasing,
    Check,
    FilePlus2,
    ImagePlus,
    Search,
    SlidersHorizontal,
    UsersRound,
    X,
} from 'lucide-react';
import { FormEvent, useEffect, useRef, useState } from 'react';
import './public-tools.css';

const actions = [
    {
        href: '#registros',
        title: 'Buscar en base de datos',
        short: 'Base de datos',
        description: 'Consulta nombres, lugares y descripciones.',
        icon: Search,
    },
    {
        href: '#estadisticas',
        title: 'Estadísticas',
        short: 'Estadísticas',
        description: 'Explora la información por lugar y edad.',
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        href: '#buscar',
        title: 'Búsqueda por fotografía',
        short: 'Fotografía',
        description: 'Busca a partir de una imagen.',
        icon: ImagePlus,
    },
    {
        href: '#solicitudes',
        title: 'Ver o crear solicitudes',
        short: 'Solicitudes',
        description: 'Consulta solicitudes o prepara una nueva.',
        icon: UsersRound,
    },
];

export function ActionGrid({
    fromDashboard = false,
}: {
    fromDashboard?: boolean;
}) {
    return (
        <div className="en-action-grid" aria-label="Acciones principales">
            {actions.map(({ href, title, description, icon: Icon }, index) => (
                <a
                    key={href}
                    href={`${fromDashboard ? '/' : ''}${href}`}
                    className={`en-action-card ${index === 0 ? 'en-action-primary' : ''}`}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>
                        <strong>{title}</strong>
                        <small>{description}</small>
                    </span>
                    <ArrowUpRight size={19} aria-hidden="true" />
                </a>
            ))}
        </div>
    );
}

export function MobileNavigation() {
    const [active, setActive] = useState('');
    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries.find((entry) => entry.isIntersecting);
                if (visible) setActive(`#${visible.target.id}`);
            },
            { rootMargin: '-10% 0px -45% 0px' },
        );
        actions.forEach(({ href }) => {
            const section = document.querySelector(href);
            if (section) observer.observe(section);
        });
        return () => observer.disconnect();
    }, []);
    return (
        <nav className="en-mobile-dock" aria-label="Accesos rápidos">
            {actions.map(({ href, short, icon: Icon }) => (
                <a
                    href={href}
                    key={href}
                    aria-current={active === href ? 'location' : undefined}
                    onClick={() => setActive(href)}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>{short}</span>
                </a>
            ))}
        </nav>
    );
}

const exampleRecords = [
    {
        id: 'EN-001',
        name: 'Ficha EN-001',
        age: 29,
        state: 'Ciudad de México',
        date: '12 de enero de 2026',
        type: 'Persona desaparecida',
        description:
            'Cabello oscuro, estatura media. La ficha reúne descripción física, señas particulares y datos de la desaparición.',
        portrait: '/woman-placeholder.png',
    },
    {
        id: 'EN-002',
        name: 'Ficha EN-002',
        age: 42,
        state: 'Jalisco',
        date: '8 de febrero de 2026',
        type: 'Solicitud de identificación',
        description:
            'Cabello corto, complexión media. La ficha incluye una descripción y un canal para aportar información.',
        portrait: '/men%20place%20holder.png',
    },
    {
        id: 'EN-003',
        name: 'Ficha EN-003',
        age: 34,
        state: 'Nuevo León',
        date: '20 de marzo de 2026',
        type: 'Persona desaparecida',
        description:
            'Cabello oscuro, complexión delgada. La ficha organiza la información disponible para facilitar su consulta.',
        portrait: '/men%20place%20holder.png',
    },
];
const states = [...new Set(exampleRecords.map((record) => record.state))];
type ExampleRecord = (typeof exampleRecords)[number];
const normalize = (value: string) =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase('es-MX')
        .trim();

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

export function RecordBrowser() {
    const [query, setQuery] = useState('');
    const [state, setState] = useState('');
    const [type, setType] = useState('');
    const [age, setAge] = useState('');
    const [filtersOpen, setFiltersOpen] = useState(false);
    const [selected, setSelected] = useState<ExampleRecord | null>(null);
    const dialog = useDialog(Boolean(selected), () => setSelected(null));
    const activeFilters = [state, type, age].filter(Boolean).length;
    const records = exampleRecords.filter(
        (record) =>
            normalize(
                `${record.name} ${record.id} ${record.state} ${record.description}`,
            ).includes(normalize(query)) &&
            (!state || record.state === state) &&
            (!type || record.type === type) &&
            (!age ||
                (age === '18-29'
                    ? record.age < 30
                    : age === '30-39'
                      ? record.age >= 30 && record.age < 40
                      : record.age >= 40)),
    );
    const reset = () => {
        setQuery('');
        setState('');
        setType('');
        setAge('');
    };
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
                className="en-database-filters"
                id="database-filters"
                hidden={!filtersOpen}
            >
                <label>
                    Estado
                    <select
                        value={state}
                        onChange={(event) => setState(event.target.value)}
                    >
                        <option value="">Todos los estados</option>
                        {states.map((item) => (
                            <option key={item}>{item}</option>
                        ))}
                    </select>
                </label>
                <label>
                    Edad
                    <select
                        value={age}
                        onChange={(event) => setAge(event.target.value)}
                    >
                        <option value="">Todas las edades</option>
                        <option value="18-29">18 a 29 años</option>
                        <option value="30-39">30 a 39 años</option>
                        <option value="40+">40 años o más</option>
                    </select>
                </label>
                <label>
                    Tipo de registro
                    <select
                        value={type}
                        onChange={(event) => setType(event.target.value)}
                    >
                        <option value="">Todos los registros</option>
                        <option>Persona desaparecida</option>
                        <option>Solicitud de identificación</option>
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
            <div className="en-database-summary">
                <span role="status" aria-live="polite">
                    {records.length}{' '}
                    {records.length === 1 ? 'resultado' : 'resultados'}
                    {activeFilters > 0 &&
                        ` · ${activeFilters} ${activeFilters === 1 ? 'filtro activo' : 'filtros activos'}`}
                </span>
            </div>
            <div className="en-database-list">
                {records.map((record) => (
                    <article className="en-person-card" key={record.id}>
                        <div className="en-person-portrait">
                            <img
                                src={record.portrait}
                                alt="Silueta de una persona"
                            />
                        </div>
                        <div className="en-person-info">
                            <span className="en-person-type">
                                {record.type}
                            </span>
                            <h3>{record.name}</h3>
                            <dl>
                                <div>
                                    <dt>Edad</dt>
                                    <dd>{record.age} años</dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd>{record.state}</dd>
                                </div>
                                <div>
                                    <dt>
                                        {record.type === 'Persona desaparecida'
                                            ? 'Desaparición'
                                            : 'Registro'}
                                    </dt>
                                    <dd>{record.date}</dd>
                                </div>
                            </dl>
                            <p>{record.description}</p>
                        </div>
                        <button
                            type="button"
                            className="en-person-open"
                            onClick={() => setSelected(record)}
                        >
                            Ver ficha <ArrowUpRight size={19} />
                        </button>
                    </article>
                ))}
                {!records.length && (
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
            <dialog
                {...dialog}
                className="en-dialog"
                aria-labelledby="record-dialog-title"
                onClick={(event) => {
                    if (event.target === event.currentTarget) setSelected(null);
                }}
            >
                {selected && (
                    <>
                        <div className="en-dialog-header">
                            <span>Ficha de registro</span>
                            <button
                                type="button"
                                onClick={() => setSelected(null)}
                                aria-label="Cerrar ficha"
                            >
                                <X size={23} />
                            </button>
                        </div>
                        <div className="en-dialog-content">
                            <div className="en-detail-portrait">
                                <img
                                    src={selected.portrait}
                                    alt="Silueta de una persona"
                                />
                            </div>
                            <span className="en-person-type">
                                {selected.type}
                            </span>
                            <h2 id="record-dialog-title">{selected.name}</h2>
                            <dl className="en-detail-data">
                                <div>
                                    <dt>Edad</dt>
                                    <dd>{selected.age} años</dd>
                                </div>
                                <div>
                                    <dt>Estado</dt>
                                    <dd>{selected.state}</dd>
                                </div>
                                <div>
                                    <dt>
                                        {selected.type ===
                                        'Persona desaparecida'
                                            ? 'Fecha de desaparición'
                                            : 'Fecha de registro'}
                                    </dt>
                                    <dd>{selected.date}</dd>
                                </div>
                            </dl>
                            <h3>Descripción</h3>
                            <p>{selected.description}</p>
                        </div>
                    </>
                )}
            </dialog>
        </div>
    );
}

export function StatisticsPanel() {
    const [group, setGroup] = useState('state');
    const [state, setState] = useState('');
    const records = exampleRecords.filter(
        (record) => !state || record.state === state,
    );
    const groups =
        group === 'state'
            ? states
            : ['18 a 29 años', '30 a 39 años', '40 años o más'];
    const bars = groups.map((label, index) => ({
        label,
        count: records.filter((record) =>
            group === 'state'
                ? record.state === label
                : index === 0
                  ? record.age < 30
                  : index === 1
                    ? record.age >= 30 && record.age < 40
                    : record.age >= 40,
        ).length,
    }));
    return (
        <section
            className="en-statistics"
            id="estadisticas"
            aria-labelledby="statistics-title"
        >
            <div className="en-statistics-inner">
                <div className="en-statistics-heading">
                    <div>
                        <div className="en-section-kicker en-kicker-light">
                            <span>02</span> / ESTADÍSTICAS
                        </div>
                        <h2 id="statistics-title">
                            MIRAR LOS DATOS.
                            <br />
                            <em>SEGUIR BUSCANDO.</em>
                        </h2>
                        <p>
                            Consulta cómo se distribuyen los registros.
                            Selecciona un estado para explorar sus datos.
                        </p>
                    </div>
                    <label className="en-statistics-state">
                        Estado
                        <select
                            value={state}
                            onChange={(event) => setState(event.target.value)}
                        >
                            <option value="">Todos los estados</option>
                            {states.map((item) => (
                                <option key={item}>{item}</option>
                            ))}
                        </select>
                    </label>
                </div>
                <p className="en-statistics-note">
                    Distribución de los registros según los filtros
                    seleccionados.
                </p>
                <div className="en-statistics-body">
                    <div className="en-statistics-totals" aria-live="polite">
                        <div>
                            <strong>{records.length}</strong>
                            <span>Registros en esta vista</span>
                        </div>
                        <div>
                            <strong>
                                {
                                    records.filter(
                                        (record) =>
                                            record.type ===
                                            'Persona desaparecida',
                                    ).length
                                }
                            </strong>
                            <span>Personas desaparecidas</span>
                        </div>
                        <div>
                            <strong>
                                {
                                    records.filter(
                                        (record) =>
                                            record.type ===
                                            'Solicitud de identificación',
                                    ).length
                                }
                            </strong>
                            <span>Solicitudes de identificación</span>
                        </div>
                    </div>
                    <div className="en-statistics-chart">
                        <div
                            className="en-chart-options"
                            aria-label="Agrupar estadísticas"
                        >
                            <button
                                type="button"
                                aria-pressed={group === 'state'}
                                onClick={() => setGroup('state')}
                            >
                                Por estado
                            </button>
                            <button
                                type="button"
                                aria-pressed={group === 'age'}
                                onClick={() => setGroup('age')}
                            >
                                Por edad
                            </button>
                        </div>
                        <div className="en-chart-bars" aria-live="polite">
                            {bars.map(({ label, count }) => (
                                <div className="en-chart-row" key={label}>
                                    <div>
                                        <span>{label}</span>
                                        <strong>
                                            {count}{' '}
                                            {count === 1
                                                ? 'registro'
                                                : 'registros'}
                                        </strong>
                                    </div>
                                    <div
                                        className="en-chart-track"
                                        aria-hidden="true"
                                    >
                                        <span
                                            style={{
                                                width: `${records.length ? (count / records.length) * 100 : 0}%`,
                                            }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}

export function RequestComposer({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const dialog = useDialog(open, onClose);
    const [preview, setPreview] = useState(false);
    const [type, setType] = useState('Búsqueda de una persona');
    const [name, setName] = useState('');
    const [place, setPlace] = useState('');
    const [description, setDescription] = useState('');
    const [contact, setContact] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [photoUrl, setPhotoUrl] = useState('');
    const [error, setError] = useState('');
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
        setPreview(true);
    };
    return (
        <dialog
            {...dialog}
            className="en-dialog en-request-dialog"
            aria-labelledby="request-dialog-title"
            onClick={(event) => {
                if (event.target === event.currentTarget) onClose();
            }}
        >
            <div className="en-dialog-header">
                <span>
                    <FilePlus2 size={19} /> Nueva solicitud
                </span>
                <button
                    type="button"
                    onClick={onClose}
                    aria-label="Cerrar solicitud"
                >
                    <X size={23} />
                </button>
            </div>
            <div className="en-dialog-content">
                <h2 ref={heading} tabIndex={-1} id="request-dialog-title">
                    {preview
                        ? 'Vista previa de tu solicitud'
                        : 'Crear solicitud'}
                </h2>
                {preview ? (
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
                        <span className="en-person-type">{type}</span>
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
                                onClick={onClose}
                            >
                                Cerrar vista previa
                            </button>
                        </div>
                    </div>
                ) : (
                    <>
                        <p>
                            Agrega la información que tengas. Podrás revisar la
                            solicitud antes de continuar.
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
                                    <option>Búsqueda de una persona</option>
                                    <option>
                                        Identificación de una persona
                                    </option>
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
