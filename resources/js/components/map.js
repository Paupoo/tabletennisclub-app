import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerIconRetina from 'leaflet/dist/images/marker-icon-2x.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

export function initMap() {
    const mapElement = document.getElementById('map');

    if (!mapElement) return;

    // Récupération des données via les attributs data-
    const lat = mapElement.dataset.lat || 50.667;
    const lon = mapElement.dataset.lon || 4.589;
    const name = mapElement.dataset.name || 'Notre Club';

    const map = L.map('map').setView([lat, lon], 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    const customIcon = L.icon({
        iconUrl: markerIcon,
        iconRetinaUrl: markerIconRetina,
        shadowUrl: markerShadow,
        iconSize: [25, 41],
        iconAnchor: [12, 41]
    });

    L.marker([lat, lon], { icon: customIcon }).addTo(map)
        .bindPopup(name)
        .openPopup();

    // Correction bug d'affichage initial
    setTimeout(() => map.invalidateSize(), 100);
}