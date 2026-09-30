import { colorSymbol } from '@/brand';
import { ActionGrid } from '@/Components/Encontrarnos/PublicTools';
import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps, PersonRecord, PersonRequestItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    ChartNoAxesColumnIncreasing,
    ImagePlus,
    Search,
    ShieldCheck,
    X,
} from 'lucide-react';
import { FormEvent, useState } from 'react';
import './dashboard.css';

const placeOf = (record: PersonRecord) =>
    [record.municipality, record.state_label].filter(Boolean).join(', ') ||
    'Sin dato';

export default function Dashboard({
    totals,
    latestRecords,
    myRequests,
    emailVerified,
}: PageProps<{
    emailVerified?: boolean;
    totals: { records: number; my_requests: number };
    latestRecords: { data: PersonRecord[] };
    myRequests: { data: PersonRequestItem[] };
}>) {
    const [query, setQuery] = useState('');
    const [bannerClosed, setBannerClosed] = useState(false);
    const records = latestRecords.data;
    const requests = myRequests.data;

    const search = (event: FormEvent) => {
        event.preventDefault();
        const term = query.trim();
        router.get(route('records'), term ? { q: term } : {});
    };

    return (
        <WorkspaceLayout
            active="overview"
            crumb="PANEL / VISTA GENERAL"
            tag={
                <span className="en-work-tag">
                    <span /> {totals.records.toLocaleString('es-MX')} FICHAS
                    PÚBLICAS
                </span>
            }
        >
            <Head title="Panel de consulta" />
            <main className="en-work-content">
                {emailVerified && !bannerClosed && (
                    <div className="en-work-banner" role="status">
                        <BadgeCheck size={20} aria-hidden="true" />
                        <span>
                            <strong>
                                Tu correo fue verificado correctamente.
                            </strong>{' '}
                            Ya puedes usar tu cuenta.
                        </span>
                        <button
                            type="button"
                            aria-label="Cerrar aviso"
                            onClick={() => setBannerClosed(true)}
                        >
                            <X size={16} />
                        </button>
                    </div>
                )}
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">
                            ENCONTRARNOS / PANEL DE CONSULTA
                        </span>
                        <h1>SEGUIR BUSCANDO.</h1>
                        <p>
                            Consulta la base de datos, revisa estadísticas o
                            busca con una fotografía. También puedes ver y
                            preparar solicitudes.
                        </p>
                    </div>
                    <div className="en-work-intro-mark">
                        <img src={colorSymbol} alt="" />
                    </div>
                </div>
                <div id="herramientas">
                    <ActionGrid />
                </div>
                <section className="en-work-registers" id="registros">
                    <div className="en-work-section-top">
                        <div>
                            <span className="en-work-kicker">
                                MÓDULO DE REGISTROS
                            </span>
                            <h2>Últimas fichas</h2>
                        </div>
                        <span className="en-work-sample-label">
                            {totals.records.toLocaleString('es-MX')} FICHAS
                            PÚBLICAS
                        </span>
                    </div>
                    <form
                        className="en-work-search"
                        role="search"
                        onSubmit={search}
                    >
                        <Search size={18} aria-hidden="true" />
                        <input
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Buscar por nombre, folio o lugar"
                            aria-label="Buscar en la base de datos"
                        />
                    </form>
                    <div className="en-work-table">
                        <div className="en-work-table-head">
                            <span>FICHA</span>
                            <span>UBICACIÓN</span>
                            <span>TIPO</span>
                            <span>FECHA</span>
                        </div>
                        {records.length ? (
                            records.map((record) => (
                                <Link
                                    className="en-work-table-row"
                                    key={record.folio}
                                    href={route('records', {
                                        q: record.folio,
                                    })}
                                >
                                    <div className="en-work-table-identity">
                                        <span className="en-work-table-avatar">
                                            <img
                                                src={record.portrait}
                                                alt=""
                                                className={
                                                    record.has_photo
                                                        ? 'is-photo'
                                                        : undefined
                                                }
                                                loading="lazy"
                                                decoding="async"
                                            />
                                        </span>
                                        <strong>
                                            {record.name}
                                            <small>{record.folio}</small>
                                        </strong>
                                    </div>
                                    <span>{placeOf(record)}</span>
                                    <span>
                                        {record.type_label}
                                        {record.status_label && (
                                            <small>{record.status_label}</small>
                                        )}
                                    </span>
                                    <span>
                                        {record.event_date_label ?? 'Sin fecha'}
                                    </span>
                                </Link>
                            ))
                        ) : (
                            <div className="en-work-empty">
                                Todavía no hay fichas públicas.
                            </div>
                        )}
                    </div>
                    <div className="en-work-table-note">
                        <ShieldCheck size={15} /> Solo se muestran las fichas
                        que el registro nacional marca como públicas.
                    </div>
                </section>
                <div className="en-work-lower">
                    <section id="solicitudes">
                        <div className="en-work-lower-icon">
                            <ImagePlus size={22} />
                        </div>
                        <span className="en-work-kicker">
                            MIS SOLICITUDES
                            {totals.my_requests > 0 &&
                                ` · ${totals.my_requests}`}
                        </span>
                        <h2>COMPARTIR LO QUE SABEMOS.</h2>
                        {requests.length ? (
                            <ul className="en-work-requests">
                                {requests.slice(0, 3).map((request) => (
                                    <li key={request.id}>
                                        <strong>
                                            {request.name ?? request.type_label}
                                        </strong>
                                        <small>
                                            {request.reference} ·{' '}
                                            {request.status_label}
                                        </small>
                                    </li>
                                ))}
                            </ul>
                        ) : (
                            <p>
                                Todavía no has enviado solicitudes. Comparte una
                                descripción, una fotografía y un canal de
                                contacto para dar seguimiento.
                            </p>
                        )}
                        <Link href={route('requests')}>
                            Ver o crear solicitudes <ArrowRight size={17} />
                        </Link>
                    </section>
                    <section>
                        <div className="en-work-lower-icon">
                            <ChartNoAxesColumnIncreasing size={22} />
                        </div>
                        <span className="en-work-kicker">ESTADÍSTICAS</span>
                        <h2>MIRAR LOS DATOS.</h2>
                        <p>
                            Explora el mapa del país, el histórico y el perfil
                            de las personas por entidad y por año.
                        </p>
                        <Link href={route('statistics')}>
                            Ver estadísticas <ArrowRight size={17} />
                        </Link>
                    </section>
                </div>
                <footer className="en-work-footer">
                    Encontrarnos · Panel de consulta{' '}
                    <span>
                        Fuente: Registro Nacional de Personas Desaparecidas y No
                        Localizadas
                    </span>
                </footer>
            </main>
        </WorkspaceLayout>
    );
}
