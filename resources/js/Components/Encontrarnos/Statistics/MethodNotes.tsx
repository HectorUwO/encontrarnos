import { RegistryStatistics } from '@/types';
import { formatNumber, formatPercent } from './format';

/** Cómo leer las cifras: de dónde vienen y qué no permiten concluir. */
export default function MethodNotes({
    totals,
    comparison,
}: {
    totals: RegistryStatistics['totals'];
    /** Lo que se compara: «entidades» en el país, «entre entidades» en un estado. */
    comparison: string;
}) {
    return (
        <div className="en-stats-card en-stats-wide">
            <section
                className="en-stats-method"
                aria-labelledby="statistics-method"
            >
                <header className="en-stats-card-head">
                    <div>
                        <h3 id="statistics-method">Cómo leer estas cifras</h3>
                    </div>
                </header>
                <ul>
                    <li>
                        <strong>Fuente.</strong> Consulta pública del Registro
                        Nacional de Personas Desaparecidas y No Localizadas. Las
                        cifras cambian cuando el registro se actualiza.
                    </li>
                    <li>
                        <strong>Confidenciales.</strong>{' '}
                        {formatPercent(
                            totals.registry > 0
                                ? (totals.confidential / totals.registry) * 100
                                : 0,
                        )}{' '}
                        de los registros del país son confidenciales: solo se
                        conoce su entidad y municipio. Su peso varía mucho{' '}
                        {comparison}, así que comparar fechas o perfiles puede
                        engañar.
                    </li>
                    <li>
                        <strong>Por 100 mil habitantes.</strong> Registros entre
                        la población de la entidad (INEGI, Censo de Población y
                        Vivienda 2020). Permite comparar entidades de distinto
                        tamaño.
                    </li>
                    <li>
                        <strong>Sin fecha.</strong>{' '}
                        {formatNumber(totals.undated)} registros que no son
                        confidenciales tampoco informan la fecha de los hechos.
                    </li>
                </ul>
            </section>
        </div>
    );
}
