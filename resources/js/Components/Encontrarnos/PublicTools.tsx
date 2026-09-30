import { classNames } from '@/classNames';
import { Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ChartNoAxesColumnIncreasing,
    ImagePlus,
    Search,
    UsersRound,
} from 'lucide-react';
import { CSSProperties } from 'react';
import { Reveal } from './motion';
import './public-tools.css';

const actions = [
    {
        href: '/base-de-datos',
        title: 'Buscar en base de datos',
        short: 'Base de datos',
        description: 'Consulta nombres, lugares y descripciones.',
        icon: Search,
    },
    {
        href: '/estadisticas',
        title: 'Estadísticas',
        short: 'Estadísticas',
        description: 'Mapa, histórico y perfil por entidad.',
        icon: ChartNoAxesColumnIncreasing,
    },
    {
        href: '/busqueda-por-fotografia',
        title: 'Búsqueda por fotografía',
        short: 'Fotografía',
        description: 'Busca a partir de una imagen.',
        icon: ImagePlus,
    },
    {
        href: '/solicitudes',
        title: 'Ver o crear solicitudes',
        short: 'Solicitudes',
        description: 'Consulta solicitudes o prepara una nueva.',
        icon: UsersRound,
    },
];

export function ActionGrid() {
    return (
        <Reveal
            stagger
            className="en-action-grid"
            aria-label="Acciones principales"
        >
            {actions.map(({ href, title, description, icon: Icon }, index) => (
                <Link
                    key={href}
                    href={href}
                    prefetch
                    style={{ '--i': index } as CSSProperties}
                    className={classNames(
                        'en-action-card',
                        index === 0 && 'en-action-primary',
                    )}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>
                        <strong>{title}</strong>
                        <small>{description}</small>
                    </span>
                    <ArrowUpRight size={19} aria-hidden="true" />
                </Link>
            ))}
        </Reveal>
    );
}

export function MobileNavigation() {
    const { url } = usePage();
    return (
        <nav className="en-mobile-dock" aria-label="Accesos rápidos">
            {actions.map(({ href, short, icon: Icon }) => (
                <Link
                    href={href}
                    key={href}
                    aria-current={url.startsWith(href) ? 'page' : undefined}
                >
                    <Icon size={23} aria-hidden="true" />
                    <span>{short}</span>
                </Link>
            ))}
        </nav>
    );
}

export { actions };
