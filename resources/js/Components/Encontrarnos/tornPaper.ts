type Vec = [number, number];

export type TearKind = 'top' | 'bottom' | 'side' | 'corner';

interface Geometry {
    head: Vec[];
    line: Vec[];
    normal: Vec;
    tail: Vec[];
}

const STEPS = 16;
const AMPLITUDE = 5;

// PRNG determinista: el rasgado es idéntico en cada render.
export function mulberry32(seed: number) {
    let a = seed | 0;
    return () => {
        a = (a + 0x6d2b79f5) | 0;
        let t = Math.imul(a ^ (a >>> 15), 1 | a);
        t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

const along = (at: (t: number) => Vec): Vec[] =>
    Array.from({ length: STEPS }, (_, i) => at(i / (STEPS - 1)));

// `depth` es el porcentaje de la ficha que se pierde; `normal` apunta hacia
// la parte arrancada.
function geometry(kind: TearKind, depth: number): Geometry {
    switch (kind) {
        case 'bottom':
            return {
                head: [
                    [0, 0],
                    [100, 0],
                ],
                line: along((t) => [100 - 100 * t, 100 - depth]),
                normal: [0, 1],
                tail: [],
            };
        case 'top':
            return {
                head: [],
                line: along((t) => [100 * t, depth]),
                normal: [0, -1],
                tail: [
                    [100, 100],
                    [0, 100],
                ],
            };
        case 'side':
            return {
                head: [[0, 0]],
                line: along((t) => [100 - depth, 100 * t]),
                normal: [1, 0],
                tail: [[0, 100]],
            };
        case 'corner': {
            const rise = depth;
            const run = depth * 1.3;
            const length = Math.hypot(rise, run);
            return {
                head: [
                    [0, 0],
                    [100, 0],
                ],
                line: along((t) => [100 - run * t, 100 - rise * (1 - t)]),
                normal: [rise / length, run / length],
                tail: [[0, 100]],
            };
        }
    }
}

const percent = (value: number) =>
    `${Math.round(Math.min(100, Math.max(0, value)) * 10) / 10}%`;

const polygon = (points: Vec[]) =>
    `polygon(${points.map(([x, y]) => `${percent(x)} ${percent(y)}`).join(', ')})`;

/**
 * Devuelve dos `clip-path` con el mismo rasgado: `sheet` recorta el papel y
 * `rim` recorta una capa más clara justo debajo, que asoma como la fibra
 * blanca del borde roto.
 */
export function tornPaper(kind: TearKind, depth: number, seed: number) {
    const rand = mulberry32(seed);
    const { head, line, normal, tail } = geometry(kind, depth);
    const tangent: Vec = [-normal[1], normal[0]];

    const sheet: Vec[] = [];
    const rim: Vec[] = [];

    line.forEach(([x, y], i) => {
        const isEnd = i === 0 || i === STEPS - 1;
        const jag = (rand() - 0.5) * AMPLITUDE * (i % 2 ? 1.5 : 0.7);
        const slide = isEnd ? 0 : (rand() - 0.5) * 1.6;
        const fiber = 0.8 + rand() * 1.6;

        const at = (offset: number): Vec => [
            x + normal[0] * offset + tangent[0] * slide,
            y + normal[1] * offset + tangent[1] * slide,
        ];

        sheet.push(at(jag));
        rim.push(at(jag + fiber));
    });

    return {
        sheet: polygon([...head, ...sheet, ...tail]),
        rim: polygon([...head, ...rim, ...tail]),
    };
}
