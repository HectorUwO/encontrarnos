import { Head } from '@inertiajs/react';

type ErrorProps = { status: number; title: string; description: string };

export default function Error({ status, title, description }: ErrorProps) {
    return (
        <div className="error-page">
            <Head title={`${status} · ${title} — Encontrarnos`}>
                <meta name="robots" content="noindex, nofollow" />
                <link rel="stylesheet" href="/errors.css" />
            </Head>
            <a className="error-skip" href="#contenido">
                Saltar al contenido
            </a>
            <header className="error-header">
                <a
                    className="error-brand"
                    href="/"
                    aria-label="Encontrarnos, ir al inicio"
                >
                    <img
                        src="/apple-touch-icon.png"
                        width="42"
                        height="42"
                        alt=""
                    />
                    <span>
                        encontrarnos<span className="error-period">.</span>
                    </span>
                </a>
                <span className="error-header-note">
                    UNA PLATAFORMA PARA SEGUIR BUSCANDO
                </span>
            </header>
            <main className="error-main" id="contenido">
                <div className="error-visual" aria-hidden="true">
                    <span className="error-orbit" />
                    <span className="error-code">{status}</span>
                    <span className="error-visual-caption">
                        ENCONTRARNOS / {status}
                    </span>
                </div>
                <section
                    className="error-content"
                    aria-labelledby="error-title"
                >
                    <p className="error-eyebrow">
                        UN ALTO EN EL CAMINO <span>— ERROR {status}</span>
                    </p>
                    <h1 id="error-title">{title}</h1>
                    <p className="error-description">{description}</p>
                    <nav
                        className="error-actions"
                        aria-label="Opciones para continuar"
                    >
                        <a
                            className="error-button"
                            href={status === 401 ? route('login') : '/'}
                        >
                            {status === 401
                                ? 'Iniciar sesión'
                                : 'Volver al inicio'}{' '}
                            <span aria-hidden="true">↗</span>
                        </a>
                        <a className="error-secondary" href={route('records')}>
                            Consultar la base de datos{' '}
                            <span aria-hidden="true">→</span>
                        </a>
                    </nav>
                    <p className="error-help">
                        La búsqueda continúa. Gracias por ser parte.
                    </p>
                </section>
            </main>
            <footer className="error-footer">
                <span>QUE LA BÚSQUEDA NO SE DETENGA.</span>
                <span>Encontrarnos · México</span>
            </footer>
        </div>
    );
}
