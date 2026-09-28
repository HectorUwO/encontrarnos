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
                    <img src="/3.png" alt="" />
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
                    <span aria-hidden="true">✳</span>
                </div>
            </aside>
            <main className="en-auth-main">
                <div className="en-auth-mobile-brand">
                    <Link href="/">
                        <img src="/1.png" alt="" /> encontrarnos<span>.</span>
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
