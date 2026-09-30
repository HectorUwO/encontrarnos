import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps, Paginated, PersonRequestItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';

type Contact = {
    email: string;
    phone: string | null;
    author: string | null;
    photo: string | null;
};

const tabs = [
    ['pending', 'Pendientes'],
    ['approved', 'Aprobadas'],
    ['rejected', 'Rechazadas'],
] as const;

const messages: Record<string, string> = {
    'request-approved': 'Publicamos la solicitud y avisamos a quien la envió.',
    'request-rejected': 'Rechazamos la solicitud y avisamos a quien la envió.',
    'request-pending': 'La solicitud volvió a pendiente.',
};

export default function AdminRequests({
    requests,
    status,
    counts,
    flash,
}: PageProps<{
    requests: Paginated<PersonRequestItem> & {
        contacts: Record<number, Contact>;
    };
    status: string;
    counts: Record<string, number>;
}>) {
    const notice = flash?.status ? messages[flash.status] : undefined;
    const decide = (item: PersonRequestItem, next: string) =>
        router.patch(
            route('admin.requests.update', item.id),
            { status: next },
            { preserveScroll: true },
        );

    return (
        <WorkspaceLayout
            active="admin-requests"
            crumb="ADMINISTRACIÓN / SOLICITUDES"
        >
            <Head title="Revisar solicitudes" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">
                            ENCONTRARNOS / ADMINISTRACIÓN
                        </span>
                        <h1>SOLICITUDES.</h1>
                        <p>
                            Revisa cada solicitud antes de publicarla. Al
                            decidir, avisamos por correo a quien la envió.
                        </p>
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
                <div className="en-admin-actions" role="tablist">
                    {tabs.map(([value, label]) => (
                        <Link
                            key={value}
                            href={route('admin.requests', { estado: value })}
                            className={`en-profile-button ${status === value ? '' : 'en-profile-button-ghost'}`}
                        >
                            {label} ({counts[value] ?? 0})
                        </Link>
                    ))}
                </div>
                <div className="en-admin-review">
                    {requests.data.map((item) => {
                        const contact = requests.contacts[item.id];

                        return (
                            <article className="en-profile-card" key={item.id}>
                                <span className="en-work-kicker">
                                    {item.reference} · {item.type_label}
                                </span>
                                <h2>
                                    {item.name ?? 'Persona sin identificar'}
                                </h2>
                                <p className="en-admin-review-meta">
                                    {[
                                        item.place,
                                        item.age !== null
                                            ? `${item.age} años`
                                            : null,
                                        item.sex_label,
                                        item.event_date_label,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                                {contact?.photo && (
                                    <img
                                        className="en-admin-review-photo"
                                        src={contact.photo}
                                        alt={`Fotografía de ${item.reference}`}
                                    />
                                )}
                                <p>{item.description}</p>
                                {item.traits.length > 0 && (
                                    <p className="en-admin-review-meta">
                                        {item.traits
                                            .map(
                                                (trait) =>
                                                    `${trait.label}: ${trait.value}`,
                                            )
                                            .join(' · ')}
                                    </p>
                                )}
                                {item.distinguishing_marks && (
                                    <p className="en-admin-review-meta">
                                        Señas: {item.distinguishing_marks}
                                    </p>
                                )}
                                {item.institution && (
                                    <p className="en-admin-review-meta">
                                        Institución: {item.institution}
                                    </p>
                                )}
                                <p className="en-admin-review-contact">
                                    Contacto: {contact?.email}
                                    {contact?.phone && ` · ${contact.phone}`}
                                    {contact?.author &&
                                        ` · cuenta ${contact.author}`}
                                </p>
                                <div className="en-profile-actions">
                                    {item.status !== 'approved' && (
                                        <button
                                            type="button"
                                            className="en-profile-button"
                                            onClick={() =>
                                                decide(item, 'approved')
                                            }
                                        >
                                            Aprobar y publicar
                                        </button>
                                    )}
                                    {item.status !== 'rejected' && (
                                        <button
                                            type="button"
                                            className="en-profile-button en-profile-button-danger"
                                            onClick={() =>
                                                decide(item, 'rejected')
                                            }
                                        >
                                            Rechazar
                                        </button>
                                    )}
                                    <Link
                                        href={item.url}
                                        className="en-profile-button en-profile-button-ghost"
                                    >
                                        Ver ficha
                                    </Link>
                                </div>
                            </article>
                        );
                    })}
                    {requests.data.length === 0 && (
                        <p className="en-admin-review-meta">
                            No hay solicitudes en esta sección.
                        </p>
                    )}
                </div>
                {requests.meta.last_page > 1 && (
                    <div className="en-admin-pager">
                        {requests.links.prev && (
                            <Link href={requests.links.prev}>← Anterior</Link>
                        )}
                        <span>
                            Página {requests.meta.current_page} de{' '}
                            {requests.meta.last_page}
                        </span>
                        {requests.links.next && (
                            <Link href={requests.links.next}>Siguiente →</Link>
                        )}
                    </div>
                )}
            </main>
        </WorkspaceLayout>
    );
}
