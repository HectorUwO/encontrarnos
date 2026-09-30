import {
    Chip,
    FichaBlock,
    FichaShell,
    FichaTraits,
} from '@/Components/Encontrarnos/Ficha';
import InformationForm from '@/Components/Encontrarnos/InformationForm';
import { RequestPortrait } from '@/Components/Encontrarnos/RequestCatalog';
import PublicLayout from '@/Layouts/PublicLayout';
import { sentenceCase, titleCase } from '@/format';
import { PersonRequestItem } from '@/types';
import { Head } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { ReactNode } from 'react';

export default function SolicitudFicha({
    request: { data: request },
    canOffer,
}: {
    request: { data: PersonRequestItem };
    canOffer: boolean;
}) {
    const isSearch = request.type === 'search';
    const title = titleCase(request.name) || 'Persona sin identificar';

    return (
        <>
            <Head title={`${title} · ${request.reference} · Encontrarnos`} />
            <div className="req-hero req-hero--slim" aria-hidden="true" />
            <FichaShell
                accent={request.type}
                crumbs={[
                    { label: 'Solicitudes', href: route('requests') },
                    { label: request.reference },
                ]}
                notices={
                    <>
                        {request.status !== 'approved' && (
                            <p
                                className="req-notice req-notice--warn"
                                role="status"
                            >
                                <Clock size={20} />
                                <span>
                                    Esta solicitud está{' '}
                                    <strong>
                                        {request.status_label.toLowerCase()}
                                    </strong>
                                    . Solo tú y el equipo de Encontrarnos pueden
                                    verla.
                                </span>
                            </p>
                        )}
                        {request.closed && (
                            <p
                                className={
                                    request.closed_reason === 'resolved'
                                        ? 'req-notice'
                                        : 'req-notice req-notice--warn'
                                }
                                role="status"
                            >
                                <Clock size={20} />
                                <span>
                                    {request.closed_reason === 'resolved' ? (
                                        <>
                                            <strong>
                                                Esta solicitud fue resuelta
                                            </strong>
                                            {request.closed_at_label &&
                                                ` el ${request.closed_at_label}`}
                                            . Gracias a quienes ayudaron.
                                        </>
                                    ) : (
                                        <>
                                            <strong>
                                                Esta solicitud fue dada de baja
                                            </strong>
                                            {request.closed_at_label &&
                                                ` el ${request.closed_at_label}`}
                                            . Ya no recibe información.
                                        </>
                                    )}
                                </span>
                            </p>
                        )}
                    </>
                }
                photo={<RequestPortrait request={request} large />}
                photoSrc={request.has_photo ? request.photo : null}
                photoAlt={`Fotografía de la solicitud ${request.reference}`}
                photoMeta={`Solicitud ${request.reference}`}
                tag={
                    <>
                        <Chip tone={request.type}>
                            {isSearch ? 'Búsqueda' : 'Identificación'}
                        </Chip>
                        <span>{request.reference}</span>
                    </>
                }
                kicker={
                    <>
                        <span>{isSearch ? 'Se busca' : 'Se identifica'}</span>
                        {request.type_label}
                    </>
                }
                title={title}
                facts={[
                    ['Sexo', request.sex_label],
                    [
                        'Edad',
                        request.age === null ? null : `${request.age} años`,
                    ],
                    ['Lugar', titleCase(request.place)],
                    [
                        isSearch
                            ? 'Fecha de desaparición'
                            : 'Fecha de localización',
                        request.event_date_label,
                    ],
                    ['Publicada', request.created_at_label],
                ]}
            >
                <FichaBlock title="Descripción">
                    <p>{sentenceCase(request.description)}</p>
                </FichaBlock>
                {request.traits.length > 0 && (
                    <FichaBlock title="Rasgos">
                        <FichaTraits traits={request.traits} />
                    </FichaBlock>
                )}
                {request.distinguishing_marks && (
                    <FichaBlock title="Señas particulares">
                        <p>{sentenceCase(request.distinguishing_marks)}</p>
                    </FichaBlock>
                )}
                {request.clothing && (
                    <FichaBlock title="Prendas de vestir">
                        <p>{sentenceCase(request.clothing)}</p>
                    </FichaBlock>
                )}
                {request.institution && (
                    <FichaBlock
                        title={
                            isSearch
                                ? 'Autoridad ante la que se reportó'
                                : 'Institución que resguarda a la persona'
                        }
                    >
                        <p>{titleCase(request.institution)}</p>
                    </FichaBlock>
                )}
                {canOffer && (
                    <InformationForm
                        action={route('requests.offer', request.id)}
                        intro={
                            isSearch
                                ? 'Si viste a esta persona o sabes algo que ayude a encontrarla, escribe a quien publicó la solicitud.'
                                : 'Si reconoces a esta persona o sabes quién es su familia, escribe a quien publicó la solicitud.'
                        }
                    />
                )}
            </FichaShell>
        </>
    );
}

SolicitudFicha.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
