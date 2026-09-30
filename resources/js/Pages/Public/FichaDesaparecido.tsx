import {
    Chip,
    FichaBlock,
    FichaShell,
    FichaTraits,
} from '@/Components/Encontrarnos/Ficha';
import {
    FichaCard,
    FichaList,
    FichaTiles,
    FichaTimeline,
} from '@/Components/Encontrarnos/FichaCards';
import InformationForm from '@/Components/Encontrarnos/InformationForm';
import { FadeImage } from '@/Components/Encontrarnos/motion';
import {
    ageParts,
    elapsedSince,
    sentenceCase,
    splitGarments,
    titleCase,
    yesNo,
} from '@/format';
import PublicLayout from '@/Layouts/PublicLayout';
import { PersonRecordDetail } from '@/types';
import { Head } from '@inertiajs/react';
import {
    Accessibility,
    Activity,
    Archive,
    Cake,
    CalendarDays,
    ClipboardList,
    FileText,
    Fingerprint,
    Globe,
    Hash,
    History,
    Home,
    Info,
    Landmark,
    Languages,
    Lock,
    MapPin,
    Radio,
    Search,
    ShieldCheck,
    Shirt,
    TriangleAlert,
    User,
} from 'lucide-react';
import { ReactNode } from 'react';

const registryLabels: Record<string, string> = {
    SI: 'Sí, autorizada',
    NO: 'No autorizada',
    'SIN DATO': 'El registro no lo indica',
};

const ageLabel = (age: number | null) =>
    age === null ? null : `${age} ${age === 1 ? 'año' : 'años'}`;

