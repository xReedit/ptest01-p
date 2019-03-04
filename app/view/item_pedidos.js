var xArrayPedidoObj;
var xidTipoConsumo;
var xidItem;
var xCambioCantidad=false;
var xLocal_xDtSubTotales;
var xLocal_TotalImporte=0;
var xErrorPrint=false;
var xArrayDesTipoConsumo=new Array();
var xVerificarImprimirComanda=false;//si solo hay items de bodega imprime o no comanda segun confg en almacen// si en  imprimir_comanda=1 imprimi
//var xLocal_TotalRowsArrayImporte=0;

var xGeneralDataCarta;
var xGeneralRelojUpdateItemsSolo;
var xGeneralRelojUpdateItemsCambioBd;
var xDisparaUpdateItems=new Event('GeneralUpdateItemsSolo');//cada 60segundos de inactividad

var xGeneralNumPedidosActual=0;
var xGeneralUpdateSeccion=0;
var xGeneralDataSeccion;

var xNumPedidosBD=0;

var xGeneralArraySubTotales=new Array();

// 'precio_total_calc': para calcular en regalas de carta

$(document).on('click', '.xBtn', function(e) {
		xCambioCantidad=true;
		var xOperacion=$(this).text();
		var objCant=$(this).parent().find('.xCant_item');
		var xStockActual=$(this).parents('.xmenu_item_2').find('.xstock_item p').text();
		var xidItem2=$(this).parents('.xmenu_item_2').attr('data-item');//iditem verdadero
		xidItem=$(this).parents('.xmenu_item_2').attr('data-id'); // iditem lista de la carta
		xidTipoConsumo=$(this).parent().attr('data-id');
		var xDesItem=$(this).parents('.xmenu_item_2').find('.xtitulo_item').text();
		var xPrecioItem=$(this).parents('.xmenu_item_2').find('.xprecio_item').text();
		var xIndicaciones=$(this).parents('.xmenu_item_2').find('#txt_referencia').val();
		var xCantActual=parseInt(objCant.text());
		var xCantSeccion=parseInt(xArrayPedidoObj[xidTipoConsumo]["cantidad"]);
		var xCantTotalItem=0;
		var xDesSeccion = xTituloDet;
		var xIdSeccionItem=$(this).parents('.xmenu_item_2').attr('data-idseccion');
		var xIdSeccionItem_index=$(this).parents('.xmenu_item_2').attr('data-idseccionindex');
		var xRowidimpresora=$(this).parents('.xmenu_item_2').attr('data-idimpresora');
		var xRowidporcion=$(this).parents('.xmenu_item_2').attr('data-idprocede');
		var xRowProcede=$(this).parents('.xmenu_item_2').attr('data-procede');
		var xRowProcede_index=$(this).parents('.xmenu_item_2').attr('data-procedeindex');//para odernar al momento de imprimir: primero carta luego 1 bodega
		var ximprmir_comanda=$(this).parents('.xmenu_item_2').attr('data-imprimircomanda'); //si solo hay items de bodega imprime o no comanda segun confg en almacen// si en  imprimir_comanda=1 imprimi
		var xcant_descontar=$(this).parents('.xmenu_item_2').attr('data-cant_descontar'); //cantidad a desconatar del stock, si es porcion pueden ser 2,1  (2 chorizos, 1 huevo) o 2(2 porciones de 1/2 cocona)
		var xidalmacen_items=$(this).parents('.xmenu_item_2').attr('data-idalmacen_items');
		var xidDescontar=xRowidporcion;
		var xPrecioTotal;

		//concatena con indicaciones >>en servidor
		//if(xIndicaciones!=""){xIndicaciones='('+xIndicaciones+')';}
		//xDesItem=xDesItem+xIndicaciones.toLowerCase();

		if(isNaN(xCantActual)){xCantActual=0}
		if(isNaN(xCantSeccion)){xCantSeccion=0}

		$(this).parents('.xpedir_item').find('.xCant_item').each(function(index,element){
				var xval=parseInt($(element).text());
				if(isNaN(xval)){xval=0}
				xCantTotalItem=parseInt(xCantTotalItem)+xval
			})
		if(isNaN(xCantTotalItem)){xCantTotalItem=0}
		xCantActual=parseInt(xCantActual)+xOperacion+parseInt(1);
		xCantTotalItem=parseInt(xCantTotalItem)+xOperacion+parseInt(1);
		xCount_cant_ico=parseInt(xCount_cant_ico)+xOperacion+parseInt(1);
		xCantSeccion=parseInt(xCantSeccion)+xOperacion+parseInt(1);

		xCantActual=eval(xCantActual);
		xCantTotalItem=eval(xCantTotalItem);
		xCount_cant_ico=eval(xCount_cant_ico);
		xCantSeccion=eval(xCantSeccion);
		if(xCount_cant_ico<0){xCount_cant_ico=0;}
		if(xStockActual!='ND'){if(xCantTotalItem>parseInt(xStockActual)){return;}}
		if(xCantActual<=0){xCantActual=0; objCant.addClass('xInvisible');}else{objCant.removeClass('xInvisible');}
		objCant.text(xCeroIzq(xCantActual,2));

		xPrecioTotal=parseFloat(xCantActual*xPrecioItem).toFixed(2);


		xArrayPedidoObj[xidTipoConsumo]["cantidad"]=xCantSeccion;
		xArrayPedidoObj[xidTipoConsumo][xidItem]={'idcategoria':xidCategoria, 'idseccion':xIdSeccionItem, 'idseccion_index':xIdSeccionItem_index, 'des_seccion':xDesSeccion, 'iditem':xidItem, 'idtipo_consumo':xidTipoConsumo, 'cantidad':xCantActual, 'precio':xPrecioItem, 'des':xDesItem,
			'precio_total': xPrecioTotal, 'precio_total_calc': xPrecioTotal ,'precio_print':'','indicaciones':xIndicaciones,'iditem2':xidItem2,'idimpresora':xRowidimpresora, 'idprocede':xRowidporcion, 'procede':xRowProcede, 'procede_index':xRowProcede_index,'imprimir_comanda':ximprmir_comanda, 'iddescontar':xidDescontar, 'cant_descontar':xcant_descontar, 'idalmacen_items':xidalmacen_items, 'visible':0};

		if(xCantActual<=0){delete xArrayPedidoObj[xidTipoConsumo][xidItem]}

		window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))

		window.localStorage.setItem("::app3_sys_dta_count_ico",xCount_cant_ico);
		if(xCount_cant_ico>0){$(".xIco_MiPedido .xCantPedio_ico").text(xCeroIzq(xCount_cant_ico,2));$(".xIco_MiPedido .xCantPedio_ico").removeClass('xInvisible');}else{$(".xIco_MiPedido .xCantPedio_ico").addClass('xInvisible');}

		// event.stopPropagation();
		e.stopPropagation();
		e.stopImmediatePropagation()
		return false;
	})

