class Vaultmap {
    constructor(mapDiv) {
        const readToken = mapDiv.dataset.readtoken;
        const map = L.map(mapDiv).setView([46.5, 7.8], 13);
        this.visiblelayers = [];
        this.visibleEvents = [];

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

        const trips = document.querySelectorAll('#trips li');
        if (trips) {
            for (let i = 0; i < trips.length; i++) {
                const trip = trips[i];
                const start = trip.dataset.start;
                const end = trip.dataset.end;
                trip.addEventListener('click', () => {
                    this.updateStartEnd(start, end, map, readToken);
                });
            }
        }
    }

    updateStartEnd(start, end, map, readToken) {
        fetch('/api/query?format=linestring&start=' + start + '&end=' + end + '&tz=UTC&token=' + readToken)
            .then(response => response.json())
            .then(data => {
                this.drawLine(data.linestring, map);
                this.drawEvents(data.events, map);
            });
    }


    updateDate(date, map, readToken) {
        fetch('/api/query?format=linestring&date=' + date + '&tz=UTC&token=' + readToken)
            .then(response => response.json())
            .then(data => {
                this.drawLine(data.linestring, map);
                this.drawEvents(data.events, map);
            });
    }

    drawEvents(mapEvents, map) {
        if (this.visibleEvents.length) {
            for (let i in this.visibleEvents) {
                map.removeLayer(this.visibleEvents[i]);
            }
        }
        this.visibleEvents = [];
        if (mapEvents.length) {
            for (let i in mapEvents) {
                let point = [mapEvents[i].geometry.coordinates[1], mapEvents[i].geometry.coordinates[0]];
                let text = '<p>arrival: ' + mapEvents[i].properties.arrival_date + '<br>departure: ' + mapEvents[i].properties.departure_date + '</p>';
                this.visibleEvents.push(L.marker(point).bindPopup(text).addTo(map));
            }
        }
    }

    drawLine(line, map) {
        if (this.visiblelayers.length) {
            for (let i in this.visiblelayers) {
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
