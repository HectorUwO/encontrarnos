import { Option } from '@/types';
import { useEffect, useState } from 'react';

/** Las entidades por orden alfabético del nombre, como las espera quien navega. */
export const statesByName = (states: Option[]) =>
    [...states].sort((a, b) => a.label.localeCompare(b.label, 'es'));

/** Años que se pueden elegir como límite del periodo, del más reciente al más antiguo. */
export function yearOptions(first: number | null, last: number | null) {
    if (first === null || last === null) return [];

    return Array.from({ length: last - first + 1 }, (_, index) => last - index);
}

/** Minúsculas y sin acentos, para buscar sin preocuparse por cómo se escribió. */
export const plainText = (text: string) =>
    text.normalize('NFD').replace(/\p{M}/gu, '').toLocaleLowerCase('es-MX');

export { classNames } from '@/classNames';

export const formatNumber = (value: number) => value.toLocaleString('es-MX');

export const formatRate = (value: number) =>
    value.toLocaleString('es-MX', { maximumFractionDigits: 1 });

export const formatPercent = (value: number) =>
    `${value.toLocaleString('es-MX', { maximumFractionDigits: 1 })}%`;

const monthFormatter = new Intl.DateTimeFormat('es-MX', {
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

/** «2026-09» → «septiembre de 2026». */
export function formatMonth(month: string) {
    const [year, number] = month.split('-').map(Number);
    return monthFormatter.format(new Date(Date.UTC(year, number - 1, 1)));
}

const dateFormatter = new Intl.DateTimeFormat('es-MX', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
});

/** «2026-09-29» → «29 de septiembre de 2026». */
export function formatDate(date: string) {
    return dateFormatter.format(new Date(`${date}T00:00:00Z`));
}

/** Escala «bonita» para el eje vertical: máximo redondeado y marcas parejas. */
export function niceScale(maximum: number, desiredTicks = 4) {
    if (maximum <= 0) return { max: 1, ticks: [0, 1] };

    const rough = maximum / desiredTicks;
    const magnitude = 10 ** Math.floor(Math.log10(rough));
    const step =
        [1, 2, 2.5, 5, 10]
            .map((factor) => factor * magnitude)
            .find((candidate) => candidate >= rough) ?? 10 * magnitude;
    const top = Math.ceil(maximum / step) * step;

    return {
        max: top,
        ticks: Array.from(
            { length: Math.round(top / step) + 1 },
            (_, index) => index * step,
        ),
    };
}

/**
 * Cortes por cuantiles: devuelve el límite inferior de todas las clases menos
 * la primera. Cada clase incluye su límite inferior y excluye el superior.
 */
export function quantileBreaks(values: number[], classes = 5) {
    const sorted = [...values].sort((a, b) => a - b);
    if (sorted.length === 0) return [];

    return Array.from({ length: classes - 1 }, (_, index) => {
        const position = Math.min(
            sorted.length - 1,
            Math.floor(((index + 1) * sorted.length) / classes),
        );
        return sorted[position];
    });
}

export const classIndex = (value: number, breaks: number[]) =>
    breaks.filter((limit) => value >= limit).length;

/**
 * Ancho actual de un elemento, para dibujar gráficas a su tamaño real. Devuelve
 * una ref de función para seguir al elemento aunque aparezca después.
 */
export function useElementWidth<T extends HTMLElement>() {
    const [element, setElement] = useState<T | null>(null);
    const [width, setWidth] = useState(0);

    useEffect(() => {
        if (!element) return;

        setWidth(element.getBoundingClientRect().width);
        const observer = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width),
        );
        observer.observe(element);

        return () => observer.disconnect();
    }, [element]);

    return [setElement, width] as const;
}