//agregar item desde control de pedidos
$(document).on('click', '.xBtn_li, .xBtn_li2', function(e) {
		var element_cant_li_sel= $(this).parent().find('.xcant_li');
		if(element_cant_li_sel.length==0){element_cant_li_sel=$(this).parent().find('.xcant_li2');}
		var element_li_add__print= $(this).parent().parent();
		var xsigno=$(this).text();
		var xcant=parseInt(element_cant_li_sel.text());
		var xcant_max=element_cant_li_sel.attr('data-cantmax');
		var xli_tipoconsumo=$("#select_ulTPC option:selected").val();
		var xli_iditem=$(element_li_add__print).attr('data-idcl');
		

		var xli_des=$(this).parent().parent().find('.xtitulo_li').text();
		if(xli_des==""){xli_des=$(this).parent().parent().find('.xtitulo_li2').text();}//.split('|');xli_des=xli_des[1].trim();}
		var xli_des_ref=$(this).parent().parent().find('#xinput_li').val();
		var xli_precio=$(element_li_add__print).attr('data-punitario');
		var xli_idimpresora=$(element_li_add__print).attr('data-idimpresora');
		var xli_idprocede=$(element_li_add__print).attr('data-idprocede');
		var xli_Procede=$(element_li_add__print).attr('data-procede');
		var xli_Procede_index=$(element_li_add__print).attr('data-procedeindex');//para odernar al momento de imprimir: primero carta luego 1 bodega
		var xPrecioTotal=0;
		var xviene_venta_rapida=$(element_li_add__print).attr('data-ventarapida');
		var ximprmir_comanda=$(element_li_add__print).attr('data-imprimircomanda') || 0; //si solo hay items de bodega imprime o no comanda segun confg en almacen// si en  imprimir_comanda=1 imprimi
		var xcant_descontar=$(element_li_add__print).attr('data-cant_descontar'); //cantidad a desconatar del stock, si es porcion pueden ser 2,1  (2 chorizos, 1 huevo) o 2(2 porciones de 1/2 cocona)
		var xidcategoria=$(element_li_add__print).attr('data-idcategoria');
		var xli_idalmacen_items=$(element_li_add__print).attr('data-idalmacen_items');

		//concatena con indicaciones >>en servidor
		//if(xIndicaciones!=""){xIndicaciones='('+xIndicaciones+')';}
		//xDesItem=xDesItem+xIndicaciones.toLowerCase();

		//var xArrayPedidoLiObj=new Array();

		if(typeof xArrayPedidoObj[xli_tipoconsumo][xli_iditem]==="undefined"){xcant=0}else{
			xcant = parseInt(xArrayPedidoObj[xli_tipoconsumo][xli_iditem].cantidad) || 0;
		}

		if(xsigno=='+'){
			//omitir para seguir vendiendo sin stock, da change a despues
			if(xcant<xcant_max){xcant++;}
		}else{
			xcant--;
		}

		xPrecioTotal=parseFloat(parseFloat(xli_precio)*parseFloat(xcant)).toFixed(2);
		xArrayPedidoObj[xli_tipoconsumo][xli_iditem]={'idcategoria':xidcategoria, 'idseccion':$(element_li_add__print).attr('data-idseccion'), 'idseccion_index':$(element_li_add__print).attr('data-idseccionindex'), 'des_seccion':$(element_li_add__print).attr('data-cat'), 'iditem':xli_iditem, 'idtipo_consumo':xli_tipoconsumo, 'cantidad':xcant, 'precio':xli_precio, 'des':xli_des,
			'precio_total': xPrecioTotal, 'precio_total_calc': xPrecioTotal,'precio_print':xPrecioTotal,'indicaciones':xli_des_ref,'iditem2':$(element_li_add__print).attr('data-iditem'), 'idimpresora':xli_idimpresora, 'idprocede':xli_idprocede, 'procede':xli_Procede, 'procede_index':xli_Procede_index, 'imprimir_comanda':ximprmir_comanda, 'cant_descontar':xcant_descontar, 'idalmacen_items':xli_idalmacen_items,  'visible':0};

		element_cant_li_sel.text(xcant);
		if(xcant<=0){xcant=0;element_cant_li_sel.removeClass('cant_fixed_li');delete xArrayPedidoObj[xli_tipoconsumo][xli_iditem];}else{
			element_cant_li_sel.addClass('cant_fixed_li')
		}


		if(xviene_venta_rapida==1){//viene de venta rapida
			xVerMipedidoVR();
		}

		// event.stopPropagation();
		e.stopPropagation();
		e.stopImmediatePropagation()
	})


//venta rapida
function xBtnSumarRestarKey(xobj,xval){
	//this=$(xobj);
	var element_cant_li_sel= $(xobj).parent().find('.xcant_li');
		if(element_cant_li_sel.length==0){element_cant_li_sel=$(xobj).parent().find('.xcant_li2');}
		var element_li_add__print= $(xobj).parent().parent();
		//var xsigno=$(xobj)text();
		var xcant=parseInt(element_cant_li_sel.text());
		var xcant_max=element_cant_li_sel.attr('data-cantmax');
		var xli_tipoconsumo=$("#select_ulTPC option:selected").val();
		var xli_iditem=$(element_li_add__print).attr('data-idcl');
		var xli_des=$(xobj).parent().parent().find('.xtitulo_li').text();
		if(xli_des==""){xli_des=$(xobj).parent().parent().find('.xtitulo_li2').text();}//.split('|');xli_des=xli_des[1].trim();}
		var xli_des_ref=$(xobj).parent().parent().find('#xinput_li').val();
		var xli_precio=$(element_li_add__print).attr('data-punitario');
		var xli_idimpresora=$(element_li_add__print).attr('data-idimpresora');
		var xli_idprocede=$(element_li_add__print).attr('data-idprocede');
		var xli_Procede=$(element_li_add__print).attr('data-procede');
		var xli_Procede_index=$(element_li_add__print).attr('data-procedeindex');//para odernar al momento de imprimir: primero carta luego 1 bodega
		var ximprmir_comanda=$(element_li_add__print).attr('data-imprimircomanda') || 0; //si solo hay items de bodega imprime o no comanda segun confg en almacen// si en  imprimir_comanda=1 imprimi
		var xcant_descontar=$(element_li_add__print).attr('data-cant_descontar'); //cantidad a desconatar del stock, si es porcion pueden ser 2,1  (2 chorizos, 1 huevo) o 2(2 porciones de 1/2 cocona)
		var xidcategoria=$(element_li_add__print).attr('data-idcategoria');
		var xli_idalmacen_items=$(element_li_add__print).attr('data-idalmacen_items');
		var xPrecioTotal=0;
		//var xArrayPedidoLiObj=new Array();

		//cantidad acutual si la hay
		//para llevar o local caso venta rapida// add a al pedido
		if(typeof xArrayPedidoObj[xli_tipoconsumo][xli_iditem]==="undefined"){xcant=0}else{
			xcant = parseInt(xArrayPedidoObj[xli_tipoconsumo][xli_iditem].cantidad) || 0;
		}

		if(xval>0){
			//omitir para seguir vendiendo sin stock, da change a despues
			if(xcant<xcant_max){xcant++;}
		}else{
			xcant--;
		}

		if(xcant<=0){xcant=0;element_cant_li_sel.removeClass('cant_fixed_li')}else{
			element_cant_li_sel.addClass('cant_fixed_li')
			delete xArrayPedidoObj[xli_tipoconsumo][xli_iditem]
		}


		xPrecioTotal=parseFloat(parseFloat(xli_precio)*parseFloat(xcant)).toFixed(2);
		xArrayPedidoObj[xli_tipoconsumo][xli_iditem]={'idcategoria':xidcategoria, 'idseccion':$(element_li_add__print).attr('data-idseccion'), 'idseccion_index':$(element_li_add__print).attr('data-idseccionindex'), 'des_seccion':$(element_li_add__print).attr('data-cat'), 'iditem':xli_iditem, 'idtipo_consumo':xli_tipoconsumo, 'cantidad':xcant, 'precio':xli_precio, 'des':xli_des,
			'precio_total': xPrecioTotal, 'precio_total_calc': xPrecioTotal,'precio_print':xPrecioTotal,'indicaciones':xli_des_ref,'iditem2':$(element_li_add__print).attr('data-iditem'), 'idimpresora':xli_idimpresora, 'idprocede':xli_idprocede, 'procede':xli_Procede, 'procede_index':xli_Procede_index, 'imprimir_comanda':ximprmir_comanda, 'cant_descontar':xcant_descontar,'idalmacen_items':xli_idalmacen_items, 'visible':0};

		element_cant_li_sel.text(xcant);;

		if(xcant<=0){xcant=0;element_cant_li_sel.removeClass('cant_fixed_li');delete xArrayPedidoObj[xli_tipoconsumo][xli_iditem]}else{
			element_cant_li_sel.addClass('cant_fixed_li')
		}

		/*event.stopPropagation();
		e.stopPropagation();
		e.stopImmediatePropagation()*/
}

$(document).on('keyup', '.xMiTextReferencia', function(e) {
		xArrayPedidoObj[xidTipoConsumo][xidItem].indicaciones=$(this).val();
		window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))

		// event.stopPropagation();
		e.stopPropagation();
		e.stopImmediatePropagation()
		return false;
	})


