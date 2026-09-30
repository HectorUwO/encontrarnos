import { Option, StatisticsEntity } from '@/types';
import { Link } from '@inertiajs/react';
import { classNames, formatNumber, statesByName } from './format';

/** Las 32 entidades como enlaces, para pasar de una página de estado a otra. */
export default function StateNavigator({
    states,
    entities,
    current,
    hrefFor,
}: {
    states: Option[];
    entities: StatisticsEntity[];
    current: string;
    hrefFor: (state: string) => string;
}) {
    const totals = new Map(
        entities.map((entity) => [entity.state, entity.total]),
    );

    return (
        <nav aria-label="Estadísticas de otros estados">
            <ul className="en-state-grid">
                {statesByName(states).map((state) => (
                    <li key={state.value}>
                        <Link
                            href={hrefFor(state.value)}
                            className={classNames(
                                state.value === current && 'is-current',
                            )}
                            aria-current={
                                state.value === current ? 'page' : undefined
                            }
                        >
                            {state.label}
                            <small>
                                {formatNumber(totals.get(state.value) ?? 0)}
                            </small>
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
