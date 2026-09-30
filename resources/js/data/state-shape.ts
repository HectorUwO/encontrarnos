/**
 * Silueta de una entidad, lista para un <svg>.
 *
 * Fuente: Marco Geoestadístico del INEGI (límites estatales), proyectado con la
 * misma cónica conforme de Lambert que el mapa nacional (paralelos estándar
 * 17.5° y 29.5°, meridiano central -102°). Cada entidad se ajusta por separado
 * a una caja de 640×520 px, se simplifica con Douglas–Peucker (0.3 px) y solo
 * conserva las islas cercanas a su territorio continental (las lejanas, como
 * el archipiélago de Revillagigedo, se omiten para no encoger la silueta).
 * Los archivos se llaman como la clave de entidad del INEGI (01 a 32).
 */
export interface StateShape {
    width: number;
    height: number;
    path: string;
}
