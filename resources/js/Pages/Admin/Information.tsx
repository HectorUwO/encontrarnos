import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { Head, Link } from '@inertiajs/react';

type Report = {
    id: number;
    message: string;
    phone: string | null;
    sender: string | null;
    sender_email: string | null;
    created_at: string | null;
    target: {
        kind: string;
        label: string;
        url: string;
        extra: string | null;
    } | null;
};

type Paginated = {
    data: Report[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function AdminInformation({ reports }: { reports: Paginated }) {
    return (
        <WorkspaceLayout
            active="admin-information"
            crumb="ADMINISTRACIÓN / INFORMACIÓN"
        >
            <Head title="Información recibida" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">ADMINISTRACIÓN</span>
                        <h1>INFORMACIÓN.</h1>
                        <p>
                            Lo que la gente compartió sobre fichas de personas
                            desaparecidas y sobre solicitudes. Cada mensaje
                            también te llegó por correo.
                        </p>
                    </div>
                </div>
                <div className="en-admin-review">
                    {reports.data.map((report) => (
                        <article className="en-profile-card" key={report.id}>
                            <span className="en-work-kicker">
                                {report.target?.kind ?? 'Ficha eliminada'} ·{' '}
                                {report.created_at}
                            </span>
                            <h2>
                                {report.target ? (
                                    <Link href={report.target.url}>
                                        {report.target.label}
                                    </Link>
                                ) : (
                                    'Sin destino'
                                )}
                            </h2>
                            <p style={{ whiteSpace: 'pre-line' }}>
                                {report.message}
                            </p>
                            <p className="en-admin-review-contact">
                                De {report.sender ?? 'cuenta eliminada'}
                                {report.sender_email &&
                                    ` (${report.sender_email})`}
                                {report.phone && ` · ${report.phone}`}
                            </p>
                            {report.target?.extra && (
                                <p className="en-admin-review-meta">
                                    {report.target.kind === 'Solicitud'
                                        ? 'Contacto de la solicitud: '
                                        : 'Autoridad responsable: '}
                                    {report.target.extra}
                                </p>
                            )}
                        </article>
                    ))}
                    {reports.data.length === 0 && (
                        <p className="en-admin-review-meta">
                            Todavía no has recibido información.
                        </p>
                    )}
                </div>
                <div className="en-admin-pager">
                    {reports.prev_page_url && (
                        <Link href={reports.prev_page_url}>← Anterior</Link>
                    )}
                    <span>
                        Página {reports.current_page} de {reports.last_page}
                    </span>
                    {reports.next_page_url && (
                        <Link href={reports.next_page_url}>Siguiente →</Link>
                    )}
                </div>
            </main>
        </WorkspaceLayout>
    );
}
