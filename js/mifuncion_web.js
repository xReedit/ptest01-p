// JavaScript Document
// $(document).bind("pageshow", function(){
document.addEventListener("WebComponentsReady", function componentsReady() {
	$('.xPasarEnter').on('keyup',function(e){
		var code=e.which;
		if ( code==13||code==186 ) {var xIndexTxt=$('input:text').index(this)+1;  $('input:text')[xIndexTxt].focus();}
	});
});

function xMoneda(xVal){
	var xvalReturn='';
	if(isNaN(parseFloat(xVal).toFixed(2))){xvalReturn=''}else{xvalReturn=parseFloat(xVal).toFixed(2)};
	return xvalReturn;
	}
function xRetornaMoneda(xObj){
	var xVal=parseFloat($(xObj).val());
	if(isNaN(xVal)){xVal=0;}
	xObj.value=parseFloat(xVal).toFixed(2);
	}


/*$.fn.reset=function(){
	$(this).each(function(){this.reset();});
	}*/

function xCeroIzq(Num, CantidadCeros){
   Num = Num.toString();
   while(Num.length < CantidadCeros) Num = "0" + Num;
   return Num;
}

function xCeroIzqNumComprobante(Num){
	if(!Num) return;
	Num = Num.toString();
	while(Num.length < 7) Num = "0" + Num;
	return Num;
 }

function conMayusculas(field) {field.value = field.value.toUpperCase();};
function getUrlParameter(sParam,simbolo) {
	var sPageURL = window.location.href;
	sPageURL=sPageURL.replace('-',' ');
	var sURLVariables = sPageURL.split(simbolo);
	for (var i = 0; i < sURLVariables.length; i++)
		{ var sParameterName = sURLVariables[i].split('=');
			if (sParameterName[0] == sParam) { return sParameterName[1]; } }
		}
function xRemoverDOM(xContenedor,xChild){$(xContenedor).find(xChild).remove().trigger('create');}

function xxCalcularTotal(xTablaCal,xClass){
	xPrecioTT=0;
	xTablaCal.find(xClass).each(function(index, element) {
		xPrecioTT=parseFloat(xPrecioTT)+parseFloat($(element).text());
        });

	return xPrecioTT;
	}
function xDevolverFechaTrim(){
	var d = new Date();
	var xFechaHora=xCeroIzq(d.getDate(),2)+xCeroIzq((d.getMonth()+1),2)+d.getFullYear();
	return xFechaHora;
	}
function xDevolverFechaTrim2(){
	var d = new Date();
	var xFechaHora=xCeroIzq(d.getDate(),2)+xCeroIzq((d.getMonth()+1),2)+d.getYear();
	return xFechaHora;
	}

function xDevolverFecha(){
	var d = new Date();
	var xFechaHora=xCeroIzq(d.getDate(),2)+'/'+xCeroIzq((d.getMonth()+1),2)+'/'+d.getFullYear();
	return xFechaHora;
	}

function xDevolverFechaParte(xParte){
	var d = new Date();
	var xrpt;
	switch(xParte){
		case 'dd':xrpt=xCeroIzq(d.getDate(),2);break;
		case 'mm':xrpt=xCeroIzq((d.getMonth()+1),2);break;
		case 'mmInt':xrpt=d.getMonth()+1;break;
		case 'mmInt0':xrpt=d.getMonth();break;
		case 'mmmm':xrpt=xDesMes(d.getMonth());break;
		case 'yy':xrpt=d.getFullYear();break;
		}
	return xrpt;
	}

function xDevolverFechaParte_Dada(xFecha, xParte){
	var d = new Date(xFecha.split('/').reverse().join('/'));
	var xrpt;
	switch(xParte){
		case 'dd':xrpt=xCeroIzq(d.getDate(),2);break;
		case 'mm':xrpt=xCeroIzq((d.getMonth()+1),2);break;
		case 'mmInt':xrpt=d.getMonth()+1;break;
		case 'mmInt0':xrpt=d.getMonth();break;
		case 'mmmm':xrpt=xDesMes(d.getMonth());break;
		case 'yy':xrpt=d.getFullYear();break;
		}
	return xrpt;
	}

function xDevolverHora(){
	var d = new Date();
	var xHora=xCeroIzq(d.getHours(),2)+':'+xCeroIzq(d.getMinutes(),2)+':'+xCeroIzq(d.getSeconds(),2);
	return xHora;
	}

