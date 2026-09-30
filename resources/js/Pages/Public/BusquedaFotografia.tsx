import { Reveal } from '@/Components/Encontrarnos/motion';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    ChevronDown,
    ImagePlus,
    LoaderCircle,
    Search,
    UploadCloud,
} from 'lucide-react';
import { ChangeEvent, DragEvent, ReactNode, useRef, useState } from 'react';

type Preview = { name: string; url: string };

type PhotoMatch = {
    folio?: string;
    reference?: string;
    name: string;
    portrait: string;
    url: string;
    source: 'record' | 'request';
    similarity: number | null;
    municipality?: string;
    state_label?: string;
};

interface PhotoSearchResult {
    available: boolean;
    matches: PhotoMatch[];
    other_matches: PhotoMatch[];
}

function MatchCards({
    matches,
    offset = 0,
}: {
    matches: PhotoMatch[];
    offset?: number;
}) {
    return (
        <ol className="en-face-grid" start={offset + 1}>
            {matches.map((person, index) => (
                <li key={person.url} className="en-face-card">
                    <Link href={person.url} className="en-face-card-link">
                        <div className="en-face-portrait">
                            <img
                                src={person.portrait}
                                alt={`Fotografía de ${person.name}`}
                                loading="lazy"
                            />
                            <span className="en-face-rank">
                                {String(offset + index + 1).padStart(2, '0')}
                            </span>
                            <span className="en-face-score">
                                {((person.similarity ?? 0) * 100).toFixed(1)} %
                                <small>SIMILITUD FACIAL</small>
                            </span>
                        </div>
                        <div className="en-face-card-body">
                            <span className="en-face-source">
                                {person.source === 'record'
                                    ? 'Desaparecidos'
                                    : 'Solicitud de información'}
                            </span>
                            <h3>{person.name}</h3>
                            <p>
                                {[person.municipality, person.state_label]
                                    .filter(Boolean)
                                    .join(', ') || 'Ubicación no disponible'}
                            </p>
                            <div className="en-face-card-footer">
                                <span>{person.folio || person.reference}</span>
                                <span>
                                    Ver ficha{' '}
                                    <ArrowUpRight
                                        size={16}
                                        aria-hidden="true"
                                    />
                                </span>
                            </div>
                        </div>
                    </Link>
                </li>
            ))}
        </ol>
    );
}

function PhotoSearchOutcome({
    search,
    preview,
}: {
    search: PhotoSearchResult;
    preview: Preview | null;
}) {
    if (!search.available) {
        return (
            <div className="en-photo-outcome" role="status">
                <strong>El servicio de comparación no está disponible.</strong>
                <p>
                    No pudimos realizar la comparación en este momento. Intenta
                    de nuevo más tarde. También puedes buscar por nombre, lugar
                    o descripción.
                </p>
                <Link href={route('records')} className="en-secondary-button">
                    Buscar en la base de datos <ArrowUpRight size={20} />
                </Link>
            </div>
        );
    }

    const otherMatches = search.other_matches ?? [];
    if (search.matches.length === 0 && otherMatches.length === 0) {
        return (
            <div className="en-photo-outcome" role="status">
                <strong>
                    No encontramos coincidencias con al menos 30 % de similitud.
                </strong>
                <p>
                    Prueba con otra fotografía o busca por nombre, lugar o
                    descripción en la base de datos.
                </p>
            </div>
        );
    }

    return (
        <div className="en-face-results">
            <header className="en-face-results-header">
                <div>
                    <p className="en-section-kicker">
                        RESULTADO DE LA COMPARACIÓN
                    </p>
                    <h2 id="photo-results-title">
                        POSIBLES <em>COINCIDENCIAS.</em>
                    </h2>
                    <p role="status">
                        {search.matches.length} resultados por encima del 80 % ·{' '}
                        {otherMatches.length} adicionales disponibles.
                    </p>
                </div>
                {preview && (
                    <div className="en-face-query">
                        <img
                            src={preview.url}
                            alt="Fotografía usada en esta comparación"
                        />
                        <span>
                            Tu fotografía<small>Solo para esta consulta</small>
                        </span>
                    </div>
                )}
            </header>
            <p className="en-face-notice">
                El porcentaje expresa similitud facial, no certeza de identidad.
                Compara las fotografías y los datos de cada ficha antes de dar
                seguimiento.
            </p>
            {search.matches.length > 0 ? (
                <>
                    <div className="en-face-group-heading">
                        <h3>Las más parecidas</h3>
                        <span>Hasta 10 · Similitud superior al 80 %</span>
                    </div>
                    <MatchCards matches={search.matches} />
                </>
            ) : (
                <p className="en-face-empty">
                    No hay resultados por encima del 80 %. Puedes revisar las
                    otras posibles coincidencias a continuación.
                </p>
            )}
            {otherMatches.length > 0 && (
                <details className="en-face-more">
                    <summary>
                        <span>
                            Otras posibles coincidencias{' '}
                            <small>
                                {otherMatches.length} resultados · Desde 30 % de
                                similitud
                            </small>
                        </span>
                        <ChevronDown size={24} aria-hidden="true" />
                    </summary>
                    <div className="en-face-more-content">
                        <p>
                            Ordenadas de mayor a menor similitud, sin repetir
                            las fichas anteriores. Se muestran hasta 100
                            resultados en total.
                        </p>
                        <MatchCards
                            matches={otherMatches}
                            offset={search.matches.length}
                        />
                    </div>
                </details>
            )}
        </div>
    );
}

