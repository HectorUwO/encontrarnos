import {
    ActionGrid,
    MobileNavigation,
    RecordBrowser,
    RequestComposer,
    StatisticsPanel,
} from '@/Components/Encontrarnos/PublicTools';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUpRight,
    Check,
    ImagePlus,
    Menu,
    Plus,
    UploadCloud,
    X,
} from 'lucide-react';
import { ChangeEvent, DragEvent, useEffect, useRef, useState } from 'react';
import './welcome.css';

type Preview = { name: string; url: string };

function Brand({ light = false }: { light?: boolean }) {
    return (
        <span className="en-brand">
            <img src={light ? '/3.png' : '/1.png'} alt="" />
            <span>
                encontrarnos<span className="en-brand-period">.</span>
            </span>
        </span>
    );
}

export default function Welcome({ auth }: PageProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [uploadError, setUploadError] = useState('');
    const [isDragging, setIsDragging] = useState(false);
    const [menuOpen, setMenuOpen] = useState(false);
    const [requestOpen, setRequestOpen] = useState(false);
    useEffect(() => {
        const close = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setMenuOpen(false);
        };
        window.addEventListener('keydown', close);
        return () => window.removeEventListener('keydown', close);
    }, []);

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
    const closeMenu = () => setMenuOpen(false);

    return (
        <div className="en-page">
            <Head title="Hasta encontrarnos · Búsqueda de personas en México" />
            <a href="#acciones" className="en-skip-link">
                Ir a las herramientas de búsqueda
            </a>
            <div className="en-topline">
                <span>MÉXICO</span>
                <span>Buscar. Identificar. Compartir.</span>
                <span>LA BÚSQUEDA NOS CONCIERNE.</span>
            </div>
            <header className="en-header">
                <a
                    href="#inicio"
                    aria-label="Encontrarnos, ir al inicio"
                    onClick={closeMenu}
                >
                    <Brand />
                </a>
                <nav
                    id="principal"
                    className={`en-nav ${menuOpen ? 'en-nav-open' : ''}`}
                    aria-label="Navegación principal"
                >
                    <a href="#registros" onClick={closeMenu}>
                        Base de datos
                    </a>
                    <a href="#estadisticas" onClick={closeMenu}>
                        Estadísticas
                    </a>
                    <a href="#buscar" onClick={closeMenu}>
                        Búsqueda por fotografía
                    </a>
                    <a href="#solicitudes" onClick={closeMenu}>
                        Solicitudes
                    </a>
                    <Link
                        className="en-nav-account"
                        href={route(auth.user ? 'dashboard' : 'login')}
                        onClick={closeMenu}
                    >
                        {auth.user ? 'Mi espacio' : 'Ingresar'}{' '}
                        <ArrowUpRight size={18} />
                    </Link>
                </nav>
                <button
                    className="en-menu-button"
                    type="button"
                    aria-controls="principal"
                    aria-expanded={menuOpen}
                    aria-label={menuOpen ? 'Cerrar menú' : 'Abrir menú'}
                    onClick={() => setMenuOpen(!menuOpen)}
                >
                    {menuOpen ? <X /> : <Menu />}
                </button>
            </header>

            <main id="inicio">
                <section className="en-manifesto" aria-labelledby="hero-title">
                    <div className="en-manifesto-copy">
                        <p className="en-section-kicker">
                            <span className="en-ink-square" /> BÚSQUEDA E
                            IDENTIFICACIÓN DE PERSONAS
                        </p>
                        <h1 id="hero-title">
                            <span>HASTA</span>
                            <strong>
                                ENCONTRARNOS<span>.</span>
                            </strong>
                        </h1>
                        <div className="en-manifesto-bottom">
                            <span
                                className="en-manifesto-cross"
                                aria-hidden="true"
                            >
                                ✳
                            </span>
                            <div>
                                <p>
                                    Detrás de cada ausencia hay una persona.
                                    <br />
                                    <strong>
                                        Y una búsqueda que nos necesita.
                                    </strong>
                                </p>
                                <p className="en-manifesto-description">
                                    Información para localizar e identificar
                                    personas desaparecidas en México. Para
                                    familias, personas e instituciones.
                                </p>
                                <a
                                    href="#registros"
                                    className="en-primary-button"
                                >
                                    Buscar en base de datos{' '}
                                    <ArrowUpRight size={22} />
                                </a>
                            </div>
                        </div>
                    </div>
                    <div
                        className="en-poster-wall"
                        aria-label="Cartel ilustrativo de búsqueda"
                    >
                        <div className="en-poster-under" aria-hidden="true">
                            <span>MEMORIA.</span>
                            <span>PRESENCIA.</span>
                        </div>
                        <div className="en-search-poster">
                            <div className="en-poster-eyebrow">
                                <span>ENCONTRARNOS / MÉXICO</span>
                                <Plus size={20} />
                            </div>
                            <h2>
                                LA BÚSQUEDA
                                <br />
                                SIGUE.
                            </h2>
                            <div className="en-poster-portrait">
                                <img
                                    src="/woman-placeholder.png"
                                    alt="Silueta ilustrativa de una persona"
                                />
                            </div>
                            <div className="en-poster-message">
                                UNA PERSONA.
                                <br />
                                TODA UNA HISTORIA.
                            </div>
                            <div className="en-poster-caption">
                                IMAGEN ILUSTRATIVA
                            </div>
                        </div>
                        <span className="en-poster-stamp" aria-hidden="true">
                            NO OLVIDAR.
                        </span>
                    </div>
                    <div className="en-manifesto-foot">
                        <span>La información también ayuda a buscar.</span>
                        <a href="#acciones">
                            Toma parte <ArrowDown size={18} />
                        </a>
                    </div>
                </section>

                <section
                    className="en-action-directory"
                    id="acciones"
                    aria-label="Herramientas de búsqueda"
                >
                    <div className="en-directory-label">
                        CUATRO FORMAS DE PARTICIPAR <ArrowDown size={19} />
                    </div>
                    <ActionGrid />
                </section>

                <section
                    className="en-records en-section"
                    id="registros"
                    aria-labelledby="database-title"
                >
                    <div className="en-section-heading">
                        <div>
                            <p className="en-section-kicker">
                                <span>01</span> / BASE DE DATOS
                            </p>
                            <h2 id="database-title">
                                CADA FICHA,
                                <br />
                                <em>UNA PERSONA.</em>
                            </h2>
                        </div>
                        <div className="en-section-aside">
                            <p>
                                Busca por nombre, lugar o descripción. Revisa
                                las fotografías y los datos que pueden ayudar a
                                continuar una búsqueda.
                            </p>
                            <div className="en-archive-count">
                                <strong>50 MIL +</strong>
                                <span>
                                    registros previstos
                                    <br />
                                    para consultar
                                </span>
                            </div>
                        </div>
                    </div>
                    <RecordBrowser />
                </section>

                <div className="en-solidarity-line" aria-hidden="true">
                    <span>LA AUSENCIA NO SE OLVIDA.</span>
                    <Plus size={36} />
                    <span>LA BÚSQUEDA NO SE DETIENE.</span>
                    <Plus size={36} />
                </div>
                <StatisticsPanel />

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
                            Una imagen puede ayudar a encontrar información.
                            Cárgala para preparar una búsqueda entre los
                            registros disponibles.
                        </p>
                        <ol className="en-photo-steps">
                            <li>
                                <span>1</span>
                                <div>
                                    <strong>Elige una fotografía</strong>
                                    <p>
                                        Usa una imagen en la que el rostro se
                                        vea con claridad.
                                    </p>
                                </div>
                            </li>
                            <li>
                                <span>2</span>
                                <div>
                                    <strong>
                                        Consulta posibles coincidencias
                                    </strong>
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
                                        <ImagePlus
                                            size={44}
                                            strokeWidth={1.3}
                                        />
                                    </div>
                                    <strong>
                                        Arrastra una fotografía aquí
                                    </strong>
                                    <span>
                                        o selecciónala desde tu dispositivo
                                    </span>
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
                        <p className="en-upload-disclaimer">
                            Vista de diseño. La fotografía permanece en tu
                            navegador; no se envía ni se compara todavía.
                        </p>
                    </div>
                </section>

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
                                Consulta solicitudes de búsqueda e
                                identificación. Si necesitas compartir
                                información, prepara una nueva solicitud.
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
                                        alt="Silueta ilustrativa"
                                    />
                                </div>
                            </div>
                            <span className="en-request-art-label">
                                FICHA ILUSTRATIVA
                            </span>
                        </div>
                        <div className="en-request-detail">
                            <span className="en-section-kicker">
                                TABLÓN DE SOLICITUDES / EJEMPLO
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
                            <a
                                href="#registros"
                                className="en-secondary-button"
                            >
                                Consultar las fichas <ArrowUpRight size={20} />
                            </a>
                            <p className="en-request-bottom">
                                Esta solicitud muestra el diseño de una ficha.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    className="en-institutions en-section"
                    id="instituciones"
                >
                    <div className="en-institutions-heading">
                        <p className="en-section-kicker">
                            <span>05</span> / PARTICIPAR EN LA BÚSQUEDA
                        </p>
                        <h2>
                            BUSCAR
                            <br />
                            TAMBIÉN ES
                            <br />
                            <em>COLABORAR.</em>
                        </h2>
                        <p>
                            La información puede estar en muchos lugares.
                            Reunirla ayuda a que una búsqueda continúe.
                        </p>
                        <Link
                            className="en-primary-button"
                            href={route(auth.user ? 'dashboard' : 'register')}
                        >
                            {auth.user ? 'Ir a mi espacio' : 'Crear una cuenta'}
                            <ArrowUpRight size={22} />
                        </Link>
                    </div>
                    <div className="en-participants">
                        <p>UN ESPACIO PARA</p>
                        {[
                            'Personas y familias',
                            'Instituciones de seguridad',
                            'Hospitales',
                            'Centros de rehabilitación',
                            'Servicios forenses',
                        ].map((name, index) => (
                            <div className="en-participant" key={name}>
                                <span>0{index + 1}</span>
                                <h3>{name}</h3>
                                <Plus size={23} aria-hidden="true" />
                            </div>
                        ))}
                        <p className="en-participants-note">
                            Consultar registros. Compartir información. Dar
                            seguimiento.
                        </p>
                    </div>
                </section>
            </main>

            <footer className="en-footer">
                <div className="en-footer-call">
                    <p>
                        QUE LA BÚSQUEDA
                        <br />
                        <span>NO SE DETENGA.</span>
                    </p>
                    <a href="#inicio" aria-label="Volver al inicio">
                        <ArrowUpRight size={54} />
                    </a>
                </div>
                <div className="en-footer-main">
                    <a
                        href="#inicio"
                        aria-label="Encontrarnos, volver al inicio"
                    >
                        <Brand light />
                    </a>
                    <div>
                        <a href="#registros">Base de datos</a>
                        <a href="#estadisticas">Estadísticas</a>
                        <a href="#buscar">Búsqueda por fotografía</a>
                        <a href="#solicitudes">Solicitudes</a>
                    </div>
                    <div>
                        <Link href={route(auth.user ? 'dashboard' : 'login')}>
                            {auth.user ? 'Mi espacio' : 'Ingresar'}
                        </Link>
                        <Link
                            href={route(auth.user ? 'dashboard' : 'register')}
                        >
                            Crear cuenta
                        </Link>
                        <a href="#instituciones">Participar</a>
                    </div>
                </div>
                <div className="en-footer-bottom">
                    <span>Encontrarnos · México · 2026</span>
                    <span>Por quienes faltan. Con quienes buscan.</span>
                </div>
            </footer>
            <MobileNavigation />
            <RequestComposer
                open={requestOpen}
                onClose={() => setRequestOpen(false)}
            />
        </div>
    );
}
