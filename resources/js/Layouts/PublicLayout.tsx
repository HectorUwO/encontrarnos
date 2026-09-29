import {
    MobileNavigation,
    actions,
} from '@/Components/Encontrarnos/PublicTools';
import { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Menu, X } from 'lucide-react';
import { PropsWithChildren, useEffect, useState } from 'react';
import '../Pages/welcome.css';

export function Brand({ light = false }: { light?: boolean }) {
    return (
        <span className="en-brand">
            <img src={light ? '/3.png' : '/1.png'} alt="" />
            <span>
                encontrarnos<span className="en-brand-period">.</span>
            </span>
        </span>
    );
}

export default function PublicLayout({ children }: PropsWithChildren) {
    const { auth } = usePage<PageProps>().props;
    const { url } = usePage();
    const [menuOpen, setMenuOpen] = useState(false);

    useEffect(() => {
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setMenuOpen(false);
        };
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, []);

    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="en-page">
            <a href="#contenido" className="en-skip-link">
                Ir al contenido
            </a>
            <div className="en-topline">
                <span>MÉXICO</span>
                <span>Buscar. Identificar. Compartir.</span>
                <span>LA BÚSQUEDA NOS CONCIERNE.</span>
            </div>
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

            <main id="contenido">{children}</main>

            <footer className="en-footer">
                <div className="en-footer-call">
                    <p>
                        QUE LA BÚSQUEDA
                        <br />
                        <span>NO SE DETENGA.</span>
                    </p>
                    <Link href="/" aria-label="Volver al inicio">
                        <ArrowUpRight size={54} />
                    </Link>
                </div>
                <div className="en-footer-main">
                    <Link href="/" aria-label="Encontrarnos, volver al inicio">
                        <Brand light />
                    </Link>
                    <div>
                        {actions.map(({ href, short }) => (
                            <Link key={href} href={href}>
                                {short}
                            </Link>
                        ))}
                    </div>
                    <div>
                        <Link href={route(auth.user ? 'dashboard' : 'login')}>
                            {auth.user ? 'Mi espacio' : 'Ingresar'}
                        </Link>
                        <Link
                            href={route(auth.user ? 'dashboard' : 'register')}
                        >
                            Crear cuenta
                        </Link>
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
                                '/LSPlogo-footer.png',
                                'Licenciatura en Seguridad Pública',
                            ],
                            [
                                'https://www.utnay.edu.mx/',
                                '/UTlogo.png',
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
                                <img src={src} alt="" loading="lazy" />
                            </a>
                        ))}
                    </div>
                    <div className="en-footer-legal">
                        <span className="en-footer-country">
                            <img
                                className="en-flag"
                                src="/flag.png"
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