export default function FichaDesaparecido({
    record: { data: record },
}: {
    record: { data: PersonRecordDetail };
}) {
    const name = titleCase(record.name);
    const garments = splitGarments(record.clothing);
    const registered = ageParts(
        record.registered_age.years,
        record.registered_age.months,
        record.registered_age.days,
    );
    const sensitive = record.sensitive;
    const disability =
        record.has_disability === null
            ? null
            : record.has_disability
              ? record.disability_type
                  ? `Sí · ${sentenceCase(record.disability_type)}`
                  : 'Sí'
              : 'No';
    const elapsed = elapsedSince(record.event_date);
    // Solo existe para administradores.
    const isAdminView = record.registry_publish !== undefined;
    const registryPublish = record.registry_publish
        ? registryLabels[record.registry_publish]
        : null;

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
                notices={
                    record.registry_publish === 'NO' ? (
                        <p
                            className="req-notice req-notice--warn"
                            role="status"
                        >
                            <TriangleAlert size={20} />
                            <span>
                                <strong>
                                    El registro nacional indica que esta ficha
                                    no debe publicarse.
                                </strong>{' '}
                                Solo tú, como administrador, ves este aviso.
                            </span>
                        </p>
                    ) : undefined
                }
                facts={[
                    ['Sexo', record.sex_label],
                    ['Edad al desaparecer', ageLabel(record.age)],
                    ['Edad actual aproximada', ageLabel(record.current_age)],
                    ['Estado', record.state_label],
                    ['Municipio', titleCase(record.municipality)],
                    ['Fecha de desaparición', record.event_date_label],
                    ['Tiempo desde la desaparición', elapsed],
                ]}
            >
                {record.traits.length > 0 && (
                    <FichaBlock title="Rasgos">
                        <FichaTraits traits={record.traits} />
                    </FichaBlock>
                )}

                <div className="fx-grid">
                    <FichaCard
                        icon={Fingerprint}
                        title="Señas particulares"
                        tone="gold"
                    >
                        {record.distinguishing_marks ? (
                            <p>{sentenceCase(record.distinguishing_marks)}</p>
                        ) : (
                            <p className="fx-muted">Sin dato</p>
                        )}
                    </FichaCard>
                    <FichaCard
                        icon={Shirt}
                        title="Prendas de vestir"
                        tone="teal"
                    >
                        {record.clothing ? (
                            garments ? (
                                <FichaList items={garments} />
                            ) : (
                                <p>{sentenceCase(record.clothing)}</p>
                            )
                        ) : (
                            <p className="fx-muted">Sin dato</p>
                        )}
                    </FichaCard>
                </div>

                <FichaCard icon={Landmark} title="Autoridad responsable">
                    {record.authority ? (
                        <p>
                            <strong>{titleCase(record.authority)}</strong>
                        </p>
                    ) : (
                        <p className="fx-muted">Sin dato</p>
                    )}
                    {record.referred_to.length > 0 && (
                        <>
                            <p className="fx-subtitle">Caso canalizado a</p>
                            <FichaList
                                items={record.referred_to.map(titleCase)}
                            />
                        </>
                    )}
                </FichaCard>

                <div className="fx-grid">
                    <FichaCard
                        icon={User}
                        title="Datos de la persona"
                        tone="teal"
                    >
                        <FichaTiles
                            columns={1}
                            tiles={[
                                {
                                    label: 'Nacionalidad',
                                    value: titleCase(record.nationality),
                                    icon: Globe,
                                },
                                {
                                    label: 'Habla español',
                                    value: yesNo(record.speaks_spanish),
                                    icon: Languages,
                                    pill: true,
                                },
                                {
                                    label: 'Discapacidad',
                                    value: disability,
                                    icon: Accessibility,
                                    pill: disability === 'No',
                                },
                                {
                                    label: 'Edad al registrarse la ficha',
                                    value: registered,
                                    icon: History,
                                },
                            ]}
                        />
                    </FichaCard>

                    {sensitive ? (
                        <FichaCard
                            icon={Cake}
                            title="Nacimiento"
                            tone="gold"
                            badge="DATO PERSONAL"
                        >
                            <FichaTiles
                                columns={1}
                                tiles={[
                                    {
                                        label: 'Fecha de nacimiento',
                                        value: sensitive.birth_date_label,
                                        icon: CalendarDays,
                                        strong: true,
                                    },
                                    {
                                        label: 'Estado de nacimiento',
                                        value: titleCase(sensitive.birth_state),
                                        icon: MapPin,
                                    },
                                    {
                                        label: 'Lugar de nacimiento',
                                        value: titleCase(sensitive.birth_place),
                                        icon: MapPin,
                                    },
                                ]}
                            />
                        </FichaCard>
                    ) : (
                        <p className="req-restricted" role="note">
                            <Lock size={20} aria-hidden="true" />
                            <span>
                                Los datos personales de esta ficha (nacimiento y
                                domicilio) están restringidos.
                            </span>
                        </p>
                    )}

                    {sensitive && (
                        <FichaCard
                            icon={Home}
                            title="Domicilio"
                            tone="red"
                            wide
                            badge="DATO PERSONAL"
                        >
                            <FichaTiles
                                columns={3}
                                tiles={[
                                    {
                                        label: 'Colonia o asentamiento',
                                        value: titleCase(
                                            sensitive.neighborhood,
                                        ),
                                        icon: MapPin,
                                    },
                                    {
                                        label: 'Calle',
                                        value: titleCase(sensitive.street),
                                        icon: MapPin,
                                    },
                                    {
                                        label: 'Número exterior',
                                        value: sensitive.exterior_number,
                                        icon: Hash,
                                    },
                                    {
                                        label: 'Número interior',
                                        value: sensitive.interior_number,
                                        icon: Hash,
                                    },
                                    {
                                        label: 'Código postal',
                                        value: sensitive.postal_code,
                                        icon: Hash,
                                    },
                                ]}
                            />
                        </FichaCard>
                    )}
                </div>

                <div className="fx-grid">
                    <FichaCard icon={History} title="Línea de tiempo del caso">
                        <FichaTimeline
                            items={[
                                {
                                    label: 'Desaparición',
                                    date: record.event_date_label,
                                    note: elapsed
                                        ? `Hace ${elapsed}`
                                        : undefined,
                                    tone: 'red',
                                },
                                {
                                    label: 'Se percataron',
                                    date: record.noticed_date_label,
                                    tone: 'gold',
                                },
                                {
                                    label: 'Ficha registrada',
                                    date: record.registered_date_label,
                                    tone: 'teal',
                                },
                                {
                                    label: 'Actualizada en el registro',
                                    date: record.source_updated_at_label,
                                    tone: 'teal',
                                },
                                {
                                    label: 'Publicada en Encontrarnos',
                                    date: record.published_at_label,
                                },
                                {
                                    label: 'Actualizada en Encontrarnos',
                                    date: record.updated_at_label,
                                },
                            ]}
                        />
                    </FichaCard>

                    <FichaCard
                        icon={ClipboardList}
                        title="Datos del registro"
                        tone="teal"
                    >
                        <FichaTiles
                            columns={1}
                            tiles={[
                                {
                                    label: 'Folio',
                                    value: record.folio,
                                    icon: Hash,
                                    strong: true,
                                },
                                {
                                    label: 'Estatus',
                                    value: record.status_label,
                                    icon: Activity,
                                },
                                {
                                    label: 'Tipo de registro',
                                    value: record.type_label,
                                    icon: FileText,
                                },
                                {
                                    label: 'Origen del registro',
                                    value: sentenceCase(record.origin),
                                    icon: Radio,
                                },
                                ...(isAdminView
                                    ? [
                                          {
                                              label: 'Publicación según el registro',
                                              value: registryPublish,
                                              icon: ShieldCheck,
                                          },
                                      ]
                                    : []),
                                {
                                    label: 'Solo búsqueda',
                                    value: yesNo(record.search_only),
                                    icon: Search,
                                    pill: true,
                                },
                                {
                                    label: 'Archivo de migración',
                                    value: record.migration_file,
                                    icon: Archive,
                                },
                            ]}
                        />
                    </FichaCard>
                </div>

                {record.description && (
                    <FichaCard
                        icon={FileText}
                        title="Texto completo del registro"
                    >
                        <p>{sentenceCase(record.description)}</p>
                    </FichaCard>
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
