import { darkBackgroundSymbol } from '@/brand';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChartNoAxesColumnIncreasing,
    ClipboardCheck,
    FileText,
    Fingerprint,
    Inbox,
    LayoutDashboard,
    LogOut,
    Mail,
    Menu,
    ShieldCheck,
    UserRound,
    Users,
    UsersRound,
    X,
} from 'lucide-react';
import { PropsWithChildren, ReactNode, useState } from 'react';
import '../Pages/dashboard.css';

type Props = PropsWithChildren<{
    /** Texto del encabezado superior, p. ej. "PANEL / VISTA GENERAL". */
    crumb: string;
    /** Sección activa del menú lateral. */
    active:
        | 'overview'
        | 'profile'
        | 'admin'
        | 'admin-users'
        | 'admin-requests'
        | 'admin-information'
        | 'admin-mail';
    /** Elemento opcional a la derecha del encabezado superior. */
    tag?: ReactNode;
}>;

export default function WorkspaceLayout({
    crumb,
    active,
    tag,
    children,
}: Props) {
    const { auth } = usePage<PageProps>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const closeMenu = () => setMobileMenuOpen(false);

    return (
        <div className="en-workspace">
            <aside
                className={`en-work-sidebar ${mobileMenuOpen ? 'en-work-sidebar-open' : ''}`}
            >
                <Link href="/" className="en-work-brand">
                    <img src={darkBackgroundSymbol} alt="" />
                    <span>
                        encontrarnos<span>.</span>
                    </span>
                </Link>
                <div className="en-work-side-label">ÁREA DE TRABAJO</div>
                <nav className="en-work-nav" aria-label="Navegación del panel">
                    <Link
                        href={route('dashboard')}
                        className={
                            active === 'overview'
                                ? 'en-work-nav-active'
                                : undefined
                        }
                        onClick={closeMenu}
                    >
                        <LayoutDashboard size={18} /> Mi espacio
                    </Link>
                    <Link href={route('records')}>
                        <FileText size={18} /> Base de datos
                    </Link>
                    <Link href={route('statistics')}>
                        <ChartNoAxesColumnIncreasing size={18} /> Estadísticas
                    </Link>
                    <Link href={route('photo-search')}>
                        <Fingerprint size={18} /> Búsqueda por fotografía
                    </Link>
                    <Link href={route('requests')}>
                        <UsersRound size={18} /> Ver o crear solicitudes
                    </Link>
                    <Link
                        href={route('profile.edit')}
                        className={
                            active === 'profile'
                                ? 'en-work-nav-active'
                                : undefined
                        }
                        onClick={closeMenu}
                    >
                        <UserRound size={18} /> Mi cuenta
                    </Link>
                </nav>
                {auth.user.is_admin && (
                    <>
                        <div className="en-work-side-label en-work-side-label-sub">
                            ADMINISTRACIÓN
                        </div>
                        <nav
                            className="en-work-nav"
                            aria-label="Administración"
                        >
                            <Link
                                href={route('admin.dashboard')}
                                className={
                                    active === 'admin'
                                        ? 'en-work-nav-active'
                                        : undefined
                                }
                                onClick={closeMenu}
                            >
                                <ShieldCheck size={18} /> Panel admin
                            </Link>
                            <Link
                                href={route('admin.users')}
                                className={
                                    active === 'admin-users'
                                        ? 'en-work-nav-active'
                                        : undefined
                                }
                                onClick={closeMenu}
                            >
                                <Users size={18} /> Usuarios
                            </Link>
                            <Link
                                href={route('admin.requests')}
                                className={
                                    active === 'admin-requests'
                                        ? 'en-work-nav-active'
                                        : undefined
                                }
                                onClick={closeMenu}
                            >
                                <ClipboardCheck size={18} /> Solicitudes
                            </Link>
                            <Link
                                href={route('admin.information')}
                                className={
                                    active === 'admin-information'
                                        ? 'en-work-nav-active'
                                        : undefined
                                }
                                onClick={closeMenu}
                            >
                                <Inbox size={18} /> Información
                            </Link>
                            <Link
                                href={route('admin.mail')}
                                className={
                                    active === 'admin-mail'
                                        ? 'en-work-nav-active'
                                        : undefined
                                }
                                onClick={closeMenu}
                            >
                                <Mail size={18} /> Correos
                            </Link>
                        </nav>
                    </>
                )}
                <div className="en-work-sidebar-bottom">
                    <div className="en-work-user">
                        <span className="en-work-user-avatar">
                            {auth.user.name.charAt(0).toUpperCase()}
                        </span>
                        <span>
                            <strong>{auth.user.name}</strong>
                            <small>{auth.user.email}</small>
                        </span>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            aria-label="Cerrar sesión"
                        >
                            <LogOut size={17} />
                        </Link>
                    </div>
                </div>
            </aside>
            {mobileMenuOpen && (
                <button
                    className="en-work-overlay"
                    type="button"
                    aria-label="Cerrar menú"
                    onClick={closeMenu}
                />
            )}
            <div className="en-work-main" id="inicio">
                <header className="en-work-topbar">
                    <button
                        className="en-work-menu"
                        type="button"
                        aria-label={
                            mobileMenuOpen ? 'Cerrar menú' : 'Abrir menú'
                        }
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                    >
                        {mobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
                    </button>
                    <span>{crumb}</span>
                    <div>
                        {tag}
                        <Link href="/" aria-label="Ir al sitio público">
                            <ArrowUpRight size={18} />
                        </Link>
                    </div>
                </header>
                {children}
            </div>
        </div>
    );
}
