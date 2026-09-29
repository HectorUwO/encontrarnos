import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';
import { ArrowUpRight, Check, ImagePlus, UploadCloud } from 'lucide-react';
import { ChangeEvent, DragEvent, useRef, useState } from 'react';

type Preview = { name: string; url: string };

export default function BusquedaFotografia() {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [uploadError, setUploadError] = useState('');
    const [isDragging, setIsDragging] = useState(false);

    const readFile = (file?: File) => {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            setUploadError('Selecciona una imagen JPG, PNG o WEBP.');
            return;
        }
        if (file.size > 10 * 1024 * 1024) {
            setUploadError('La imagen debe pesar menos de 10 MB.');
            return;
        }
        setUploadError('');
        const reader = new FileReader();
        reader.onload = () =>
            setPreview({ name: file.name, url: String(reader.result) });
        reader.readAsDataURL(file);
    };
    const handleFileChange = (event: ChangeEvent<HTMLInputElement>) =>
        readFile(event.target.files?.[0]);
    const handleDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault();
        setIsDragging(false);
        readFile(event.dataTransfer.files[0]);
    };

    return (
        <PublicLayout>
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
                    <ol className="en-photo-steps">
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
                    </ol>
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
                    {uploadError && (
                        <p className="en-upload-error" role="alert">
                            {uploadError}
                        </p>
                    )}
                    <button
                        className="en-upload-button en-primary-button"
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        {preview
                            ? 'Cambiar fotografía'
                            : 'Seleccionar fotografía'}
                        <ArrowUpRight size={22} />
                    </button>
                </div>
            </section>
        </PublicLayout>
    );
}