function xClassEstadoItem(xCantItem){
	var xClassEstado='';
	var xClassEstadoStock='';
	if(xCantItem=='ND'){xClassEstado='xEstadoVerde';xClassEstadoStock='xFondoColorVerde';}
			else{
				xCantItem=parseInt(xCantItem);
				if(xCantItem>=1){xClassEstado='xEstadoAmarillo';xClassEstadoStock='xFondoColorAmarillo';}
				if(xCantItem>=10){xClassEstado='xEstadoVerde';xClassEstadoStock='xFondoColorVerde';}
				if(xCantItem<=0){xClassEstado='xEstadoRojo';xClassEstadoStock='xFondoColorRojo';}
			}

	return xClassEstado+'|'+xClassEstadoStock
}

function xLoadArrayPedido(){
	xArrayPedidoObj=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));
	if(xArrayPedidoObj!==null){if(xArrayPedidoObj.length>0){return;}}

	var xtpc_t=[];
	xArrayDesTipoConsumo=[];
	xArrayPedidoObj=[];

	xtpc_t=xm_log_get('estructura_pedido'); //$.parseJSON(window.localStorage.getItem("::app3_sys_dta_tct_estructura"));
	for (var i = 0; i < xtpc_t.length; i++) {
		xArrayPedidoObj[xtpc_t[i].idtipo_consumo]={'id':xtpc_t[i].idtipo_consumo, 'des':xtpc_t[i].descripcion, 'titulo':xtpc_t[i].titulo};
		xArrayDesTipoConsumo.push({'id':xtpc_t[i].idtipo_consumo, 'des':xtpc_t[i].descripcion, 'titulo':xtpc_t[i].titulo});
	};
	window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))

	/*>>xm_log_get
	if(xtpc_t==null){
		$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=3'})
		.done( function (DtArray) {
			var xDtArray=$.parseJSON(DtArray);
			xDtArray=xDtArray.datos;
			for (var i = 0; i < xDtArray.length; i++) {
				//xArrayPedido[xDtArray[i].idtipo_consumo]=new Array();
				xArrayPedidoObj[xDtArray[i].idtipo_consumo]={'id':xDtArray[i].idtipo_consumo, 'des':xDtArray[i].descripcion, 'titulo':xDtArray[i].titulo};
				xArrayDesTipoConsumo.push({'id':xDtArray[i].idtipo_consumo, 'des':xDtArray[i].descripcion, 'titulo':xDtArray[i].titulo});
			};
			window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))
			window.localStorage.removeItem("::app3_sys_dta_tct");
			window.localStorage.removeItem("::app3_sys_dta_tct_estructura");
			window.localStorage.setItem("::app3_sys_dta_tct",JSON.stringify(xArrayDesTipoConsumo))
			window.localStorage.setItem("::app3_sys_dta_tct_estructura",JSON.stringify(xArrayDesTipoConsumo))
		})
	}else{
		xArrayDesTipoConsumo=[];
		for (var i = 0; i < xtpc_t.length; i++) {
			//xArrayPedido[xDtArray[i].idtipo_consumo]=new Array();
			/*
			try{xArrayPedido[xtpc_t[i].id]={'id':xtpc_t[i].id, 'des':xtpc_t[i].des, 'titulo':xtpc_t[i].titulo};}
			catch(err){window.localStorage.removeItem("::app3_sys_dta_tct_estructura"); xLoadArrayPedido(); return;}
			*/
			/*xArrayPedidoObj[xtpc_t[i].id]={'id':xtpc_t[i].id, 'des':xtpc_t[i].des, 'titulo':xtpc_t[i].titulo};
			xArrayDesTipoConsumo.push({'id':xtpc_t[i].id, 'des':xtpc_t[i].des, 'titulo':xtpc_t[i].titulo});
		};
		window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))
	}*/
}


//verifica si lo hay items de almacen bodega y segun confg imprime o no
function xVerificarSiSeImprimeComanda(xArray){
	if(xArray==null){xArray=xArrayPedidoObj}
	xVerificarImprimirComanda=false;
	for (var i = 0; i < xArray.length; i++) {
		if(xArray[i]==null){continue;}
		$.map(xArray[i], function(n, z) {
			if (typeof n=="object"){
				if(n.cantidad!=0){
					if(n.imprimir_comanda==1){xVerificarImprimirComanda=true; return;}
				}
			}
		});
	}
}

//para calcular la cantidad a descontar // segun item
function xObtnerValSumArray(xArray,filter){
	var cuenta=0;
	for (var i = 0; i < xArray.length; i++) {
		if(xArray[i]==null){continue;}
		$.map(xArray[i], function(n, z) {
			if (typeof n=="object"){
				var xIdRowTb=n.idseccion;
				if(xIdRowTb === filter){
					cuenta=parseFloat(cuenta)+parseFloat(n.cantidad);
				}
			}
		});
	}
	return cuenta;
}

//obtener la suma del total row segun attr
//subfind = td donde esta el valor
function xObtnerValSumTable(tb,BuscarEn,filter,subfind){
	var cuenta=0;
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb === filter){
			cuenta=parseFloat(cuenta)+parseFloat($(element).find(subfind).text());
		}
    });
    return parseInt(cuenta);
}

//sumar del array // precioprint // para totales
function xSumaCantArray(ArrySum){
	var suma=0;
	for (var i = 0; i < ArrySum.length; i++) {
		if(ArrySum[i] == null){continue;}
		$.map(ArrySum[i], function(n, z) {
			if (typeof n === "object"){
				const _xprecio_unitario = parseFloat(n.precio);
				
				// _total = x.precio_total_calc || x.total;
				// _total = _total.toString().indexOf(',') > -1 ? x.precio_total : _total; // cuando juntan la cuenta
				// _total = parseFloat(_total).toFixed(2);
				
				let _xcantidad = n.cantidad;				
				if (_xcantidad.toString().indexOf(",") > -1) { // caso de que se junte los items
					_xcantidad = _xcantidad.split(',');
					_xcantidad = _xcantidad.reduce((a, b) => parseFloat(a) + parseFloat(b));
				} 
				
				const importe_calculado_unitario = _xcantidad * _xprecio_unitario;

				// let xp_print;
				// if (n.precio_print === '') { xp_print = n.precio_total_calc; } // cuando es igual a vacio viene de una regla de carta
				
				let xp_print = n.precio_print === '' ? parseFloat(n.precio_total_calc) : parseFloat(n.precio_print);
				// xp_print = xp_print === 0 ? _xprecio_unitario : xp_print;
				
				// let xp_print = isNaN(parseFloat(n.precio_print)) ? 0 : parseFloat(n.precio_print);
				// xp_print = xp_print === 0 ? _xprecio_unitario : xp_print;
				
				// si el precio calculado de und * punitario es menor que ptotal quiere decir que viene de desajuntar

				let xprecio_p = xp_print > importe_calculado_unitario ? _xprecio_unitario.toFixed(2) : xp_print.toFixed(2);

				if(xprecio_p === ""){
					// xprecio_p = n.precio_total;
					xprecio_p = n.precio_total_calc; // cambio para calcular las reglas de carta
				}
				suma=suma+parseFloat(xprecio_p)
			}
		})
	}
	return suma;
}


