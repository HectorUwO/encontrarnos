import { Reveal } from '@/Components/Encontrarnos/motion';
import PublicLayout from '@/Layouts/PublicLayout';
import { PersonRecord } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowUpRight,
    Check,
    ImagePlus,
    LoaderCircle,
    Search,
    UploadCloud,
} from 'lucide-react';
import {
    CSSProperties,
    ChangeEvent,
    DragEvent,
    ReactNode,
    useRef,
    useState,
} from 'react';

type Preview = { name: string; url: string };

interface PhotoSearchResult {
    available: boolean;
    matches: PersonRecord[];
}

function PhotoSearchOutcome({ search }: { search: PhotoSearchResult }) {
    if (!search.available) {
        return (
            <div className="en-photo-outcome" role="status">
                <strong>Esta búsqueda todavía no está disponible.</strong>
                <p>
                    Comparar rostros implica tratar datos biométricos, así que
                    antes de activarla se está definiendo cómo hacerlo con
                    cuidado. Mientras tanto puedes buscar por nombre, lugar o
                    descripción.
                </p>
                <Link href={route('records')} className="en-secondary-button">
                    Buscar en la base de datos <ArrowUpRight size={20} />
                </Link>
            </div>
        );
    }

    if (search.matches.length === 0) {
        return (
            <div className="en-photo-outcome" role="status">
                <strong>No encontramos coincidencias.</strong>
                <p>
                    Prueba con otra fotografía o busca por nombre, lugar o
                    descripción en la base de datos.
                </p>
            </div>
        );
    }

    return (
        <div className="en-photo-outcome" role="status">
            <strong>Posibles coincidencias</strong>
            <p>
                Son solo una guía: compara los datos de cada ficha antes de dar
                seguimiento.
            </p>
            <ul className="en-photo-matches">
                {search.matches.map((record, index) => (
                    <li
                        key={record.folio}
                        style={{ '--i': index } as CSSProperties}
                    >
                        <Link href={route('records', { q: record.folio })}>
                            <img src={record.portrait} alt="" />
                            <span>
                                {record.name}
                                <small>
                                    {[record.municipality, record.state_label]
                                        .filter(Boolean)
                                        .join(', ') || record.folio}
                                </small>
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
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

    const readFile = (selected?: File) => {
        if (!selected) return;
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
        setFile(selected);
        const reader = new FileReader();
        reader.onload = () =>
            setPreview({ name: selected.name, url: String(reader.result) });
        reader.readAsDataURL(selected);
    };
    const submit = () => {
        if (!file) return;
        router.post(
            route('photo-search.store'),
            { photo: file },
            {
                forceFormData: true,
                preserveState: true,
                preserveScroll: true,
                onStart: () => setSearching(true),
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
                        para preparar una búsqueda entre los registros
                        disponibles.
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
                    {search && <PhotoSearchOutcome search={search} />}
                </div>
            </section>
        </>
    );
}

BusquedaFotografia.layout = (page: ReactNode) => (
    <PublicLayout>{page}</PublicLayout>
);
