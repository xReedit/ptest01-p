
function validarNumDoc_ValidarDNIRUC (tipoComprobante, valor) {
    const codSunat = tipoComprobante.codsunat;
    const requiereCliente = tipoComprobante.requiere_cliente;
    const inicial = tipoComprobante.inicial;
    
    var rpt = {};

    // validaciones
    if (valor.length === 0) {
        rpt.valid = false;
        rpt.msj = "Nro de documento no valido"; 
        return rpt;
    }
    
    // si es factura acepta solo ruc
    if (codSunat === '01') {
        if (valor.length < 11) {
            rpt.valid = false;
            rpt.msj = 'Nro de RUC no valido';            
            return rpt;
        }
    }

    if (valor.length < 8) {
        rpt.valid = false;
        rpt.msj = "Nro de DNI no valido";
        return rpt;        
    }
    
    
    const servicio = valor.length <= 8 ? 'dni' : 'ruc';
    rpt.valid = true;
    rpt.msj = "ok";
    rpt.servicio = servicio;
    rpt.numdoc = valor;

    return rpt
	
}