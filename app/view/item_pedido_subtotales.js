/// calcula los subtotales desde el total de la mesa
/// se utiliza en dashboard - control de pedidos
var xLocal_xDtSubTotales;
var xLocal_SubTotal_Quitados = "";
var arrVerifySubTotalTachados = []; // para tachar a todo o no
function xArmarSubtotalesFromTotal(itemMesa) {
	if (!itemMesa) return;
	
	var dtDetalle = itemMesa.secciones.split(',');
	var subtotales_tachados_bd = itemMesa.subtotales_tachados; 
	var arrDt = [];

	dtDetalle.map((d,index) => {
		var _d = d.split(':');
		arrDt.push({'tipoconsumo':_d[0], 'seccion': _d[1], 'cantidad': _d[2], 'subtotales_tachados': subtotales_tachados_bd});		
    });
    
    return xCalcTotalSubArray(arrDt,itemMesa.importe);
}

//Armar subtotales del array a imprimir
function xArmarSubtotalesArray(xarr_data_pedido=null, total=0){
	var xLocal_TotalImporte=0, arrDt=[];

	// si data_pedido es null es decir no indican, es por que viene de nuevo pedido
	// xmipedido, control mesa o venta rapida 
	// en ese caso toma xArrayPedidoObj que es la variable global donde se almacena el nuevo pedido 
	xarr_data_pedido = xarr_data_pedido || xArrayPedidoObj;
	
	xLocal_TotalImporte = total===0 ? xSumaCantArray(xarr_data_pedido) : total;
	
	// estructuramos para calcular
    for (var i = 0; i < xarr_data_pedido.length; i++) {
		if(xarr_data_pedido[i]==null){continue;}
		$.map(xarr_data_pedido[i], function(n, z) {
			if (typeof n=="object"){
				const  subtotales_tachados = n.subtotales_tachados !== undefined ? n.subtotales_tachados : xLocal_SubTotal_Quitados;
				const  idpedido = n.idpedido ? n.idpedido : 0;
				arrDt.push({'tipoconsumo':n.idtipo_consumo, 'seccion': n.idseccion, 'cantidad':n.cantidad, 'subtotales_tachados': subtotales_tachados, 'idpedido': idpedido});
			}
		})
	}
       
    return xCalcTotalSubArray(arrDt,xLocal_TotalImporte);
}

 
function xCalcTotalSubArray(arrDt, importeTotal) {
	var xCartaSubtotales=xm_log_get('carta_subtotales'),
		arrTipoConsumo = arrDt || [];
	
	xLocal_xDtSubTotales=[]; // variable global
	var arrSuma = [];

	xLocal_xDtSubTotales.push({'descripcion':'Sub Total', 'importe':xMoneda(importeTotal), 'visible':true , 'quitar': false}); 
	arrSuma.push({ 'descripcion': 'Sub Total', 'importe': xMoneda(importeTotal), 'visible': true, 'quitar': false, 'visible_cpe': true}); 

	
	xSumTotalPorcentaje = 0;
	xSumCantImporte = 0; // suma totales
	// arrVerifySubTotalTachados = []; // para tachar a todo o no
	const countPedidos = arrTipoConsumo.length; 

	
	// adicionales taper | deliver | etc
	if ( arrTipoConsumo.length === 0  || arrTipoConsumo[0].subtotales_tachados === undefined ) return;

	// se agrupa la data y se suma las cantidades	
	var arrCocinada = arrTipoConsumo				
	.map(x => { 
		x.grupo = x.tipoconsumo+x.seccion;
		// x.grupoTipoconsumo = x.tipoconsumo
		return x;
	 })		
	.reduce((obj,val) => {
		const grupo = val.grupo;
		if (obj[grupo]) {
			obj[grupo].grupo = val.grupo;
			obj[grupo].grupoTipoconsumo = val.tipoconsumo;
			obj[grupo].cantidad = parseFloat(obj[grupo].cantidad) + parseFloat(val.cantidad)
			obj[grupo].subtotales_tachados = !obj[grupo].subtotales_tachados ? val.subtotales_tachados : obj[grupo].subtotales_tachados + val.subtotales_tachados;
		} else {
			obj[grupo] = [];
			obj[grupo].grupo = val.grupo;
			obj[grupo].grupoTipoconsumo = val.tipoconsumo;
			obj[grupo].cantidad = val.cantidad
			obj[grupo].subtotales_tachados = !obj[grupo].subtotales_tachados ? val.subtotales_tachados : obj[grupo].subtotales_tachados + val.subtotales_tachados;
		}
		return obj;
	}, []);

	var all_subtotales_tachados = '';
	arrCocinada.map( x => {
		all_subtotales_tachados += x.subtotales_tachados
	})

	arrCocinada.all_subtotales_tachados = all_subtotales_tachados;

	// CALCULAR RECORRIENDO CADA ITEM UNO X UNO
	
	xCartaSubtotales
		// .filter(c => c.tipo==='a')// todos los que son adicionales
		.map(c => {c.grupo = c.idtipo_consumo+c.idseccion; return c;})
		.map(c => {		
			switch (c.tipo) {
				case 'a': // todos los que son adicionales
					arrTipoConsumo // arr que contiene todos los items
						// .filter(x => x.grupo===c.grupo)				
						.map(x => {
							
							const id = c.tipo+c.id; // para quitar												
							const nivel = parseInt(c.nivel);										
							let sumItem = 0;
							let importe_tachado = 0;
							let subtotales_tachados_local = '';
							let cantidadItemPedido = 0;
							
							// arrCocinada[x.grupo].subtotales_tachados = evalua el grupo
							xLocal_SubTotal_Quitados = arrCocinada[x.grupo].subtotales_tachados != '' ? arrCocinada[x.grupo].subtotales_tachados : xLocal_SubTotal_Quitados;
		
							if ( nivel === 0){ // nivel item x item							
								if (x.grupo !== c.grupo) {return}
								sumItem = parseFloat(x.cantidad) * parseFloat(c.monto);
								if ( sumItem === 0) { return; }//no tiene esta seccion
							} else { // nivel pedido
								if (x.tipoconsumo !== c.idtipo_consumo) {return}
								sumItem = parseFloat(c.monto);						
						}
		
							// si esta para tachar al item no suma
							// evalua uno por uno
							importe_tachado = sumItem ;

							// if ( x.subtotales_tachados === "" ){ // quiere decir que no hay para procesar o que viene de venta rapida
							// 	subtotales_tachados_local = xLocal_SubTotal_Quitados;
							// 	cantidadItemPedido = 1;
							// } else {
								subtotales_tachados_local = x.subtotales_tachados;
								cantidadItemPedido = arrCocinada[x.grupo].cantidad
							// }


							subtotales_tachados_local.indexOf(id) >=0 ? sumItem = 0 : importe_tachado = 0;

							const tachado = checkSubTotalQuitado(cantidadItemPedido, id, sumItem);
							
							var IdExite;
							arrSuma.map((z, index) => {if (z.id === id) {IdExite = index; return;}} );
														

							if (IdExite) {
								// const importeItem = parseFloat(arrSuma[IdExite].importe);
								const importeItem = parseFloat(arrSuma[IdExite].importe);
								const importeTachadoItem = parseFloat(arrSuma[IdExite].importe_tachado);
								arrSuma[IdExite].importe = nivel === 0 ? parseFloat( importeItem + parseFloat(sumItem))
								.toFixed(2) : parseFloat(sumItem).toFixed(2);

								// arrSuma[IdExite].cantidad++; // con punitario sacamos cantidad
								
								arrSuma[IdExite].importe_tachado = nivel === 0 ? parseFloat(importeTachadoItem + parseFloat(importe_tachado)).toFixed(2) : parseFloat(importe_tachado).toFixed(2);
							} else {
								arrSuma.push({ 'id': id, 'descripcion': c.descripcion, 'importe_tachado': xMoneda(importe_tachado), 'importe': xMoneda(sumItem), 'punitario': c.monto, 'esImpuesto': 0, 'visible': true, 'quitar': true, 'tachado': tachado, 'visible_cpe': false}); 
							}
		
						});
					break;

				case 'p': // todos los porcentajes // que se añaden al total de la cuentas y NO SON QUITABLES
					const id = c.tipo+c.id;
					const esImpuesto = c.es_impuesto;
					const valorImpuesto = c.activo === "0" ? c.monto : 0; // se marca 0=activo o 1=desactivado para obtener el % del impuesto requerido por comprobante electronico			
					const visible_cpe = esImpuesto === "1" ? true : false; // indica si se muestra en la facturacion electronica
					let porcentaje = parseFloat(parseFloat(valorImpuesto)/100).toFixed(2);		
					porcentaje = parseFloat(parseFloat(importeTotal)*parseFloat(porcentaje)).toFixed(2);

					// const esVisible = porcentaje > 0 ? true : false; // ver que implica
					
					arrSuma.push({ 'id': id, 'descripcion': c.descripcion, 'importe': xMoneda(porcentaje), 'esImpuesto': esImpuesto, 'visible': true, 'quitar': false, 'tachado': false, 'visible_cpe': visible_cpe}); 
					break;
			}	
		});

	 
	const sumTotal = Object.keys(arrSuma).map(x => arrSuma[x].importe).reduce((a, b) => parseFloat(a) + parseFloat(b));
	if(arrSuma.length==1){arrSuma=[];}
	arrSuma.push({ 'descripcion': 'Total', 'importe': xMoneda(sumTotal), 'visible': true, 'visible_cpe': true});

	xLocal_xDtSubTotales = arrSuma;
	xSumCantImporte = sumTotal;
	// retorna el importe total + subtotales(igv,servio,taper)

	return xSumCantImporte;
}

// cheque si el subtotal fue quitaodo o marcado como no cobrar
// val = valor a sumar si no esta quitado
// x = grupo analizado que trae el subtotales_tachados
// countPedidos == cantidad de items
function checkSubTotalQuitado(countPedidos, id , val) {	
	var rpt = false;
	if (!xLocal_SubTotal_Quitados) { return; }	

	const CountIdTacahdos = xLocal_SubTotal_Quitados.toLowerCase().split(",").sort().filter(x => x===id).length;
	const hbilitarTachado =  parseInt(CountIdTacahdos) >= parseFloat(countPedidos);

	rpt = xLocal_SubTotal_Quitados.indexOf(id) >=0 ? true : false;
	if (!rpt) {xSumCantImporte += parseFloat(val);}
	
	return hbilitarTachado ? rpt : false; 
}

var groupBy = function(xs, key) {
	return xs.reduce(function(rv, x) {
	  (rv[x[key]] = rv[x[key]] || []).push(x);
	  return rv;
	}, {});
  };