function xDiasTranscurridos(f1,f2){
	var xdias;
	if(f1==''){f1=xDevolverFecha();f1=new Date(f1.split('/').reverse().join('/'));}
	f2=new Date(f2.split('/').reverse().join('/'));
	xdias=f1-f2;
	xdias = Math.floor(xdias / (1000 * 60 * 60 * 24));
	return xdias;
	}
function xDiasTranscurridos_f1(f1,f2){//formato normal
	var xdias;
	if(f1==''){f1=xDevolverFecha();}
	f1=new Date(f1.split('/').reverse().join('/'));
	f2=new Date(f2.split('/').reverse().join('/'));
	xdias=f1-f2;
	if(xdias<0){xdias=xdias*(-1)}
	xdias = Math.floor(xdias / (1000 * 60 * 60 * 24));
	return xdias;
	}

//en hh:mm:ss
function xTiempoTranscurridos(h1,h2,response){
	var hora1 = h1.split(":"),
    hora2 = h2.split(":"),
    t1 = new Date(),
    t2 = new Date();

 	if(hora2[2]==null){hora2[2]='00';}
 	if(hora1[2]==null){hora1[2]='00';}
	t1.setHours(hora1[0], hora1[1], hora1[2]);
	t2.setHours(hora2[0], hora2[1], hora2[2]);

	//Aquí hago la resta
	//t1.setHours(t1.getHours() - t2.getHours(), t1.getMinutes() - t2.getMinutes(), t1.getSeconds() - t2.getSeconds());
	//Imprimo el resultado
	//rpt=(t1.getHours() ? t1.getHours() + (t1.getHours() > 1 ? " horas" : " hora") : "") + (t1.getMinutes() ? ", " + t1.getMinutes() + (t1.getMinutes() > 1 ? " minutos" : " minuto") : "") + (t1.getSeconds() ? (t1.getHours() || t1.getMinutes() ? " y " : "") + t1.getSeconds() + (t1.getSeconds() > 1 ? " segundos" : " segundo") : "");
	var dif= t2 - t1; // diferencia en milisegundos
	var difSeg = Math.floor(dif/1000);
	var segundos = difSeg % 60; //segundos
	var difMin = Math.floor(difSeg/60);
	var minutos = difMin % 60; //minutos
	var difHs = Math.floor(difMin/60);
	var horas = difHs % 24; //horas
	response(xCeroIzq(horas,2),xCeroIzq(minutos,2),xCeroIzq(segundos,2),dif);
	//return horas+":"+minutos+":"+segundos; //armo el tiempo de diferencia
	}

//calcular el tiempo transcurrudos en minutos y segundo de una hora con la hora actual
function xTiempoTranscurridos_2(h2,response){
	var h1=xDevolverHora();
	var hora1 = h1.split(":"),
    hora2 = h2.split(":"),
    t1 = new Date(),
    t2 = new Date();

 	if(hora2[2]==null){hora2[2]='00';}
	t2.setHours(hora1[0], hora1[1], hora1[2]);
	t1.setHours(hora2[0], hora2[1], hora2[2]);

	//Aquí hago la resta
	//t1.setHours(t1.getHours() - t2.getHours(), t1.getMinutes() - t2.getMinutes(), t1.getSeconds() - t2.getSeconds());
	//Imprimo el resultado
	//rpt=(t1.getHours() ? t1.getHours() + (t1.getHours() > 1 ? " horas" : " hora") : "") + (t1.getMinutes() ? ", " + t1.getMinutes() + (t1.getMinutes() > 1 ? " minutos" : " minuto") : "") + (t1.getSeconds() ? (t1.getHours() || t1.getMinutes() ? " y " : "") + t1.getSeconds() + (t1.getSeconds() > 1 ? " segundos" : " segundo") : "");
	var dif= t2 - t1; // diferencia en milisegundos
	var difSeg = Math.floor(dif/1000);
	var segundos = difSeg % 60; //segundos
	var difMin = Math.floor(difSeg/60);
	var minutos = difMin % 60; //minutos
	var difHs = Math.floor(difMin/60);
	var horas = difHs % 24; //horas
	response(xCeroIzq(horas,2),xCeroIzq(minutos,2),xCeroIzq(segundos,2),dif);
	//return xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);
	}

//devolver tiempo en milisegundos
function xTiempoMS(h1){
	var hora1 = h1.split(":");
	t1 = new Date();
	t2 = new Date();
	t1.setHours(hora1[0], hora1[1], hora1[2]);
	t1.setHours(0, 0, 0);
	return t1-t2;
	}

