class Vaultmap {
    constructor(mapDiv) {
        const readToken = mapDiv.dataset.readtoken;
        const map = L.map(mapDiv).setView([46.5, 7.8], 13);
        this.visiblelayers = [];

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        const dateField = document.querySelector('#date');
        if (dateField) {
            this.updateDate(dateField.value, map, readToken);
            dateField.addEventListener('change', () => {
                if (dateField.value && dateField.value !== '') {
                    this.updateDate(dateField.value, map, readToken);
                }
            });
        }
    }

    updateDate(date, map, readToken) {
        fetch('/api/query?format=linestring&date=' + date + '&tz=UTC&token=' + readToken)
            .then(response => response.json())
            .then(data => {
                this.drawLine(data.linestring, map);
            });
    }

    drawLine(line, map) {
        if(this.visiblelayers.length) {
            for(let i in this.visiblelayers) {
                map.removeLayer(this.visiblelayers[i]);
            }
        }
        this.visiblelayers = [];
        if (line.coordinates && line.coordinates.length > 0) {
            let lastCoord = null;
            for (let i in line.coordinates) {
                if (line.coordinates[i] == null) {
                    line.coordinates[i] = lastCoord;
                } else {
                    lastCoord = line.coordinates[i];
                }
            }
            if (lastCoord != null) {
                let point = [lastCoord[1], lastCoord[0]];
                map.setView(point);
                this.visiblelayers.push(L.marker(point).addTo(map));
            }
        }
        this.visiblelayers.push(L.geoJson(line, {}).addTo(map));
    }

    static init() {
        const mapDiv = document.querySelector('div.map');
        if (mapDiv) {
            new Vaultmap(mapDiv);
        }
    }
}

export default Vaultmap;
