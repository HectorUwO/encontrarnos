import { classNames } from '@/classNames';
import { RequestChip } from '@/Components/Encontrarnos/RequestCatalog';
import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps, PersonRequestItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    BadgeCheck,
    ChartNoAxesColumnIncreasing,
    Check,
    Inbox,
    Mail,
    PenLine,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import './dashboard.css';
import './my-space.css';

type MyRequest = PersonRequestItem & {
    reports_count: number;
    unattended_count: number;
    updated_at_label: string | null;
};

type Report = {
    id: number;
    message: string;
    phone: string | null;
    sender: string;
    sender_email: string | null;
    request_id: number;
    request_name: string | null;
    reference: string | null;
    attended: boolean;
    created_at_label: string | null;
};

type Summary = {
    total: number;
    published: number;
    pending: number;
    closed: number;
    unattended: number;
    records: number;
};

const notices: Record<string, string> = {
    'request-updated': 'Guardamos los cambios de tu solicitud.',
    'request-updated-review':
        'Guardamos los cambios. Tu solicitud volvió a revisión y se publicará de nuevo cuando la aprobemos.',
    'request-resolved': 'Marcamos tu solicitud como resuelta. ¡Nos alegra!',
    'request-withdrawn':
        'Diste de baja tu solicitud: ya no aparece en el catálogo. Puedes reabrirla cuando quieras.',
    'request-reopened': 'Reabrimos tu solicitud.',
    'request-deleted': 'Eliminamos tu solicitud y su fotografía.',
    'information-attended': 'Marcaste la información como atendida.',
    'information-reopened': 'La información volvió a pendiente.',
};

function statusOf(request: MyRequest): { label: string; tone: string } {
    if (request.closed) {
        return request.closed_reason === 'resolved'
            ? { label: 'Resuelta', tone: 'done' }
            : { label: 'Dada de baja', tone: 'muted' };
    }
    return (
        {
            pending: { label: 'En revisión', tone: 'warn' },
            approved: { label: 'Publicada', tone: 'ok' },
            rejected: { label: 'Rechazada', tone: 'bad' },
        }[request.status] ?? { label: request.status_label, tone: 'muted' }
    );
}

function Steps({ request }: { request: MyRequest }) {
    const steps = [
        { label: 'Enviada', state: 'done' },
        {
            label: request.status === 'rejected' ? 'Rechazada' : 'Revisión',
            state:
                request.status === 'pending'
                    ? 'current'
                    : request.status === 'rejected'
                      ? 'bad'
                      : 'done',
        },
        {
            label: 'Publicada',
            state:
                request.status === 'approved'
                    ? request.closed
                        ? 'done'
                        : 'current'
                    : 'todo',
        },
        ...(request.closed
            ? [
                  {
                      label:
                          request.closed_reason === 'resolved'
                              ? 'Resuelta'
                              : 'Dada de baja',
                      state: 'current',
                  },
              ]
            : []),
    ];

    return (
        <ol className="my-steps" aria-label="Estado de la solicitud">
            {steps.map((step) => (
                <li key={step.label} className={`is-${step.state}`}>
                    <span aria-hidden="true">
                        {step.state === 'done' ? (
                            <Check size={12} />
                        ) : step.state === 'bad' ? (
                            <X size={12} />
                        ) : null}
                    </span>
                    {step.label}
                </li>
            ))}
        </ol>
    );
}

