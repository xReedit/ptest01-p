// imprimir otros documentos --- -1 precuenta - -2 factura
// xArrayCuerpo debe tener estructura de mod impresion, (como sub pedido ::app3_sys_dta_pe)
// xArraySubTotales ya esta calculado los subtotales
// xArrayComprobante datos del comprobante : tipodoc , serie correlativo id's
// xArrayCliente datos del cliente nombre dni ruc direccion


var xImpresoraPrint;
// idregistro_pago = para manda a guardar el id_externo_comprobante electronico en la tabla registro_pago
// showPrint = true; es false si lo mando desde facturador.
async function xCocinarImprimirComprobante(xArrayCuerpo, xArraySubTotales, xArrayComprobante, xArrayCliente, idregistro_pago, xidDoc, showPrint = true){
	let rptPrint = {}
	if (xArrayComprobante && xArrayComprobante.idtipo_comprobante === "0") { rptPrint.imprime = false; return rptPrint;} // ninguno no imprime
	
	// busca impresora donde imprimir
	if (showPrint) {
		if (!xgetComprobanteImpresora(xidDoc)) {
			rptPrint.imprime = false;			
			// return rptPrint;
		}
	}
	// si no viene datos return false

    if(xArrayCuerpo.length==0){
		rptPrint.imprime = false;
		rptPrint.ok = false;
		rptPrint.msj = 'Los datos del comprobante no son correctos.';
		return rptPrint;
	}

    // array encabezado org sede
	var xArrayEncabezado = xm_log_get('datos_org_sede');    
	
	// escribir el importe total en letras
	// siempre ultimo es es el total
	const index_total = xArraySubTotales.length-1;
	const total_pagar = parseFloat(xArraySubTotales[index_total].importe);
	xArraySubTotales[index_total].importe_letras = numeroALetras(total_pagar);

	//comprobante electronico // ponemos el pie de pagina para el comprobante
	if (showPrint) { xArrayComprobante.pie_pagina_comprobante = xImpresoraPrint[0].pie_pagina_comprobante; }	

	rptPrint = await xJsonSunatCocinarDatos(xArrayCuerpo, xArraySubTotales, xArrayComprobante, xArrayCliente, idregistro_pago);
	if (!rptPrint.ok) { // si el documento electronico no es valido
		alert(rptPrint.msj_error + ". Mande a imprimir el comprobante desde Registro de pagos");
		xPopupLoad.close;
		return rptPrint;
	}

	console.log(rptPrint);
	xArrayEncabezado[0].hash = rptPrint.hash; // que es realidad el qr
	xArrayEncabezado[0].external_id = rptPrint.external_id;
	
	// return true; // temporal probamos facturacion electronica
	//
	if (!showPrint) {
		return xArrayEncabezado;
	}

    xImprimirComprobanteAhora(xArrayEncabezado,xArrayCuerpo,xArraySubTotales,xArrayComprobante,xArrayCliente,function(rpt_print){
		if(rpt_print==false){return false;}
		xPopupLoad.titulo="Imprimiendo...";
		xPopupLoad.xopen();
        setTimeout(function(){ xPopupLoad.xclose()}, 3000);
        return true;
	});
}

function xImprimirComprobanteAhora(xArrayEncabezado,xArrayCuerpo,xArraySubtotal,xArrayComprobante,xArrayCliente,callback){
	xPopupLoad.titulo="Imprimiendo...";	

	// formato de impresion items comprobante donde no se tiene en cuenta el tipo de consumo solo seccion e items
	let _arrBodyComprobante = xEstructuraItemsJsonComprobante(xArrayCuerpo, xArraySubtotal, true); // cpe = true subtotal + adicional
	_arrBodyComprobante = xEstructuraItemsAgruparPrintJsonComprobante(_arrBodyComprobante);

	$.ajax({type: 'POST', url: '../../print/print5.php', 
			data:{
				Array_enca: xArrayEncabezado, 
				Array_print: xImpresoraPrint, 
				ArrayItem: _arrBodyComprobante, // xArrayCuerpo 
				ArraySubTotales: xArraySubtotal,
				ArrayComprobante: xArrayComprobante,
				ArrayCliente: xArrayCliente				
			}
		})
	.done( function (dtPbd) {
		//xPopupLoad.xclose();
		if(dtPbd.indexOf('Error')!=-1){
			xPopupLoad.xclose();
			$("#print_error").text(dtPbd);
			xErrorPrint=true;
			dialog_erro_print.open();
		}else{
			//xPopupLoad.titulo='Exito!';
			xErrorPrint=false;
			xPopupLoad.titulo="Imprimiendo...";
			xPopupLoad.xopen();
			setTimeout(function(){ xPopupLoad.xclose()}, 3000);
		}
		return callback(xErrorPrint);
	});
}