//devolver hora de ms
function xTiempoDe_MS_Hora(dif){
	var difSeg = Math.floor(dif/1000);
	var segundos = difSeg % 60; //segundos
	var difMin = Math.floor(difSeg/60);
	var minutos = difMin % 60; //minutos
	var difHs = Math.floor(difMin/60);
	var horas = difHs % 24;

	return xCeroIzq(horas,2)+':'+xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);
	}

//devolver hora de ms
function xTiempoDe_MS_minutos(dif){
	var difSeg = Math.floor(dif/1000);
	var segundos = difSeg % 60; //segundos
	var difMin = Math.floor(difSeg/60);
	var minutos = difMin % 60; //minutos
	var difHs = Math.floor(difMin/60);
	var horas = difHs % 24;
	if(horas>0){horas=60*horas;minutos=minutos+horas}

	return xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);
	}

function xSumaDiasFecha(f1,dias){
	var fecha=new Date(f1.split('/').reverse().join('/'));
	fecha=fecha.setDate(fecha.getDate()+dias);
	d=new Date(fecha);
	return xCeroIzq(d.getDate(),2)+'/'+xCeroIzq((d.getMonth()+1),2)+'/'+d.getFullYear();
	}
//h1 hora a restar h1-h2
function xHorasQueFaltan(f1,h1,response){
	var hora1 = (h1).split(":");
	var fecha=new Date(f1.split('/').reverse().join('/'));
	var xDif;
	var xSigno='';
	//fecha=fecha+1;
	//fecha=fecha.getDate()+1;
	fecha=fecha.setDate(fecha.getDate()+1);
	fecha=new Date(fecha);
	fecha.setHours(hora1[0], hora1[1], hora1[2]);
    //hora2 = (h2).split(":"),
    //t1 = new Date(),
    var hoy= new Date();

 	//t1=new Date(f2.split('/').reverse().join('/'),hora1[0], hora1[1], hora1[2]);
 	//t1.setDate(fecha.getYear(),,hora1[0], hora1[1], hora1[2]);
	//t1.setHours(hora1[0], hora1[1], hora1[2]);
	//t2.setHours(hora2[0], hora2[1], hora2[2]);

	//Aquí hago la resta
	//t1.setHours(t1.getHours() - t2.getHours(), t1.getMinutes() - t2.getMinutes(), t1.getSeconds() - t2.getSeconds());
	//return t1.getHours()+':'+t1.getMinutes()+':'+t1.getSeconds();
	var diferencia=(fecha.getTime()-hoy.getTime())/1000;
    dias=Math.floor(diferencia/86400)
	xDif=dias;
	xSigno='';

	if(dias<0){
		xSigno='-';
		//dias=parseInt(dias)*-1;
		diferencia=(hoy.getTime()-fecha.getTime())/1000
    	dias=Math.floor(diferencia/86400)
	}

		diferencia=diferencia-(86400*dias)
		horas=Math.floor(diferencia/3600)
		diferencia=diferencia-(3600*horas)
		minutos=Math.floor(diferencia/60)
		diferencia=diferencia-(60*minutos)
		segundos=Math.floor(diferencia)

	xRpt=xCeroIzq(horas,2) + ':' + xCeroIzq(minutos,2) + ':' + xCeroIzq(segundos,2);
	response(xDif,horas,xRpt);
	return xSigno+xRpt
	}
/*
function xHorasTranscurridos(h1,h2){

	var hora1 = (h1).split(":");
	var hora2 = (h2).split(":");
	d1=new Date();
	d1.setHours(hora1[0], hora1[1], hora1[2]);

	d2=new Date();
	d2.setHours(hora2[0], hora1[2], hora1[2]);


	return d1-d2;
	}
*/
function xDiasDeUnMes(mes,anio){
	dias=[31,29,31,30,31,30,31,31,30,31,30,31];
	ultimo=0;
	if (mes==1){
		fecha=new Date(anio,1,29)
		vermes=fecha.getMonth();
		if(vermes!=mes){ultimo=28}
	}
	if(ultimo==0){ultimo=dias[mes]}
	return ultimo;
	}

function xDesMes(mes){
	var rpt;
	switch(mes){
		case 0:rpt='ENERO';break;
		case 1:rpt='FEBRERO';break;
		case 2:rpt='MARZO';break;
		case 3:rpt='ABRIL';break;
		case 4:rpt='MAYO';break;
		case 5:rpt='JUNIO';break;
		case 6:rpt='JULIO';break;
		case 7:rpt='AGOSTO';break;
		case 8:rpt='SETIEMBRE';break;
		case 9:rpt='OCTUBRE';break;
		case 10:rpt='NOVIEMBRE';break;
		case 11:rpt='DICIEMBRE';break;
		}

	return rpt;
	}

