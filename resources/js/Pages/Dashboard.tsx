import { ActionGrid } from '@/Components/Encontrarnos/PublicTools';
import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    ArrowUpRight,
    ChartNoAxesColumnIncreasing,
    FileText,
    Fingerprint,
    ImagePlus,
    LayoutDashboard,
    LogOut,
    Menu,
    Search,
    ShieldCheck,
    UsersRound,
    X,
} from 'lucide-react';
import { useState } from 'react';
import './dashboard.css';

const records = [
    {
        id: 'MUESTRA-001',
        region: 'Ciudad de México',
        kind: 'Registro con fotografía',
        portrait: '/woman-placeholder.png',
    },
    {
        id: 'MUESTRA-002',
        region: 'Jalisco',
        kind: 'Solicitud de identificación',
        portrait: '/men%20place%20holder.png',
    },
    {
        id: 'MUESTRA-003',
        region: 'Nuevo León',
        kind: 'Registro con fotografía',
        portrait: '/men%20place%20holder.png',
    },
];

export default function Dashboard({ auth }: PageProps) {
    const [query, setQuery] = useState('');
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const visibleRecords = records.filter((record) =>
        `${record.id} ${record.region} ${record.kind}`
            .toLocaleLowerCase('es-MX')
            .includes(query.toLocaleLowerCase('es-MX')),
    );

    return (
        <div className="en-workspace">
            <Head title="Panel de consulta" />
            <aside
                className={`en-work-sidebar ${mobileMenuOpen ? 'en-work-sidebar-open' : ''}`}
            >
                <Link href="/" className="en-work-brand">
                    <img src="/3.png" alt="" />
                    <span>
                        encontrarnos<span>.</span>
                    </span>
                </Link>
                <div className="en-work-side-label">ÁREA DE TRABAJO</div>
                <nav className="en-work-nav" aria-label="Navegación del panel">
                    <a
                        href="#inicio"
                        className="en-work-nav-active"
                        onClick={() => setMobileMenuOpen(false)}
                    >
                        <LayoutDashboard size={18} /> Vista general
                    </a>
                    <a
                        href="#registros"
                        onClick={() => setMobileMenuOpen(false)}
                    >
                        <FileText size={18} /> Base de datos
                    </a>
                    <a
                        href="/#estadisticas"
                        onClick={() => setMobileMenuOpen(false)}
                    >
                        <ChartNoAxesColumnIncreasing size={18} /> Estadísticas
                    </a>
                    <a
                        href="#herramientas"
                        onClick={() => setMobileMenuOpen(false)}
                    >
                        <Fingerprint size={18} /> Búsqueda por fotografía
                    </a>
                    <a
                        href="#solicitudes"
                        onClick={() => setMobileMenuOpen(false)}
                    >
                        <UsersRound size={18} /> Ver o crear solicitudes
                    </a>
                </nav>
                <div className="en-work-sidebar-bottom">
                    <div className="en-work-user">
                        <span className="en-work-user-avatar">
                            {auth.user.name.charAt(0).toUpperCase()}
                        </span>
                        <span>
                            <strong>{auth.user.name}</strong>
                            <small>Cuenta de usuario</small>
                        </span>
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            aria-label="Cerrar sesión"
                        >
                            <LogOut size={17} />
                        </Link>
                    </div>
                </div>
            </aside>
            {mobileMenuOpen && (
                <button
                    className="en-work-overlay"
                    type="button"
                    aria-label="Cerrar menú"
                    onClick={() => setMobileMenuOpen(false)}
                />
            )}
            <div className="en-work-main" id="inicio">
                <header className="en-work-topbar">
                    <button
                        className="en-work-menu"
                        type="button"
                        aria-label={
                            mobileMenuOpen ? 'Cerrar menú' : 'Abrir menú'
                        }
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                    >
                        {mobileMenuOpen ? <X size={22} /> : <Menu size={22} />}
                    </button>
                    <span>PANEL / VISTA GENERAL</span>
                    <div>
                        <span className="en-work-demo-tag">
                            <span /> VISTA DE DISEÑO
                        </span>
                        <Link href="/" aria-label="Ir al sitio público">
                            <ArrowUpRight size={18} />
                        </Link>
                    </div>
                </header>
                <main className="en-work-content">
                    <div className="en-work-intro">
                        <div>
                            <span className="en-work-kicker">
                                ENCONTRARNOS / PANEL DE CONSULTA
                            </span>
                            <h1>SEGUIR BUSCANDO.</h1>
                            <p>
                                Consulta la base de datos, revisa estadísticas o
                                busca con una fotografía. También puedes ver y
                                preparar solicitudes.
                            </p>
                        </div>
                        <div className="en-work-intro-mark">
                            <img src="/1.png" alt="" />
                        </div>
                    </div>
                    <div id="herramientas">
                        <ActionGrid />
                    </div>
                    <section className="en-work-registers" id="registros">
                        <div className="en-work-section-top">
                            <div>
                                <span className="en-work-kicker">
                                    MÓDULO DE REGISTROS
                                </span>
                                <h2>Explorador de fichas</h2>
                            </div>
                            <span className="en-work-sample-label">
                                DATOS ILUSTRATIVOS
                            </span>
                        </div>
                        <div className="en-work-search">
                            <Search size={18} />
                            <input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Buscar por folio, ubicación o tipo de ficha"
                                aria-label="Buscar fichas de ejemplo"
                            />
                        </div>
                        <div className="en-work-table">
                            <div className="en-work-table-head">
                                <span>REGISTRO</span>
                                <span>UBICACIÓN</span>
                                <span>TIPO</span>
                                <span>ESTADO</span>
                            </div>
                            {visibleRecords.length ? (
                                visibleRecords.map((record) => (
                                    <div
                                        className="en-work-table-row"
                                        key={record.id}
                                    >
                                        <div className="en-work-table-identity">
                                            <span className="en-work-table-avatar">
                                                <img
                                                    src={record.portrait}
                                                    alt="Silueta de ejemplo"
                                                />
                                            </span>
                                            <strong>
                                                {record.id}
                                                <small>Ficha ilustrativa</small>
                                            </strong>
                                        </div>
                                        <span>{record.region}</span>
                                        <span>{record.kind}</span>
                                        <span className="en-work-table-status">
                                            <span /> Ejemplo
                                        </span>
                                    </div>
                                ))
                            ) : (
                                <div className="en-work-empty">
                                    No hay fichas de ejemplo que coincidan con
                                    la búsqueda.
                                </div>
                            )}
                        </div>
                        <div className="en-work-table-note">
                            <ShieldCheck size={15} /> Las fichas muestran
                            únicamente la estructura visual del módulo.
                        </div>
                    </section>
                    <div className="en-work-lower">
                        <section id="solicitudes">
                            <div className="en-work-lower-icon">
                                <ImagePlus size={22} />
                            </div>
                            <span className="en-work-kicker">
                                SOLICITUDES DE IDENTIFICACIÓN
                            </span>
                            <h2>COMPARTIR LO QUE SABEMOS.</h2>
                            <p>
                                El módulo reunirá solicitudes con descripción,
                                fotografía y un canal de contacto para dar
                                seguimiento.
                            </p>
                            <a href="/#solicitudes">
                                Ver o crear solicitudes <ArrowRight size={17} />
                            </a>
                        </section>
                        <section>
                            <div className="en-work-lower-icon">
                                <ChartNoAxesColumnIncreasing size={22} />
                            </div>
                            <span className="en-work-kicker">ESTADÍSTICAS</span>
                            <h2>MIRAR LOS DATOS.</h2>
                            <p>
                                Explora los registros por estado y edad para
                                consultar cómo se distribuye la información.
                            </p>
                            <a href="/#estadisticas">
                                Ver estadísticas <ArrowRight size={17} />
                            </a>
                        </section>
                    </div>
                    <footer className="en-work-footer">
                        Encontrarnos · Panel de diseño{' '}
                        <span>Sin registros reales ni búsqueda conectada</span>
                    </footer>
                </main>
            </div>
        </div>
    );
}
