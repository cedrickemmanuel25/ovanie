import L from 'leaflet';
import '@maplibre/maplibre-gl-leaflet';

export class MapService {
    constructor(mapContainerId, options = {}) {
        this.mapContainerId = mapContainerId;
        this.options = {
            center: [5.345317, -4.024429], // Abidjan par défaut
            zoom: 12,
            ...options
        };
        this.map = null;
        this.markers = [];
    }

    /**
     * Initialiser la carte avec Leaflet + MapLibre + MapTiler
     */
    init(mapTilerKey) {
        this.map = L.map(this.mapContainerId, {
            minZoom: 10,
            maxZoom: 19,
            maxBounds: [[4.0, -9.2], [11.3, -2.0]],
            maxBoundsViscosity: 0.9,
            worldCopyJump: false,
            zoomControl: true
        }).setView(this.options.center, Math.max(10, this.options.zoom));

        // Utilisation de MapLibre GL pour le rendu vectoriel des tuiles MapTiler
        // S'il n'y a pas de clé MapTiler configurée, on utilise OpenStreetMap classique
        if (mapTilerKey && mapTilerKey !== 'votre_cle_maptiler') {
            L.maplibreGL({
                style: `https://api.maptiler.com/maps/streets-v2/style.json?key=${mapTilerKey}`
            }).addTo(this.map);
        } else {
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(this.map);
        }

        return this.map;
    }

    /**
     * Ajouter un marqueur stylisé
     */
    addMarker(lat, lng, color = '#3B82F6', popupContent = null) {
        const icon = L.divIcon({
            className: 'ovanie-leaflet-marker',
            html: `<span aria-hidden="true" style="display:grid;place-items:center;width:38px;height:38px;border:3px solid #fff;border-radius:50% 50% 50% 10px;background:${color};color:#fff;transform:rotate(-45deg);box-shadow:0 9px 22px rgba(15,23,42,.24)"><svg viewBox="0 0 24 24" width="21" height="21" style="transform:rotate(45deg)" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10h16M6 10v9h12v-9M7 5h10l2 5H5l2-5Z"/><path d="M9 19v-5h6v5"/></svg></span>`,
            iconSize: [42, 48],
            iconAnchor: [21, 45],
            popupAnchor: [0, -43]
        });

        const marker = L.marker([lat, lng], {icon: icon}).addTo(this.map);
        
        if (popupContent) {
            marker.bindPopup(popupContent);
        }
        
        this.markers.push(marker);
        return marker;
    }

    /**
     * Géocodage via Nominatim (OpenStreetMap)
     */
    async geocode(address) {
        try {
            const response = await fetch(`/geo/search?q=${encodeURIComponent(address)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await response.json();
            const result = data.results?.[0];
            return result ? { lat: parseFloat(result.latitude), lng: parseFloat(result.longitude) } : null;
        } catch (error) {
            console.error("Geocoding error:", error);
            return null;
        }
    }

    /**
     * Calcul d'itinéraire via OpenRouteService
     */
    async getRouteORS(startCoord, endCoord, apiKey) {
        if (!apiKey || apiKey === 'votre_cle_openrouteservice') return null;
        
        try {
            const response = await fetch(`https://api.openrouteservice.org/v2/directions/driving-car?api_key=${apiKey}&start=${startCoord.lng},${startCoord.lat}&end=${endCoord.lng},${endCoord.lat}`);
            const data = await response.json();
            return data; // Contient les coordonnées GeoJSON de l'itinéraire
        } catch (error) {
            console.error("ORS Route error:", error);
            return null;
        }
    }

    /**
     * Calcul d'itinéraire via GraphHopper
     */
    async getRouteGraphHopper(startCoord, endCoord, apiKey) {
        if (!apiKey || apiKey === 'votre_cle_graphhopper') return null;
        
        try {
            const url = `https://graphhopper.com/api/1/route?point=${startCoord.lat},${startCoord.lng}&point=${endCoord.lat},${endCoord.lng}&vehicle=car&locale=fr&key=${apiKey}&points_encoded=false`;
            const response = await fetch(url);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error("GraphHopper Route error:", error);
            return null;
        }
    }

    /**
     * Dessiner un itinéraire sur la carte depuis GeoJSON
     */
    drawRoute(geoJsonCoordinates, color = '#3B82F6') {
        const routeLines = geoJsonCoordinates.map(coord => [coord[1], coord[0]]); // GeoJSON is [lng, lat], Leaflet is [lat, lng]
        return L.polyline(routeLines, {color: color, weight: 4, opacity: 0.8}).addTo(this.map);
    }
}
