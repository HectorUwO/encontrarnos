import { FadeImage, Reveal } from '@/Components/Encontrarnos/motion';
import { RequestComposer } from '@/Components/Encontrarnos/PublicTools';
import PublicLayout from '@/Layouts/PublicLayout';
import { PersonRequestItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Plus, UserRound } from 'lucide-react';
import { ReactNode, useState } from 'react';

function RequestCard({
    request,
    index,
}: {
    request: PersonRequestItem;
    index: number;
}) {
    return (
        <Reveal as="article" index={index} className="en-request-card">
            <div className="en-notice-top">
                <span>{request.reference}</span>
                <span>
                    {request.type === 'identification'
                        ? 'IDENTIFICACIÓN'
                        : 'BÚSQUEDA'}
                </span>
            </div>
            <div className="en-request-card-body">
                {request.photo_thumb ? (
                    <div className="en-request-card-photo is-photo">
                        <FadeImage
                            src={request.photo_thumb}
                            alt={`Fotografía de la solicitud ${request.reference}`}
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                ) : (
                    <div
                        className="en-request-card-photo"
                        role="img"
                        aria-label="Sin fotografía"
                    >
                        <UserRound size={44} strokeWidth={1.5} />
                    </div>
                )}
                <div>
                    <h3>{request.name ?? 'Persona sin nombre'}</h3>
                    <p className="en-request-card-meta">
                        {[request.place, request.created_at_label]
                            .filter(Boolean)
                            .join(' · ')}
                    </p>
                    <p className="en-request-card-text">
                        {request.description}
                    </p>
                </div>
            </div>
        </Reveal>
    );
}

export default function Solicitudes({
    requests,
}: {
    requests: { data: PersonRequestItem[] };
}) {
    const [requestOpen, setRequestOpen] = useState(false);
    const published = requests.data;

    return (
        <>
            <Head title="Solicitudes · Encontrarnos" />
            <section
                className="en-requests en-section"
                id="solicitudes"
                aria-labelledby="requests-title"
            >
                <div className="en-section-heading">
                    <div>
                        <p className="en-section-kicker">
                            <span>04</span> / SOLICITUDES
                        </p>
                        <h2 id="requests-title">
                            LO QUE SABES
                            <br />
                            <em>PUEDE AYUDAR.</em>
                        </h2>
                    </div>
                    <div className="en-section-aside">
                        <p>
                            Consulta solicitudes de búsqueda e identificación.
                            Si necesitas compartir información, prepara una
                            nueva solicitud.
                        </p>
                        <button
                            type="button"
                            className="en-primary-button"
                            onClick={() => setRequestOpen(true)}
                        >
                            Crear solicitud <Plus size={22} />
                        </button>
                    </div>
                </div>
                {published.length > 0 && (
                    <div
                        className="en-request-board"
                        id="lista-solicitudes"
                        aria-label="Solicitudes publicadas"
                    >
                        {published.map((request, index) => (
                            <RequestCard
                                key={request.id}
                                request={request}
                                index={index}
                            />
                        ))}
                    </div>
                )}
                <div className="en-request-showcase">
                    <div className="en-request-art">
                        <div className="en-notice-top">
                            <span>TABLÓN / AVISO</span>
                            <Plus size={22} />
                        </div>
                        <h3>
                            SE BUSCA
                            <br />
                            INFORMACIÓN.
                        </h3>
                        <div className="en-request-portrait-stage">
                            <div className="en-request-art-orbit" />
                            <div className="en-request-avatar">
                                <img
                                    src="/placeholder-man.webp"
                                    alt="Silueta de una persona"
                                />
                            </div>
                        </div>
                        <span className="en-request-art-label">
                            SOLICITUD DE IDENTIFICACIÓN
                        </span>
                    </div>
                    <div className="en-request-detail">
                        <span className="en-section-kicker">
                            TABLÓN DE SOLICITUDES
                        </span>
                        <h3>¿Reconoces algún dato?</h3>
                        <p>
                            Una descripción, una seña particular o un lugar
                            pueden servir para continuar una búsqueda.
                        </p>
                        <dl className="en-request-fields">
                            <div>
                                <dt>Información disponible</dt>
                                <dd>
                                    Fotografía, descripción y señas
                                    particulares.
                                </dd>
                            </div>
                            <div>
                                <dt>Cómo aportar información</dt>
                                <dd>
                                    A través del contacto indicado en cada
                                    solicitud.
                                </dd>
                            </div>
                        </dl>
                        {published.length === 0 && (
                            <p className="en-request-bottom">
                                Todavía no hay solicitudes publicadas. Las que
                                se envían se revisan antes de aparecer aquí.
                            </p>
                        )}
                        <Link
                            href="/base-de-datos"
                            className="en-secondary-button"
                        >
                            Consultar las fichas <ArrowUpRight size={20} />
                        </Link>
                    </div>
                </div>
            </section>
            <RequestComposer
                open={requestOpen}
                onClose={() => setRequestOpen(false)}
            />
        </>
    );
}

Solicitudes.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