/// imprimir comprobante con impresora preseleccionada | desde: facturador
function xImprimirComprobanteAhoraPrintPreSelect(xArrayEncabezado, xArrayCuerpo, xArraySubtotal, xArrayComprobante, xArrayCliente, xArrImpresora, callback) {
	xPopupLoad.titulo = "Imprimiendo...";

	// formato de impresion items comprobante donde no se tiene en cuenta el tipo de consumo solo seccion e items
	// let _arrBodyComprobante = xEstructuraItemsJsonComprobante(xArrayCuerpo, xArraySubtotal, true); // cpe = true subtotal + adicional
	// _arrBodyComprobante = xEstructuraItemsAgruparPrintJsonComprobante(_arrBodyComprobante);

	$.ajax({
		type: 'POST', url: '../../print/print5.php',
		data: {
			Array_enca: xArrayEncabezado,
			Array_print: xArrImpresora,
			ArrayItem: xArrayCuerpo, // xArrayCuerpo 
			ArraySubTotales: xArraySubtotal,
			ArrayComprobante: xArrayComprobante,
			ArrayCliente: xArrayCliente
		}
	})
		.done(function (dtPbd) {
			//xPopupLoad.xclose();
			if (dtPbd.indexOf('Error') != -1) {
				xPopupLoad.xclose();
				$("#print_error").text(dtPbd);
				xErrorPrint = true;
				dialog_erro_print.open();
			} else {
				//xPopupLoad.titulo='Exito!';
				xErrorPrint = false;
				xPopupLoad.titulo = "Imprimiendo...";
				xPopupLoad.xopen();
				setTimeout(function () { xPopupLoad.xclose() }, 3000);
			}
			return callback(xErrorPrint);
		});
}


// impresora donde se va a imprimir
// retorna boolean si encontro impresora donde imprimir
function xgetComprobanteImpresora(xidDoc) {
    var xArrayImpresoras=xm_log_get('app3_woIpPrint'); //JSON.parse(window.localStorage.getItem("::app3_woIpPrint"));
    var xDtTipoDoc=xm_log_get('app3_woIpPrintO');//JSON.parse(window.localStorage.getItem("::app3_woIpPrintO"));
    var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");
	xImpresoraPrint = xm_log_get("sede_generales");
	
	const num_copias_all = xImpresoraPrint[0].num_copias; // numero de copias para las demas impresoras -local


    var xIpPrintDoc=xidDoc;
	var xpasePrint=false;

	for (var i = 0; i < xDtTipoDoc.length; i++) {
		//pre-cuenta
		// if(xDtTipoDoc[i].idtipo_otro==-1){xIpPrintDoc=xDtTipoDoc[i].idimpresora; break;}
		if (xDtTipoDoc[i].idtipo_otro == xidDoc){xIpPrintDoc=xDtTipoDoc[i].idimpresora; break;}
	};
	//si existe impresora local // imprime todos los otros documentos en esta impresora local.
	if(xPrintLocal!=undefined && xPrintLocal!=''){
		xPrintLocal=$.parseJSON(xPrintLocal);
		xImpresoraPrint[0].ip_print=xPrintLocal.ip;
		xImpresoraPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;
		xImpresoraPrint[0].var_size_font=xPrintLocal.var_size_font
		xImpresoraPrint[0].local = 1;
		xImpresoraPrint[0].num_copias = xPrintLocal.num_copias;
		xImpresoraPrint[0].copia_local = xPrintLocal.copia_local;
		xImpresoraPrint[0].img64 = xPrintLocal.img64;
		xpasePrint=true;
	}else{
		for (var i = 0; i < xArrayImpresoras.length; i++) {
			if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){
				xpasePrint=true;
				xIpPrintDoc=xArrayImpresoras[i].ip; 
				xImpresoraPrint[0].ip_print=xIpPrintDoc;
				xImpresoraPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;
				xImpresoraPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;
				xImpresoraPrint[0].local = 0;
				xImpresoraPrint[0].num_copias = num_copias_all;
				xImpresoraPrint[0].copia_local = 0; // no imprime // solo para impresora local 
				xImpresoraPrint[0].img64 = xArrayImpresoras[i].img64; // ya no manda la img en base64 si no esta activo img64
				break;
			}
		}
    }

    // si existe impresora donde imprimir
    return xpasePrint; 
}

