import { classNames } from '@/classNames';
import { router } from '@inertiajs/react';
import { ArrowUpRight, LoaderCircle } from 'lucide-react';
import {
    CSSProperties,
    HTMLAttributes,
    ImgHTMLAttributes,
    createElement,
    useEffect,
    useRef,
    useState,
} from 'react';
import './motion.css';

/**
 * Dice si el elemento ya se mostró en pantalla, y lo recuerda. Sin IntersectionObserver
 * cuenta como mostrado desde el principio para no dejar contenido escondido.
 */
export function useInView<T extends Element>(margin = '0px 0px -8% 0px') {
    const ref = useRef<T>(null);
    const [seen, setSeen] = useState(
        () => typeof IntersectionObserver === 'undefined',
    );

    useEffect(() => {
        const node = ref.current;
        if (seen || !node) return;

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting) return;
                setSeen(true);
                observer.disconnect();
            },
            { rootMargin: margin },
        );

        observer.observe(node);
        return () => observer.disconnect();
    }, [seen, margin]);

    return [ref, seen] as const;
}

type RevealProps = HTMLAttributes<HTMLElement> & {
    as?: 'div' | 'section' | 'article' | 'header' | 'ol' | 'ul';
    /** Posición dentro de un grupo: escalona la entrada de los hermanos. */
    index?: number;
    /** En lugar de aparecer él, escalona la entrada de sus hijos (que llevan --i). */
    stagger?: boolean;
};

/**
 * Envuelve algo que debe aparecer al llegar a él con el scroll. Los hijos que lleven
 * `en-grow` (barras) o `en-wipe` (gráficas) se dibujan en ese momento.
 */
export function Reveal({
    as = 'div',
    index,
    stagger = false,
    className,
    style,
    children,
    ...rest
}: RevealProps) {
    const [ref, seen] = useInView<HTMLElement>();

    return createElement(
        as,
        {
            ref,
            className: classNames(
                stagger ? 'en-stagger' : 'en-reveal',
                seen && 'is-visible',
                className,
            ),
            style:
                index === undefined
                    ? style
                    : ({ ...style, '--i': index } as CSSProperties),
            ...rest,
        },
        children,
    );
}

/**
 * true mientras una visita de Inertia está en curso. Espera un instante antes de
 * encenderse para que las respuestas rápidas no hagan parpadear la pantalla.
 */
export function useVisitPending(delay = 120) {
    const [pending, setPending] = useState(false);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout> | undefined;

        // Las precargas (al pasar el puntero por un enlace) también emiten estos eventos,
        // pero no son una visita: no deben atenuar nada.
        const clear = () => {
            clearTimeout(timer);
            setPending(false);
        };
        const stopStart = router.on('before', (event) => {
            if (event.detail.visit.prefetch) return;
            clearTimeout(timer);
            timer = setTimeout(() => setPending(true), delay);
        });
        // Una visita que usa una página precargada no emite `finish`, pero sí `navigate`.
        const stopNavigate = router.on('navigate', clear);
        const stopFinish = router.on('finish', (event) => {
            if (!event.detail.visit.prefetch) clear();
        });

        return () => {
            stopStart();
            stopNavigate();
            stopFinish();
            clearTimeout(timer);
        };
    }, [delay]);

    return pending;
}

/**
 * Cuando se cambia de página avisa a los lectores de pantalla (con el título nuevo) y
 * lleva el foco al contenido, como haría una recarga completa. Las visitas que solo
 * cambian filtros o páginas de resultados no cuentan.
 */
export function RouteAnnouncer() {
    const [message, setMessage] = useState('');

    useEffect(() => {
        let previous = window.location.pathname;

        return router.on('navigate', () => {
            const current = window.location.pathname;
            if (current === previous) return;
            previous = current;

            // <Head> pone el título nuevo un instante después de este evento.
            window.setTimeout(() => {
                setMessage(document.title);
                document
                    .getElementById('contenido')
                    ?.focus({ preventScroll: true });
            }, 60);
        });
    }, []);

    return (
        <div className="sr-only" role="status" aria-live="polite">
            {message}
        </div>
    );
}

/**
 * Imagen que aparece con un fundido al terminar de cargar, sobre el fondo que late
 * de su contenedor (ver motion.css). Si falla la carga se muestra igual para que se
 * vea el texto alternativo.
 */
export function FadeImage({
    className,
    onLoad,
    onError,
    ...props
}: ImgHTMLAttributes<HTMLImageElement>) {
    const image = useRef<HTMLImageElement>(null);
    const [loaded, setLoaded] = useState(false);

    // Una imagen que ya estaba en caché puede estar lista antes de que React escuche su evento.
    useEffect(() => {
        if (image.current?.complete) setLoaded(true);
    }, []);

    return (
        <img
            ref={image}
            {...props}
            className={classNames(
                'en-fade-img',
                loaded && 'is-loaded',
                className,
            )}
            onLoad={(event) => {
                setLoaded(true);
                onLoad?.(event);
            }}
            onError={(event) => {
                setLoaded(true);
                onError?.(event);
            }}
        />
    );
}

/** Ícono de un botón que envía algo: la flecha de siempre, o un indicador mientras se envía. */
export function ActionIcon({
    pending,
    size = 19,
}: {
    pending: boolean;
    size?: number;
}) {
    return pending ? (
        <LoaderCircle className="en-spin" size={size} aria-hidden="true" />
    ) : (
        <ArrowUpRight size={size} aria-hidden="true" />
    );
}