export default function BusquedaFotografia({
    search,
    errors,
}: {
    search?: PhotoSearchResult;
    errors: { photo?: string };
}) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [searching, setSearching] = useState(false);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [uploadError, setUploadError] = useState('');
    const [isDragging, setIsDragging] = useState(false);
    const [showResults, setShowResults] = useState(Boolean(search));

    const readFile = (selected?: File) => {
        if (!selected || searching) return;
        if (
            !['image/jpeg', 'image/png', 'image/webp'].includes(selected.type)
        ) {
            setUploadError('Selecciona una imagen JPG, PNG o WEBP.');
            return;
        }
        if (selected.size > 10 * 1024 * 1024) {
            setUploadError('La imagen debe pesar menos de 10 MB.');
            return;
        }
        setUploadError('');
        setShowResults(false);
        setFile(selected);
        const reader = new FileReader();
        reader.onload = () =>
            setPreview({ name: selected.name, url: String(reader.result) });
        reader.readAsDataURL(selected);
    };
    const submit = () => {
        if (!file || searching) return;
        router.post(
            route('photo-search.store'),
            { photo: file },
            {
                forceFormData: true,
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    setSearching(true);
                    setShowResults(false);
                },
                onSuccess: () => setShowResults(true),
                onFinish: () => setSearching(false),
            },
        );
    };
    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) =>
        readFile(event.target.files?.[0]);
    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        readFile(event.dataTransfer.files[0]);
    };

    return (
        <>
            <Head title="Búsqueda por fotografía · Encontrarnos" />
            <section
                className="en-search-section"
                id="buscar"
                aria-labelledby="photo-title"
            >
                <div className="en-search-intro">
                    <p className="en-section-kicker">
                        <span>03</span> / BÚSQUEDA POR FOTOGRAFÍA
                    </p>
                    <h2 id="photo-title">
                        ¿TIENES
                        <br />
                        <em>UNA FOTO?</em>
                    </h2>
                    <p>
                        Una imagen puede ayudar a encontrar información. Cárgala
                        para buscar entre fichas de desaparecidos y solicitudes
                        de información publicadas.
                    </p>
                    <Reveal as="ol" stagger className="en-photo-steps">
                        <li>
                            <span>1</span>
                            <div>
                                <strong>Elige una fotografía</strong>
                                <p>
                                    Usa una imagen en la que el rostro se vea
                                    con claridad.
                                </p>
                            </div>
                        </li>
                        <li>
                            <span>2</span>
                            <div>
                                <strong>Consulta posibles coincidencias</strong>
                                <p>
                                    Compara las descripciones y los datos
                                    disponibles.
                                </p>
                            </div>
                        </li>
                        <li>
                            <span>3</span>
                            <div>
                                <strong>Da seguimiento</strong>
                                <p>
                                    Revisa los canales de contacto de cada
                                    ficha.
                                </p>
                            </div>
                        </li>
                    </Reveal>
                </div>
                <div className="en-upload-card">
                    <div className="en-upload-card-header">
                        <span>ARCHIVO FOTOGRÁFICO</span>
                        <UploadCloud size={24} />
                    </div>
                    <h3>Empieza con una imagen.</h3>
                    <div
                        className={`en-dropzone ${isDragging ? 'en-dropzone-dragging' : ''} ${preview ? 'en-dropzone-filled' : ''}`}
                        onDragOver={(event) => {
                            event.preventDefault();
                            setIsDragging(true);
                        }}
                        onDragLeave={() => setIsDragging(false)}
                        onDrop={handleDrop}
                    >
                        {preview ? (
                            <>
                                <img
                                    src={preview.url}
                                    alt="Vista previa de la fotografía seleccionada"
                                />
                                <div className="en-preview-info">
                                    <Check size={21} />
                                    <span>
                                        {preview.name}
                                        <small>
                                            Imagen lista para revisar.
                                        </small>
                                    </span>
                                </div>
                            </>
                        ) : (
                            <>
                                <div className="en-photo-outline">
                                    <ImagePlus size={44} strokeWidth={1.3} />
                                </div>
                                <strong>Arrastra una fotografía aquí</strong>
                                <span>o selecciónala desde tu dispositivo</span>
                                <small>JPG, PNG o WEBP · Hasta 10 MB</small>
                            </>
                        )}
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            disabled={searching}
                            onChange={handleFileChange}
                            className="en-file-input"
                            aria-label="Seleccionar fotografía"
                        />
                    </div>
                    {(uploadError || errors.photo) && (
                        <p className="en-upload-error" role="alert">
                            {uploadError || errors.photo}
                        </p>
                    )}
                    <button
                        className={
                            preview
                                ? 'en-upload-button en-secondary-button'
                                : 'en-upload-button en-primary-button'
                        }
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        disabled={searching}
                    >
                        {preview
                            ? 'Cambiar fotografía'
                            : 'Seleccionar fotografía'}
                        <ArrowUpRight size={22} />
                    </button>
                    {preview && (
                        <button
                            className="en-upload-button en-primary-button"
                            type="button"
                            onClick={submit}
                            disabled={searching}
                            aria-busy={searching}
                        >
                            {searching
                                ? 'Buscando…'
                                : 'Consultar posibles coincidencias'}
                            {searching ? (
                                <LoaderCircle
                                    className="en-spin"
                                    size={22}
                                    aria-hidden="true"
                                />
                            ) : (
                                <Search size={22} />
                            )}
                        </button>
                    )}
                    <p className="en-upload-disclaimer">
                        La fotografía solo se usa para esta consulta: no se
                        guarda ni se publica.
                    </p>
                </div>
            </section>
            {showResults && search && !searching && (
                <section
                    className="en-photo-results-section"
                    aria-label="Resultados de comparación facial"
                >
                    <PhotoSearchOutcome search={search} preview={preview} />
                </section>
            )}
        </>
    );
}

BusquedaFotografia.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
