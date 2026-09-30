import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Mail, Users } from 'lucide-react';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    is_admin: boolean;
    created_at: string | null;
};

const stats = (totals: Record<string, number>) => [
    ['USUARIOS', totals.users],
    ['CORREOS VERIFICADOS', totals.verified],
    ['ADMINISTRADORES', totals.admins],
    ['SOLICITUDES', totals.requests],
    ['CORREOS ENVIADOS', totals.emails_sent],
];

export default function AdminIndex({
    totals,
    latestUsers,
}: PageProps<{
    totals: Record<string, number>;
    latestUsers: AdminUser[];
}>) {
    return (
        <WorkspaceLayout active="admin" crumb="ADMINISTRACIÓN / PANEL">
            <Head title="Panel de administración" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">
                            ENCONTRARNOS / ADMINISTRACIÓN
                        </span>
                        <h1>PANEL ADMIN.</h1>
                        <p>
                            Revisa las cuentas, administra roles y envía correos
                            a la comunidad.
                        </p>
                    </div>
                </div>
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
                <div className="en-admin-actions">
                    <Link
                        href={route('admin.mail')}
                        className="en-profile-button"
                    >
                        <Mail size={15} style={{ marginRight: 8 }} /> Enviar
                        correos
                    </Link>
                    <Link
                        href={route('admin.users')}
                        className="en-profile-button en-profile-button-ghost"
                    >
                        <Users size={15} style={{ marginRight: 8 }} /> Ver
                        usuarios
                    </Link>
                </div>
                <section style={{ marginTop: 40 }}>
                    <span className="en-work-kicker">ÚLTIMAS CUENTAS</span>
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
                                                    : 'Pendiente'}
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
