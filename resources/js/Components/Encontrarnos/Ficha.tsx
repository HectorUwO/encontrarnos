import { classNames } from '@/classNames';
import { capitalize } from '@/format';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import PhotoViewer from './PhotoViewer';
import './requests.css';

/**
 * Estructura común de las fichas públicas (personas desaparecidas y
 * solicitudes): ruta, foto con etiqueta, título, datos clave y secciones.
 */

export type ChipTone = 'search' | 'identification' | 'missing' | 'not_located';

export function Chip({
    tone,
    children,
}: {
    tone: ChipTone;
    children: ReactNode;
}) {
    return (
        <span className={classNames('req-chip', `req-chip--${tone}`)}>
            {children}
        </span>
    );
}

export function Crumbs({
    items,
}: {
    items: { label: string; href?: string }[];
}) {
    return (
        <nav className="req-crumbs" aria-label="Ruta">
            {items.map((item, index) => (
                <span key={item.label} className="req-crumb">
                    {index > 0 && <span aria-hidden="true">/</span>}
                    {item.href ? (
                        <Link href={item.href}>{item.label}</Link>
                    ) : (
                        <span>{item.label}</span>
                    )}
                </span>
            ))}
        </nav>
    );
}

export function FichaBlock({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <section className="req-block">
            <h2>{title}</h2>
            {children}
        </section>
    );
}

const TRAIT_GROUPS: { title: string; labels: string[] }[] = [
    {
        title: 'Complexión y medidas',
        labels: ['Complexión', 'Estatura', 'Peso'],
    },
    { title: 'Cabello y piel', labels: ['Cabello', 'Color de piel'] },
    { title: 'Rostro', labels: ['Cara', 'Ojos', 'Nariz', 'Boca', 'Labios'] },
];
const METRICS = new Set(['Estatura', 'Peso']);

/**
 * Media filiación agrupada: medidas, cabello y piel, rostro. Los rasgos que no
 * encajan en un grupo van en «Otros rasgos».
 */
export function FichaTraits({
    traits,
}: {
    traits: { label: string; value: string }[];
}) {
    const known = new Set(TRAIT_GROUPS.flatMap((group) => group.labels));
    const groups = [
        ...TRAIT_GROUPS.map((group) => ({
            title: group.title,
            items: group.labels
                .map((label) => traits.find((trait) => trait.label === label))
                .filter((trait) => trait !== undefined),
        })),
        {
            title: 'Otros rasgos',
            items: traits.filter((trait) => !known.has(trait.label)),
        },
    ].filter((group) => group.items.length > 0);

    return (
        <div className="req-traitcard">
            {groups.map((group) => (
                <section key={group.title} className="req-traitgroup">
                    <h3>{group.title}</h3>
                    <dl>
                        {group.items.map((trait) => (
                            <div
                                key={trait.label}
                                className={classNames(
                                    METRICS.has(trait.label) && 'is-metric',
                                )}
                            >
                                <dt>{trait.label}</dt>
                                <dd>{capitalize(trait.value)}</dd>
                            </div>
                        ))}
                    </dl>
                </section>
            ))}
        </div>
    );
}

/**
 * Tabla de datos etiquetados. Los datos que no se tienen aparecen como «Sin
 * dato» para dejar claro que el registro no los trae.
 */
export function FichaData({
    rows,
}: {
    rows: [string, string | null | undefined][];
}) {
    return (
        <dl className="req-datasheet">
            {rows.map(([label, value]) => (
                <div key={label} className={classNames(!value && 'is-empty')}>
                    <dt>{label}</dt>
                    <dd>{value || 'Sin dato'}</dd>
                </div>
            ))}
        </dl>
    );
}

export function FichaShell({
    accent,
    crumbs,
    notices,
    photo,
    photoSrc,
    photoAlt,
    photoMeta,
    tag,
    kicker,
    title,
    facts,
    children,
}: {
    accent: ChipTone;
    crumbs: { label: string; href?: string }[];
    notices?: ReactNode;
    photo: ReactNode;
    /** Si hay fotografía real, se puede abrir a pantalla completa y acercar. */
    photoSrc?: string | null;
    photoAlt?: string;
    /** Referencia que se muestra junto al nombre en el visor (folio o SOL-…). */
    photoMeta?: string;
    tag: ReactNode;
    kicker: ReactNode;
    title: string;
    facts: [string, string | null | undefined][];
    children: ReactNode;
}) {
    return (
        <article className="req-body" aria-labelledby="ficha-title">
            <Crumbs items={crumbs} />
            {notices}
            <div className="req-ficha-grid">
                <aside className="req-ficha-side">
                    <div
                        className={`req-ficha-photo req-ficha-photo--${accent}`}
                    >
                        <div>
                            {photoSrc ? (
                                <PhotoViewer
                                    src={photoSrc}
                                    alt={photoAlt ?? 'Fotografía'}
                                    caption={title}
                                    meta={photoMeta}
                                >
                                    {photo}
                                </PhotoViewer>
                            ) : (
                                photo
                            )}
                        </div>
                    </div>
                    <div className="req-ficha-tag">{tag}</div>
                </aside>
                <div>
                    <p className="req-kicker" style={{ margin: 0 }}>
                        {kicker}
                    </p>
                    <h1 className="req-ficha-title" id="ficha-title">
                        {title}
                    </h1>
                    <dl className="req-facts">
                        {facts
                            .filter(([, value]) => value)
                            .map(([label, value]) => (
                                <div key={label}>
                                    <dt>{label}</dt>
                                    <dd>{value}</dd>
                                </div>
                            ))}
                    </dl>
                    {children}
                </div>
            </div>
        </article>
    );
}
