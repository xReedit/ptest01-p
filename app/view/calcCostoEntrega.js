class calcCostoEntrega {    


    constructor(distanceKm, importeDistancia) {
        this.distanceKm = distanceKm;
        this.importeDistancia = importeDistancia;
    }

    getDistance() {
        return this.distanceKm;
    }

    setDistance(val) {
        this.distanceKm = val;
    }

    getImporteDistance() {
        return this.importeDistancia;
    }

    setImporteDistance(val) {
        this.importeDistancia = val;
    }


    distance(start, end) {
        var directionsService = new google.maps.DirectionsService();

        start.lat = typeof start.lat === 'string' ? parseFloat(start.lat) : start.lat;
        start.lng = typeof start.lng === 'string' ? parseFloat(start.lng) : start.lng;

        end.lat = typeof end.lat === 'string' ? parseFloat(end.lat) : end.lat;
        end.lng = typeof end.lng === 'string' ? parseFloat(end.lng) : end.lng;

        var request = {
            origin: start,
            destination: end,
            travelMode: google.maps.TravelMode.DRIVING
          };

        console.log('request', request);
        var km = 0;
        directionsService.route(request,function(result, status) {
            console.log('status', result);
            if (status === 'OK') {
                km = result.routes[0].legs[0].distance.value;                
                km = km / 1000;
            }

        });

        setTimeout(() => {                
            this.setDistance(km);
        }, 500);
    }

    costoDistancia(distancia_km, dirEstablecimiento) {
        var km = Math.ceil(distancia_km); // lo redondea
        var c_km = parseFloat(dirEstablecimiento.c_km.toString()); // costo x km adicional // puede variar
        var c_servicio = parseFloat(dirEstablecimiento.c_minimo.toString()); // puede variar
        var menosKm = 0;
        menosKm = km > 2 ? 2 : menosKm;
        menosKm = km > 4 ? 0 : menosKm; // si es mayor o igual  a 4 kilometros entonce no resta        
        if ( km > 2 ) {
            c_servicio = (( km - menosKm ) * c_km) + c_servicio;
            dirEstablecimiento.c_servicio = c_servicio;
        }

        this.setImporteDistance(c_servicio);
    }
}