function RequestCard({ request }: { request: MyRequest }) {
    const [confirming, setConfirming] = useState<'close' | 'delete' | null>(
        null,
    );
    const status = statusOf(request);
    const visit = (
        method: 'patch' | 'delete',
        name: string,
        data: Record<string, string> = {},
    ) => {
        setConfirming(null);
        router[method](
            route(name, request.id),
            method === 'patch' ? data : {},
            {
                preserveScroll: true,
            } as never,
        );
    };

    return (
        <article className="my-request">
            <header>
                <div className="req-cardhead">
                    <RequestChip type={request.type} />
                    <span className="req-ref">{request.reference}</span>
                </div>
                <span className={`my-status my-status--${status.tone}`}>
                    {status.label}
                </span>
            </header>
            <h3>{request.name ?? 'Persona sin identificar'}</h3>
            <p className="my-request-meta">
                {[request.place, request.event_date_label]
                    .filter(Boolean)
                    .join(' · ')}
            </p>
            <Steps request={request} />
            {request.status === 'rejected' && (
                <p className="my-hint">
                    No pudimos publicarla. Edítala con más datos y se enviará de
                    nuevo a revisión.
                </p>
            )}
            {request.status === 'pending' && (
                <p className="my-hint">
                    La estamos revisando. Te avisaremos por correo cuando esté
                    lista.
                </p>
            )}
            <p className="my-request-info">
                <Mail size={15} aria-hidden="true" />
                {request.reports_count === 0
                    ? 'Sin mensajes todavía'
                    : `${request.reports_count} ${request.reports_count === 1 ? 'mensaje' : 'mensajes'}`}
                {request.unattended_count > 0 && (
                    <strong>{request.unattended_count} sin atender</strong>
                )}
            </p>

            {confirming ? (
                <div className="my-confirm" role="alert">
                    <p>
                        {confirming === 'delete'
                            ? 'Se eliminará la solicitud y su fotografía de forma definitiva. Esta acción no se puede deshacer.'
                            : 'Dejará de aparecer en el catálogo y nadie podrá enviarte información. Puedes reabrirla cuando quieras.'}
                    </p>
                    <div>
                        <button
                            type="button"
                            className={classNames(
                                'my-button',
                                confirming === 'delete' && 'my-button--danger',
                            )}
                            onClick={() =>
                                confirming === 'delete'
                                    ? visit('delete', 'mine.requests.destroy')
                                    : visit('patch', 'mine.requests.close', {
                                          reason: 'withdrawn',
                                      })
                            }
                        >
                            {confirming === 'delete'
                                ? 'Sí, eliminar'
                                : 'Sí, dar de baja'}
                        </button>
                        <button
                            type="button"
                            className="my-button my-button--ghost"
                            onClick={() => setConfirming(null)}
                        >
                            Cancelar
                        </button>
                    </div>
                </div>
            ) : (
                <div className="my-actions">
                    <Link
                        href={request.url}
                        className="my-button my-button--ghost"
                    >
                        Ver ficha <ArrowUpRight size={14} />
                    </Link>
                    <Link
                        href={route('mine.requests.edit', request.id)}
                        className="my-button my-button--ghost"
                    >
                        <PenLine size={14} /> Editar
                    </Link>
                    {request.closed ? (
                        <button
                            type="button"
                            className="my-button"
                            onClick={() =>
                                visit('patch', 'mine.requests.reopen')
                            }
                        >
                            Reabrir
                        </button>
                    ) : (
                        <>
                            {request.status === 'approved' && (
                                <button
                                    type="button"
                                    className="my-button"
                                    onClick={() =>
                                        visit('patch', 'mine.requests.close', {
                                            reason: 'resolved',
                                        })
                                    }
                                >
                                    <Check size={14} /> Marcar resuelta
                                </button>
                            )}
                            <button
                                type="button"
                                className="my-button my-button--ghost"
                                onClick={() => setConfirming('close')}
                            >
                                Dar de baja
                            </button>
                        </>
                    )}
                    <button
                        type="button"
                        className="my-button my-button--icon"
                        aria-label="Eliminar solicitud"
                        title="Eliminar"
                        onClick={() => setConfirming('delete')}
                    >
                        <Trash2 size={15} />
                    </button>
                </div>
            )}
        </article>
    );
}

