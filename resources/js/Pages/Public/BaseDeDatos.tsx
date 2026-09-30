import { RecordBrowser } from '@/Components/Encontrarnos/PublicTools';
import '@/Components/Encontrarnos/requests.css';
import PublicLayout from '@/Layouts/PublicLayout';
import { Paginated, PersonRecord, RecordFilters, RecordOptions } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { ReactNode } from 'react';

const steps = [
    [
        'Busca',
        'Por nombre, lugar o descripción, con filtros por estado y edad.',
    ],
    [
        'Abre la ficha',
        'Revisa la fotografía, los rasgos y las señas particulares.',
    ],
    [
        'Comparte información',
        'Si reconoces algún dato, escríbenos desde la ficha.',
    ],
];

export default function BaseDeDatos({
    records,
    totalPublished,
    filters,
    options,
}: {
    records: Paginated<PersonRecord>;
    totalPublished: number;
    filters: RecordFilters;
    options: RecordOptions;
}) {
    return (
        <>
            <Head title="Base de datos · Encontrarnos" />
            <section
                className="req-hero req-hero--ink"
                aria-labelledby="database-title"
            >
                <div className="req-hero-inner">
                    <div>
                        <p className="req-kicker">
                            <span>01</span> BASE DE DATOS
                        </p>
                        <h1 id="database-title">
                            Cada ficha,
                            <em>una persona.</em>
                        </h1>
                        <p className="req-lead">
                            Personas desaparecidas y no localizadas del registro
                            nacional. Revisa las fotografías y los datos que
                            pueden ayudar a continuar una búsqueda.
                        </p>
                    </div>
                    <aside className="req-hero-panel">
                        <div className="req-count">
                            <strong>
                                {totalPublished.toLocaleString('es-MX')}
                            </strong>
                            <span>
                                fichas públicas
                                <br />
                                para consultar
                            </span>
                        </div>
                        <div className="req-cta">
                            <Link href={route('photo-search')}>
                                Buscar con una fotografía{' '}
                                <ArrowUpRight size={20} />
                            </Link>
                            <Link href={route('requests')}>
                                Ver solicitudes de búsqueda{' '}
                                <ArrowUpRight size={20} />
                            </Link>
                        </div>
                    </aside>
                </div>
                <ol className="req-steps" aria-label="Cómo usarla">
                    {steps.map(([title, text], index) => (
                        <li key={title}>
                            <b>0{index + 1}</b>
                            <span>
                                <strong>{title}</strong>
                                {text}
                            </span>
                        </li>
                    ))}
                </ol>
            </section>
            <section className="req-body" id="registros">
                <RecordBrowser
                    records={records}
                    filters={filters}
                    options={options}
                />
            </section>
        </>
    );
}

BaseDeDatos.layout = (page: ReactNode) => <PublicLayout>{page}</PublicLayout>;
