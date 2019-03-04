function CpeInterno_Registrar(data) {
    
    if (data.success) { //si todo salio bien graba en CE (comprobantes electronicos)
        
        let dataSave = {}
    
        dataSave.jsonxml = ''; 
        // datos
        dataSave.pdf = data.links.pdf != '' ? 1 : 0; 
        dataSave.cdr = data.links.cdr != '' ? 1 : 0; 
        dataSave.xml = data.links.xml != '' ? 1 : 0; 
        dataSave.nomcliente = data.data.nomcliente;
        dataSave.idcliente = data.data.idcliente === "" ? 0 : data.data.idcliente;
        dataSave.total = data.data.total;
        dataSave.totales_json = data.data.totales_json;
        dataSave.numero = data.data.numero;
        dataSave.external_id = data.data.external_id;
        dataSave.hash = data.data.hash;
        dataSave.idregistro_pago = data.data.idregistro_pago;
        dataSave.viene_facturador = data.data.viene_facturador;
        dataSave.idtipo_comprobante_serie = data.data.idtipo_comprobante_serie;
        
        // response si tiene datos es factura y se registro ya en sunat
        dataSave.estado_api = 0; // se registro correctamente
        dataSave.estado_sunat = 1; // aun no se envia ( si es boleta va en resumen)
        dataSave.msj = 'Registrado'; 
        dataSave.msj_error = '';
        
        if ( data.response.length != 0 ) {
            dataSave.estado_sunat = data.response.code;
            dataSave.msj = data.response.description; 
        } 

        CpeInterno_SaveBD(dataSave);

    }
}


// hubo un error no conexion con el servicio o algo parecido
// guarda el cpe para volver a enviarlo al cierre
// registra el jsonxml para ser enviado luego
// idtipo_comprobante_serie => guardar el correlativo
function CpeInterno_Error(data, jsonxml, _idregistro_p, _viene_facturador, idtipo_comprobante_serie) {
  let dataSave = {};

  dataSave = data;
  dataSave.estado_api = 1;
  dataSave.estado_sunat = 1;
  dataSave.msj = "Sin registrar";
  if ( _idregistro_p != 0 ) {
      dataSave.idregistro_pago = _idregistro_p;
  }
  CpeInterno_SaveBD(dataSave);
}

// registra pero la sunat devuelve error m// en facturas
function CpeInterno_ErrorValidacionSunat(_idregistro_p, dataSave) {
  if (_idregistro_p != 0) {
    dataSave.idregistro_pago = _idregistro_p;
  }
  CpeInterno_SaveBD(dataSave);
};

// guardar en la base de datos el comprobante
function CpeInterno_SaveBD(dataSave) {
    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '1', data: dataSave}})
    .done( function (res) {
        console.log(res);
    });
}

// actualiza estados de documentos reenviados// estado_api , estado_sunat
// desde cierre de caja - soapSunat
function CpeInterno_UpdateRegistro(dataUpdate) {
    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '2', data: dataUpdate } })
    .done(function (res) {
        console.log(res);
    });
}


function CpeInterno_SaveResumenDiario(dataResumen) {
    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '202', data: dataResumen } })
    .done(function (res) {
        console.log(res);
    });
}

async function CpeInterno_UpdateAnulacion(dataAnulacion) {
    let rpt=false;
    await $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '8', data: dataAnulacion } })
        .done(function (res) {
            rpt=true;
        });
    
        return rpt;
}


// al actualiar factura tambien actualiza el cdr y el xml
function CpeInterno_UpdateAnulacionFactura(dataAnulacion) {
    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '8', data: dataAnulacion } })
        .done(function (res) {
            console.log(res);
        });
}



// actualiza resumen boletas con la respuesta obtenida
// ademas si es aceptado cambia el estado de las boletas estado_api=0 -> aceptado
function CpeInterno_UpdateResumenDiario(dataUpdateResumen) {
    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '203', data: dataUpdateResumen } })
    .done(function (res) {
        console.log(res);
    });

}