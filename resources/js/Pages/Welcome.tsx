import { ActionGrid } from '@/Components/Encontrarnos/PublicTools';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDown, ArrowUpRight, Plus } from 'lucide-react';

export default function Welcome({ auth }: PageProps) {
    return (
        <PublicLayout>
            <Head title="Hasta encontrarnos · Búsqueda de personas en México" />
            <section className="en-manifesto" aria-labelledby="hero-title">
                <div className="en-manifesto-copy">
                    <p className="en-section-kicker">
                        <span className="en-ink-square" /> BÚSQUEDA E
                        IDENTIFICACIÓN DE PERSONAS
                    </p>
                    <h1 id="hero-title">
                        <span>HASTA</span>
                        <strong>
                            ENCONTRARNOS<span>.</span>
                        </strong>
                    </h1>
                    <div className="en-manifesto-bottom">
                        <span className="en-manifesto-cross" aria-hidden="true">
                            ✳
                        </span>
                        <div>
                            <p>
                                Detrás de cada ausencia hay una persona.
                                <br />
                                <strong>
                                    Y una búsqueda que nos necesita.
                                </strong>
                            </p>
                            <p className="en-manifesto-description">
                                Reunimos información para localizar e
                                identificar personas desaparecidas en México.
                                Para familias, personas e instituciones.
                            </p>
                            <Link
                                href="/base-de-datos"
                                className="en-primary-button"
                            >
                                Buscar en base de datos{' '}
                                <ArrowUpRight size={22} />
                            </Link>
                        </div>
                    </div>
                </div>
                <div className="en-poster-wall" aria-label="Cartel de búsqueda">
                    <div className="en-poster-under" aria-hidden="true">
                        <span>MEMORIA.</span>
                        <span>PRESENCIA.</span>
                    </div>
                    <div className="en-search-poster">
                        <div className="en-poster-eyebrow">
                            <span>ENCONTRARNOS / MÉXICO</span>
                            <Plus size={20} />
                        </div>
                        <h2>
                            LA BÚSQUEDA
                            <br />
                            SIGUE.
                        </h2>
                        <div className="en-poster-portrait">
                            <img
                                src="/woman-placeholder.png"
                                alt="Silueta de una persona"
                            />
                        </div>
                        <div className="en-poster-message">
                            UNA PERSONA.
                            <br />
                            TODA UNA HISTORIA.
                        </div>
                    </div>
                    <span className="en-poster-stamp" aria-hidden="true">
                        NO OLVIDAR.
                    </span>
                </div>
                <div className="en-manifesto-foot">
                    <span>La información también ayuda a buscar.</span>
                    <a href="#acciones">
                        Qué hacemos <ArrowDown size={18} />
                    </a>
                </div>
            </section>

            <section
                className="en-action-directory"
                id="acciones"
                aria-label="Qué hacemos"
            >
                <div className="en-directory-label">
                    QUÉ HACEMOS <ArrowDown size={19} />
                </div>
                <ActionGrid />
            </section>

            <section className="en-institutions en-section" id="participar">
                <div className="en-institutions-heading">
                    <p className="en-section-kicker">
                        <span>+</span> / QUIÉNES SOMOS
                    </p>
                    <h2>
                        BUSCAR
                        <br />
                        TAMBIÉN ES
                        <br />
                        <em>COLABORAR.</em>
                    </h2>
                    <p>
                        Encontrarnos es un espacio para reunir información que
                        ayude a localizar e identificar personas desaparecidas.
                        Consultar, compartir y dar seguimiento: cada dato puede
                        ayudar a que una búsqueda continúe.
                    </p>
                    <Link
                        className="en-primary-button"
                        href={route(auth.user ? 'dashboard' : 'register')}
                    >
                        {auth.user ? 'Ir a mi espacio' : 'Crear una cuenta'}
                        <ArrowUpRight size={22} />
                    </Link>
                </div>
                <div className="en-participants">
                    <p>UN ESPACIO PARA</p>
                    {[
                        'Personas y familias',
                        'Instituciones de seguridad',
                        'Hospitales',
                        'Centros de rehabilitación',
                        'Servicios forenses',
                    ].map((name, index) => (
                        <div className="en-participant" key={name}>
                            <span>0{index + 1}</span>
                            <h3>{name}</h3>
                            <Plus size={23} aria-hidden="true" />
                        </div>
                    ))}
                </div>
            </section>
        </PublicLayout>
    );
}