// devuelve una impresora segun idoc
function xgetImpresora(xidDoc) {
	var xArrayImpresoras = xm_log_get('app3_woIpPrint'); //JSON.parse(window.localStorage.getItem("::app3_woIpPrint"));
	var xDtTipoDoc = xm_log_get('app3_woIpPrintO');//JSON.parse(window.localStorage.getItem("::app3_woIpPrintO"));
	var xPrintLocal = window.localStorage.getItem("::app3_woIpPrintLo");
	xImpresoraPrint = xm_log_get("sede_generales");

	const num_copias_all = xImpresoraPrint[0].num_copias; // numero de copias para las demas impresoras -local


	var xIpPrintDoc = xidDoc;
	var xpasePrint = false;

	for (var i = 0; i < xDtTipoDoc.length; i++) {
		//pre-cuenta
		// if (xDtTipoDoc[i].idtipo_otro == -1) { xIpPrintDoc = xDtTipoDoc[i].idimpresora; break; }
		if (xDtTipoDoc[i].idtipo_otro == xidDoc) { xIpPrintDoc = xDtTipoDoc[i].idimpresora; break; }
	};
	//si existe impresora local // imprime todos los otros documentos en esta impresora local.
	if (xPrintLocal != undefined && xPrintLocal != '') {
		xPrintLocal = $.parseJSON(xPrintLocal);
		xImpresoraPrint[0].ip_print = xPrintLocal.ip;
		xImpresoraPrint[0].var_margen_iz = xPrintLocal.var_margen_iz;
		xImpresoraPrint[0].var_size_font = xPrintLocal.var_size_font
		xImpresoraPrint[0].local = 1;
		xImpresoraPrint[0].num_copias = xPrintLocal.num_copias;
		xImpresoraPrint[0].copia_local = xPrintLocal.copia_local;
		xImpresoraPrint[0].img64 = xPrintLocal.img64;
		xpasePrint = true;
	} else {
		for (var i = 0; i < xArrayImpresoras.length; i++) {
			if (xArrayImpresoras[i].idimpresora == xIpPrintDoc) {
				xpasePrint = true;
				xIpPrintDoc = xArrayImpresoras[i].ip;
				xImpresoraPrint[0].ip_print = xIpPrintDoc;
				xImpresoraPrint[0].var_margen_iz = xArrayImpresoras[i].var_margen_iz;
				xImpresoraPrint[0].var_size_font = xArrayImpresoras[i].var_size_font;
				xImpresoraPrint[0].local = 0;
				xImpresoraPrint[0].num_copias = num_copias_all;
				xImpresoraPrint[0].copia_local = 0; // no imprime // solo para impresora local 
				xImpresoraPrint[0].img64 = xArrayImpresoras[i].img64; // ya no manda la img en base64 si no esta activo img64
				break;
			}
		}
	}

	const print_return = xpasePrint ? xImpresoraPrint : null;
	return print_return;
}


