import { classNames } from '@/classNames';
import '@/Components/Encontrarnos/requests.css';
import PublicLayout from '@/Layouts/PublicLayout';
import { Option, PersonRequestItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowUpRight,
    ImagePlus,
    LoaderCircle,
    ShieldCheck,
} from 'lucide-react';
import {
    ChangeEvent,
    FormEvent,
    ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';

type RequestType = 'search' | 'identification';

const copy: Record<
    RequestType,
    {
        title: string;
        lead: string;
        name: string;
        age: string;
        date: string;
        institution: string;
        description: string;
    }
> = {
    search: {
        title: 'Busco a una persona',
        lead: 'Para familias y personas cercanas que buscan a alguien. Tu solicitud se publica después de una revisión y quienes tengan información podrán escribirte.',
        name: 'Nombre de la persona que buscas',
        age: 'Edad',
        date: 'Fecha de desaparición',
        institution: 'Autoridad ante la que hiciste el reporte',
        description:
            'Cuéntanos cómo era, dónde y cuándo se le vio por última vez y cualquier dato que ayude a reconocerla.',
    },
    identification: {
        title: 'Quiero identificar a alguien',
        lead: 'Para hospitales, servicios forenses, centros de rehabilitación, instituciones o personas que tienen a alguien sin identificar y buscan a su familia.',
        name: 'Nombre, si lo conoces',
        age: 'Edad aproximada',
        date: 'Fecha en que fue localizada',
        institution: 'Institución que resguarda a la persona',
        description:
            'Describe a la persona, dónde fue localizada y cualquier dato que ayude a que su familia la reconozca.',
    },
};

const typeHelp: Record<RequestType, string> = {
    search: 'Tengo a alguien desaparecido y quiero que se sepa.',
    identification: 'Tengo a alguien sin identificar y busco a su familia.',
};

type FormData = {
    type: RequestType;
    name: string;
    sex: string;
    age: string;
    state: string;
    municipality: string;
    event_date: string;
    description: string;
    traits: Record<string, string>;
    clothing: string;
    distinguishing_marks: string;
    institution: string;
    contact_email: string;
    contact_phone: string;
    photo: File | null;
};

function Step({
    number,
    title,
    hint,
    children,
}: {
    number: number;
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="req-step" aria-labelledby={`step-${number}`}>
            <header>
                <b aria-hidden="true">{number}</b>
                <div>
                    <h2 id={`step-${number}`}>{title}</h2>
                    {hint && <p>{hint}</p>}
                </div>
            </header>
            {children}
        </section>
    );
}

type Editing = PersonRequestItem & {
    contact_email: string;
    contact_phone: string | null;
    raw_traits: Record<string, string>;
    event_date: string | null;
    has_stored_photo: boolean;
};

export default function SolicitudCrear({
    initialType,
    options,
    editing,
}: {
    initialType: RequestType;
    editing?: Editing;
    options: {
        states: Option[];
        sexes: Option[];
        types: Option[];
        traits: Option[];
    };
}) {
    const { data, setData, post, processing, errors, clearErrors, transform } =
        useForm<FormData>({
            type: initialType,
            name: editing?.name ?? '',
            sex: editing?.sex ?? '',
            age: editing?.age?.toString() ?? '',
            state: editing?.state ?? '',
            municipality: editing?.municipality ?? '',
            event_date: editing?.event_date ?? '',
            description: editing?.description ?? '',
            traits: editing?.raw_traits ?? {},
            clothing: editing?.clothing ?? '',
            distinguishing_marks: editing?.distinguishing_marks ?? '',
            institution: editing?.institution ?? '',
            contact_email: editing?.contact_email ?? '',
            contact_phone: editing?.contact_phone ?? '',
            photo: null,
        });
    const [removePhoto, setRemovePhoto] = useState(false);
    const [photoUrl, setPhotoUrl] = useState('');
    const [photoError, setPhotoError] = useState('');
    const summary = useRef<HTMLParagraphElement>(null);
    const text = copy[data.type];
    const today = new Date().toISOString().slice(0, 10);
    const errorCount = Object.keys(errors).length;

    useEffect(() => {
        if (!data.photo) {
            setPhotoUrl('');
            return;
        }
        const url = URL.createObjectURL(data.photo);
        setPhotoUrl(url);
        return () => URL.revokeObjectURL(url);
    }, [data.photo]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((current) =>
            editing
                ? { ...current, _method: 'put', remove_photo: removePhoto }
                : current,
        );
        post(
            editing
                ? route('mine.requests.update', editing.id)
                : route('requests.store'),
            {
                forceFormData: true,
                onError: () =>
                    requestAnimationFrame(() => {
                        summary.current?.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                        summary.current?.focus();
                    }),
            },
        );
    };

    const field = (key: keyof FormData) => ({
        id: key,
        value: (data[key] as string) ?? '',
        'aria-invalid': Boolean(errors[key as keyof typeof errors]),
        onChange: (
            event: ChangeEvent<
                HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement
            >,
        ) => setData(key, event.target.value as never),
    });

    const message = (key: string) => {
        const value = errors[key as keyof typeof errors];
        return value ? (
            <span className="req-error" role="alert">
                {value}
            </span>
        ) : null;
    };
    const cls = (key: string, ...more: string[]) =>
        classNames(
            'req-field',
            ...more,
            errors[key as keyof typeof errors] && 'has-error',
        );

    const pickPhoto = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        if (
            file &&
            (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
                file.size > 10 * 1024 * 1024)
        ) {
            setPhotoError('Elige un archivo JPG, PNG o WEBP de hasta 10 MB.');
            event.target.value = '';
            setData('photo', null);
        } else {
            setPhotoError('');
            setData('photo', file);
        }
    };

    return (
        <>
            <Head
                title={`${editing ? 'Editar solicitud' : text.title} · Solicitudes · Encontrarnos`}
            />
            <section className="req-hero" aria-labelledby="request-form-title">
                <div className="req-hero-inner req-hero-inner--single">
                    <div>
                        <p className="req-kicker">
                            <span>04</span>{' '}
                            {editing ? editing.reference : 'NUEVA SOLICITUD'}
                        </p>
                        <h1 id="request-form-title">
                            {editing ? 'Editar solicitud' : text.title}
                            <span className="req-dot">.</span>
                        </h1>
                        <p className="req-lead">
                            {editing
                                ? editing.status === 'pending'
                                    ? 'Cambia lo que necesites. Tu solicitud sigue en revisión.'
                                    : 'Cambia lo que necesites. Al guardar, tu solicitud vuelve a revisión y se publica de nuevo cuando la aprobemos.'
                                : text.lead}
                        </p>
                    </div>
                </div>
                <div style={{ height: 56 }} />
            </section>
            <div className="req-body">
                <Link
                    href={editing ? route('dashboard') : route('requests')}
                    className="req-crumbs"
                    style={{ textDecoration: 'underline' }}
                >
                    {editing
                        ? '← Volver a Mi espacio'
                        : '← Volver a las solicitudes'}
                </Link>
                <div className="req-form-layout">
                    <form className="req-form" onSubmit={submit} noValidate>
                        {errorCount > 0 && (
                            <p
                                className="req-summary"
                                role="alert"
                                tabIndex={-1}
                                ref={summary}
                            >
                                Revisa {errorCount}{' '}
                                {errorCount === 1 ? 'campo' : 'campos'}: hay
                                datos que faltan o no son válidos.
                            </p>
                        )}

                        <Step
                            number={1}
                            title="Tipo de solicitud"
                            hint="Elige la opción que mejor te describe."
                        >
                            <div className="req-types" role="radiogroup">
                                {options.types.map((item) => {
                                    const value = item.value as RequestType;
                                    return (
                                        <label
                                            key={value}
                                            className={classNames(
                                                'req-type',
                                                data.type === value &&
                                                    'is-selected',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="type"
                                                value={value}
                                                checked={data.type === value}
                                                onChange={() => {
                                                    setData('type', value);
                                                    clearErrors();
                                                }}
                                            />
                                            <strong>{copy[value].title}</strong>
                                            <span>{typeHelp[value]}</span>
                                        </label>
                                    );
                                })}
                            </div>
                            {message('type')}
                        </Step>

                        <Step number={2} title="Datos de la persona">
                            <div className="req-grid">
                                <div className={cls('name', 'span-2')}>
                                    <label htmlFor="name">
                                        {text.name}
                                        {data.type === 'search' && (
                                            <small> (obligatorio)</small>
                                        )}
                                    </label>
                                    <input
                                        {...field('name')}
                                        maxLength={120}
                                        autoComplete="off"
                                        placeholder="Nombre completo"
                                    />
                                    {message('name')}
                                </div>
                                <div className={cls('sex')}>
                                    <label htmlFor="sex">Sexo</label>
                                    <select {...field('sex')}>
                                        <option value="">
                                            Sin especificar
                                        </option>
                                        {options.sexes.map((item) => (
                                            <option
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                    {message('sex')}
                                </div>
                                <div className={cls('age')}>
                                    <label htmlFor="age">{text.age}</label>
                                    <input
                                        {...field('age')}
                                        type="number"
                                        inputMode="numeric"
                                        min={0}
                                        max={120}
                                        placeholder="Años"
                                    />
                                    {message('age')}
                                </div>
                            </div>
                        </Step>

                        <Step number={3} title="Lugar y fecha">
                            <div className="req-grid">
                                <div className={cls('state')}>
                                    <label htmlFor="state">
                                        Estado <small>(obligatorio)</small>
                                    </label>
                                    <select {...field('state')}>
                                        <option value="">
                                            Elige un estado
                                        </option>
                                        {options.states.map((item) => (
                                            <option
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                    {message('state')}
                                </div>
                                <div className={cls('municipality')}>
                                    <label htmlFor="municipality">
                                        Municipio <small>(obligatorio)</small>
                                    </label>
                                    <input
                                        {...field('municipality')}
                                        maxLength={120}
                                        autoComplete="off"
                                    />
                                    {message('municipality')}
                                </div>
                                <div className={cls('event_date')}>
                                    <label htmlFor="event_date">
                                        {text.date}
                                    </label>
                                    <input
                                        {...field('event_date')}
                                        type="date"
                                        max={today}
                                    />
                                    {message('event_date')}
                                </div>
                            </div>
                        </Step>

                        <Step
                            number={4}
                            title="Descripción"
                            hint="Mientras más detalles, más fácil será reconocerla."
                        >
                            <div className="req-grid">
                                <div className={cls('description', 'span-2')}>
                                    <label htmlFor="description">
                                        Descripción <small>(obligatorio)</small>
                                    </label>
                                    <textarea
                                        {...field('description')}
                                        rows={5}
                                        maxLength={3000}
                                        placeholder={text.description}
                                    />
                                    <span className="req-counter">
                                        {data.description.length}/3000
                                    </span>
                                    {message('description')}
                                </div>
                                <div className={cls('clothing', 'span-2')}>
                                    <label htmlFor="clothing">
                                        Prendas de vestir
                                    </label>
                                    <textarea
                                        {...field('clothing')}
                                        rows={2}
                                        maxLength={1000}
                                    />
                                    {message('clothing')}
                                </div>
                                <div
                                    className={cls(
                                        'distinguishing_marks',
                                        'span-2',
                                    )}
                                >
                                    <label htmlFor="distinguishing_marks">
                                        Señas particulares
                                    </label>
                                    <textarea
                                        {...field('distinguishing_marks')}
                                        rows={2}
                                        maxLength={1000}
                                        placeholder="Tatuajes, cicatrices, lunares, prótesis…"
                                    />
                                    {message('distinguishing_marks')}
                                </div>
                            </div>
                            <h3 className="req-subhead">
                                Rasgos <small>(opcional)</small>
                            </h3>
                            <div className="req-grid req-grid--traits">
                                {options.traits.map((trait) => (
                                    <div
                                        key={trait.value}
                                        className={cls(`traits.${trait.value}`)}
                                    >
                                        <label htmlFor={`trait-${trait.value}`}>
                                            {trait.label}
                                        </label>
                                        <input
                                            id={`trait-${trait.value}`}
                                            maxLength={80}
                                            value={
                                                data.traits[trait.value] ?? ''
                                            }
                                            onChange={(event) =>
                                                setData('traits', {
                                                    ...data.traits,
                                                    [trait.value]:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        {message(`traits.${trait.value}`)}
                                    </div>
                                ))}
                            </div>
                        </Step>

                        <Step
                            number={5}
                            title="Fotografía"
                            hint="Opcional, pero ayuda mucho. Elige una imagen donde se vea claramente el rostro."
                        >
                            {!photoUrl &&
                            editing?.has_stored_photo &&
                            !removePhoto ? (
                                <div className="req-preview">
                                    {editing.photo_thumb && (
                                        <img
                                            src={editing.photo_thumb}
                                            alt="Fotografía actual"
                                        />
                                    )}
                                    <p>Ya tienes una fotografía guardada.</p>
                                    <button
                                        type="button"
                                        onClick={() => setRemovePhoto(true)}
                                    >
                                        Quitar
                                    </button>
                                </div>
                            ) : photoUrl ? (
                                <div className="req-preview">
                                    <img
                                        src={photoUrl}
                                        alt="Vista previa de la fotografía"
                                    />
                                    <p>{data.photo?.name}</p>
                                    <button
                                        type="button"
                                        onClick={() => setData('photo', null)}
                                    >
                                        Quitar
                                    </button>
                                </div>
                            ) : (
                                <label className="req-drop" htmlFor="photo">
                                    <ImagePlus size={34} strokeWidth={1.4} />
                                    <strong>Elige una fotografía</strong>
                                    <span>JPG, PNG o WEBP · hasta 10 MB</span>
                                    <input
                                        id="photo"
                                        type="file"
                                        accept="image/jpeg,image/png,image/webp"
                                        onChange={pickPhoto}
                                    />
                                </label>
                            )}
                            {(photoError || errors.photo) && (
                                <span className="req-error" role="alert">
                                    {photoError || errors.photo}
                                </span>
                            )}
                        </Step>

                        <Step number={6} title="Contacto">
                            <p className="req-hint">
                                Tu correo y tu teléfono no se publican. Quien
                                tenga información te escribe desde la ficha y el
                                mensaje llega a tu correo.
                            </p>
                            <div className="req-grid">
                                <div className={cls('institution', 'span-2')}>
                                    <label htmlFor="institution">
                                        {text.institution}{' '}
                                        <small>(opcional)</small>
                                    </label>
                                    <input
                                        {...field('institution')}
                                        maxLength={150}
                                        autoComplete="organization"
                                    />
                                    {message('institution')}
                                </div>
                                <div className={cls('contact_email')}>
                                    <label htmlFor="contact_email">
                                        Correo de contacto{' '}
                                        <small>(obligatorio)</small>
                                    </label>
                                    <input
                                        {...field('contact_email')}
                                        type="email"
                                        autoComplete="email"
                                        maxLength={254}
                                        placeholder="tu@correo.com"
                                    />
                                    {message('contact_email')}
                                </div>
                                <div className={cls('contact_phone')}>
                                    <label htmlFor="contact_phone">
                                        Teléfono <small>(opcional)</small>
                                    </label>
                                    <input
                                        {...field('contact_phone')}
                                        type="tel"
                                        autoComplete="tel"
                                        maxLength={30}
                                    />
                                    {message('contact_phone')}
                                </div>
                            </div>
                        </Step>

                        <div className="req-submit">
                            <button
                                type="submit"
                                className="en-primary-button"
                                disabled={processing || Boolean(photoError)}
                                aria-busy={processing}
                            >
                                {processing
                                    ? 'Guardando…'
                                    : editing
                                      ? 'Guardar cambios'
                                      : 'Enviar solicitud'}{' '}
                                {processing ? (
                                    <LoaderCircle
                                        className="en-spin"
                                        size={19}
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <ArrowUpRight size={19} />
                                )}
                            </button>
                            <p>
                                La revisamos antes de publicarla y te avisamos
                                por correo cuando esté lista.
                            </p>
                        </div>
                    </form>

                    <aside className="req-aside" aria-label="Antes de enviar">
                        <section>
                            <h2>Cómo funciona</h2>
                            <ol>
                                <li>Llenas el formulario con lo que sepas.</li>
                                <li>
                                    Revisamos que la información sea útil y
                                    segura.
                                </li>
                                <li>
                                    Se publica en el catálogo y te avisamos por
                                    correo.
                                </li>
                                <li>
                                    Quien tenga información te escribe desde la
                                    ficha.
                                </li>
                            </ol>
                        </section>
                        <section className="req-private">
                            <h2>
                                <ShieldCheck
                                    size={20}
                                    style={{ verticalAlign: '-3px' }}
                                />{' '}
                                Tu contacto es privado
                            </h2>
                            <p>
                                Nunca publicamos tu correo ni tu teléfono. Los
                                mensajes te llegan por correo y puedes
                                responderlos desde ahí.
                            </p>
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

SolicitudCrear.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