//imprimir otros documentos --- -1 precuenta - -2 factura
//xArrayCuerpo debe tener estructura de mod impresion, (como sub pedido ::app3_sys_dta_pe)
function xMandarImprimirOtroDoc(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xidDoc){
	var xIdPrint=0;
	var xArrayBodyPrint=[];
	var xArrayImpresoras=xm_log_get('app3_woIpPrint'); //JSON.parse(window.localStorage.getItem("::app3_woIpPrint"));
	var xDtTipoDoc=xm_log_get('app3_woIpPrintO');//JSON.parse(window.localStorage.getItem("::app3_woIpPrintO"));
	var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");
	var xIpPrintDoc=xidDoc;
	var xpasePrint=false;

	for (var i = 0; i < xDtTipoDoc.length; i++) {
		//pre-cuenta
		if(xDtTipoDoc[i].idtipo_otro==-1){xIpPrintDoc=xDtTipoDoc[i].idimpresora; break;}
	};
	//si existe impresora local // imprime todos los otros documentos en esta impresora local.
	if(xPrintLocal!=undefined && xPrintLocal!=''){
		xPrintLocal=$.parseJSON(xPrintLocal);
		xArrayDatosPrint[0].ip_print=xPrintLocal.ip;
		xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;
		xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font
		xArrayDatosPrint[0].local = 1;
		xpasePrint=true;
	}else{
		for (var i = 0; i < xArrayImpresoras.length; i++) {
			if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){
				xpasePrint=true;
				xIpPrintDoc=xArrayImpresoras[i].ip; 
				xArrayDatosPrint[0].ip_print=xIpPrintDoc;
				xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;
				xArrayDatosPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;
				xArrayDatosPrint[0].local = 0;
				break;
			}
		}
	}
	if(xpasePrint==false){return false;}
	if(xArrayCuerpo.length==0){return false}

	xArmarSubtotalesArray(xArrayCuerpo,xArrayDatosPrint)
	//xArrayDatosPrint[0].ip_print=xIpPrintDoc;
	xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xLocal_xDtSubTotales,function(rpt_print){
		if(rpt_print==false){return;}
		xPopupLoad.titulo="Imprimiendo...";
		xPopupLoad.xopen();
		setTimeout(function(){ xPopupLoad.xclose()}, 3000);
	});
}

//imprimir otros documentos --- -1 precuenta // - -2 factura // no se usa
//xArrayCuerpo debe tener estructura de mod impresion, (como sub pedido ::app3_sys_dta_pe)
// xArraySubTotales ya esta calculado los subtotales
function xMandarImprimirOtroDoc(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo, xArraySubTotales,xidDoc){
	var xArrayImpresoras=xm_log_get('app3_woIpPrint'); //JSON.parse(window.localStorage.getItem("::app3_woIpPrint"));
	var xDtTipoDoc=xm_log_get('app3_woIpPrintO');//JSON.parse(window.localStorage.getItem("::app3_woIpPrintO"));
	var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");
	var xIpPrintDoc=xidDoc;
	var xpasePrint=false;

	// no es comanda
	xArrayDatosPrint[0].var_size_font_tall_comanda = 0; //default

	for (var i = 0; i < xDtTipoDoc.length; i++) {
		//pre-cuenta
		if (xDtTipoDoc[i].idtipo_otro == xidDoc){xIpPrintDoc=xDtTipoDoc[i].idimpresora; break;}
	};
	//si existe impresora local // imprime todos los otros documentos en esta impresora local.
	if(xPrintLocal!=undefined && xPrintLocal!=''){
		xPrintLocal=$.parseJSON(xPrintLocal);
		xArrayDatosPrint[0].ip_print=xPrintLocal.ip;
		xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;
		xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font
		xArrayDatosPrint[0].local = 1;
		xpasePrint=true;
	}else{
		for (var i = 0; i < xArrayImpresoras.length; i++) {
			if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){
				xpasePrint=true;
				xIpPrintDoc=xArrayImpresoras[i].ip; 
				xArrayDatosPrint[0].ip_print=xIpPrintDoc;
				xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;
				xArrayDatosPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;
				xArrayDatosPrint[0].local = 0;
				break;
			}
		}
	}
	if(xpasePrint==false){return false;}
	if(xArrayCuerpo.length==0){return false}

	// xArmarSubtotalesArray(xArrayCuerpo,xArrayDatosPrint) // ya viene con la funciom
	//xArrayDatosPrint[0].ip_print=xIpPrintDoc;
	xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xArraySubTotales,function(rpt_print){
		if(rpt_print==false){return;}
		xPopupLoad.titulo="Imprimiendo...";
		xPopupLoad.xopen();
		setTimeout(function(){ xPopupLoad.xclose()}, 3000);
	});
}

function xMandarImprimir(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,responde){
	var xArrayImpresoras;
	var xIdPrint=0;
	var xArrayBodyPrint=new Array();
	var xCuentaImpresorasEvaluadas=0;//cuenta las impresoras evaluadas
	var xcuentaSeccionesImpresas=0;//cuenta secciones impresas, sino imprime ninguna manda nuevo x ejemplo en helados.
	var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");
	xArrayImpresoras=xm_log_get('app3_woIpPrint'); //$.parseJSON(window.localStorage.getItem("::app3_woIpPrint"));

	//si existe impresora local // saca una copia de todo el pedido
	if(xPrintLocal!=undefined && xPrintLocal!=''){
		xPrintLocal=$.parseJSON(xPrintLocal);
		xArmarSubtotalesArray(xArrayCuerpo,xArrayDatosPrint)
		xArrayDatosPrint[0].ip_print=xPrintLocal.ip;
		xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;
		xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font;
		xArrayDatosPrint[0].local = 1;

		xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xLocal_xDtSubTotales,function(rpt_print){
			if(rpt_print==false){return;}
			xPopupLoad.titulo="Imprimiendo...";
			xPopupLoad.xopen();
			setTimeout(function(){ xPopupLoad.xclose()}, 3000);
		});
	}

	//evalua impresoras y secciones, despachos o areas, la seccion en que impresora se imprime
	for (var z = 0; z < xArrayImpresoras.length; z++) {
		xIdPrint=xArrayImpresoras[z].idimpresora;
		xArrayBodyPrint=new Array();
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
		xArmarSubtotalesArray(xArrayBodyPrint,xArrayDatosPrint)
		//xArrayDatosPrint[0].ip_print='192.168.1.80';
		xArrayDatosPrint[0].ip_print=xArrayImpresoras[z].ip;
		xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[z].var_margen_iz;
		xArrayDatosPrint[0].var_size_font=xArrayImpresoras[z].var_size_font;
		xArrayDatosPrint[0].local = 0;
		xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayBodyPrint,xLocal_xDtSubTotales,function(rpt_print){
			if(xArrayImpresoras.length==xCuentaImpresorasEvaluadas && rpt_print==false){//si todas las impresoras fueron evaluadas y no presentaron error termina la funcion
				setTimeout( function(){try{xNuevoPedidoMP();}catch(err){return false;}}, 2700); //nuevo pedido en mipedido
				if(responde){responde(true)};
			}
			else{if(responde){responde(false);}}
		});
	};

	if(xcuentaSeccionesImpresas==0){if(responde){responde(true)};}
}

// xArrayEncabezado ==> encabezados( mesa | num_pedido | correlativo_dia | reservado | solo_llevar | precuenta )
// xArrayDatosPrint ==> datos de impresora e impresion ( logo | ip_print | num_copias | pie_pagina | totales[{igv , 18}])
// xArrayCuerpo ==> datos del pedido ( categoria | secciones | items  )
// xArraySubtotal ==> todos los subtotales asignados | totales[{igv , 18}]
function xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xArraySubtotal,responde){
	//cuando se agrega item
	//xArrayEncabezado.solo_llevar=0;
	//xArrayEncabezado.reservar=0;
	xPopupLoad.titulo="Imprimiendo...";
	$.ajax({type: 'POST', url: '../../print/print3.php', 
	data:{
		Array_enca:xArrayEncabezado, Array_print:xArrayDatosPrint, ArrayItem:xArrayCuerpo, ArraySubTotales:xArraySubtotal}})
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
			//setTimeout( function(){ xNuevoPedido(); }, 600);
			//xNuevoPedido()
			//setTimeout( function(){ xNuevoPedido(); }, 1500);

		}
		return responde(xErrorPrint);
	});
}

//Armar subtotales del array a imprimir
// function xArmarSubtotalesArray(xArrayPrint,xDatosPrint){
// 	//sub totales
// 	var xLocal_TotalImporte=0;
// 	xLocal_TotalImporte=xSumaCantArray(xArrayPrint);
// 	//xTotalImporte=xSumaCantRow($("#xli_tb_pedido_ad"),'.ptotal');