export default function Dashboard({
    auth,
    summary,
    myRequests,
    reports,
    emailVerified,
    flash,
}: PageProps<{
    summary: Summary;
    myRequests: MyRequest[];
    reports: Report[];
    emailVerified?: boolean;
}>) {
    const [onlyPending, setOnlyPending] = useState(summary.unattended > 0);
    const [bannerClosed, setBannerClosed] = useState(false);
    const notice = flash?.status ? notices[flash.status] : undefined;
    const shownReports = onlyPending
        ? reports.filter((report) => !report.attended)
        : reports;
    const firstName = auth.user.name.split(' ')[0];

    const tiles: [string, number, boolean?][] = [
        ['MIS SOLICITUDES', summary.total],
        ['PUBLICADAS', summary.published],
        ['EN REVISIÓN', summary.pending],
        ['INFORMACIÓN SIN ATENDER', summary.unattended, summary.unattended > 0],
    ];

    return (
        <WorkspaceLayout active="overview" crumb="MI ESPACIO / SEGUIMIENTO">
            <Head title="Mi espacio" />
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
                            ENCONTRARNOS / MI ESPACIO
                        </span>
                        <h1>SEGUIMIENTO.</h1>
                        <p>
                            Hola, {firstName}. Aquí ves cómo van tus
                            solicitudes, atiendes la información que llega y las
                            editas, das de baja o cierras cuando sea necesario.
                        </p>
                    </div>
                    <div className="my-cta">
                        <Link
                            href={route('requests.create', { tipo: 'search' })}
                            className="en-profile-button"
                        >
                            <Plus size={15} style={{ marginRight: 8 }} />
                            Busco a una persona
                        </Link>
                        <Link
                            href={route('requests.create', {
                                tipo: 'identification',
                            })}
                            className="en-profile-button en-profile-button-ghost"
                        >
                            <Plus size={15} style={{ marginRight: 8 }} />
                            Quiero identificar a alguien
                        </Link>
                    </div>
                </div>

                {notice && (
                    <div
                        className="en-profile-status"
                        role="status"
                        style={{ marginTop: 24 }}
                    >
                        {notice}
                    </div>
                )}

                <div className="en-admin-stats">
                    {tiles.map(([label, value, alert]) => (
                        <div
                            className={classNames(
                                'en-admin-stat',
                                alert && 'my-stat--alert',
                            )}
                            key={label}
                        >
                            <span>{label}</span>
                            <strong>{value.toLocaleString('es-MX')}</strong>
                        </div>
                    ))}
                </div>

                <div className="my-grid">
                    <section aria-labelledby="my-requests-title">
                        <div className="my-section-head">
                            <h2 id="my-requests-title">Mis solicitudes</h2>
                        </div>
                        {myRequests.length === 0 ? (
                            <div className="my-empty">
                                <h3>Todavía no tienes solicitudes</h3>
                                <p>
                                    Publica una solicitud de búsqueda o de
                                    identificación y aquí podrás darle
                                    seguimiento.
                                </p>
                                <Link
                                    href={route('requests.create')}
                                    className="en-profile-button"
                                >
                                    Crear mi primera solicitud
                                </Link>
                            </div>
                        ) : (
                            <div className="my-list">
                                {myRequests.map((request) => (
                                    <RequestCard
                                        key={request.id}
                                        request={request}
                                    />
                                ))}
                            </div>
                        )}
                    </section>

                    <section
                        className="my-inbox"
                        aria-labelledby="my-inbox-title"
                    >
                        <div className="my-section-head">
                            <h2 id="my-inbox-title">
                                <Inbox size={20} /> Información recibida
                            </h2>
                            <div className="my-tabs" role="tablist">
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={onlyPending}
                                    className={classNames(
                                        onlyPending && 'is-on',
                                    )}
                                    onClick={() => setOnlyPending(true)}
                                >
                                    Sin atender
                                </button>
                                <button
                                    type="button"
                                    role="tab"
                                    aria-selected={!onlyPending}
                                    className={classNames(
                                        !onlyPending && 'is-on',
                                    )}
                                    onClick={() => setOnlyPending(false)}
                                >
                                    Todas
                                </button>
                            </div>
                        </div>
                        {shownReports.length === 0 ? (
                            <div className="my-empty my-empty--small">
                                <p>
                                    {reports.length === 0
                                        ? 'Cuando alguien comparta información sobre tus solicitudes, la verás aquí y te llegará por correo.'
                                        : 'No tienes información pendiente. ¡Todo atendido!'}
                                </p>
                            </div>
                        ) : (
                            <ul className="my-reports">
                                {shownReports.map((report) => (
                                    <li
                                        key={report.id}
                                        className={classNames(
                                            report.attended && 'is-attended',
                                        )}
                                    >
                                        <div className="my-report-head">
                                            <strong>{report.sender}</strong>
                                            <span>
                                                {report.created_at_label}
                                            </span>
                                        </div>
                                        <p className="my-report-about">
                                            Sobre{' '}
                                            <Link
                                                href={route(
                                                    'requests.show',
                                                    report.request_id,
                                                )}
                                            >
                                                {report.reference} ·{' '}
                                                {report.request_name ??
                                                    'persona sin identificar'}
                                            </Link>
                                        </p>
                                        <p className="my-report-message">
                                            {report.message}
                                        </p>
                                        <div className="my-report-actions">
                                            {report.sender_email && (
                                                <a
                                                    href={`mailto:${report.sender_email}?subject=${encodeURIComponent(`Sobre la solicitud ${report.reference}`)}`}
                                                    className="my-button"
                                                >
                                                    <Mail size={14} /> Responder
                                                </a>
                                            )}
                                            {report.phone && (
                                                <a
                                                    href={`tel:${report.phone}`}
                                                    className="my-button my-button--ghost"
                                                >
                                                    {report.phone}
                                                </a>
                                            )}
                                            <button
                                                type="button"
                                                className="my-button my-button--ghost"
                                                onClick={() =>
                                                    router.patch(
                                                        route(
                                                            'mine.information.attend',
                                                            report.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                {report.attended
                                                    ? 'Reabrir'
                                                    : 'Marcar atendida'}
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="my-safety">
                            Verifica la información antes de actuar y, si hay un
                            riesgo inmediato, llama al 911.
                        </p>
                    </section>
                </div>

                <nav className="my-shortcuts" aria-label="Accesos rápidos">
                    <Link href={route('records')}>
                        <Search size={18} />
                        <span>
                            <strong>Base de datos</strong>
                            {summary.records.toLocaleString('es-MX')} fichas
                            públicas
                        </span>
                    </Link>
                    <Link href={route('requests')}>
                        <Inbox size={18} />
                        <span>
                            <strong>Catálogo de solicitudes</strong>
                            Consulta las de otras personas
                        </span>
                    </Link>
                    <Link href={route('statistics')}>
                        <ChartNoAxesColumnIncreasing size={18} />
                        <span>
                            <strong>Estadísticas</strong>
                            Mapa, histórico y perfil
                        </span>
                    </Link>
                </nav>
            </main>
        </WorkspaceLayout>
    );
}
