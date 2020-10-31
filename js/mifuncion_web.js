document.addEventListener("WebComponentsReady",function componentsReady(){$('.xPasarEnter').on('keyup',function(e){var code=e.which;if(code==13||code==186){var xIndexTxt=$('input:text').index(this)+1;$('input:text')[xIndexTxt].focus();}});});function xMoneda(xVal){var xvalReturn='';try{if(isNaN(parseFloat(xVal).toFixed(2))){xvalReturn=''}else{xvalReturn=parseFloat(xVal).toFixed(2)};}catch(error){xvalReturn=xVal;}
return xvalReturn;}
function xRetornaMoneda(xObj){var xVal=parseFloat($(xObj).val());if(isNaN(xVal)){xVal=0;}
xObj.value=parseFloat(xVal).toFixed(2);}
function numeroConComas(x){return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g,",");}
function xCeroIzq(Num,CantidadCeros){Num=Num.toString();while(Num.length<CantidadCeros)Num="0"+Num;return Num;}
function xCeroIzqNumComprobante(Num){if(!Num)return;Num=Num.toString();while(Num.length<7)Num="0"+Num;return Num;}
function conMayusculas(field){field.value=field.value.toUpperCase();};function primeraConMayusculas(field){return field.charAt(0).toUpperCase()+field.slice(1);};function getUrlParameter(sParam,simbolo){var sPageURL=window.location.href;sPageURL=sPageURL.replace('-',' ');var sURLVariables=sPageURL.split(simbolo);for(var i=0;i<sURLVariables.length;i++)
{var sParameterName=sURLVariables[i].split('=');if(sParameterName[0]==sParam){return sParameterName[1];}}}
function xRemoverDOM(xContenedor,xChild){$(xContenedor).find(xChild).remove().trigger('create');}
function xxCalcularTotal(xTablaCal,xClass){xPrecioTT=0;xTablaCal.find(xClass).each(function(index,element){xPrecioTT=parseFloat(xPrecioTT)+parseFloat($(element).text());});return xPrecioTT;}
function xDevolverFechaTrim(){var d=new Date();var xFechaHora=xCeroIzq(d.getDate(),2)+xCeroIzq((d.getMonth()+1),2)+d.getFullYear();return xFechaHora;}
function xDevolverFechaTrim2(){var d=new Date();var xFechaHora=xCeroIzq(d.getDate(),2)+xCeroIzq((d.getMonth()+1),2)+d.getYear();return xFechaHora;}
function xDevolverFecha(){var d=new Date();var xFechaHora=xCeroIzq(d.getDate(),2)+'/'+xCeroIzq((d.getMonth()+1),2)+'/'+d.getFullYear();return xFechaHora;}
function xDevolverFechaDDMMYYY(d){var xFechaHora=xCeroIzq(d.getDate(),2)+'/'+xCeroIzq((d.getMonth()+1),2)+'/'+d.getFullYear();return xFechaHora;}
function xDevolverFechaFormatInputDate(fecha_string){return fecha_string.split('-').reverse().join('/');}
function xSetInputDate(fecha_string){return fecha_string.split('/').reverse().join('-');}
function xDevolverFechaParte(xParte){var d=new Date();var xrpt;switch(xParte){case'dd':xrpt=xCeroIzq(d.getDate(),2);break;case'mm':xrpt=xCeroIzq((d.getMonth()+1),2);break;case'mmInt':xrpt=d.getMonth()+1;break;case'mmInt0':xrpt=d.getMonth();break;case'mmmm':xrpt=xDesMes(d.getMonth());break;case'yy':xrpt=d.getFullYear();break;}
return xrpt;}
function xDevolverFechaParte_Dada(xFecha,xParte){var d=new Date(xFecha.split('/').reverse().join('/'));var xrpt;switch(xParte){case'dd':xrpt=xCeroIzq(d.getDate(),2);break;case'mm':xrpt=xCeroIzq((d.getMonth()+1),2);break;case'mmInt':xrpt=d.getMonth()+1;break;case'mmInt0':xrpt=d.getMonth();break;case'mmmm':xrpt=xDesMes(d.getMonth());break;case'yy':xrpt=d.getFullYear();break;}
return xrpt;}
function xDevolverHora(){var d=new Date();var xHora=xCeroIzq(d.getHours(),2)+':'+xCeroIzq(d.getMinutes(),2)+':'+xCeroIzq(d.getSeconds(),2);return xHora;}
function xDiasTranscurridos(f1,f2){var xdias;if(f1==''){f1=xDevolverFecha();f1=new Date(f1.split('/').reverse().join('/'));}
f2=new Date(f2.split('/').reverse().join('/'));xdias=f1-f2;xdias=Math.floor(xdias/(1000*60*60*24));return xdias;}
function xDiasTranscurridos_f1(f1,f2){var xdias;if(f1==''){f1=xDevolverFecha();}
f1=new Date(f1.split('/').reverse().join('/'));f2=new Date(f2.split('/').reverse().join('/'));xdias=f1-f2;if(xdias<0){xdias=xdias*(-1)}
xdias=Math.floor(xdias/(1000*60*60*24));return xdias;}
function xTiempoTranscurridos(h1,h2,response){var hora1=h1.split(":"),hora2=h2.split(":"),t1=new Date(),t2=new Date();if(hora2[2]==null){hora2[2]='00';}
if(hora1[2]==null){hora1[2]='00';}
t1.setHours(hora1[0],hora1[1],hora1[2]);t2.setHours(hora2[0],hora2[1],hora2[2]);var dif=t2-t1;var difSeg=Math.floor(dif/1000);var segundos=difSeg%60;var difMin=Math.floor(difSeg/60);var minutos=difMin%60;var difHs=Math.floor(difMin/60);var horas=difHs%24;response(xCeroIzq(horas,2),xCeroIzq(minutos,2),xCeroIzq(segundos,2),dif);}
function xTiempoTranscurridos_2(h2,response){var h1=xDevolverHora();var hora1=h1.split(":"),hora2=h2.split(":"),t1=new Date(),t2=new Date();if(hora2[2]==null){hora2[2]='00';}
t2.setHours(hora1[0],hora1[1],hora1[2]);t1.setHours(hora2[0],hora2[1],hora2[2]);var dif=t2-t1;var difSeg=Math.floor(dif/1000);var segundos=difSeg%60;var difMin=Math.floor(difSeg/60);var minutos=difMin%60;var difHs=Math.floor(difMin/60);var horas=difHs%24;response(xCeroIzq(horas,2),xCeroIzq(minutos,2),xCeroIzq(segundos,2),dif);}
function xTiempoMS(h1){var hora1=h1.split(":");t1=new Date();t2=new Date();t1.setHours(hora1[0],hora1[1],hora1[2]);t1.setHours(0,0,0);return t1-t2;}
function xTiempoDe_MS_Hora(dif){var difSeg=Math.floor(dif/1000);var segundos=difSeg%60;var difMin=Math.floor(difSeg/60);var minutos=difMin%60;var difHs=Math.floor(difMin/60);var horas=difHs%24;return xCeroIzq(horas,2)+':'+xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);}
function xTiempoDe_MS_minutos(dif){var difSeg=Math.floor(dif/1000);var segundos=difSeg%60;var difMin=Math.floor(difSeg/60);var minutos=difMin%60;var difHs=Math.floor(difMin/60);var horas=difHs%24;if(horas>0){horas=60*horas;minutos=minutos+horas}
return xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);}
function xTiempoTrascurridoYYMMDD(x){var y=365,y2=31,remainder=x%y,casio=remainder%y2;year=(x-remainder)/y;month=(remainder-casio)/y2;const _year=year>0?year+' AÑOS ':'';const _meses=month>0?month+' MESES Y ':'';const result=_year+_meses+casio+' DIAS';return result;}
function xSumaDiasFecha(f1,dias){var fecha=new Date(f1.split('/').reverse().join('/'));fecha=fecha.setDate(fecha.getDate()+dias);d=new Date(fecha);return xCeroIzq(d.getDate(),2)+'/'+xCeroIzq((d.getMonth()+1),2)+'/'+d.getFullYear();}
function xHorasQueFaltan(f1,h1,response){var hora1=(h1).split(":");var fecha=new Date(f1.split('/').reverse().join('/'));var xDif;var xSigno='';fecha=fecha.setDate(fecha.getDate()+1);fecha=new Date(fecha);fecha.setHours(hora1[0],hora1[1],hora1[2]);var hoy=new Date();var diferencia=(fecha.getTime()-hoy.getTime())/1000;dias=Math.floor(diferencia/86400)
xDif=dias;xSigno='';if(dias<0){xSigno='-';diferencia=(hoy.getTime()-fecha.getTime())/1000
dias=Math.floor(diferencia/86400)}
diferencia=diferencia-(86400*dias)
horas=Math.floor(diferencia/3600)
diferencia=diferencia-(3600*horas)
minutos=Math.floor(diferencia/60)
diferencia=diferencia-(60*minutos)
segundos=Math.floor(diferencia)
xRpt=xCeroIzq(horas,2)+':'+xCeroIzq(minutos,2)+':'+xCeroIzq(segundos,2);response(xDif,horas,xRpt);return xSigno+xRpt}
function xReturnSumaFechaDada(fecha,perido,cantidad){var rpt_sum_fecha;fecha=fecha?fecha:new Date();switch(perido){case'dd':rpt_sum_fecha=fecha.getDate()+cantidad;break;case'mm':rpt_sum_fecha=fecha.getMonth()+cantidad;break;case'yy':rpt_sum_fecha=fecha.getFullYear()+cantidad;break;}
return rpt_sum_fecha;}
function xDiasDeUnMes(mes,anio){dias=[31,29,31,30,31,30,31,31,30,31,30,31];ultimo=0;if(mes==1){fecha=new Date(anio,1,29)
vermes=fecha.getMonth();if(vermes!=mes){ultimo=28}}
if(ultimo==0){ultimo=dias[mes]}
return ultimo;}
function xDesMes(mes){var rpt;switch(mes){case 0:rpt='ENERO';break;case 1:rpt='FEBRERO';break;case 2:rpt='MARZO';break;case 3:rpt='ABRIL';break;case 4:rpt='MAYO';break;case 5:rpt='JUNIO';break;case 6:rpt='JULIO';break;case 7:rpt='AGOSTO';break;case 8:rpt='SETIEMBRE';break;case 9:rpt='OCTUBRE';break;case 10:rpt='NOVIEMBRE';break;case 11:rpt='DICIEMBRE';break;}
return rpt;}
function xDesDiaSemana(diaSemana,isFecha=false){var dias=["Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sabado"];if(isFecha){dias=["Lunes","Martes","Miercoles","Jueves","Viernes","Sabado","Domingo"];const f=new Date(diaSemana);return dias[f.getDay()];}else{return dias[diaSemana-1];}}
function xMayusculaPrimera(string){return string.charAt(0).toUpperCase()+string.slice(1);}
function xSoloMayusculas(field){return field.toUpperCase();};function xSoloMinuscula(field){return field.toLowerCase();};function conMayusculas(field){field.value=field.value.toUpperCase();};$.fn.reset=function(){$(this).each(function(){this.reset();});}
function xImprSelec(nombre){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');ventimp.document.write(ficha.innerHTML);ventimp.document.close();ventimp.print();ventimp.close();}
function xImprSelec2(nombre){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');ventimp.document.write(ficha.innerHTML);ventimp.document.close();ventimp.print();ventimp.close();}
function xImprSelec3(nombre){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');ventimp.document.write('<style>@page{margin-left:0px;} .xpage{position:relative;} span{position:absolute; float:left;}</style>'+ficha.innerHTML);ventimp.document.close();ventimp.print();ventimp.close();}
function ImprBoletaConCSS(nombre,Titulo){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
ventimp.document.write('<html><head><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><script>jQuery(document).ready(function() { setTimeout(function() { window.close(); }, 1); }); </script><style type="text/css" media="print">table{background-color:#FFF;} @page{ margin: 0px;}</style>'+Titulo+''+ficha.innerHTML+'</body></html>');ventimp.print();ventimp.document.close();ventimp.close();}
function xImprBoletaConCSS2(nombre,Titulo){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
ventimp.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}</style>'+Titulo+' '+ficha.innerHTML+'</body></html>');ventimp.print();ventimp.close();}
function ImprBoletaConCSS2(nombre,Titulo){var ficha=document.getElementById(nombre);var ventimp=window.open(' ','popimpr');if(Titulo!=""){Titulo='<h3>'+Titulo+'</h3><br>';}
ventimp.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}table tr td img{opacity:1;} </style>'+Titulo+''+ficha.innerHTML+'</body></html>');ventimp.print();ventimp.document.close();ventimp.close();}
function xArmarInsertDetalle(xTable,NomIdPadre,IdPadre,xCondicion,xSoloUpdate){xCondicion||(xCondicion='');var xNomCampoCondicion="";var xValCampoCondicion="";var xSimboloCondicion="";if(xSoloUpdate==""){xSoloUpdate=null;}
if(xCondicion!=""){var Condicion=xCondicion.split(" ");xNomCampoCondicion='#'+Condicion[0];xSimboloCondicion=Condicion[1];xValCampoCondicion=Condicion[2];}
var xColumName="";var xCampoSumar;var xValColum="";var xSqlEncabezado="";var xSqlEncabezadoOk="";var xSqlValuesArray=new Array();var xSql="";var xSqlUpdate='';var xCondicionUpdate='';var xBodySqlUpdate='';var xNomTabla=xTable.attr('data-TablaName');var xComa="";var xCuenta=0;var xCuentaFila=0;xTable.find('tr').each(function(index,element){xValColum="";if(!$(element).hasClass('nomarcar')){if($(element).attr('data-yaguardo')==1){return;}}
$(element).attr('data-yaguardo',1);if(xCondicion!=""){var xValCampo=$(element).find(xNomCampoCondicion).text();if(xValCampo.length){switch(xSimboloCondicion){case'>':if(parseInt(xValCampo)<parseInt(xValCampoCondicion)){return true;}break;case'<':if(parseInt(xValCampo)>parseInt(xValCampoCondicion)){return true;}break;case'>=':if(parseInt(xValCampo)<=parseInt(xValCampoCondicion)){return true;}break;case'<=':if(parseInt(xValCampo)>=parseInt(xValCampoCondicion)){return true;}break;case'=':if(parseInt(xValCampo)!=parseInt(xValCampoCondicion)){return true;}break;case'!=':if(parseInt(xValCampo)==parseInt(xValCampoCondicion)){return true;}break;case'==':if(xValCampo!=xValCampoCondicion){return true;}break;}}}
var xIdRow='';xBodySqlUpdate='';xIdRow=$(element).attr('data-update');xCondicionUpdate="id"+xNomTabla+"="+xIdRow;$(element).find('td').each(function(index,element){xComa="";xColumName=$(element).attr('data-ColumName');xCampoOperacion=$(element).attr('data-operacion');if(xIdRow!=undefined&&xColumName!=null){if($(element)[0].childElementCount>0){xValColum=$(element).find('input').val();}else{xValColum=$(element).text();}
if(xCampoOperacion!=undefined){switch(xCampoOperacion){case"resta":xColumName=xColumName+'='+xColumName+'-';break;case"suma":xColumName=xColumName+'='+xColumName+'+';break;}
xBodySqlUpdate=String(xBodySqlUpdate+xColumName+"'"+xValColum+"',");}else{xBodySqlUpdate=String(xBodySqlUpdate+xColumName+"='"+xValColum+"',");}}
else{if(xSoloUpdate!=null){return;}
if(xColumName){if(xCuenta>=0){xComa=', '}
xSqlEncabezado=xSqlEncabezado+xColumName+xComa;if($(element)[0].childElementCount>0){xValColum=xValColum+"'"+$(element).find('input').val()+"'"+xComa;}else{xValColum=xValColum+"'"+$(element).text()+"'"+xComa;}
xCuenta++}}});if(xBodySqlUpdate!=""){xBodySqlUpdate=xBodySqlUpdate.slice(0,-1);xSqlUpdate=String(xSqlUpdate+' Update '+xNomTabla+' set '+xBodySqlUpdate+' where '+xCondicionUpdate+'; ');}
else{if(xValColum!=""){xValColum=xValColum.substr(0,xValColum.length-2);xSqlEncabezado=xSqlEncabezado.substr(0,xSqlEncabezado.length-2);if(NomIdPadre){xValColum='('+IdPadre+','+xValColum+')';}else{xValColum='('+xValColum+')';}
if(NomIdPadre){xSqlEncabezado='('+NomIdPadre+','+xSqlEncabezado+')';}else{xSqlEncabezado='('+xSqlEncabezado+')';}
xSqlEncabezadoOk=xSqlEncabezado;xSqlValuesArray[xCuentaFila]=xValColum;xCuentaFila++;xSqlEncabezado="";}}});var xSqlBody="";for(a in xSqlValuesArray){xComa=",";if(a==xSqlValuesArray.length-1){xComa=""}
xSqlBody=xSqlBody+xSqlValuesArray[a]+xComa;}
if(xSqlEncabezadoOk!=""){xSql="insert into "+xNomTabla+' '+xSqlEncabezadoOk+' values '+xSqlBody;if(xSqlUpdate!=""){xSql="insert into "+xNomTabla+' '+xSqlEncabezadoOk+' values '+xSqlBody+'; '+xSqlUpdate;}}
else{if(xSqlUpdate!=""){xSql=xSqlUpdate;}}
return xSql;}
function xBuscarArray(obj,busca){for(var i=0;i<obj.length;i++){if(obj[i]==value)return 1;}
return-1;}
function xBuscarTbData(tb,filter){var regExPattern="gi";var regEx=new RegExp(filter,regExPattern);$(tb).find(".row").each(function(){if($(this).text().search(new RegExp(filter,"i"))<0){$(this).hide();}else{$(this).show();}});}
function xBuscarTbDataVisible(tb,filter){var regExPattern="gi";var regEx=new RegExp(filter,regExPattern);$(tb).find(".row").each(function(){if($(this).is(':visible')){if($(this).text().search(new RegExp(filter,"i"))<0){$(this).hide();}else{$(this).show();}}});}
function xBuscarAttrTbData(tb,BuscarEn,filter){var xItem=false;$(tb).find(".row").each(function(index,element){var xIdRowTb=$(element).attr(BuscarEn);if(xIdRowTb==filter){xItem=$(element);return;}});return xItem;}
function xFiltrarRowAttr(tb,BuscarEn,filter,visible){$(tb).find(".row").each(function(index,element){var xIdRowTb=$(element).attr(BuscarEn);if(xIdRowTb==filter){if(visible){$(this).show();}else{$(this).hide();}}});}
function xFiltrarRowAttr2(tb,BuscarEn,filter){$(tb).find(".row").each(function(index,element){var xIdRowTb=$(element).attr(BuscarEn);if(xIdRowTb==filter){$(this).show();}else{$(this).hide();}});}
function xBuscarAttrTbData2(tb,BuscarEn,filter,clase){var xItem=false;$(tb).find(clase).each(function(index,element){var xIdRowTb=$(element).attr(BuscarEn);if(xIdRowTb==filter){xItem=$(element);return;}});return xItem;}
function xOrdernarArray(xArray,xOrdenPor){}