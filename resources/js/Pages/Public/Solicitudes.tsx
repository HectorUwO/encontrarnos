import { RequestComposer } from '@/Components/Encontrarnos/PublicTools';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Plus } from 'lucide-react';
import { useState } from 'react';

export default function Solicitudes() {
    const [requestOpen, setRequestOpen] = useState(false);

    return (
        <PublicLayout>
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
                <div className="en-request-showcase" id="lista-solicitudes">
                    <div className="en-request-art">
                        <div className="en-notice-top">
                            <span>SOLICITUD / 001</span>
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
                                    src="/men%20place%20holder.png"
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
        </PublicLayout>
    );
}
