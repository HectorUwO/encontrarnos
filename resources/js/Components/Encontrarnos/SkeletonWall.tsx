import { CSSProperties } from 'react';

type PosterTone = 'red' | 'gold' | 'sage' | 'teal';

const WALL_COLUMNS = 3;
const POSTERS_PER_COLUMN = 5;
const TONES: PosterTone[] = ['red', 'gold', 'sage', 'teal'];
const TILTS = [-1.6, 1.1, -0.6, 1.7, -1.1, 0.7, 1.4];
const PHOTO_RATIOS = ['1 / 0.82', '1 / 0.74', '1 / 0.9'];

function SkeletonPoster({ index, tone }: { index: number; tone: PosterTone }) {
    const style = {
        '--i': index,
        '--tilt': `${TILTS[index % TILTS.length]}deg`,
        '--photo': PHOTO_RATIOS[index % PHOTO_RATIOS.length],
    } as CSSProperties;

    return (
        <div
            className={`en-skeleton-poster en-skeleton-poster--${tone}`}
            style={style}
        >
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
                        style={{ '--col': column } as CSSProperties}
                    >
                        {Array.from(
                            { length: POSTERS_PER_COLUMN },
                            (_, row) => (
                                <SkeletonPoster
                                    key={row}
                                    index={column * POSTERS_PER_COLUMN + row}
                                    tone={
                                        TONES[(column * 2 + row) % TONES.length]
                                    }
                                />
                            ),
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
