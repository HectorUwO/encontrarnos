import { darkBackgroundSymbol } from '@/brand';
import { Maximize2, Minus, Plus, RotateCcw, X } from 'lucide-react';
import {
    KeyboardEvent,
    PointerEvent,
    ReactNode,
    useEffect,
    useRef,
    useState,
} from 'react';
import './requests.css';
import './viewer.css';

const MIN_SCALE = 1;
const MAX_SCALE = 6;

const clamp = (value: number, min: number, max: number) =>
    Math.min(max, Math.max(min, value));

/** La misma fotografía en su tamaño original (sin `size`). */
const originalOf = (src: string) =>
    src.replace(/([?&])size=[^&]*&?/, '$1').replace(/[?&]$/, '');

/**
 * Foto que se abre a pantalla completa con zoom: rueda, botones, teclado,
 * doble clic, pellizco en pantallas táctiles y arrastre para moverla.
 */
export default function PhotoViewer({
    src,
    alt,
    caption,
    meta,
    children,
}: {
    src: string;
    alt: string;
    caption?: string;
    meta?: string;
    children: ReactNode;
}) {
    const dialog = useRef<HTMLDialogElement>(null);
    const stage = useRef<HTMLDivElement>(null);
    const [open, setOpen] = useState(false);
    const [scale, setScale] = useState(1);
    const [offset, setOffset] = useState({ x: 0, y: 0 });
    const [dragging, setDragging] = useState(false);
    const pointers = useRef(new Map<number, { x: number; y: number }>());
    const pinch = useRef({ distance: 0, scale: 1 });

    const reset = () => {
        setScale(1);
        setOffset({ x: 0, y: 0 });
    };

    const zoomTo = (next: number) => {
        const value = clamp(next, MIN_SCALE, MAX_SCALE);
        setScale(value);
        if (value === MIN_SCALE) setOffset({ x: 0, y: 0 });
    };

    useEffect(() => {
        if (!open) return;
        const element = dialog.current;
        element?.showModal();
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return () => {
            document.body.style.overflow = previous;
            element?.close();
        };
    }, [open]);

    // La rueda debe poder cancelarse para que no desplace la página.
    useEffect(() => {
        const element = stage.current;
        if (!open || !element) return;
        const onWheel = (event: WheelEvent) => {
            event.preventDefault();
            setScale((current) => {
                const next = clamp(
                    current * (event.deltaY < 0 ? 1.15 : 0.87),
                    MIN_SCALE,
                    MAX_SCALE,
                );
                if (next === MIN_SCALE) setOffset({ x: 0, y: 0 });
                return next;
            });
        };
        element.addEventListener('wheel', onWheel, { passive: false });
        return () => element.removeEventListener('wheel', onWheel);
    }, [open]);

    const close = () => {
        setOpen(false);
        reset();
    };

    const onPointerDown = (event: PointerEvent<HTMLDivElement>) => {
        event.currentTarget.setPointerCapture(event.pointerId);
        pointers.current.set(event.pointerId, {
            x: event.clientX,
            y: event.clientY,
        });
        if (pointers.current.size === 2) {
            const [a, b] = [...pointers.current.values()];
            pinch.current = {
                distance: Math.hypot(a.x - b.x, a.y - b.y),
                scale,
            };
        }
        setDragging(true);
    };

    const onPointerMove = (event: PointerEvent<HTMLDivElement>) => {
        const previous = pointers.current.get(event.pointerId);
        if (!previous) return;
        const current = { x: event.clientX, y: event.clientY };
        pointers.current.set(event.pointerId, current);

        if (pointers.current.size === 2 && pinch.current.distance > 0) {
            const [a, b] = [...pointers.current.values()];
            zoomTo(
                pinch.current.scale *
                    (Math.hypot(a.x - b.x, a.y - b.y) / pinch.current.distance),
            );
        } else if (pointers.current.size === 1 && scale > MIN_SCALE) {
            setOffset((value) => ({
                x: value.x + current.x - previous.x,
                y: value.y + current.y - previous.y,
            }));
        }
    };

    const onPointerUp = (event: PointerEvent<HTMLDivElement>) => {
        pointers.current.delete(event.pointerId);
        if (pointers.current.size === 0) setDragging(false);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDialogElement>) => {
        if (event.key === '+' || event.key === '=') zoomTo(scale * 1.3);
        else if (event.key === '-') zoomTo(scale / 1.3);
        else if (event.key === '0') reset();
        else if (scale > MIN_SCALE && event.key.startsWith('Arrow')) {
            event.preventDefault();
            const step = 60;
            setOffset((value) => ({
                x:
                    value.x +
                    (event.key === 'ArrowLeft'
                        ? step
                        : event.key === 'ArrowRight'
                          ? -step
                          : 0),
                y:
                    value.y +
                    (event.key === 'ArrowUp'
                        ? step
                        : event.key === 'ArrowDown'
                          ? -step
                          : 0),
            }));
        }
    };

    return (
        <>
            <button
                type="button"
                className="req-photo-open"
                onClick={() => setOpen(true)}
                aria-label="Ampliar fotografía"
            >
                {children}
                <span className="req-photo-hint">
                    <Maximize2 size={14} aria-hidden="true" /> Ampliar
                </span>
            </button>
            <dialog
                ref={dialog}
                className="req-viewer"
                aria-label="Fotografía ampliada"
                onCancel={(event) => {
                    event.preventDefault();
                    close();
                }}
                onKeyDown={onKeyDown}
            >
                <header className="req-viewer-top">
                    <div className="req-viewer-brand" aria-hidden="true">
                        <img src={darkBackgroundSymbol} alt="" />
                        <span>
                            encontrarnos<i>.</i>
                        </span>
                    </div>
                    <div className="req-viewer-caption">
                        {caption && <strong>{caption}</strong>}
                        {meta && <span>{meta}</span>}
                    </div>
                    <div className="req-viewer-bar">
                        <button
                            type="button"
                            onClick={() => zoomTo(scale / 1.4)}
                            disabled={scale <= MIN_SCALE}
                            aria-label="Alejar"
                        >
                            <Minus size={20} />
                        </button>
                        <output aria-live="polite">
                            {Math.round(scale * 100)}%
                        </output>
                        <button
                            type="button"
                            onClick={() => zoomTo(scale * 1.4)}
                            disabled={scale >= MAX_SCALE}
                            aria-label="Acercar"
                        >
                            <Plus size={20} />
                        </button>
                        <button
                            type="button"
                            onClick={reset}
                            disabled={scale === 1}
                            aria-label="Restablecer zoom"
                        >
                            <RotateCcw size={18} />
                        </button>
                        <button
                            type="button"
                            onClick={close}
                            aria-label="Cerrar"
                            className="req-viewer-close"
                        >
                            <X size={22} />
                        </button>
                    </div>
                </header>
                <div
                    className="req-viewer-stage"
                    onClick={(event) => {
                        if (event.target === event.currentTarget) close();
                    }}
                >
                    <div
                        ref={stage}
                        className="req-viewer-frame"
                        data-zoomed={scale > MIN_SCALE}
                        data-dragging={dragging}
                        onPointerDown={onPointerDown}
                        onPointerMove={onPointerMove}
                        onPointerUp={onPointerUp}
                        onPointerCancel={onPointerUp}
                        onDoubleClick={() =>
                            scale > MIN_SCALE ? reset() : zoomTo(2.5)
                        }
                    >
                        {open && (
                            <img
                                src={originalOf(src)}
                                alt={alt}
                                draggable={false}
                                style={{
                                    transform: `translate(${offset.x}px, ${offset.y}px) scale(${scale})`,
                                }}
                            />
                        )}
                    </div>
                </div>
                <footer className="req-viewer-foot">
                    <span className="req-viewer-help">
                        Rueda o +/− para acercar · doble clic para ampliar ·
                        arrastra para moverla · Esc para cerrar
                    </span>
                    <span className="req-viewer-tagline">
                        Por quienes faltan. Con quienes buscan.
                    </span>
                </footer>
            </dialog>
        </>
    );
}
