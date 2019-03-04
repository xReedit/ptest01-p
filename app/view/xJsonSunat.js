// cocina la data del comprobante electronico en un json para ser enviada a la sunat 
//

// xArrayCuerpo (items) debe tener estructura de mod impresion, (como sub pedido ::app3_sys_dta_pe)
// xArraySubTotales ya esta calculado los subtotales
// xArrayComprobante datos del comprobante : tipodoc , serie correlativo id's
// xArrayCliente datos del cliente nombre dni ruc direccion

async function xJsonSunatCocinarDatos(xArrayCuerpo, xArraySubTotales, xArrayComprobante, xArrayCliente, idregistro_pago) {
    var hash = {};
    var _arrSedes = xm_log_get('datos_org_sede'); // todas las sedes
    const isFacturacionElectronica = _arrSedes[0].facturacion_e_activo === "0" ? false : true; // si se emiten comprobantes electronicos    

    if (!isFacturacionElectronica || xArrayComprobante.codsunat === "0") { // porque puede ser ticket
        hash.ok=true;
        hash.qr='';
        hash.hash = '';
        hash.external_id = "";
        return hash;   
    }
    
    // evalua si I.G.V es = 0 esta exonerado
    var procentajeIGV = 0;
    var xCartaSubtotales=xm_log_get('carta_subtotales')    
    xCartaSubtotales.filter(x => x.descripcion.indexOf('I.G.V') > -1)
        .map(x => procentajeIGV = x);
    const valIGV = parseFloat(procentajeIGV.monto);
    const isExoneradoIGV = procentajeIGV.activo === "1" ? true : false; //1 = desactivado => exonerado


    // cpe = false subtotal + adicional -> lo ponemos en xImprimirComprobanteAhora() // para mostrar en la impresion
    var xitems = xEstructuraItemsJsonComprobante(xArrayCuerpo, xArraySubTotales, false);
    xitems = xJsonSunatCocinarItemDetalle(xitems, valIGV, isExoneradoIGV);


    // array encabezado org sede
    var xArrayEncabezado = xm_log_get('datos_org_sede');
    const logo64 = xArrayEncabezado[0].logo64.split("base64,")[1]; 
    var items = [], fecha_actual = '', hora_actual = '';
    var xnum_doc_cliente = xArrayCliente.num_doc;

    const abreviaCo = xArrayComprobante.descripcion.substr(0,1).toUpperCase();

    const xtipo_de_documento_identidad_cliente = xnum_doc_cliente.length >= 10 ? 6 : 1;
    const xtipo_de_documento_comprobante = xArrayComprobante.codsunat;
    const xidtipo__comprobante_serie = xArrayComprobante.idtipo_comprobante_serie;


    // si viene dni sin valor '00000000 = publico en general'
    xnum_doc_cliente = xnum_doc_cliente.length === 0 ? '00000000' : xnum_doc_cliente;

    // Importe total a pagar siempre ultimo es es el total
	const index_total = xArraySubTotales.length-1;
    const importe_total_pagar = xArraySubTotales[index_total].importe;
    const importe_total_igv = xArraySubTotales.filter(x => x.descripcion === 'I.G.V').map( x => x.importe)[0] || 0;
    
    //verifica si esta exonerado al igv /*/ caso de la selva u otros ubigeos exonerados del igv
    // const isExoneradoIGV = true;
    // let total_valor_de_venta_operaciones_gravadas = 0,total_valor_de_venta_operaciones_exoneradas = 0, leyenda = [];
    let totales = {};

    if ( isExoneradoIGV ) { // exonerado del igv
     
        //totales
        totales = {
            "total_exportacion": 0.00,
            "total_operaciones_gravadas": 0.00,
            "total_operaciones_inafectas": 0.00,
            "total_operaciones_exoneradas": importe_total_pagar,
            "total_operaciones_gratuitas": 0.00,
            "total_igv": 0.00,
            "total_impuestos": 0.00,
            "total_valor": importe_total_pagar,
            "total_venta": importe_total_pagar
        }
    } else {

        const total_operaciones_gravadas = xArraySubTotales[0].importe; // el subtotal

        totales = {
            "total_exportacion": 0.00,
            "total_operaciones_gravadas": total_operaciones_gravadas,
            "total_operaciones_inafectas": 0.00,
            "total_operaciones_exoneradas": 0.00,
            "total_operaciones_gratuitas": 0.00,
            "total_igv": importe_total_igv,
            "total_impuestos": importe_total_igv,
            "total_valor": total_operaciones_gravadas,
            "total_venta": importe_total_pagar
        }
    }


    // fecha actual del servidor
    // cabecera
    await $.ajax({type: 'POST', url: '../../bdphp/log_001.php', data:{'p_from':'z'}})
    .done( function (rptDate) {        
        rptDate=rptDate.split('|');

        const fecha_manual = xArrayComprobante.fecha_manual || null; // para regularizar desde facturador

        fecha_actual = fecha_manual === null ? rptDate[0] : fecha_manual;
        hora_actual = rptDate[1];    

        var jsonData = {                    
            "serie_documento": `${abreviaCo}${xArrayComprobante.serie}`,
            "numero_documento": xArrayComprobante.correlativo,
            "fecha_de_emision": `${fecha_actual}`,
            "hora_de_emision": `${hora_actual}`,
            "codigo_tipo_operacion": "0101",
            "codigo_tipo_documento": `${xtipo_de_documento_comprobante}`,
            "codigo_tipo_moneda": "PEN",
            "fecha_de_vencimiento": `${fecha_actual}`,
            "numero_orden_de_compra": "",
            "datos_del_emisor": {
                "codigo_pais": "PE",
                "ubigeo": xArrayEncabezado[0].ubigeo,
                "direccion": `${xArrayEncabezado[0].sededireccion} | ` + `${xArrayEncabezado[0].sedeciudad}`,
                "correo_electronico": "",
                "telefono": `${xArrayEncabezado[0].telefono}`,
                "codigo_del_domicilio_fiscal": xArrayEncabezado[0].codigo_del_domicilio_fiscal
            },
            "datos_del_cliente_o_receptor":{
                "codigo_tipo_documento_identidad": `${xtipo_de_documento_identidad_cliente}`,
                "numero_documento": `${xnum_doc_cliente}`,
                "apellidos_y_nombres_o_razon_social": `${xArrayCliente.nombres === "" ? "PUBLICO EN GENERAL" : xArrayCliente.nombres}`,
                "codigo_pais": "PE",
                "ubigeo": "150101",
                "direccion": xArrayCliente.direccion,
                "correo_electronico": "",
                "telefono": ""
            },
            "totales": totales,
            "items": xitems,
            "extras":{
                "forma_de_pago": "",
                "observaciones": "",
                "vendedor": "",
                "caja": "",
                "idcliente": xArrayCliente.idcliente
            }

        }

        console.log(JSON.stringify(jsonData));

        hash = xSendApiSunat(jsonData, idregistro_pago, xidtipo__comprobante_serie);        
    })
    
    return hash;
}


