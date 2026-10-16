import L from 'leaflet';

declare global {
    interface Window {
        L: typeof L;
    }
}

// leaflet.heat (UMD) se registra sobre el `L` global, que el import ES de
// Leaflet no define. Importar este módulo ANTES de 'leaflet.heat'.
window.L = L;

export default L;
