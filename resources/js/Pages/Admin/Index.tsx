import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, CircleCheck, Mail, UserCheck, Users } from 'lucide-react';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    is_admin: boolean;
    created_at: string | null;
};

type PendingRequest = {
    id: number;
    reference: string;
    name: string | null;
    type_label: string;
    place: string | null;
    waiting: string | null;
};

const stats = (totals: Record<string, number>) => [
    ['USUARIOS', totals.users],
    ['CON CORREO VERIFICADO', totals.verified],
    ['ADMINISTRADORES', totals.admins],
    ['SOLICITUDES PUBLICADAS', totals.published],
    ['SOLICITUDES RECIBIDAS', totals.requests],
    ['CORREOS ENVIADOS', totals.emails_sent],
];

export default function AdminIndex({
    pending,
    pendingRequests,
    totals,
    latestUsers,
}: PageProps<{
    pending: { requests: number; unverified: number };
    pendingRequests: PendingRequest[];
    totals: Record<string, number>;
    latestUsers: AdminUser[];
}>) {
    return (
        <WorkspaceLayout active="admin" crumb="ADMINISTRACIÓN / RESUMEN">
            <Head title="Resumen de administración" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">ADMINISTRACIÓN</span>
                        <h1>RESUMEN.</h1>
                        <p>
                            Lo que espera tu decisión y el estado general de la
                            plataforma.
                        </p>
                    </div>
                </div>

                <section
                    className="en-admin-todo"
                    aria-labelledby="por-atender"
                >
                    <span className="en-work-kicker" id="por-atender">
                        POR ATENDER
                    </span>
                    {pending.requests === 0 ? (
                        <p className="en-admin-clear">
                            <CircleCheck size={20} aria-hidden="true" />
                            No hay solicitudes esperando revisión.
                        </p>
                    ) : (
                        <>
                            <Link
                                href={route('admin.requests')}
                                className="en-admin-alert"
                            >
                                <strong>{pending.requests}</strong>
                                <span>
                                    {pending.requests === 1
                                        ? 'solicitud espera tu revisión'
                                        : 'solicitudes esperan tu revisión'}
                                </span>
                                <em>
                                    Revisar <ArrowRight size={16} />
                                </em>
                            </Link>
                            <ul className="en-admin-queue">
                                {pendingRequests.map((item) => (
                                    <li key={item.id}>
                                        <Link href={route('admin.requests')}>
                                            <span>
                                                <small>
                                                    {item.reference} ·{' '}
                                                    {item.type_label}
                                                </small>
                                                <strong>
                                                    {item.name ??
                                                        'Persona sin identificar'}
                                                </strong>
                                                {item.place && (
                                                    <small>{item.place}</small>
                                                )}
                                            </span>
                                            <time>{item.waiting}</time>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                    {pending.unverified > 0 && (
                        <p className="en-admin-note">
                            <UserCheck size={16} aria-hidden="true" />
                            {pending.unverified === 1
                                ? '1 usuario sin verificar su correo.'
                                : `${pending.unverified} usuarios sin verificar su correo.`}{' '}
                            <Link href={route('admin.users')}>
                                Ver usuarios
                            </Link>
                        </p>
                    )}
                </section>

                <section className="en-admin-section">
                    <span className="en-work-kicker">EN NÚMEROS</span>
                    <div className="en-admin-stats">
                        {stats(totals).map(([label, value]) => (
                            <div className="en-admin-stat" key={label}>
                                <span>{label}</span>
                                <strong>
                                    {Number(value).toLocaleString('es-MX')}
                                </strong>
                            </div>
                        ))}
                    </div>
                </section>

                <div className="en-admin-actions">
                    <Link
                        href={route('admin.mail')}
                        className="en-profile-button"
                    >
                        <Mail size={16} aria-hidden="true" /> Enviar correos
                    </Link>
                    <Link
                        href={route('admin.users')}
                        className="en-profile-button en-profile-button-ghost"
                    >
                        <Users size={16} aria-hidden="true" /> Ver usuarios
                    </Link>
                </div>

                <section className="en-admin-section">
                    <span className="en-work-kicker">ÚLTIMOS USUARIOS</span>
                    <div className="en-admin-table-scroll">
                        <table className="en-admin-table">
                            <thead>
                                <tr>
                                    <th>USUARIO</th>
                                    <th>ESTADO</th>
                                    <th>ALTA</th>
                                </tr>
                            </thead>
                            <tbody>
                                {latestUsers.map((user) => (
                                    <tr key={user.id}>
                                        <td>
                                            {user.name}
                                            <small>{user.email}</small>
                                        </td>
                                        <td>
                                            <span
                                                className={`en-admin-badge ${user.verified ? '' : 'en-admin-badge-warn'}`}
                                            >
                                                {user.verified
                                                    ? 'Verificado'
                                                    : 'Sin verificar'}
                                            </span>{' '}
                                            {user.is_admin && (
                                                <span className="en-admin-badge en-admin-badge-dark">
                                                    Admin
                                                </span>
                                            )}
                                        </td>
                                        <td>{user.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </WorkspaceLayout>
    );
}
