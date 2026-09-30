# Imágenes de la interfaz

Derivados en WebP de los originales de `public/` (kit de marca), reducidos al tamaño
en que se muestran (símbolo 256 px, logos aliados 192 px, bandera 120 px). Se importan
desde `resources/js/brand.ts` para que Vite les ponga huella en el nombre.

| Archivo | Origen |
| --- | --- |
| `simbolo-color.webp` | `public/1.png` |
| `simbolo-fondo-claro.webp` | `public/2.png` |
| `simbolo-fondo-oscuro.webp` | `public/3.png` |
| `aliado-lsp.webp` | `public/LSPlogo-footer.png` |
| `aliado-ut.webp` | `public/UTlogo.png` |
| `bandera-mexico.webp` | `public/flag.png` |

Si cambia un original, se regenera con cualquier herramienta que remuestree con
Lanczos y guarde WebP con canal alfa (calidad 90 con pérdida, o sin pérdida en los
que ya pesan poco).
