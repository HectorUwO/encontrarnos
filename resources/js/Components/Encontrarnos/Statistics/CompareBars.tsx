import { CSSProperties } from 'react';

export interface Comparison {
    title: string;
    format: (value: number) => string;
    bars: { label: string; value: number; highlight?: boolean }[];
    note?: string;
}

/** Cada comparación pone dos barras a la misma escala: el estado y el país. */
export default function CompareBars({
    comparisons,
}: {
    comparisons: Comparison[];
}) {
    return (
        <div className="en-stats-compare">
            {comparisons.map((comparison) => {
                const maximum = Math.max(
                    1e-9,
                    ...comparison.bars.map((bar) => bar.value),
                );

                return (
                    <section key={comparison.title}>
                        <h4>{comparison.title}</h4>
                        <ul>
                            {comparison.bars.map((bar, index) => (
                                <li
                                    key={bar.label}
                                    className={
                                        bar.highlight
                                            ? 'is-highlight'
                                            : undefined
                                    }
                                >
                                    <span>{bar.label}</span>
                                    <span
                                        className="en-stats-compare-track"
                                        aria-hidden="true"
                                    >
                                        <span
                                            className="en-grow"
                                            style={
                                                {
                                                    width: `${(bar.value / maximum) * 100}%`,
                                                    '--i': index,
                                                } as CSSProperties
                                            }
                                        />
                                    </span>
                                    <strong>
                                        {comparison.format(bar.value)}
                                    </strong>
                                </li>
                            ))}
                        </ul>
                        {comparison.note && <p>{comparison.note}</p>}
                    </section>
                );
            })}
        </div>
    );
}
