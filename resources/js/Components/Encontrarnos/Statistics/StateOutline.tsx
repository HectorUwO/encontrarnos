import { MEXICO_MAP_STATES, MEXICO_MAP_VIEWBOX } from '@/data/mexico-map';
import type { StateShape } from '@/data/state-shape';
import { useEffect, useState } from 'react';
import { classNames } from './format';

// Cada silueta es un módulo aparte: solo se descarga la del estado que se abre.
const shapes = import.meta.glob<{ default: StateShape }>(
    '../../../data/states/*.ts',
);

/** Silueta del estado, dibujada a partir de los límites del INEGI. */
export default function StateOutline({
    code,
    label,
}: {
    code: number;
    label: string;
}) {
    const [shape, setShape] = useState<StateShape | null>(null);

    useEffect(() => {
        let current = true;
        const load =
            shapes[`../../../data/states/${String(code).padStart(2, '0')}.ts`];

        setShape(null);
        load?.().then((module) => {
            if (current) setShape(module.default);
        });

        return () => {
            current = false;
        };
    }, [code]);

    if (!shape) {
        return (
            <div className="en-state-outline is-loading" aria-hidden="true" />
        );
    }

    return (
        <svg
            className="en-state-outline"
            viewBox={`0 0 ${shape.width} ${shape.height}`}
            role="img"
            aria-label={`Silueta de ${label}`}
        >
            <path d={shape.path} fillRule="evenodd" />
        </svg>
    );
}

/** El país en pequeño, con el estado marcado, para ubicarlo. */
export function LocationMap({ code, label }: { code: number; label: string }) {
    return (
        <svg
            className="en-state-location"
            viewBox={`0 0 ${MEXICO_MAP_VIEWBOX.width} ${MEXICO_MAP_VIEWBOX.height}`}
            role="img"
            aria-label={`Ubicación de ${label} en el mapa de México`}
        >
            {MEXICO_MAP_STATES.map((state) => (
                <path
                    key={state.code}
                    d={state.path}
                    fillRule="evenodd"
                    className={classNames(state.code === code && 'is-current')}
                />
            ))}
        </svg>
    );
}