function xMayusculaPrimera(string){ return string.charAt(0).toUpperCase() + string.slice(1); }

//solo mayusculas
function xSoloMayusculas(field) {return field.toUpperCase();};
function xSoloMinuscula(field) {return field.toLowerCase();};

function conMayusculas(field) {field.value = field.value.toUpperCase();};


$.fn.reset=function(){
	$(this).each(function(){this.reset();});
	}

function xImprSelec(nombre) {
  ////////
  var ficha = document.getElementById(nombre);
  var ventimp = window.open(' ', 'popimpr');
  ventimp.document.write( ficha.innerHTML );
 // ventimp.document.write('<style>@page{margin:10px; font-family:"Agency FB";line-height:2px;} body{font-family:"Agency FB";} table{line-height:2px;}</style>');
  ventimp.document.close();
  ventimp.print();
  ventimp.close();
  }


function xImprSelec2(nombre) {
  ////////
  var ficha = document.getElementById(nombre);
  var ventimp = window.open(' ', 'popimpr');
  ventimp.document.write( ficha.innerHTML );
 // ventimp.document.write('<style>@page{margin:10px; font-family:"Agency FB";line-height:2px;} body{font-family:"Agency FB";} table{line-height:2px;}</style>');
  ventimp.document.close();
  ventimp.print();
  ventimp.close();
  }

//margin 0
function xImprSelec3(nombre) {
  ////////
  var ficha = document.getElementById(nombre);
  var ventimp = window.open(' ', 'popimpr');
  ventimp.document.write('<style>@page{margin-left:0px;} .xpage{position:relative;} span{position:absolute; float:left;}</style>'+ficha.innerHTML);
  //ventimp.document.write( ficha.innerHTML );
  //ventimp.document.write('<style>@page{margin:-1mm !important;}</style>');
  //ventimp.document.write('<style> @page{margin:0mm !important;} @media print { body{margin:0mm !important;}} body{margin:0mm !important;}</style>');
  //ventimp.document.write('<html><head><body><style> @media print { margin: 0;padding: 0; left:0; top:0;} @media print { html,body{margin: 0;padding: 0;left:0; top:0;} .page{margin: 0;padding: 0;left:0; top:0;}}</style></head>'+ficha.innerHTML+ '</body></html>');
  //ventimp.document.write('<html><head><body><style> @page {margin-top: -0.2cm !important;margin-left: -0.3cm !important;}  @media print { body{margin-top: -0.3cm !important;margin-left: -0.5cm !important;}}</style></head>'+ficha.innerHTML+ '</body></html>');
  //ventimp.document.write('<html><head><style>@media screen, print {body{margin: 0 !important;}}</style></head><body>'+ficha.innerHTML+ '</body></html>');
  ventimp.document.close();
  ventimp.print();
  ventimp.close();
  }

function ImprBoletaConCSS(nombre,Titulo) {
  ////////
	var ficha = document.getElementById(nombre);
 	var ventimp = window.open(' ', 'popimpr');
	if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
	ventimp.document.write('<html><head><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><script>jQuery(document).ready(function() { setTimeout(function() { window.close(); }, 1); }); </script><style type="text/css" media="print">table{background-color:#FFF;} @page{ margin: 0px;}</style>'+Titulo+''+  ficha.innerHTML  + '</body></html>');
	ventimp.print();
	ventimp.document.close();
	ventimp.close();
  }

function xImprBoletaConCSS2(nombre,Titulo) {
  ////////
	var ficha = document.getElementById(nombre);
 	var ventimp = window.open(' ', 'popimpr');
 	if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
	ventimp.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}</style>'+Titulo+' '+ficha.innerHTML +'</body></html>');
	//ventimp.document.close();
	ventimp.print();
  	ventimp.close();
  }

 function ImprBoletaConCSS2(nombre,Titulo) {
  ////////
	var ficha = document.getElementById(nombre);
 	var ventimp = window.open(' ', 'popimpr');
	if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
	ventimp.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}table tr td img{opacity:1;} </style>'+Titulo+''+  ficha.innerHTML  + '</body></html>');
	ventimp.print();
	ventimp.document.close();
	ventimp.close();
  }


