import './styles/app.css';
import 'leaflet/dist/leaflet.min.css';
import 'leaflet';

import Vaultmap from './modules/vaultmap.js';

document.addEventListener("DOMContentLoaded", function() {
    Vaultmap.init();
});
