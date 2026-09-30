import { colorSymbol, darkBackgroundSymbol, lspLogo, utLogo } from '@/brand';
import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PropsWithChildren } from 'react';
import './auth.css';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="en-auth-shell">
            <aside className="en-auth-aside">
                <Link
                    href="/"
                    className="en-auth-brand"
                    aria-label="Encontrarnos, volver al inicio"
                >
                    <img src={darkBackgroundSymbol} alt="" />
                    <span>
                        encontrarnos<span>.</span>
                    </span>
                </Link>
                <div className="en-auth-aside-content">
                    <span className="en-auth-overline">
                        BÚSQUEDA E IDENTIFICACIÓN EN MÉXICO
                    </span>
                    <h1>
                        LA BÚSQUEDA
                        <br />
                        NOS REÚNE.
                    </h1>
                    <p>
                        Un espacio para quienes buscan y para quienes tienen
                        información que puede ayudar.
                    </p>
                </div>
                <div className="en-auth-solidarity">
                    <span>
                        POR QUIENES FALTAN.
                        <br />
                        CON QUIENES BUSCAN.
                    </span>
                    <div
                        className="en-auth-allies"
                        aria-label="Instituciones aliadas"
                    >
                        <a
                            href="https://www.facebook.com/LSPUTNay"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                src={lspLogo}
                                width={192}
                                height={192}
                                alt="Licenciatura en Seguridad Pública"
                            />
                        </a>
                        <a
                            href="https://www.utnay.edu.mx/"
                            target="_blank"
                            rel="noreferrer"
                        >
                            <img
                                src={utLogo}
                                width={192}
                                height={192}
                                alt="Universidad Tecnológica de Nayarit"
                            />
                        </a>
                    </div>
                </div>
            </aside>
            <main className="en-auth-main">
                <div className="en-auth-mobile-brand">
                    <Link href="/">
                        <img src={colorSymbol} alt="" /> encontrarnos
                        <span>.</span>
                    </Link>
                </div>
                <div className="en-auth-content">
                    <Link href="/" className="en-auth-back">
                        <ArrowLeft size={16} /> Volver al inicio
                    </Link>
                    {children}
                </div>
                <div className="en-auth-main-bottom">
                    © 2026 Encontrarnos · México
                </div>
            </main>
        </div>
    );
}