// 	xLocal_xDtSubTotales=new Array();
//  	xLocal_xDtSubTotales.push({'descripcion':'Sub Total', 'importe':xMoneda(xLocal_TotalImporte), 'visible':true});

//  	for (var i = 0; i < xDatosPrint.length; i++) {
//  		xdes_sb=xDatosPrint[i].des_detalle;
//  		if(xdes_sb!=''){
//  			xporcentaje_sb=parseFloat(parseFloat(xDatosPrint[i].porcentaje)/100).toFixed(2);
//  			xporcentaje_sb=parseFloat(parseFloat(xLocal_TotalImporte)*parseFloat(xporcentaje_sb)).toFixed(2);
//  			xLocal_xDtSubTotales.push({'descripcion':xMayusculaPrimera(xdes_sb.toLowerCase()), 'importe':xMoneda(xporcentaje_sb), 'visible':true});
//  			xLocal_TotalImporte=parseFloat(xLocal_TotalImporte)+parseFloat(xporcentaje_sb);
//  		}

//  		//adicionales van con seccion ejemplo taper van con seccion para llevar
//  		var xid_tp_consumo_ad=xDatosPrint[i].ad_idtp_consumo;
//  		var xtt_adicional=0;

//  		if(xid_tp_consumo_ad!=''){
//  			xid_ad_seccion=xDatosPrint[i].ad_idseccion;
//  			xid_ad_seccion=xid_ad_seccion.split(',');

//  			var xCant_item_sec=0;//=xArrayPedidoObj[xid_tp_consumo_ad].cantidad;
//  			var u_id_ad_seccion;
//  			for (var q = 0; q < xid_ad_seccion.length; q++){
//  				u_id_ad_seccion=xid_ad_seccion[q];
//  				xCant_item_sec=parseFloat(xCant_item_sec)+xBuscarCantidadPorSeccionArray(xid_tp_consumo_ad,u_id_ad_seccion,xArrayPrint);
//  			};

//  			if(xCant_item_sec==0){continue;}
//  			xtt_adicional=parseInt(xtt_adicional)+(parseFloat(xCant_item_sec)*parseFloat(xDatosPrint[i].ad_importe));
//  			xtt_adicional=xMoneda(xtt_adicional);
//  			//para ver si el check de cobro de este item esta activo
//  			var xvisibleCobro=true;
//  			try{xvisibleCobro=$("#check"+xDatosPrint[i].ad_descripcion)[0].checked;}catch(err){xvisibleCobro=true;}
//  			if(xvisibleCobro==true){
//  				xLocal_xDtSubTotales.push({'descripcion':xMayusculaPrimera(xDatosPrint[i].ad_descripcion.toLowerCase()), 'importe':xtt_adicional, 'visible':true});
//  				xLocal_TotalImporte=parseFloat(xLocal_TotalImporte)+parseFloat(xtt_adicional);
//  			}
//  		}
// 	};

//  	if(xLocal_xDtSubTotales.length==1){xLocal_xDtSubTotales=new Array()}

//  	//xLocal_TotalRowsArrayImporte=parseFloat(xLocal_TotalRowsArrayImporte)+parseFloat(xLocal_TotalImporte);
//  	xLocal_xDtSubTotales.push({'descripcion':'Total', 'importe':xMoneda(xLocal_TotalImporte), 'visible':true});
//  	return xLocal_TotalImporte;
// }

// /// calcula los subtotales desde el total de la mesa
// /// se utiliza en dashboard
// function xArmarSubtotalesFromTotal(itemMesa) {
// 	var xCartaSubtotales=xm_log_get('carta_subtotales'); 
// 	var xResTotal = 0, porcentaje=0,subtotal=0;
// 	var dtDetalle = itemMesa.secciones.split(',');
// 	var tipoConsumo = '', secciones = '', seccionCantidad, arrDt;

// 	arrDt=[];
// 	dtDetalle.map((d,index) => {
// 		var _d = d.split(':');
// 		// arrDt[_d[0]] = arrDt[_d[0]] ? arrDt[_d[0]]={} : arrDt[_d[0]];
// 		arrDt.push({'tipoconsumo':_d[0], 'seccion': _d[1], 'cantidad': _d[2]});
// 		tipoConsumo += _d[0]+',';
// 		secciones += _d[1]+',';
// 	});

// 	xSumTotalPorcentaje = 0;
// 	xCartaSubtotales
// 		.filter(c => c.tipo==='p')
// 		.map(c => {
// 			porcentaje=parseFloat(parseFloat(c.monto)/100).toFixed(2);		
// 			porcentaje=parseFloat(parseFloat(itemMesa.importe)*parseFloat(porcentaje)).toFixed(2)
// 			xSumTotalPorcentaje += parseFloat(porcentaje);
// 		})

	
// 	xSumCantImporte = 0
// 	xCartaSubtotales
// 		.filter(c => c.tipo==='a')		
// 		.map(c => {
// 			arrDt
// 				.filter (i => i.tipoconsumo === c.idtipo_consumo)
// 				.filter (i => i.seccion === c.idseccion)
// 				.map(i => {
// 					xSumCantImporte=parseFloat(xSumCantImporte) + (parseFloat((i.cantidad) * parseFloat(c.monto)));
// 				})
// 		})

// 	// retorna el importe total + subtotales(igv,servio,taper)
// 	xSumCantImporte += parseFloat(xSumTotalPorcentaje);
// 	return parseFloat(itemMesa.importe) + parseFloat(xSumCantImporte); 
	
// }

function xBuscarCantidadPorSeccionArray(idtpc_ad,idseccion_ad,xArraySumT){
	var xcant_sec_ad=0;
	if(xArraySumT[idtpc_ad]==undefined){return xcant_sec_ad;}
	$.map(xArraySumT[idtpc_ad], function(n,z){
 		if (typeof n=="object" && n!=null){
 			if(n.idseccion == idseccion_ad){
 				xcant_sec_ad=parseFloat(xcant_sec_ad)+parseFloat(n.cantidad);
 			}
 		}
 	});
 	return xcant_sec_ad;
}


function xCargarCategoriaActual(responde){
	//$(".xMenu_body #xContenedoPaginas").html('<div class="xCentradoVerticalHorizontal spinner"><paper-spinner active></paper-spinner></div>').trigger('create');
	var xCategoriaActual;
	//$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=302'})
	//.done( function (dtCat) {
		var xdtCat=xm_log_get('categorias'); //$.parseJSON(dtCat);
		var xCadenaCartegoria='';

		for (var i = 0; i < xdtCat.length; i++) {
			xMenuCategoria={'id':xdtCat[i].idcategoria, 'des':xdtCat[i].descripcion};
		}
		//window.localStorage.setItem("::app3_sys_dt_mlc",JSON.stringify(xMenuCategoria));
		if(xdtCat.length==1){xCategoriaActual=xdtCat[0].idcategoria}
		return responde(xdtCat);
	//})
}


//>>xm_log_get;
/*function xLoadDtPrint(){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=307'})
	.done( function (DtPrint) {
		var xDtPrint=$.parseJSON(DtPrint);
		xDtPrint=xDtPrint.datos;
		window.localStorage.setItem("::app3_sys_dta_prt",JSON.stringify(xDtPrint))
	});
}

function xLoadRegla(){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=306', data:{i:xidCategoria}})
	.done( function (DtR) {
		var xDtR=$.parseJSON(DtR);
		xDtR=xDtR.datos;
		window.localStorage.setItem("::app3_sys_dta_rec",JSON.stringify(xDtR))
	})
}*/

//load general para item del la carta
// carta lista y boodega
//para venta_rapida y mipedido se actualiza cada nuevo pedido o cada 60segundos de inactividad
//para control_pedido cada que lo solicite
// xidCategoria  obligatorio en venta rapida y control de pedidos 
function xGeneralLoadItems(xidCategoria, x_rpt){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=205', data:{'idcategoria': xidCategoria}})
	.done( function (dtCarta) {
		var xdt_rpt=$.parseJSON(dtCarta)
		// if(!xdt_rpt.success){alert(xdt_rpt.error); return x_rpt(false);}
		xGeneralDataCarta=xdt_rpt.datos;
		if(x_rpt){return x_rpt(xGeneralDataCarta);}
	})
}

