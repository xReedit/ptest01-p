var xIdOrg;
var xIdSede;
var xNomU;
var xPopupLoad;

// window.onload = function(){setTimeout( function(){ xIniControlPedido(); }, 600); };

document.addEventListener("DOMContentLoaded", function componentsReady() {
	xIniControlPedido();
});

function xIniControlPedido(){
	xVerificarSession();
	//setInterval(function(){ xVerificarSession(); }, 5000); // constantemente actualiza

	xPopupLoad=document.getElementById('xLoad');
	xm_LogChequea(function(){
		xm_log_get('ini_us');
		var xDatos_p=xm_log_get('sede_generales');//$.parseJSON(window.localStorage.getItem("::app3_sys_dta_prt"));
		$("#en_nom_sede").text(xDatos_p[0].des_sede);
		$("#en_nom_ciudad").text(xDatos_p[0].ciudad);
		$("#en_nom_us").text(xNomUsario);

		xLoadTipoConsumoX();
		// setTimeout(() => {
		// 	xOpenPage(2, '?f1=1?df1=LOCAL');
		// }, 2500);

		xOpenPage(2, "?f1=1?df1=LOCAL");
		
	})
	//if(xIdUsuario==''){	xIdUsuario=window.localStorage.getItem('::app3_woU');}


	/*xLoadTipoConsumoX();
	xOpenPage(2,'?f1=1?df1=LOCAL')
	var xDatos_p=xm_LogIni('sede_generales');//$.parseJSON(window.localStorage.getItem("::app3_sys_dta_prt"));
	$("#en_nom_sede").text(xDatos_p[0].des_sede);
	$("#en_nom_us").text(window.localStorage.getItem('::app3_woNus').toLowerCase());*/
}
function xOpenPage(xop, parametro){
	if(parametro==null){parametro='';}
	var xruta='';
	switch(xop){
		case -1:xruta='/prueba';break;
		case 0:	xruta='/categoria';break;
		case 1:	xruta='/menu';break;
		case 2:	xruta='/c_pedido';break;
		case 3:	xruta='/caja';break;
		case 4:	document.location.href='m_panel.html';return;break;
		case 5:
			h = window.screen.availHeight-100;
			var myWindow = window.open('m_menu.html', "Carta", "width=400,height="+h);	return;break;
		case 6:	xruta='/pedido_detalle';break;
		case 7:	xruta='/monitor_pedidos';break;
		case 8:	xruta='/historial_ventas';break;
		case 22:xruta='/resumen_caja';break;
	}
	xruta=xruta+parametro
	// setTimeout( function(){
	// 	if (router != undefined) {
	// 		router.go(xruta); 
	// 	} else {
	// 		setTimeout(() => {
	// 			router.go(xruta); 
	// 		}, 4000);
	// 	}
	// }, 50);

	// setTimeout(() => {
	// 	router.go(xruta);
	// }, 2000);


	router.go(xruta);
}
function xLoadTipoConsumoX(){
	//$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=3'})
	//.done( function (dtTPC) {
		//var xdtTPC=$.parseJSON(dtTPC);
		var xdtTPC=xm_log_get('estructura_pedido'); //xdtTPC.datos;
		var xCadenaTPC='';
		var xDesLi='';
		var xIdTpc
		var xidfiltro='';
		for (var i = 0; i < xdtTPC.length; i++) {
			if(xdtTPC[i]==null){continue;}
			xDesLi=xdtTPC[i].titulo;
			xIdTpc=xdtTPC[i].idtipo_consumo;
			xidfiltro='.tpc'+xIdTpc;
			if(xDesLi==''){xDesLi=xdtTPC[i].descripcion;}
			if(i==0){xOpenPage(2,'?f1='+xIdTpc+'?df1='+xDesLi)}
			xCadenaTPC=xCadenaTPC+String('<li onclick="xFiltroPedidos(this);" class="li_row m_pedidos" data-id="'+xIdTpc+'" id="DesTPC'+xIdTpc+'">'+
		    			'<div>'+
		    				'<span class="titulo">'+xDesLi+'</span>'+
		    				//'<span class="xIndicadorCant"></span>'+
		    			'</div>'+
		    		'</li>');

		};
		$("#list_menu_lateral .li_row").remove();
		$("#list_menu_lateral li:first-child").after(xCadenaTPC).trigger('create');
	//});
}