////////////////////////////////////////////////////
///////  COMANDA   ///////////////
/// para imprimir comanda se requier xArrayPedidoObj como  xArrayCuerpo
// con este array se crea los nuevos pedidos

// function xCocinarImprimirComanda(xArrayEnca, xArrayCuerpo, xArraySubTotales, responde) {
// 	if (xArrayCuerpo.length ===0 ) return;

// 	xgetComprobanteImpresoraComanda(xArrayEnca, xArrayCuerpo, xArraySubTotales, (rpt)=>{
// 		responde(rpt);
// 	})
// }

function xCocinarImprimirComanda(xArrayEnca, xArrayCuerpo, xArraySubTotales, callback) {
	if (xArrayCuerpo.length ===0 ) return;

	var xArrayImpresoras=xm_log_get('app3_woIpPrint'); //JSON.parse(window.localStorage.getItem("::app3_woIpPrint"));
    var xDtTipoDoc=xm_log_get('app3_woIpPrintO');//JSON.parse(window.localStorage.getItem("::app3_woIpPrintO"));
	var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");
	var xImpresoraPrint=xm_log_get('sede_generales');
	var xcuentaSeccionesImpresas = 0;
	var xCuentaImpresorasEvaluadas = 0;
	const num_copias_all = xImpresoraPrint[0].num_copias; // numero de copias para las demas impresoras -local
	const var_size_font_tall_comanda = xImpresoraPrint[0].var_size_font_tall_comanda;
	
	//si existe impresora local // saca una copia de todo el pedido
	if(xPrintLocal!=undefined && xPrintLocal!=''){
		xPrintLocal=$.parseJSON(xPrintLocal);
		xArmarSubtotalesArray(xArrayCuerpo,xImpresoraPrint)
		xImpresoraPrint[0].ip_print=xPrintLocal.ip;
		xImpresoraPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;
		xImpresoraPrint[0].var_size_font=xPrintLocal.var_size_font;
		xImpresoraPrint[0].local = 1;
		xImpresoraPrint[0].num_copias = xPrintLocal.num_copias;
		xImpresoraPrint[0].copia_local = xPrintLocal.copia_local;
		xImpresoraPrint[0].img64 = xPrintLocal.img64;
		if (xPrintLocal.img64 === "0") { xImpresoraPrint[0].logo64 = ''; } // ya no manda la img en base64 si no esta activo img64

		xImprimirComandaAhora(xArrayEnca,xImpresoraPrint,xArrayCuerpo,xArraySubTotales,(res)=>{
			callback(res);
			// if(rpt_print==false){callback(rpt_print); return;}
			// xPopupLoad.titulo="Imprimiendo...";
			// xPopupLoad.xopen();
			// setTimeout(function(){ xPopupLoad.xclose()}, 3000);
		});
	}

	//evalua impresoras y secciones, despachos o areas, la seccion en que impresora se imprime
	for (var z = 0; z < xArrayImpresoras.length; z++) {
		xIdPrint=xArrayImpresoras[z].idimpresora;
		xArrayBodyPrint=[];
		xCuentaImpresorasEvaluadas++;
		for (var i = 0; i < xArrayCuerpo.length; i++) {
			//xCuentaItemsEvaluados++;
			if(xArrayCuerpo[i]==null){continue;}
			$.map(xArrayCuerpo[i], function(xn_p, z) {
				if (typeof xn_p=="object"){
					if(xIdPrint==xn_p.idimpresora){
						//if(xArrayBodyPrint.length==0){
						if(xArrayBodyPrint[i]===undefined){
							xArrayBodyPrint[i]={'des':xArrayCuerpo[i].des, 'id':xArrayCuerpo[i].id, 'titlo':xArrayCuerpo[i].titulo};
						}
						//try {xArrayBodyPrint[i][xn_p.iditem]=xn_p;}
						//catch(err) {console.log(err);}
						xArrayBodyPrint[i][xn_p.iditem]=xn_p;
					}
				}
			})
		}
		if(xArrayBodyPrint.length==0){continue}
		xcuentaSeccionesImpresas++;
		xArmarSubtotalesArray(xArrayBodyPrint,xImpresoraPrint)
		//xImpresoraPrint[0].ip_print='192.168.1.80';
		xImpresoraPrint[0].ip_print=xArrayImpresoras[z].ip;
		xImpresoraPrint[0].var_margen_iz=xArrayImpresoras[z].var_margen_iz;
		xImpresoraPrint[0].var_size_font=xArrayImpresoras[z].var_size_font;
		xImpresoraPrint[0].local = 0;		
		xImpresoraPrint[0].num_copias = num_copias_all;
		xImpresoraPrint[0].var_size_font_tall_comanda = var_size_font_tall_comanda;
		xImpresoraPrint[0].copia_local = 0; // no imprime // solo para impresora local 
		xImpresoraPrint[0].img64 = xArrayImpresoras[z].img64;		
		if (xArrayImpresoras[z].img64 === "0") { xImpresoraPrint[0].logo64 = '';}
		
		xImprimirComandaAhora(xArrayEnca,xImpresoraPrint,xArrayBodyPrint,xArraySubTotales,function(rpt_print){
			if(xArrayImpresoras.length==xCuentaImpresorasEvaluadas && rpt_print==false){//si todas las impresoras fueron evaluadas y no presentaron error termina la funcion
				// setTimeout( function(){try{xNuevoPedidoMP();}catch(err){return false;}}, 2700); //nuevo pedido en mipedido
				if(callback){
					callback(false); //falso = no hjay error
				};
			}
			else{
				if(callback){callback(true);} 
			}
		});
	};

	// si no encuentra ninguna impresora pasa como false = no hay error
	if(xcuentaSeccionesImpresas === 0){if(callback){callback(false)};}
}