//mi pedido // solo secciones
function xGeneralSeccionMiPedido(xidCategoria, x_rpt){	
	const ultima_categoria_cargada = localStorage.getItem('::app3_sys_last_cat_load');
	if (ultima_categoria_cargada === xidCategoria && xGeneralDataSeccion !== undefined ) {return x_rpt(false); } //1118 //si ya esta cargado, pasa, tiene que actualizar manual // remplaza al de abajo
	// if(xGeneralDataSeccion!=undefined){if(x_rpt){return x_rpt(false);}else{return;}}//si ya esta cargado, pasa, tiene que actualizar manual
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=2041', data:{'idcategoria': xidCategoria}})
	.done( function (dtSecciones_mp) {
		var xdtSecciones_mp=$.parseJSON(dtSecciones_mp)
		if(!xdtSecciones_mp.success){alert(xdtSecciones_mp.error); return;}
		xGeneralDataSeccion=xdtSecciones_mp.datos;

		localStorage.setItem('::app3_sys_last_cat_load', xidCategoria);

		if(x_rpt){return x_rpt(xGeneralDataSeccion);}
	})
}


//dispara evento de actualizacion aumento de registtro en pedidos ca,bio bd
function xDisparaEventoLoadItemCambioBd(){
	clearInterval(xGeneralRelojUpdateItemsCambioBd);
	xGeneralRelojUpdateItemsCambioBd=setInterval(function () {xGeneralActualizarLoadItemCambioBd()},20000);
}

function xGeneralActualizarLoadItemCambioBd(){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=3012'})
	.done( function (xCantPedidos) {
		xNumPedidosBD=xCantPedidos;
		if(xGeneralNumPedidosActual!=xNumPedidosBD){
			xGeneralNumPedidosActual=1
			xGeneralLoadItems();
		}
	})
}

//dispara evento por inactividad
function xDisparaEventoLoadItemInactividad(){
	clearInterval(xGeneralRelojUpdateItemsSolo);
	xGeneralRelojUpdateItemsSolo=setInterval(function () {xGeneralActualizaItemInactividad()},110000);
}
//para venta_rapida y mipedido se actualiza cada nuevo pedido o cada 60segundos de inactividad
function xGeneralActualizaItemInactividad(){
	var xpaseRefreshItem=true;
	if(xArrayPedidoObj!=undefined){
		for (var y = 0; y < xArrayPedidoObj.length; y++) {
			if(xArrayPedidoObj[y]==null){continue;}
			$.map(xArrayPedidoObj[y], function(n, z) {
				if (typeof n=="object"){
					xpaseRefreshItem=false;
				}
			})
			if(!xpaseRefreshItem){break;}
		}
	}

	if(xpaseRefreshItem){
		if(xDisparaUpdateItems.cancelBubble==true){xDisparaUpdateItems=new Event('GeneralUpdateItemsSolo');}
		document.dispatchEvent(xDisparaUpdateItems);
	}
}

