import L from 'leaflet';

// leaflet.heat (UMD) se registra sobre el `L` global, que el import ES de
// Leaflet no define. Importar este módulo ANTES de 'leaflet.heat'.
window.L = L;

export default L;
