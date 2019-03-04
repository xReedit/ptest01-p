let ListDocs = [], ListEstadistica = [], IntervalClearCola = null, IntervalLoadCola, ultimoId = '', ultimoIdData, valRows='', xsourceEventCola;

$(document).ready(function() {
	ultimoId='';
	setTimeout(() => {
		$("body").addClass("loaded");
		// xInitPrintServer();
	}, 1000);		
	
	xVerificarColaImpresion();
});

function xVerificarColaImpresion(){
	if(typeof(EventSource) !== "undefined") {
		xsourceEventCola = new EventSource('../bdphp/log_003.php?op=201');
		xsourceEventCola.onmessage = function(event) {
			valRows = event.data === "" ? valRows : event.data;
			if (valRows !== ultimoId) {
				ultimoIdData = event.data;
				xInitPrintServer();
			}
	        // if(event.data!==xValCountPedidos){xValCountPedidos=event.data;xActualizarItems();}
	    };
	}
}

function xInitPrintServer() {
	// const _ultimoId = ListDocs.length === 0 ? '' : ultimoId;
	$.ajax({
		url: '../bdphp/log_003.php?op=2',
		type: 'POST',	
		data: { ultimoId: ultimoId }
	})
	.done((res) => {
		const _res = $.parseJSON(res);
		let _ListDocumentos = _res.datos;

		/// agregar a la lista
		let row = ListDocs.length;

		_ListDocumentos.map((x)=>{
			ListDocs.push(x);		
			ListEstadistica.push(x);
		});

		xGenerarGrafico();


		let cadena_tr = '';				
		_ListDocumentos.map((x, index) => {			
			const id = x.idprint_server_detalle;			
			row++;
			cadena_tr += '<tr id="tr' + id +'">'+
				'<td>'+ row +'</td>'+
				'<td>' + x.hora + '</td>' +
				'<td>' + x.descripcion_doc + '</td>' +
				'<td id="td_estado' + id +'">Pendiente</td>' +
			'</tr>';
		});

		$("#listDoc").append(cadena_tr).trigger('create');

		ultimoId = ultimoIdData;
		xSendPrint();
	});	  
}

function xSendPrint() {
	// const _listSend = ListDocs.map((x)=> {
	ListDocs.map((x, index)=> {
		if (x.impreso===1) return;
		const _id = x.idprint_server_detalle;
		const _detalle_json = JSON.parse(x.detalle_json);
		const _nomUs = x.nomUs.split(' ')[0]; // -> viene de session		
		const _listSend = { data: _detalle_json, nom_documento: x.nom_documento, nomUs:_nomUs, hora: x.hora };
		x.impreso=1;
		// return { data: _detalle_json, nom_documento: x.nom_documento, nomUs: _nomUs };

		$.ajax({
			url: 'http://192.168.1.64/restobar/print/client/pruebas.print_url.php',
			type: 'POST',
			data: { arrData: _listSend }
		})
		.done((res) => {
			xUpdateEstado(_id);
		});	
		
	});
	
}

function xUpdateEstado(_id) {
	// const _id = ListDocs[_index].idprint_server_detalle;	
	$.ajax({
		url: '../bdphp/log_003.php?op=3',
		type: 'POST',
		data: { id: _id}
	})
	.done( ()=> {
		const nomTd = "#td_estado" + _id;
		$(nomTd).text('Impreso');
		$(nomTd).addClass('xVerde');

		if (IntervalClearCola===null) {
			IntervalClearCola = setInterval(xClearCola, 7000);
		}
	});
}

function xClearCola() {
	if (ListDocs.length===0) {
		clearInterval(IntervalClearCola);
		IntervalClearCola=null;
		return;}	
	
	let paso=false;
	ListDocs.map((x, index) => {
		if (x.impreso === 1 && !paso) {
			const nomTr = "#tr" + x.idprint_server_detalle;
			$(nomTr).fadeOut("slow", ()=>{
				$(this).remove();
				ListDocs.splice(index,1);
			});			
			paso=true;
		}
	})
}


function xUpdateEstructuras() {
	$.ajax({
		url: '../bdphp/log_003.php?op=5',
		type: 'POST',
	})
	.done((res) => {
		const logo = res;
		$.ajax({
			url: '../bdphp/log_003.php?op=4',
			type: 'POST',		
		})
		.done((res) => {
			const _res = $.parseJSON(res);		
			let listEstructuras = _res.datos;
	
			$.ajax({
				url: 'http://192.168.1.64/restobar/print/client/comprobar_estructura.php',
				type: 'POST',
				data: { arrEstructura: listEstructuras, logo: logo}
			})
			.done((res) => {
				// console.log(res);
			});	
	
		});
	});
}

function xGenerarGrafico() {
	const _ListEstadisticaView = groupBy(ListEstadistica, 'nom_documento');
	let _chart = []
	Object.keys(_ListEstadisticaView).map(k => {
		_chart=[k, _ListEstadisticaView[k].length];
	});

	console.log(_chart);

	var chart = c3.generate({
		bindto: '#xchart',
		data: {
			columns: [
				_chart,				
			],
			type: 'bar', labels: true
		},
		bar: {
			width: {
				ratio: 0.3 // this makes bar width 50% of length between ticks
			}			
		}
	});

}

var groupBy = function (xs, key) {
	return xs.reduce(function (rv, x) {
		(rv[x[key]] = rv[x[key]] || []).push(x);
		return rv;
	}, {});
};