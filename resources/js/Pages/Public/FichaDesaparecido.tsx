import {
    Chip,
    FichaBlock,
    FichaShell,
    FichaTraits,
} from '@/Components/Encontrarnos/Ficha';
import InformationForm from '@/Components/Encontrarnos/InformationForm';
import { FadeImage } from '@/Components/Encontrarnos/motion';
import PublicLayout from '@/Layouts/PublicLayout';
import { sentenceCase, splitGarments, titleCase } from '@/format';
import { PersonRecord } from '@/types';
import { Head } from '@inertiajs/react';
import { Info } from 'lucide-react';
import { ReactNode } from 'react';

const ageLabel = (age: number | null) =>
    age === null ? null : `${age} ${age === 1 ? 'año' : 'años'}`;

export default function FichaDesaparecido({
    record: { data: record },
}: {
    record: { data: PersonRecord };
}) {
    const name = titleCase(record.name);
    const garments = splitGarments(record.clothing);
    const place =
        [titleCase(record.municipality), record.state_label]
            .filter(Boolean)
            .join(', ') || null;
    const hasDetails =
        record.traits.length > 0 ||
        record.distinguishing_marks ||
        record.clothing;

    return (
        <>
            <Head title={`${name} · ${record.folio} · Encontrarnos`} />
            <div
                className="req-hero req-hero--ink req-hero--slim"
                aria-hidden="true"
            />
            <FichaShell
                accent="missing"
                crumbs={[
                    { label: 'Base de datos', href: route('records') },
                    { label: record.folio },
                ]}
                photo={
                    <FadeImage
                        src={record.portrait_large}
                        alt={
                            record.has_photo
                                ? `Fotografía de la ficha ${record.folio}`
                                : 'Silueta de una persona'
                        }
                        className={record.has_photo ? 'en-photo' : undefined}
                        decoding="async"
                    />
                }
                photoSrc={record.has_photo ? record.portrait_large : null}
                photoAlt={`Fotografía de la ficha ${record.folio}`}
                photoMeta={`Ficha ${record.folio}`}
                tag={
                    <>
                        <Chip
                            tone={
                                record.status_label === 'No localizada'
                                    ? 'not_located'
                                    : 'missing'
                            }
                        >
                            {record.status_label ?? record.type_label}
                        </Chip>
                        <span>{record.folio}</span>
                    </>
                }
                kicker={
                    <>
                        <span>Se busca</span>
                        {record.type_label}
                    </>
                }
                title={name}
                facts={[
                    ['Sexo', record.sex_label],
                    ['Edad al desaparecer', ageLabel(record.age)],
                    [
                        'Edad actual aproximada',
                        record.current_age !== null &&
                        record.current_age !== record.age
                            ? ageLabel(record.current_age)
                            : null,
                    ],
                    ['Lugar', place],
                    ['Fecha de desaparición', record.event_date_label],
                ]}
            >
                {record.description && !hasDetails && (
                    <FichaBlock title="Descripción">
                        <p>{sentenceCase(record.description)}</p>
                    </FichaBlock>
                )}
                {record.traits.length > 0 && (
                    <FichaBlock title="Rasgos">
                        <FichaTraits traits={record.traits} />
                    </FichaBlock>
                )}
                {record.distinguishing_marks && (
                    <FichaBlock title="Señas particulares">
                        <p>{sentenceCase(record.distinguishing_marks)}</p>
                    </FichaBlock>
                )}
                {record.clothing && (
                    <FichaBlock title="Prendas de vestir">
                        {garments ? (
                            <ul className="req-list">
                                {garments.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        ) : (
                            <p>{sentenceCase(record.clothing)}</p>
                        )}
                    </FichaBlock>
                )}
                {!record.description && !hasDetails && (
                    <FichaBlock title="Descripción">
                        <p>Aún no hay una descripción física registrada.</p>
                    </FichaBlock>
                )}
                {record.authority && (
                    <FichaBlock title="Autoridad responsable">
                        <p>{titleCase(record.authority)}</p>
                    </FichaBlock>
                )}
                <InformationForm
                    action={route('records.information', record.folio)}
                    intro="Si tienes datos que puedan ayudar a localizar a esta persona, compártelos. Le llegan al equipo de Encontrarnos, que los canaliza con la autoridad responsable."
                />
                <p className="req-source">
                    <Info size={16} aria-hidden="true" />
                    Esta ficha proviene del Registro Nacional de Personas
                    Desaparecidas y No Localizadas. Encontrarnos no sustituye el
                    reporte ante la autoridad.
                </p>
            </FichaShell>
        </>
    );
}

FichaDesaparecido.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