// 'precio_total_calc': para calcular en regalas de carta
function xGeneralValidarRegalasCarta(xObjEvaluar,esarray){
	var xArrayRegla=xm_log_get('reglas_de_carta'); //$.parseJSON(window.localStorage.getItem("::app3_sys_dta_rec"));
	var xSecc_bus='';
	var xSecc_detalle;
	var xCantidadBuscar=0;
	xVerificarImprimirComanda=false;

	//resete a precio_print all
	if(xArrayPedidoObj!=undefined){
		for (var y = 0; y < xArrayPedidoObj.length; y++) {
			if(xArrayPedidoObj[y]==null){continue;}
			$.map(xArrayPedidoObj[y], function(n, z) {
				if (typeof n=="object"){
					n.precio_print='';
					n.precio_total_calc = n.precio_total; // para calcular las reglas de la carta cuando es array reseteamos precio_total_calc
					// n.precio_total = n.precio * n.cantidad;
					// n.precio = xMoneda(n.precio_total);
				}
			})
		}
	}

	//xVerificarImprimirComanda=false;
	if( !esarray ) {//establa
		var xtb=$(xObjEvaluar);
		for (var i = 0; i < xArrayRegla.length; i++) {
		//if(xSecc_bus!=xArrayRegla[i].idseccion){
			xSecc_bus=xArrayRegla[i].idseccion;
			xSecc_detalle=xArrayRegla[i].idseccion_detalle;
			//buscar
			xCantidadBuscar=xObtnerValSumTable($(xtb),'data-idbus',xSecc_bus,'#cant_descontar');

			if (xCantidadBuscar === 0 ) continue; // no hay nada que buscar

			xCantidadBuscarSecc_detalle=xObtnerValSumTable($(xtb),'data-idbus',xSecc_detalle,'#cant_descontar');

			var diferencia = xCantidadBuscar - xCantidadBuscarSecc_detalle;			
			diferencia = diferencia < 0 ? xCantidadBuscar : diferencia; // no valores negativos 
			
			$(xtb).find(".row").each(function (index, element) {
				// if(xCantidadBuscar <= 0) {return;} // lo dejamos pasar si es cero para que coloque el precio normal a todos los items de lo contrario dejaria con precios del caso anterior
				var xIdRowTb = $(element).attr('data-idbus'),
				xIdtb_Item = $(element).attr('data-id'),
				xIdtb_tpc = $(element).attr('data-idtipocobus'),
				xPrecio_mostrado = parseFloat($(element).find('#ptotal').text()),
				xPrecio_item_bus = xMoneda(xPrecio_mostrado);

				if (xPrecio_mostrado === 0) return; // si es 0 quiere decir que ya fue descontado, continua con el siguiente

				// xPrecio_item_bus = xMoneda(xPrecio_item_bus);
				// xCant_item_bus,
				// xPreciott_item_bus=$(element).find('#ptotal').text();

				if (xIdRowTb === xSecc_detalle) {

					const cant_item = parseInt($(element).find('#cant_descontar').text());

					if ( xCantidadBuscar >= xCantidadBuscarSecc_detalle) {
						xPrecio_item_bus='0.00';
					} else if ( diferencia > 0 ){
						// const cant_item = parseInt($(element).find('#cant_descontar').text());
						const precioUnitario_item=parseFloat($(element).find('#punitario').text());

						// xPrecio_item_bus = parseFloat(parseFloat(cant_item * precioUnitario_item)-(diferencia * precioUnitario_item));
						xPrecio_item_bus = parseFloat(parseFloat(diferencia * precioUnitario_item));
						xPrecio_item_bus = xPrecio_mostrado - xPrecio_item_bus; // descuenta del precio que se muestra en pantalla( precio que ya fue procesado)
						xPrecio_item_bus = xPrecio_item_bus < 0 ? '0.00' : xMoneda(xPrecio_item_bus);
						
						diferencia = diferencia - cant_item < 0 ? 0 : diferencia - cant_item;													
					}

					$(element).find('#ptotal').text(xPrecio_item_bus);
					// $(element).attr('cant_descontado',xCant_item_bus)
					$(element).attr('cant_descontado', cant_item);

					//coloca en array precio a imprimir
					//xDtPedido[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;
					xArrayPedidoObj[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;					

				}
			})		
		}
	}
	else{//es array
		var xSqlTbSave='';
		var xArrayEv=xObjEvaluar;
		for (xi in xArrayRegla) {
			//if(xSecc_bus!=xArrayRegla[i].idseccion){
				xSecc_bus=xArrayRegla[xi].idseccion;
				xSecc_detalle=xArrayRegla[xi].idseccion_detalle;
				//buscar
				xCantidadBuscar=xObtnerValSumArray(xArrayEv,xSecc_bus);
				xCantidadBuscarSecc_detalle=xObtnerValSumArray(xArrayEv,xSecc_detalle);

				var diferencia = xCantidadBuscar - xCantidadBuscarSecc_detalle;			
				diferencia = diferencia < 0 ? xCantidadBuscar : diferencia; // no valores negativos 

				for (var y = 0; y < xArrayEv.length; y++) {
					if(xArrayEv[y] == null){continue;}
					if(xCantidadBuscar <= 0){break;}
					$.map(xArrayEv[y], function(n, z) {
						if (typeof n ==="object"){
							var xIdRowTb=n.idseccion;
							var xIdtb_Item=n.iditem;
							var xIdtb_tpc=n.idtipo_consumo;
							var xPrecio_mostrado = parseFloat(n.precio_total_calc);
							var xPrecio_item_bus = xMoneda(xPrecio_mostrado);

							if (xPrecio_mostrado === 0) return; // si es 0 quiere decir que ya fue descontado, continua con el siguiente
							
							if(xIdRowTb === xSecc_detalle){

								const cant_item = n.cantidad;
								if ( xCantidadBuscar >= xCantidadBuscarSecc_detalle) {
									xPrecio_item_bus='0.00';
								} else if ( diferencia > 0 ){
									// const cant_item = n.cantidad;
									const precioUnitario_item=n.precio;
									
									// xPrecio_item_bus=parseFloat(parseFloat(cant_item * precioUnitario_item)-(diferencia * precioUnitario_item));
									xPrecio_item_bus = parseFloat(parseFloat(diferencia * precioUnitario_item));
									xPrecio_item_bus = xPrecio_mostrado - xPrecio_item_bus; // descuenta del precio que se muestra en pantalla( precio que ya fue procesado)
									xPrecio_item_bus = xPrecio_item_bus < 0 ? '0.00' : xMoneda(xPrecio_item_bus);
									
									diferencia = diferencia - cant_item < 0 ? 0 : diferencia - cant_item;						
								}

								
								// n.precio_total = xPrecio_item_bus; // es el que se muestra para calcular la regla de carta
								n.precio_total_calc = xPrecio_item_bus; //
								n.precio_print = xPrecio_item_bus; //
								n.cant_descontado = cant_item;

								///coloca en array precio a imprimir
								xArrayEv[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;
								xArrayPedidoObj[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;

							}
						}
					});
				}
		};

		return xArrayEv;
	}
	//xGeneralSumarTotales();
}

// se utliza mayormente para cuande se hace nuevo pedido
// devuelve los subtotates desde
// xArraySum => estructura de impresion {tipo consumo > seccion > items}
function xGeneralSumarTotales(xArraySum){
	var xArrayFucRpt=new Array();// array de respuesta
	var xGeSumTotal=0;
	var xprecio_item_sum=0;
	// estructura de pedido si null toma xArrayPedidoObj=el array en storage del ultimo pedido que se esta trabajando. 
	if(xArraySum==null){xArraySum=xArrayPedidoObj}

	// sub total del pedido
	for (var a = 0; a < xArraySum.length; a++) {
		if(xArraySum[a]==null){continue;}
		$.map(xArraySum[a], function(n, z) {
			if (typeof n=="object"){
				//total del pedido
				if(n.precio_print==""){xprecio_item_sum=n.precio_total}else{xprecio_item_sum=n.precio_print;}
				xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xprecio_item_sum);
				///
			}
		})
	}

	//sub totales / igv / servicio / adicionales/ etc
	// xDtPrint=> datos de impresion = impresora, ip, slogan etc
	var xDtPrint=xm_log_get('sede_generales'); //$.parseJSON(window.localStorage.getItem("::app3_sys_dta_prt"));
	var xdes_sb='';
 	var xporcentaje_sb;
 	var xCadenta_tt='';
 	var xCadena_tt_ad='';
 	var xtt_adicional=0;
 	var xid_tp_consumo_ad;
 	var xid_ad_seccion;

	xGeneralArraySubTotales=new Array();
	xGeneralArraySubTotales.push({'descripcion':'Sub Total', 'importe':xMoneda(xGeSumTotal), 'visible':true});

	for (var i = 0; i < xDtPrint.length; i++) {
		xdes_sb=xDtPrint[i].des_detalle;
	 	if(xdes_sb!=''){
			xporcentaje_sb=parseFloat(parseFloat(xDtPrint[i].porcentaje)/100).toFixed(2);
	 		xporcentaje_sb=parseFloat(parseFloat(xGeSumTotal)*parseFloat(xporcentaje_sb)).toFixed(2);
			xCadenta_tt=String(xCadenta_tt+'<tr class="row"><td data-ColumName="descripcion" class="xPedidoSubTotal">'+xdes_sb+'</td><td data-ColumName="importe" align="right">'+xporcentaje_sb+'</td><td class="xInvisible" data-ColumName="estado">0</td><td class="xInvisible" data-ColumName="idpedido">?p</td></tr>');

			xGeneralArraySubTotales.push({'descripcion':xMayusculaPrimera(xdes_sb.toLowerCase()), 'importe':xMoneda(xporcentaje_sb), 'visible':true});
	 		xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xporcentaje_sb);
	 	}

 		//adicionales van con seccion ejemplo taper van con seccion para llevar
 		xid_tp_consumo_ad=xDtPrint[i].ad_idtp_consumo;

 		if(xid_tp_consumo_ad!=''){
 			xid_ad_seccion=xDtPrint[i].ad_idseccion;
 			xid_ad_seccion=xid_ad_seccion.split(',');

 			var xCant_item_sec=0;//=xArrayPedidoObj[xid_tp_consumo_ad].cantidad;
 			var u_id_ad_seccion;
 			for (var q = 0; q < xid_ad_seccion.length; q++){
 				u_id_ad_seccion=xid_ad_seccion[q];
 				xCant_item_sec=parseFloat(xCant_item_sec)+xBuscarCantidadPorSeccion(xid_tp_consumo_ad,u_id_ad_seccion);
 			};

 			if(xCant_item_sec==0){continue;}
 			xtt_adicional=parseInt(xtt_adicional)+(parseFloat(xCant_item_sec)*parseFloat(xDtPrint[i].ad_importe));
 			xtt_adicional=xMoneda(xtt_adicional);
 			xCadena_tt_ad=String(xCadena_tt_ad+'<tr class="row"><td><div class="xPedidoSubTotal" data-iddes="'+xGeneralArraySubTotales.length+'" data-importe="'+xtt_adicional+'"><paper-checkbox class="noselect" onchange="xNoCobrarSubTotal(this);" checked title="no cobrar" id="check'+xDtPrint[i].ad_descripcion+'">'+xDtPrint[i].ad_descripcion+'</paper-checkbox></div></td><td class="xInvisible" data-ColumName="descripcion">'+xDtPrint[i].ad_descripcion+'</td><td data-ColumName="importe" align="right">'+xtt_adicional+'</td><td class="xInvisible" data-ColumName="estado" id="td_estado_subt">0</td></tr>');
 			xGeneralArraySubTotales.push({'descripcion':xMayusculaPrimera(xDtPrint[i].ad_descripcion.toLowerCase()), 'importe':xtt_adicional, 'visible':true});

 			xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xtt_adicional);
 		}
	}

	if(xGeneralArraySubTotales.length==1){xGeneralArraySubTotales=new Array()}
 	xGeneralArraySubTotales.push({'descripcion':'Total', 'importe':xMoneda(xGeSumTotal), 'visible':true});
 	xCadenta_tt=xCadenta_tt+xCadena_tt_ad+'<tr class="row xBold"><td class="xInvisible" data-ColumName="descripcion">TOTAL</td><td class="xInvisible" data-ColumName="importe" id="td_total_subt_2">'+xMoneda(xGeSumTotal)+'</td><td colspan="2" align="right" class="xPedidoTotal xSinBorde"><h3 class="xBold" id="td_total_subt">'+xMoneda(xGeSumTotal)+'</h3></td><td class="xInvisible" data-ColumName="estado">0</td></tr>';

 	xArrayFucRpt.push({'ImporteTotal':xGeSumTotal,'CadenaRow':xCadenta_tt});
 	//callback(xArrayFucRpt);
 	return xArrayFucRpt;
 	//$("#xtb_pedido_subtotales").html(xCadenta_tt).trigger('create');
}

