import WorkspaceLayout from '@/Layouts/WorkspaceLayout';
import { PageProps } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    verified: boolean;
    is_admin: boolean;
    created_at: string | null;
};

type Paginated = {
    data: AdminUser[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const messages: Record<string, string> = {
    'admin-granted': 'La persona ahora es administradora.',
    'admin-revoked': 'Quitamos el rol de administrador.',
    'verification-sent': 'Enviamos un nuevo enlace de verificación.',
    'user-verified': 'Marcamos el correo como verificado.',
};

export default function AdminUsers({
    auth,
    users,
    filters,
    flash,
}: PageProps<{ users: Paginated; filters: { q: string } }>) {
    const [query, setQuery] = useState(filters.q);
    const status = flash?.status ? messages[flash.status] : undefined;

    const search = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('admin.users'),
            query.trim() ? { q: query.trim() } : {},
            { preserveState: true },
        );
    };

    const act = (method: 'post' | 'patch', name: string, user: AdminUser) =>
        router[method](route(name, user.id), {}, { preserveScroll: true });

    return (
        <WorkspaceLayout active="admin-users" crumb="ADMINISTRACIÓN / USUARIOS">
            <Head title="Usuarios" />
            <main className="en-work-content">
                <div className="en-work-intro">
                    <div>
                        <span className="en-work-kicker">ADMINISTRACIÓN</span>
                        <h1>USUARIOS.</h1>
                        <p>
                            {users.total.toLocaleString('es-MX')} cuentas
                            registradas.
                        </p>
                    </div>
                </div>
                {status && (
                    <div
                        className="en-profile-status"
                        role="status"
                        style={{ marginTop: 24 }}
                    >
                        {status}
                    </div>
                )}
                <form
                    className="en-admin-toolbar"
                    role="search"
                    onSubmit={search}
                >
                    <input
                        type="search"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Buscar por nombre o correo"
                        aria-label="Buscar usuarios"
                    />
                    <button type="submit" className="en-profile-button">
                        Buscar
                    </button>
                </form>
                <div className="en-admin-table-scroll">
                    <table className="en-admin-table">
                        <thead>
                            <tr>
                                <th>USUARIO</th>
                                <th>ESTADO</th>
                                <th>ALTA</th>
                                <th>ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
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
                                    <td>
                                        <div className="en-admin-row-actions">
                                            {!user.verified && (
                                                <>
                                                    <button
                                                        type="button"
                                                        className="en-admin-mini"
                                                        onClick={() =>
                                                            act(
                                                                'post',
                                                                'admin.users.resend',
                                                                user,
                                                            )
                                                        }
                                                    >
                                                        Reenviar enlace
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="en-admin-mini"
                                                        onClick={() =>
                                                            act(
                                                                'post',
                                                                'admin.users.verify',
                                                                user,
                                                            )
                                                        }
                                                    >
                                                        Marcar verificado
                                                    </button>
                                                </>
                                            )}
                                            {user.id !== auth.user.id && (
                                                <button
                                                    type="button"
                                                    className="en-admin-mini"
                                                    onClick={() =>
                                                        act(
                                                            'patch',
                                                            'admin.users.admin',
                                                            user,
                                                        )
                                                    }
                                                >
                                                    {user.is_admin
                                                        ? 'Quitar admin'
                                                        : 'Hacer admin'}
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {users.data.length === 0 && (
                                <tr>
                                    <td colSpan={4}>
                                        No encontramos usuarios con ese dato.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="en-admin-pager">
                    {users.prev_page_url && (
                        <Link href={users.prev_page_url} preserveScroll>
                            ← Anterior
                        </Link>
                    )}
                    <span>
                        Página {users.current_page} de {users.last_page}
                    </span>
                    {users.next_page_url && (
                        <Link href={users.next_page_url} preserveScroll>
                            Siguiente →
                        </Link>
                    )}
                </div>
            </main>
        </WorkspaceLayout>
    );
}
