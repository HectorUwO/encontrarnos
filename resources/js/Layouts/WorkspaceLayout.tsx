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
    LucideIcon,
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

type Section =
    | 'overview'
    | 'profile'
    | 'admin'
    | 'admin-users'
    | 'admin-requests'
    | 'admin-information'
    | 'admin-mail';

type Props = PropsWithChildren<{
    /** Texto del encabezado superior, p. ej. "PANEL / VISTA GENERAL". */
    crumb: string;
    /** Sección activa del menú lateral. */
    active: Section;
    /** Elemento opcional a la derecha del encabezado superior. */
    tag?: ReactNode;
}>;

type Item = {
    href: string;
    label: string;
    icon: LucideIcon;
    /** Sección del panel a la que pertenece; sin ella es un enlace externo al panel. */
    section?: Section;
    /** Cantidad por atender, mostrada como insignia. */
    badge?: number | null;
};

export default function WorkspaceLayout({
    crumb,
    active,
    tag,
    children,
}: Props) {
    const { auth, adminPending } = usePage<PageProps>().props;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const closeMenu = () => setMobileMenuOpen(false);

    const mine: Item[] = [
        {
            href: route('dashboard'),
            label: 'Mi espacio',
            icon: LayoutDashboard,
            section: 'overview',
        },
        {
            href: route('profile.edit'),
            label: 'Mi cuenta',
            icon: UserRound,
            section: 'profile',
        },
    ];
    const explore: Item[] = [
        { href: route('records'), label: 'Base de datos', icon: FileText },
        {
            href: route('statistics'),
            label: 'Estadísticas',
            icon: ChartNoAxesColumnIncreasing,
        },
        {
            href: route('photo-search'),
            label: 'Búsqueda por foto',
            icon: Fingerprint,
        },
        {
            href: route('requests'),
            label: 'Solicitudes públicas',
            icon: UsersRound,
        },
    ];
    const admin: Item[] = [
        {
            href: route('admin.dashboard'),
            label: 'Resumen',
            icon: ShieldCheck,
            section: 'admin',
        },
        {
            href: route('admin.requests'),
            label: 'Revisar solicitudes',
            icon: ClipboardCheck,
            section: 'admin-requests',
            badge: adminPending,
        },
        {
            href: route('admin.information'),
            label: 'Información recibida',
            icon: Inbox,
            section: 'admin-information',
        },
        {
            href: route('admin.users'),
            label: 'Usuarios',
            icon: Users,
            section: 'admin-users',
        },
        {
            href: route('admin.mail'),
            label: 'Enviar correos',
            icon: Mail,
            section: 'admin-mail',
        },
    ];

    const group = (label: string, items: Item[], first = false) => (
        <>
            <div
                className={`en-work-side-label ${first ? '' : 'en-work-side-label-sub'}`}
            >
                {label}
            </div>
            <nav className="en-work-nav" aria-label={label}>
                {items.map(
                    ({ href, label: text, icon: Icon, section, badge }) => (
                        <Link
                            key={href}
                            href={href}
                            className={
                                section !== undefined && active === section
                                    ? 'en-work-nav-active'
                                    : undefined
                            }
                            aria-current={
                                section !== undefined && active === section
                                    ? 'page'
                                    : undefined
                            }
                            onClick={closeMenu}
                        >
                            <Icon size={18} aria-hidden="true" /> {text}
                            {badge ? (
                                <span
                                    className="en-work-badge"
                                    aria-label={`${badge} por revisar`}
                                >
                                    {badge}
                                </span>
                            ) : null}
                        </Link>
                    ),
                )}
            </nav>
        </>
    );

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
                <div className="en-work-sidebar-scroll">
                    {auth.user.is_admin ? (
                        <>
                            {group('ADMINISTRACIÓN', admin, true)}
                            {group('MI ESPACIO', mine)}
                        </>
                    ) : (
                        group('MI ESPACIO', mine, true)
                    )}
                    {group('EXPLORAR EL SITIO', explore)}
                </div>
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