//para mostrar cuenta en mipedido, imprimir precuenta o comprobante
//se hace de esta forma porque puede que no cobre taper o adicionales check
function xGeneralArmarSubTotalesBD(xnummesa_bus,responde){
	var xcadena_tt='';
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=2052',data:{m:xnummesa_bus}})
	.done( function (dtsub) {
		var xdtSub=$.parseJSON(dtsub);
		if(!xdtSub.success){alert(xdtSub.error); return;}
		xdtSub=xdtSub.datos;

		var count_row=xdtSub.length;
		var xtotal_sub_res=0;
		var xtotal_sub=0;
		xGeneralArraySubTotales=new Array()
		for (var i = 0; i < xdtSub.length; i++) {
			if(i==0){xtotal_sub=xdtSub[i].importe;xtotal_sub_res=xtotal_sub;}
			if(count_row>1 && i>0){//subtotal
				xtotal_sub_res=xtotal_sub_res-xdtSub[i].importe;
				xGeneralArraySubTotales[i]={'descripcion':xdtSub[i].descripcion, 'importe':xMoneda(xdtSub[i].importe), 'visible':true};
			}
		}
		if(count_row>1){
			xGeneralArraySubTotales[0]={'descripcion':'Sub Total', 'importe':xMoneda(xtotal_sub_res), 'visible':true};
			xGeneralArraySubTotales[count_row]={'descripcion':'Total', 'importe':xMoneda(xtotal_sub), 'visible':true};
		}else{
			xGeneralArraySubTotales[0]={'descripcion':'Total', 'importe':xMoneda(xtotal_sub), 'visible':true};
		}

		for (var a = 0; a < xGeneralArraySubTotales.length-1; a++) {
			xcadena_tt=xcadena_tt+'<tr class="row"><td>'+xGeneralArraySubTotales[a].descripcion+'</td><td align="right">'+xGeneralArraySubTotales[a].importe+'</td></tr>';
		}
		xcadena_tt=xcadena_tt+'<tr class="row xBold"><td></td><td align="right"><h3 class="xBold">'+xMoneda(xtotal_sub)+'</h3></td></tr>';

		if(responde){return responde(xcadena_tt);}
	})
}
//en array pedidos // para suma total
function xBuscarCantidadPorSeccion(idtpc_ad,idseccion_ad){
	var xcant_sec_ad=0;
	$.map(xArrayPedidoObj[idtpc_ad], function(n,z){
 		if (typeof n=="object" && n!=null){
 			if(n.idseccion == idseccion_ad){
 				xcant_sec_ad=parseFloat(xcant_sec_ad)+parseFloat(n.cantidad);
 			}
 		}
 	});
 	return xcant_sec_ad;
}

function xNoCobrarSubTotal(obj){
	var xid_ad_sec_restar=$(obj).parent().attr('data-iddes');
	var ximporte_ad_sec_restar=$(obj).parent().attr('data-importe');
	var ximpp_tt=$("#td_total_subt").text();
	var xestado_ad_item=0;
	xGeneralArraySubTotales[xid_ad_sec_restar].visible=obj.checked;
	if(obj.checked==false){xestado_ad_item=1;obj.title="cobrar"; $(obj).addClass('check_red');ximpp_tt=xMoneda(parseFloat(ximpp_tt)-parseFloat(ximporte_ad_sec_restar))}else{obj.title="no cobrar"; $(obj).removeClass('check_red');ximpp_tt=xMoneda(parseFloat(ximpp_tt)+parseFloat(ximporte_ad_sec_restar));}
	$(obj).parents('tr').find('#td_estado_subt').text(xestado_ad_item);
	$("#td_total_subt").text(ximpp_tt);
	$("#td_total_subt_2").text(ximpp_tt);
	xGeneralArraySubTotales[xGeneralArraySubTotales.length-1].importe=ximpp_tt;
}

//descuenta stock al vender o aumenta al anular o borrar
//op=operacion + o -
function xArmarArrayDescontarStock(obj_row,op){
	var xarray_rpt=new Array();// array respuesta
	var xrpt_row='';
	var xrpt_row_importe='';
	var xid_row_descontar=$(obj_row).attr('data-iddescontar');
	var x_row_tabla_procede=$(obj_row).attr('data-procede');
	var x_row_cant_descontar=$(obj_row).attr('data-cant_descontar');
	var x_row_cant=parseInt($(obj_row).find(".xcant_li").text());
	var x_row_cant_array=x_row_cant;


	if(isNaN(x_row_cant)){x_row_cant=parseFloat($(obj_row).find('#td_cant').text());}
	if(!isNaN(parseInt(x_row_tabla_procede))){x_row_tabla_procede=$(obj_row).attr('data-descontar');}
	if(op=='+'){x_row_cant=1}//borra item de uno en uno

	var x_id_p=$(obj_row).attr('data-idpedido');
	var x_id_pd=$(obj_row).attr('data-idpedidodetalle');
	var x_row_importe=parseFloat($(obj_row).attr('data-punitario'));


	x_row_cant_descontar=x_row_cant_descontar.split(',');
	xid_row_descontar=xid_row_descontar.split(',');

	//importe en caso de borrar item
	if(x_id_pd!=undefined){
		//x_row_cant_array--;
		//xArrayPedido[x_row_tpc][x_row_cl].cantidad=x_row_cant_array;
		//if(x_row_cant_array<=0){xArrayPedido[x_row_tpc][x_row_cl].visible=1}
		//x_row_tabla_procede=$(obj_row).attr('data-descontar');
		xsql1="update pedido_detalle set cantidad=cantidad-1, estado=if((cantidad)<=0,1,0), ptotal=format(ptotal-"+x_row_importe+",2) where idpedido_detalle="+x_id_pd+"; \r";
		xsql2="update pedido set total=format(total-"+x_row_importe+",2),estado=if((total)<=0,3,0) where idpedido="+x_id_p+"; \r update pedido_subtotales set importe=format(importe-"+x_row_importe+",2) where idpedido="+x_id_p+" and descripcion='TOTAL'; \r";
		xrpt_row_importe=String(xsql1+' '+xsql2)

	}
	//stock descuento o aumento
	for(xi in xid_row_descontar){
		xid_des=xid_row_descontar[xi];
		//if(x_row_tabla_procede!='porcion'){x_row_cant=1;x_row_cant_descontar[xi]=1;}// si se borra se borra de 1
		xcant_des=x_row_cant;
		if(x_row_tabla_procede=='porcion'){xcant_des=parseFloat(x_row_cant_descontar[xi])*parseFloat(x_row_cant);}// si se borra se borra de 1

		//xcant_des=parseFloat(x_row_cant_descontar[xi])*parseFloat(x_row_cant);

		xcampo_procede="stock=stock"+op+xcant_des;
		if(x_row_tabla_procede=='carta_lista'){xcampo_procede="cantidad=if(cantidad='ND','ND',cantidad"+op+xcant_des+")"}
		xrpt_row=xrpt_row+" update "+x_row_tabla_procede+" set "+xcampo_procede+" where id"+x_row_tabla_procede+"="+xid_des+";\r";
	}


	xarray_rpt.push({'stock':xrpt_row,'importe':xrpt_row_importe})
	return xarray_rpt;
}

//el detalle de los item en mipedido
function xArmarTipoConsumo(){
	var xcadenaTC='';
	xArrayDesTipoConsumo=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));
	for(a in xArrayDesTipoConsumo){
		if(xArrayDesTipoConsumo[a]==null){continue;}
		xcadenaTC=String(xcadenaTC+'<div class="xpedir_row" data-id="'+xArrayDesTipoConsumo[a].id+'">'+
							'<p>'+xArrayDesTipoConsumo[a].des+'</p>'+
							'<p class="xCant_item"></p>'+
							'<div class="xBtnIz xBtn">-</div>'+
							'<div class="xBtnDe xBtn">+</div>'+
						'</div>');
	}
	xcadenaTC='<div class="xpedir_item">'+xcadenaTC+'</div>';
	return xcadenaTC;
}