function xImprimirComandaAhora(xArrayEncabezado,xImpresoraPrint,xArrayCuerpo,xArraySubtotal,callback){
	xPopupLoad.titulo="Imprimiendo...";
	
	const _data = {
		Array_enca: xArrayEncabezado,
		Array_print: xImpresoraPrint,
		ArrayItem: xArrayCuerpo,
		ArraySubTotales: xArraySubtotal	
	}
	xSendDataPrintServer(_data,1,'comanda');


	$.ajax({type: 'POST', url: '../../print/print3.php', 
			data:{
				Array_enca:xArrayEncabezado, 
				Array_print:xImpresoraPrint, 
				ArrayItem:xArrayCuerpo, 
				ArraySubTotales:xArraySubtotal				
			},
			success: function(dtPbd){
				if (dtPbd.indexOf('Error') != -1) {
					xPopupLoad.xclose();
					$("#print_error").text(dtPbd);
					xErrorPrint = true;
					// dialog_erro_print.open();
				} else {
					//xPopupLoad.titulo='Exito!';
					xErrorPrint = false;
					xPopupLoad.titulo = "Imprimiendo...";
					xPopupLoad.xopen();
					setTimeout(function () { xPopupLoad.xclose() }, 3000);
				}

				callback(xErrorPrint);
			},
			error: function (XMLHttpRequest, textStatus, errorThrown) {
				console.log(errorThrown);				
				xPopupLoad.xclose();
				$("#print_error").text("Error, Verifique que la ticketera este prendida y que tenga papel.");
				xErrorPrint = true;
				callback(xErrorPrint);
			}
		});	
}



/// enviar a print server
function xSendDataPrintServer(_data, _idprint_server_estructura, _tipo){
	// _data = JSON.stringify(JSON.stringify(_data));
	if (_data.Array_print[0].ip_print==='') return;
	
	_data = JSON.stringify(_data);

	$.ajax({
		url: '../../bdphp/log_003.php?op=1',
		type: 'POST',		
		data: {			
			datos: _data,
			idprint_server_estructura: _idprint_server_estructura,
			tipo: _tipo
		}
	})
	.done((x)=> {	
		console.log(x);
	});	
}