import type { LatLngBoundsExpression, LatLngTuple } from 'leaflet';
import { useEffect } from 'react';
import {
    CircleMarker,
    MapContainer,
    Rectangle,
    TileLayer,
    useMap,
    useMapEvents,
} from 'react-leaflet';

const CENTRO_VILLA_MARIA: LatLngTuple = [-32.4075, -63.2402];

type Elegir = (latitud: number, longitud: number) => void;

/** Área válida de coordenadas (PuntoMonitoreo::LIMITES): [mín, máx]. */
interface Limites {
    latitud: [number, number];
    longitud: [number, number];
}

interface Props {
    /** Valor del form: vacío ('' o null) mientras no se elige. */
    latitud: number | string | null;
    longitud: number | string | null;
    limites: Limites;
    onChange: Elegir;
    className?: string;
}

function AlHacerClic({ onElegir }: { onElegir: Elegir }) {
    useMapEvents({
        click: (e) =>
            onElegir(
                Number(e.latlng.lat.toFixed(7)),
                Number(e.latlng.lng.toFixed(7)),
            ),
    });

    return null;
}

function AjustarTamano() {
    const map = useMap();

    useEffect(() => {
        const observer = new ResizeObserver(() => map.invalidateSize());
        observer.observe(map.getContainer());

        return () => observer.disconnect();
    }, [map]);

    return null;
}

/**
 * US-024: elegir las coordenadas de un PM haciendo clic en el mapa. El
 * rectángulo marca el área válida (PuntoMonitoreo::LIMITES).
 */
export default function SelectorCoordenadas({ latitud, longitud, limites, onChange, className = '' }: Props) {
    const hay = latitud !== null && latitud !== '' && longitud !== null && longitud !== '';
    const area: LatLngBoundsExpression = [
        [limites.latitud[0], limites.longitud[0]],
        [limites.latitud[1], limites.longitud[1]],
    ];

    return (
        <div className={`mapa-tactico relative overflow-hidden border border-atalaya-border ${className}`}>
            <MapContainer
                center={hay ? [Number(latitud), Number(longitud)] : CENTRO_VILLA_MARIA}
                zoom={hay ? 16 : 13}
                className="h-full w-full cursor-crosshair"
            >
                <TileLayer
                    url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
                    attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                />
                <AjustarTamano />
                <Rectangle
                    bounds={area}
                    pathOptions={{ color: '#00D2FF', weight: 1, dashArray: '4 4', fill: false }}
                />
                {hay && (
                    <CircleMarker
                        center={[Number(latitud), Number(longitud)]}
                        radius={8}
                        pathOptions={{ color: '#ffffff', weight: 2, fillColor: '#F97316', fillOpacity: 0.9 }}
                    />
                )}
                <AlHacerClic onElegir={onChange} />
            </MapContainer>
        </div>
    );
}