/*function xxVerificarPass(xu, response){
	$.mobile.loading('show');
	$.post('bdphp/log.php?op=10',{u:xu, c:xParam[1]},function (xdUs){
		if(xdUs.length>0){
			response(true); return;
			}else{alert('Constraseña actual incorrecta');}
			$.mobile.loading('hide');
			response(false);
		});
	}*/

//sql detalle
//xCondicion // [Id de la columna de tabla] nombre del campo = 0 // ejemplo // cantidad > 0 / tener en cuenta los espacios // el signo == espera caracteres
//200115 //agregar columna estado 1=guardado para no volver a guardar
//020216/ xSoloGuardar //si tiene un valor solo actualiza no inserta; relacionado con $(element).attr('data-update')
//040216/ xCamposSumar relacionado con attr('data-suma'); //Sumar campos si es update por ejemplo stock o cantidad
//100216/ //nomarcar == no marcar como guardado... guardar cuantas veces requiera
function xArmarInsertDetalle(xTable,NomIdPadre,IdPadre,xCondicion,xSoloUpdate){
	xCondicion || ( xCondicion = '' );
	//evaluar condicion
	var xNomCampoCondicion="";
	var xValCampoCondicion="";
	var xSimboloCondicion="";
	if(xSoloUpdate==""){xSoloUpdate=null;}
	if(xCondicion!=""){
		var Condicion= xCondicion.split(" ");
		xNomCampoCondicion='#'+Condicion[0];
		xSimboloCondicion=Condicion[1];
		xValCampoCondicion=Condicion[2];
		}
	//
	var xColumName="";
	var xCampoSumar;//suma campos por ejemplo stock o cantidad
	var xValColum="";
	var xSqlEncabezado="";
	var xSqlEncabezadoOk="";
	var xSqlValuesArray=new Array();
	var xSql="";

	var xSqlUpdate='';
	var xCondicionUpdate='';
	var xBodySqlUpdate='';

	var xNomTabla=xTable.attr('data-TablaName');
	var xComa="";
	var xCuenta=0;
	var xCuentaFila=0;

	xTable.find('tr').each(function(index,element) {
		xValColum="";

		//agregar columna estado 1=guardado para no volver a guardar
		if(!$(element).hasClass('nomarcar')){
			if($(element).attr('data-yaguardo')==1){return;}
		}
		$(element).attr('data-yaguardo',1);


		//evalua condicion
		if(xCondicion!=""){
			var xValCampo=$(element).find(xNomCampoCondicion).text();
			if(xValCampo.length){
				switch(xSimboloCondicion){
					case '>':if(parseInt(xValCampo)<parseInt(xValCampoCondicion)){return true;} break;
					case '<':if(parseInt(xValCampo)>parseInt(xValCampoCondicion)){return true;} break;
					case '>=':if(parseInt(xValCampo)<=parseInt(xValCampoCondicion)){return true;} break;
					case '<=':if(parseInt(xValCampo)>=parseInt(xValCampoCondicion)){return true;} break;
					case '=':if(parseInt(xValCampo)!=parseInt(xValCampoCondicion)){return true;} break;
					case '!=':if(parseInt(xValCampo)==parseInt(xValCampoCondicion)){return true;} break;
					case '==':if(xValCampo!=xValCampoCondicion){return true;} break;
					}
				}
			}

		var xIdRow='';//guarda
		xBodySqlUpdate='';
		xIdRow=$(element).attr('data-update');

		xCondicionUpdate="id"+xNomTabla+"="+xIdRow;

		$(element).find('td').each(function(index,element) {
			xComa="";
			xColumName=$(element).attr('data-ColumName');
			xCampoOperacion=$(element).attr('data-operacion');

			//update si $(element).attr('data-update') es diferente de vacio
			if(xIdRow != undefined && xColumName!=null){
				if($(element)[0].childElementCount>0){
					xValColum=$(element).find('input').val();
				}else{
					xValColum=$(element).text();
				}
				//busca si hay campo sumar con cualquier valor diferente a null
				if(xCampoOperacion != undefined){
					switch (xCampoOperacion){
						case "resta":xColumName=xColumName+'='+xColumName+'-';break;
						case "suma":xColumName=xColumName+'='+xColumName+'+';break;
					}
					xBodySqlUpdate=String(xBodySqlUpdate+xColumName+"'"+xValColum+"',");
				}else{
					xBodySqlUpdate=String(xBodySqlUpdate+xColumName+"='"+xValColum+"',");
				}

			}
			else{ //insert
				if(xSoloUpdate!=null){return;}
				if(xColumName){
					//alert(xColumName);
					if(xCuenta>=0){xComa=', '}
					xSqlEncabezado=xSqlEncabezado+xColumName+xComa;
					//values
					if($(element)[0].childElementCount>0){
						xValColum=xValColum+"'"+$(element).find('input').val()+"'"+xComa;
					}else{
						xValColum=xValColum+"'"+$(element).text()+"'"+xComa;
					}
					//xValColum=xValColum+"'"+$(element).text()+"'"+xComa;
					xCuenta++
					}
				}
			});


		if(xBodySqlUpdate!=""){
				xBodySqlUpdate=xBodySqlUpdate.slice(0,-1);
				xSqlUpdate=String(xSqlUpdate+' Update '+xNomTabla +' set '+xBodySqlUpdate+' where '+xCondicionUpdate+'; ');
		}
		else{
				if(xValColum!=""){
					xValColum=xValColum.substr(0,xValColum.length-2);
					xSqlEncabezado=xSqlEncabezado.substr(0,xSqlEncabezado.length-2);

					if(NomIdPadre){xValColum='('+IdPadre+','+xValColum+')';}else{xValColum='('+xValColum+')';}
					if(NomIdPadre){xSqlEncabezado='('+NomIdPadre+','+xSqlEncabezado+')';}else{xSqlEncabezado='('+xSqlEncabezado+')';}
					xSqlEncabezadoOk=xSqlEncabezado;

					xSqlValuesArray[xCuentaFila]=xValColum;

					xCuentaFila++;
					xSqlEncabezado="";
				}
			}

		});

		var xSqlBody="";
		for(a in xSqlValuesArray){
			xComa=",";
			if(a==xSqlValuesArray.length-1){xComa=""}
			xSqlBody=xSqlBody+ xSqlValuesArray[a]+xComa;
			}


		if(xSqlEncabezadoOk!=""){
			xSql="insert into "+xNomTabla+' '+xSqlEncabezadoOk+' values '+xSqlBody;
			if(xSqlUpdate!=""){
				xSql="insert into "+xNomTabla+' '+xSqlEncabezadoOk+' values '+xSqlBody+'; '+xSqlUpdate;
			}
		}
		else{
			if(xSqlUpdate!=""){
				xSql=xSqlUpdate;
			}
		}

		return xSql;
	}