function xJsonSunatCocinarItemDetalle(items, ValorIGV, isExoneradoIGV ) {
    var xListItemsRpt =[];
    const procentaje_IGV = parseFloat(parseFloat(ValorIGV)/100);
    
    // var valor_referencial_unitario_por_item_en_operaciones_no_onerosas_y_codigo = {"monto_de_valor_referencial_unitario": "01", "codigo_de_tipo_de_precio": "02"};

    items.map( (x, index) => {
        index++;
        

        let codigo_tipo_afectacion_igv = "20";
        let total_base_igv = 0;
        let total_igv = 0;
        let total_valor_item = parseFloat(x.precio_total).toFixed(2);
        if (!isExoneradoIGV) {// con igv
        //   valor_referencial_unitario_por_item_en_operaciones_no_onerosas_y_codigo = { monto_de_valor_referencial_unitario: "01" };
          codigo_tipo_afectacion_igv = "10";
          total_igv = parseFloat(parseFloat(x.precio_total) * procentaje_IGV).toFixed(2);
          total_base_igv = parseFloat(x.precio_total) - total_igv;
          total_valor_item = parseFloat(total_base_igv);
        } 
        
        //const montoIGVItem =  parseFloat(parseFloat(x.precio_total) * procentaje_IGV).toFixed(2);
        var jsonItem = {
            "codigo_interno": x.id,
            "descripcion": x.des,
            "codigo_producto_sunat": "90101500",
            "codigo_producto_gsl": "90101500",
            "unidad_de_medida": "NIU",
            "cantidad": x.cantidad,
            "valor_unitario": x.punitario,
            "codigo_tipo_precio": "01",
            "precio_unitario": x.punitario,
            "codigo_tipo_afectacion_igv": codigo_tipo_afectacion_igv,
            "total_base_igv": total_base_igv,
            "porcentaje_igv": ValorIGV,
            "total_igv": total_igv,
            "total_impuestos": total_igv,
            "total_valor_item": total_valor_item,
            "total_item": x.precio_total
        }

        xListItemsRpt.push(jsonItem);

    })
    
    return xListItemsRpt;
}

