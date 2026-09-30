/**
 * Formato de texto para datos que llegan de la base en minúsculas o todo en
 * mayúsculas. Los textos que ya vienen bien escritos (mayúsculas y
 * minúsculas mezcladas) solo se corrigen en la primera letra, para no
 * romper siglas como «ONG» o «CDMX».
 */

const CONNECTORS = new Set([
    'a',
    'al',
    'con',
    'de',
    'del',
    'e',
    'el',
    'en',
    'la',
    'las',
    'lo',
    'los',
    'o',
    'para',
    'por',
    'u',
    'y',
]);

const hasLower = (text: string) => /\p{Ll}/u.test(text);
const hasUpper = (text: string) => /\p{Lu}/u.test(text);
const isMixed = (text: string) => hasLower(text) && hasUpper(text);

/** Solo se capitaliza si la palabra empieza con una letra («180cm» queda igual). */
const upperFirst = (word: string) =>
    word.replace(
        /^([^\p{L}\p{N}]*)(\p{L})/u,
        (_match, prefix: string, letter: string) =>
            prefix + letter.toLocaleUpperCase('es-MX'),
    );

/** Primera letra en mayúscula, el resto igual. */
export function capitalize(text: string | null | undefined): string {
    return upperFirst((text ?? '').trim());
}

/**
 * Nombres propios y lugares: cada palabra con inicial mayúscula
 * («la magdalena tlaltelulco» → «La Magdalena Tlaltelulco»), con los
 * conectores en minúscula («Fiscalía General del Estado»).
 */
export function titleCase(text: string | null | undefined): string {
    const value = (text ?? '').trim();
    if (!value) return '';

    const base = isMixed(value) ? value : value.toLocaleLowerCase('es-MX');
    let first = true;

    return base
        .split(/(\s+)/)
        .map((token) => {
            if (/^\s*$/.test(token)) return token;
            const lower = token.toLocaleLowerCase('es-MX');
            const keepLower =
                !first && CONNECTORS.has(lower.replace(/[^\p{L}]/gu, ''));
            first = false;

            return keepLower && !isMixed(token)
                ? lower
                : token.split('-').map(upperFirst).join('-');
        })
        .join('');
}

/**
 * Textos corridos: inicial mayúscula al empezar cada oración
 * («cabello negro. ojos cafés.» → «Cabello negro. Ojos cafés.»). Si todo el
 * texto viene en mayúsculas se pasa primero a minúsculas.
 */
export function sentenceCase(text: string | null | undefined): string {
    const value = (text ?? '').trim();
    if (!value) return '';

    const base =
        hasUpper(value) && !hasLower(value)
            ? value.toLocaleLowerCase('es-MX')
            : value;

    return upperFirst(base).replace(
        /([.!?¡¿]\s+)(\p{L})/gu,
        (_match, separator: string, letter: string) =>
            separator + letter.toLocaleUpperCase('es-MX'),
    );
}

/**
 * El registro nacional junta las prendas en una sola línea
 * («Prenda de vestir: pantalón, color: negro Prenda de vestir: sudadera…»).
 * Las separa en una lista; devuelve null si el texto no tiene ese formato.
 */
export function splitGarments(
    text: string | null | undefined,
): string[] | null {
    const value = (text ?? '').trim();
    if (!/^prenda de vestir:/i.test(value)) return null;

    const items = value
        .split(/prenda de vestir:\s*/i)
        .map((item) => item.trim().replace(/[,;]$/, ''))
        .filter(Boolean)
        .map(sentenceCase);

    return items.length > 1 ? items : null;
}

/**
 * Tiempo transcurrido desde una fecha ISO («2 años y 5 meses», «12 días»).
 */
export function elapsedSince(
    iso: string | null | undefined,
    now: Date = new Date(),
): string | null {
    if (!iso) return null;
    const start = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(start.getTime()) || start > now) return null;

    let months =
        (now.getFullYear() - start.getFullYear()) * 12 +
        now.getMonth() -
        start.getMonth();
    if (now.getDate() < start.getDate()) months -= 1;

    if (months >= 12) {
        const years = Math.floor(months / 12);
        const rest = months % 12;
        const yearLabel = `${years} ${years === 1 ? 'año' : 'años'}`;
        return rest
            ? `${yearLabel} y ${rest} ${rest === 1 ? 'mes' : 'meses'}`
            : yearLabel;
    }
    if (months >= 1) return `${months} ${months === 1 ? 'mes' : 'meses'}`;

    const days = Math.max(
        0,
        Math.floor((now.getTime() - start.getTime()) / 86_400_000),
    );
    return `${days} ${days === 1 ? 'día' : 'días'}`;
}