function xBuscarArray(obj,busca){
	//return obj.map((element) => element.id).indexOf(busca);
	for(var i=0; i<obj.length; i++) {
    	if (obj[i] == value) return 1;
  	}
  	return -1;

}
/*
function xComprobarIdOrg(){
	if(xIdOrg==''){	xIdOrg=window.localStorage.getItem('::app2_wo');}
	if(xIdOrg==null){//crea regresar a login

	}
}*/

function xBuscarTbData(tb,filter){
	var regExPattern = "gi";
	var regEx = new RegExp(filter, regExPattern);
	$(tb).find(".row").each(function () {
		if ($(this).text().search(new RegExp(filter, "i")) < 0 ){
	        $(this).hide();
	    } else {
	        $(this).show();
	    }
    });
}
function xBuscarTbDataVisible(tb,filter){
	var regExPattern = "gi";
	var regEx = new RegExp(filter, regExPattern);
	$(tb).find(".row").each(function () {
		if($(this).is(':visible')) {
			if ($(this).text().search(new RegExp(filter, "i")) < 0 ){
	            $(this).hide();
	        } else {
	            $(this).show();
	        }
		}
    });
}

//busca en tabla por su propiedad attr, develve elemento row
function xBuscarAttrTbData(tb,BuscarEn,filter){
	var xItem=false;
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){
			xItem=$(element);
			return;
		}
    });
    return xItem;
}

// filtrar por attr considerar visible
function xFiltrarRowAttr(tb,BuscarEn,filter,visible){
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){
			if(visible){$(this).show();}else{$(this).hide();}
		}
    });
}
// filtrar por attr si no coincide hide al row
function xFiltrarRowAttr2(tb,BuscarEn,filter){
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){$(this).show();}else{$(this).hide();}
    });
}
//busca con clase diferente a row
function xBuscarAttrTbData2(tb,BuscarEn,filter,clase){
	var xItem=false;
	$(tb).find(clase).each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){
			xItem=$(element);
			return;
		}
    });
    return xItem;
}

function xOrdernarArray(xArray,xOrdenPor){

}
