import { RecordBrowser } from '@/Components/Encontrarnos/PublicTools';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

export default function BaseDeDatos() {
    return (
        <PublicLayout>
            <Head title="Base de datos · Encontrarnos" />
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
                            Busca por nombre, lugar o descripción. Revisa las
                            fotografías y los datos que pueden ayudar a
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
        </PublicLayout>
    );
}
