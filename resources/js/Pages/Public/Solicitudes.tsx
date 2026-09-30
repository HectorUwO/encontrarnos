import { RequestBrowser } from '@/Components/Encontrarnos/RequestCatalog';
import '@/Components/Encontrarnos/requests.css';
import PublicLayout from '@/Layouts/PublicLayout';
import {
    PageProps,
    Paginated,
    PersonRequestItem,
    RequestFilters,
    RequestOptions,
} from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowUpRight, Check } from 'lucide-react';
import { ReactNode } from 'react';

const steps = [
    ['Publica', 'Cuéntanos a quién buscas o a quién quieres identificar.'],
    ['Revisamos', 'Un equipo revisa cada solicitud antes de hacerla pública.'],
    ['Alguien responde', 'Quien tenga información te escribe a tu correo.'],
];

export default function Solicitudes({
    requests,
    totalPublished,
    filters,
    options,
}: {
    requests: Paginated<PersonRequestItem>;
    totalPublished: number;
    filters: RequestFilters;
    options: RequestOptions;
}) {
    const { flash } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Solicitudes · Encontrarnos" />
            <section className="req-hero" aria-labelledby="requests-title">
                <div className="req-hero-inner">
                    <div>
                        <p className="req-kicker">
                            <span>04</span> SOLICITUDES
                        </p>
                        <h1 id="requests-title">
                            Lo que sabes
                            <em>puede ayudar.</em>
                        </h1>
                        <p className="req-lead">
                            Publica una solicitud de búsqueda o de
                            identificación, o consulta las que otras personas e
                            instituciones ya compartieron. Están separadas de la
                            base de datos de personas desaparecidas.
                        </p>
                    </div>
                    <aside className="req-hero-panel">
                        <div className="req-count">
                            <strong>
                                {totalPublished.toLocaleString('es-MX')}
                            </strong>
                            <span>
                                solicitudes
                                <br />
                                publicadas
                            </span>
                        </div>
                        <div className="req-cta">
                            <Link
                                href={route('requests.create', {
                                    tipo: 'search',
                                })}
                            >
                                Busco a una persona <ArrowUpRight size={20} />
                            </Link>
                            <Link
                                href={route('requests.create', {
                                    tipo: 'identification',
                                })}
                            >
                                Quiero identificar a alguien{' '}
                                <ArrowUpRight size={20} />
                            </Link>
                        </div>
                    </aside>
                </div>
                <ol className="req-steps" aria-label="Cómo funciona">
                    {steps.map(([title, text], index) => (
                        <li key={title}>
                            <b>0{index + 1}</b>
                            <span>
                                <strong>{title}</strong>
                                {text}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>
            <section className="req-body" id="catalogo-solicitudes">
                {flash?.status === 'request-received' && (
                    <p className="req-notice" role="status">
                        <Check size={20} />
                        <span>
                            <strong>Recibimos tu solicitud.</strong> La
                            revisamos antes de publicarla y te avisaremos por
                            correo.
                        </span>
                    </p>
                )}
                <RequestBrowser
                    requests={requests}
                    filters={filters}
                    options={options}
                />
            </section>
        </>
    );
}

Solicitudes.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
