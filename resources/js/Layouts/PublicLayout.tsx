import {
    MobileNavigation,
    actions,
} from '@/Components/Encontrarnos/PublicTools';
import {
    colorSymbol,
    darkBackgroundSymbol,
    lspLogo,
    mexicanFlag,
    utLogo,
} from '@/brand';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Menu, X } from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';
import '../Pages/welcome.css';

export function Brand({ light = false }: { light?: boolean }) {
    return (
        <span className="en-brand">
            <img src={light ? darkBackgroundSymbol : colorSymbol} alt="" />
            <span>
                encontrarnos<span className="en-brand-period">.</span>
            </span>
        </span>
    );
}

/**
 * Cabecera, pie y menú de las páginas públicas. Cada página lo declara como layout
 * persistente (`Pagina.layout = ...`), así que no se vuelve a montar al navegar: solo
 * cambia el contenido, que entra con un fundido suave (salvo en la portada, que ya
 * trae su propia animación).
 */
export default function PublicLayout({
    children,
    enter = true,
}: PropsWithChildren<{ enter?: boolean }>) {
    const { auth } = usePage<PageProps>().props;
    const { url } = usePage();
    const [menuOpen, setMenuOpen] = useState(false);
    const path = url.split('?')[0];

    useEffect(() => {
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setMenuOpen(false);
        };
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, []);

    // Con el layout persistente el menú móvil seguiría abierto en la página nueva.
    useEffect(() => setMenuOpen(false), [path]);

    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="en-page">
            <a href="#contenido" className="en-skip-link">
                Ir al contenido
            </a>
            <header className="en-header">
                <Link href="/" aria-label="Encontrarnos, ir al inicio">
                    <Brand />
                </Link>
                <nav
                    id="principal"
                    className={`en-nav ${menuOpen ? 'en-nav-open' : ''}`}
                    aria-label="Navegación principal"
                >
                    {actions.map(({ href, short }) => (
                        <Link
                            key={href}
                            href={href}
                            prefetch
                            aria-current={
                                url.startsWith(href) ? 'page' : undefined
                            }
                            onClick={closeMenu}
                        >
                            {short}
                        </Link>
                    ))}
                    <Link
                        className="en-nav-account"
                        href={route(auth.user ? 'dashboard' : 'login')}
                        onClick={closeMenu}
                    >
                        {auth.user ? 'Mi espacio' : 'Ingresar'}{' '}
                        <ArrowUpRight size={18} />
                    </Link>
                </nav>
                <button
                    className="en-menu-button"
                    type="button"
                    aria-controls="principal"
                    aria-expanded={menuOpen}
                    aria-label={menuOpen ? 'Cerrar menú' : 'Abrir menú'}
                    onClick={() => setMenuOpen(!menuOpen)}
                >
                    {menuOpen ? <X /> : <Menu />}
                </button>
            </header>

            <main id="contenido" tabIndex={-1}>
                <div key={path} className={enter ? 'en-page-enter' : undefined}>
                    {children}
                </div>
            </main>

            <footer className="en-footer">
                <div className="en-footer-top">
                    <div className="en-footer-call">
                        <div className="en-footer-brandrow">
                            <Link
                                href="/"
                                aria-label="Encontrarnos, volver al inicio"
                            >
                                <Brand light />
                            </Link>
                            <Link
                                className="en-footer-home"
                                href="/"
                                aria-label="Volver al inicio"
                            >
                                <ArrowUpRight size={28} />
                            </Link>
                        </div>
                        <p>
                            QUE LA BÚSQUEDA
                            <br />
                            <span>NO SE DETENGA.</span>
                        </p>
                    </div>
                    <div className="en-footer-main">
                        <div>
                            <p className="en-footer-title">Secciones</p>
                            {actions.map(({ href, short }) => (
                                <Link key={href} href={href} prefetch>
                                    {short}
                                </Link>
                            ))}
                        </div>
                        <div>
                            <p className="en-footer-title">Cuenta</p>
                            <Link
                                href={route(auth.user ? 'dashboard' : 'login')}
                            >
                                {auth.user ? 'Mi espacio' : 'Ingresar'}
                            </Link>
                            <Link
                                href={route(
                                    auth.user ? 'dashboard' : 'register',
                                )}
                            >
                                Crear cuenta
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="en-footer-bottom">
                    <div
                        className="en-footer-logos"
                        aria-label="Instituciones aliadas"
                    >
                        {[
                            [
                                'https://www.facebook.com/LSPUTNay',
                                lspLogo,
                                'Licenciatura en Seguridad Pública',
                            ],
                            [
                                'https://www.utnay.edu.mx/',
                                utLogo,
                                'Universidad Tecnológica de Nayarit',
                            ],
                        ].map(([href, src, alt]) => (
                            <a
                                key={href}
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={alt}
                            >
                                <img
                                    src={src}
                                    width={192}
                                    height={192}
                                    alt=""
                                    loading="lazy"
                                />
                            </a>
                        ))}
                    </div>
                    <div className="en-footer-legal">
                        <span className="en-footer-country">
                            <img
                                className="en-flag"
                                src={mexicanFlag}
                                width={120}
                                height={69}
                                alt="Bandera de México"
                            />
                            Encontrarnos · México · 2026
                        </span>
                        <span>Por quienes faltan. Con quienes buscan.</span>
                    </div>
                </div>
            </footer>
            <MobileNavigation />
        </div>
    );
}
