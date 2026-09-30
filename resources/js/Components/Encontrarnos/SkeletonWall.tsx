import { CSSProperties } from 'react';
import { TearKind, mulberry32, tornPaper } from './tornPaper';

type PosterTone = 'red' | 'gold' | 'sage' | 'teal';

interface Wear {
    aged?: boolean;
    peeled?: boolean;
    tear?: { kind: TearKind; depth: number };
}

// Las tres columnas de la derecha son el muro original, a plena opacidad. Las
// demás se extienden hacia la izquierda, detrás del texto, y el CSS las va
// desvaneciendo con la distancia.
const WALL_COLUMNS = 9;
const POSTERS_PER_COLUMN = 7;
const FOCUS_COLUMNS = 3;
const FOCUS_ROWS = 5;
const FOCUS_START = WALL_COLUMNS - FOCUS_COLUMNS;

const TONES: PosterTone[] = ['red', 'gold', 'sage', 'teal'];
const TILTS = [-1.6, 1.1, -0.6, 1.7, -1.1, 0.7, 1.4];
const PEELED_TILTS = [6.5, -5.5, 7];
const PHOTO_RATIOS = ['1 / 0.82', '1 / 0.74', '1 / 0.9'];

// Fichas viejas, rasgadas o a medio arrancar del muro original, según su
// posición (columna * FOCUS_ROWS + fila). Las demás quedan intactas.
const WEAR: Record<number, Wear> = {
    0: { aged: true },
    1: { aged: true, tear: { kind: 'corner', depth: 40 } },
    3: { tear: { kind: 'bottom', depth: 12 } },
    6: { peeled: true },
    7: { aged: true },
    8: { tear: { kind: 'side', depth: 38 } },
    10: { aged: true, tear: { kind: 'top', depth: 10 } },
    12: { aged: true, tear: { kind: 'bottom', depth: 14 } },
    13: { aged: true, peeled: true },
};

// Desgaste repartido al azar (pero fijo) entre las fichas de la extensión.
function ghostWear(seed: number): Wear {
    const rand = mulberry32(seed * 131 + 17);
    const roll = rand();

    if (roll < 0.14) {
        return {
            aged: rand() < 0.5,
            tear: { kind: 'bottom', depth: 10 + rand() * 6 },
        };
    }
    if (roll < 0.26) {
        return { tear: { kind: 'corner', depth: 28 + rand() * 16 } };
    }
    if (roll < 0.34) {
        return {
            aged: rand() < 0.5,
            tear: { kind: 'side', depth: 22 + rand() * 20 },
        };
    }
    if (roll < 0.4) return { peeled: true };
    if (roll < 0.58) return { aged: true };
    return {};
}

const toneAt = (n: number) =>
    TONES[((n % TONES.length) + TONES.length) % TONES.length];

function SkeletonPoster({
    index,
    tone,
    wear,
}: {
    index: number;
    tone: PosterTone;
    wear: Wear;
}) {
    const tear = wear.tear
        ? tornPaper(wear.tear.kind, wear.tear.depth, index + 1)
        : null;
    const tilts = wear.peeled ? PEELED_TILTS : TILTS;

    const style = {
        '--i': index,
        '--tilt': `${tilts[index % tilts.length]}deg`,
        '--photo': PHOTO_RATIOS[index % PHOTO_RATIOS.length],
        ...(tear && { '--sheet-clip': tear.sheet, '--rim-clip': tear.rim }),
    } as CSSProperties;

    const className = [
        'en-skeleton-poster',
        `en-skeleton-poster--${tone}`,
        wear.aged && 'is-aged',
        wear.peeled && 'is-peeled',
        wear.tear && `is-torn is-torn-${wear.tear.kind}`,
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={className} style={style}>
            <div className="en-sk-body">
                {tear && <span className="en-sk-rim" />}
                <div className="en-sk-sheet">
                    <span className="en-sk-bar en-sk-title" />
                    <span className="en-sk-bar en-sk-subtitle" />
                    <span className="en-sk-photo" />
                    <span className="en-sk-bar en-sk-name" />
                    <span className="en-sk-bar en-sk-line" />
                    <span className="en-sk-bar en-sk-line en-sk-line--short" />
                    <span className="en-sk-foot">
                        <span className="en-sk-bar" />
                        <span className="en-sk-qr" />
                    </span>
                </div>
                {wear.peeled && <span className="en-sk-curl" />}
            </div>
            <span className="en-sk-shine" />
        </div>
    );
}

export default function SkeletonWall() {
    return (
        <div
            className="en-wall"
            role="img"
            aria-label="Muro de fichas de búsqueda de personas desaparecidas en México"
        >
            <div className="en-wall-grid" aria-hidden="true">
                {Array.from({ length: WALL_COLUMNS }, (_, column) => (
                    <div
                        className="en-wall-col"
                        key={column}
                        style={
                            {
                                '--col': WALL_COLUMNS - 1 - column,
                            } as CSSProperties
                        }
                    >
                        {Array.from(
                            { length: POSTERS_PER_COLUMN },
                            (_, row) => {
                                const focus =
                                    column >= FOCUS_START && row < FOCUS_ROWS;
                                const key = focus
                                    ? (column - FOCUS_START) * FOCUS_ROWS + row
                                    : 100 + column * POSTERS_PER_COLUMN + row;

                                return (
                                    <SkeletonPoster
                                        key={row}
                                        index={key}
                                        tone={toneAt(
                                            (column - FOCUS_START) * 2 + row,
                                        )}
                                        wear={
                                            focus
                                                ? (WEAR[key] ?? {})
                                                : ghostWear(key)
                                        }
                                    />
                                );
                            },
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
