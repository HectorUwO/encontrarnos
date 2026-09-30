/** Une nombres de clase omitiendo los vacíos (evita plantillas con espacios). */
export const classNames = (...names: (string | false | null | undefined)[]) =>
    names.filter(Boolean).join(' ');