// tipo_documento = 01 > factura se envia de manera individual 
// idtipo_comprobante_serie => guardar el correlativo
async function xSendApiSunat(json_xml, idregistro_pago, idtipo_comprobante_serie, guardarError=true) {
    const _url = URL_COMPROBANTE+'/documents';
    let _headers = HEADERS_COMPROBANTE;
    _headers.Authorization = "Bearer " + xm_log_get("datos_org_sede")[0].authorization_api_comprobante;

    var rpt = {};
    const numero_comp = json_xml.serie_documento + "-" + json_xml.numero_documento;
    const nomCliente = json_xml.datos_del_cliente_o_receptor.apellidos_y_nombres_o_razon_social;
    const idclienteComprobante = json_xml.extras.idcliente;
    const totalComprobante = json_xml.totales.total_venta;
    const totalesJson = JSON.stringify(json_xml.totales);
    json_xml = JSON.stringify(json_xml);   

    const _idregistro_p = typeof idregistro_pago === "object" ? idregistro_pago[1] : idregistro_pago;
    const _viene_facturador = typeof idregistro_pago === "object" ? 1 : 0; 
    
    //xPopupLoad.xopen();
    xm_all_xToastOpen("Conectando con Sunat...");

    await fetch(_url, {
        method: 'POST',
        headers: _headers,
        body: json_xml,
    }).then(function (response) {
        return response.json();
    }).then(function (res) {
        console.log(res);
        if (res.success) {
            rpt.ok = true;
            rpt.qr = res.data.qr;
            rpt.hash = res.data.hash;
            rpt.external_id = res.data.external_id;
                        
            res.data.nomcliente = nomCliente;
            res.data.idcliente = idclienteComprobante;
            res.data.total = totalComprobante;
            res.data.totales_json = totalesJson;
            res.data.numero = numero_comp;
            res.data.idregistro_pago = _idregistro_p;
            res.data.viene_facturador = _viene_facturador;
            res.data.idtipo_comprobante_serie = idtipo_comprobante_serie;
            
            CpeInterno_Registrar(res);

        } else { 
            // error de ingreso de datos / no continua / advierrte al usuario
            rpt.ok = false;
            rpt.error = 'Error al ingresar los datos';
            rpt.msj_error = res.message;
            rpt.hash='';            

            const data = {
                pdf:'0',
                cdr: '0',
                xml: '0',                
                idcliente: idclienteComprobante,
                total: totalComprobante,
                totales_json: totalesJson,
                nomcliente: nomCliente,
                numero: numero_comp, 
                jsonxml: json_xml, 
                external_id: '',  
                estado_api: 0,
                estado_sunat: 1,
                anulado: 1,
                msj: res.message,
                viene_facturador: _viene_facturador,
                idtipo_comprobante_serie: idtipo_comprobante_serie,                
            }

            // el api registra pero la sunat lo devuelve = validacion - datos no cumplen con lo establecido
            CpeInterno_ErrorValidacionSunat(_idregistro_p, data);

        }
    }).catch(function (error) { // error de conexion o algo pero imprime
        
        const data = {
                pdf:'0',
                cdr: '0',
                xml: '0',                
                idcliente: idclienteComprobante,
                total: totalComprobante,
                totales_json: totalesJson,
                nomcliente: nomCliente,
                numero: numero_comp, 
                jsonxml: json_xml, 
                external_id: '',  
                estado_api: 0,
                estado_sunat: 1,
                viene_facturador: _viene_facturador,
                idtipo_comprobante_serie: idtipo_comprobante_serie,                
            }
        
        rpt.ok = true;
        rpt.qr = '';
        rpt.hash = "www.papaya.com.pe";
        rpt.external_id = '';
        CpeInterno_Error(data, json_xml, _idregistro_p, _viene_facturador, idtipo_comprobante_serie);
    });
    
    setTimeout(() => {        
        xm_all_xToastClose();
    }, 500);
    
    return rpt;
}

