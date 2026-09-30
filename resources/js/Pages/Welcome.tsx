import { darkBackgroundSymbol } from '@/brand';
import { classNames } from '@/classNames';
import { Reveal } from '@/Components/Encontrarnos/motion';
import { ActionGrid } from '@/Components/Encontrarnos/PublicTools';
import SkeletonWall from '@/Components/Encontrarnos/SkeletonWall';
import PublicLayout from '@/Layouts/PublicLayout';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDown, ArrowUpRight, Plus } from 'lucide-react';
import { ReactNode, useState } from 'react';

const PARTICIPANTS = [
    {
        name: 'Personas y familias',
        description:
            'Consulta fichas, comparte datos que puedan ayudar y da seguimiento a la búsqueda de tu ser querido.',
    },
    {
        name: 'Instituciones de seguridad',
        description:
            'Coordina información con otras instancias para acelerar la localización y evitar duplicidad de esfuerzos.',
    },
    {
        name: 'Hospitales',
        description:
            'Registra el ingreso de personas sin identificar para facilitar que sus familias las encuentren.',
    },
    {
        name: 'Centros de rehabilitación',
        description:
            'Aporta datos de personas que se encuentran bajo su cuidado y ayuda a reconectarlas con sus familiares.',
    },
    {
        name: 'Servicios forenses',
        description:
            'Comparte información de identificación para cotejarla con reportes de personas desaparecidas.',
    },
];

export default function Welcome({ auth }: PageProps) {
    const [openParticipant, setOpenParticipant] = useState<number | null>(null);

    return (
        <>
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
                            <img src={darkBackgroundSymbol} alt="" />
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
                <SkeletonWall />
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
                <Reveal className="en-institutions-heading">
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
                </Reveal>
                <div className="en-participants">
                    <p>UN ESPACIO PARA</p>
                    {PARTICIPANTS.map(({ name, description }, index) => {
                        const isOpen = openParticipant === index;
                        return (
                            <Reveal
                                index={index}
                                className={classNames(
                                    'en-participant',
                                    isOpen && 'is-open',
                                )}
                                key={name}
                            >
                                <button
                                    type="button"
                                    className="en-participant-toggle"
                                    aria-expanded={isOpen}
                                    aria-controls={`participant-${index}`}
                                    onClick={() =>
                                        setOpenParticipant(
                                            isOpen ? null : index,
                                        )
                                    }
                                >
                                    <span>0{index + 1}</span>
                                    <h3>{name}</h3>
                                    <Plus size={23} aria-hidden="true" />
                                </button>
                                <div
                                    className="en-participant-panel"
                                    id={`participant-${index}`}
                                    role="region"
                                >
                                    <div>
                                        <p>{description}</p>
                                    </div>
                                </div>
                            </Reveal>
                        );
                    })}
                </div>
            </section>
        </>
    );
}

Welcome.layout = (page: ReactNode) => (
    <PublicLayout enter={false}>{page}</PublicLayout>
);
