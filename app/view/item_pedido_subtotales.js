var xLocal_xDtSubTotales;var xLocal_SubTotal_Quitados="";var arrVerifySubTotalTachados=[];function xArmarSubtotalesFromTotal(itemMesa){if(!itemMesa)return;var dtDetalle=itemMesa.secciones.split(',');var subtotales_tachados_bd=itemMesa.subtotales_tachados;var arrDt=[];dtDetalle.map((d,index)=>{var _d=d.split(':');arrDt.push({'tipoconsumo':_d[0],'seccion':_d[1],'cantidad':_d[2],'subtotales_tachados':subtotales_tachados_bd});});return xCalcTotalSubArray(arrDt,itemMesa.importe);}
function xArmarSubtotalesArray(xarr_data_pedido=null,total=0){var xLocal_TotalImporte=0,arrDt=[];xarr_data_pedido=xarr_data_pedido||xArrayPedidoObj;xLocal_TotalImporte=total===0?xSumaCantArray(xarr_data_pedido):total;for(var i=0;i<xarr_data_pedido.length;i++){if(xarr_data_pedido[i]==null){continue;}
$.map(xarr_data_pedido[i],function(n,z){if(typeof n=="object"){const subtotales_tachados=n.subtotales_tachados!==undefined?n.subtotales_tachados:xLocal_SubTotal_Quitados;const idpedido=n.idpedido?n.idpedido:0;arrDt.push({'tipoconsumo':n.idtipo_consumo,'seccion':n.idseccion,'cantidad':n.cantidad,'subtotales_tachados':subtotales_tachados,'idpedido':idpedido});}})}
return xCalcTotalSubArray(arrDt,xLocal_TotalImporte);}
function xCalcTotalSubArray(arrDt,importeTotal){var xCartaSubtotales=xm_log_get('carta_subtotales'),arrTipoConsumo=arrDt||[];xLocal_xDtSubTotales=[];var arrSuma=[];xLocal_xDtSubTotales.push({'descripcion':'Sub Total','importe':xMoneda(importeTotal),'visible':true,'quitar':false});arrSuma.push({'descripcion':'Sub Total','importe':xMoneda(importeTotal),'visible':true,'quitar':false,'visible_cpe':true});xSumTotalPorcentaje=0;xSumCantImporte=0;const countPedidos=arrTipoConsumo.length;if(arrTipoConsumo.length===0||arrTipoConsumo[0].subtotales_tachados===undefined)return;var arrCocinada=arrTipoConsumo.map(x=>{x.grupo=x.tipoconsumo+x.seccion;return x;}).reduce((obj,val)=>{const grupo=val.grupo;if(obj[grupo]){obj[grupo].grupo=val.grupo;obj[grupo].grupoTipoconsumo=val.tipoconsumo;obj[grupo].cantidad=parseFloat(obj[grupo].cantidad)+parseFloat(val.cantidad)
obj[grupo].subtotales_tachados=!obj[grupo].subtotales_tachados?val.subtotales_tachados:obj[grupo].subtotales_tachados+val.subtotales_tachados;}else{obj[grupo]=[];obj[grupo].grupo=val.grupo;obj[grupo].grupoTipoconsumo=val.tipoconsumo;obj[grupo].cantidad=val.cantidad
obj[grupo].subtotales_tachados=!obj[grupo].subtotales_tachados?val.subtotales_tachados:obj[grupo].subtotales_tachados+val.subtotales_tachados;}
return obj;},[]);var all_subtotales_tachados='';arrCocinada.map(x=>{all_subtotales_tachados+=x.subtotales_tachados})
arrCocinada.all_subtotales_tachados=all_subtotales_tachados;xCartaSubtotales.map(c=>{c.grupo=c.idtipo_consumo+c.idseccion;return c;}).map(c=>{switch(c.tipo){case'a':arrTipoConsumo.map(x=>{const id=c.tipo+c.id;const nivel=parseInt(c.nivel);let sumItem=0;let importe_tachado=0;let subtotales_tachados_local='';let cantidadItemPedido=0;xLocal_SubTotal_Quitados=arrCocinada[x.grupo].subtotales_tachados!=''?arrCocinada[x.grupo].subtotales_tachados:xLocal_SubTotal_Quitados;if(nivel===0){if(x.grupo!==c.grupo){return}
sumItem=parseFloat(x.cantidad)*parseFloat(c.monto);if(sumItem===0){return;}}else{if(x.tipoconsumo!==c.idtipo_consumo){return}
sumItem=parseFloat(c.monto);}
importe_tachado=sumItem;subtotales_tachados_local=x.subtotales_tachados;cantidadItemPedido=arrCocinada[x.grupo].cantidad
subtotales_tachados_local.indexOf(id)>=0?sumItem=0:importe_tachado=0;const tachado=checkSubTotalQuitado(cantidadItemPedido,id,sumItem);var IdExite;arrSuma.map((z,index)=>{if(z.id===id){IdExite=index;return;}});if(IdExite){const importeItem=parseFloat(arrSuma[IdExite].importe);const importeTachadoItem=parseFloat(arrSuma[IdExite].importe_tachado);arrSuma[IdExite].importe=nivel===0?parseFloat(importeItem+parseFloat(sumItem)).toFixed(2):parseFloat(sumItem).toFixed(2);arrSuma[IdExite].importe_tachado=nivel===0?parseFloat(importeTachadoItem+parseFloat(importe_tachado)).toFixed(2):parseFloat(importe_tachado).toFixed(2);}else{arrSuma.push({'id':id,'descripcion':c.descripcion,'importe_tachado':xMoneda(importe_tachado),'importe':xMoneda(sumItem),'punitario':c.monto,'esImpuesto':0,'visible':true,'quitar':true,'tachado':tachado,'visible_cpe':false});}});break;case'p':const id=c.tipo+c.id;const esImpuesto=c.es_impuesto;const valorImpuesto=c.activo==="0"?c.monto:0;const visible_cpe=esImpuesto==="1"?true:false;let porcentaje=parseFloat(parseFloat(valorImpuesto)/100).toFixed(2);porcentaje=c.descripcion==='I.G.V'?porcentaje:parseFloat(parseFloat(importeTotal)*parseFloat(porcentaje)).toFixed(2);arrSuma.push({'id':id,'descripcion':c.descripcion,'importe':xMoneda(porcentaje),'esImpuesto':esImpuesto,'visible':true,'quitar':false,'tachado':false,'visible_cpe':visible_cpe});break;}});var _costoServicio=localStorage.getItem('::app3_woDUS::cxe');if(_costoServicio){rpt={};rpt.id=-2;rpt.descripcion='Entrega';rpt.isDeliveryApp=true;rpt.esImpuesto=0;rpt.visible=true;rpt.quitar=false;rpt.tachado=false;rpt.visible_cpe=false;rpt.importe=parseFloat(_costoServicio.toString()).toFixed(2);arrSuma.push(rpt);}
var sumTotal=arrSuma.filter(x=>x.descripcion!=="I.G.V").map(x=>x.importe).reduce((a,b)=>parseFloat(a)+parseFloat(b));var rowSubTotal=arrSuma.filter(x=>x.descripcion==="Sub Total")[0];var rowImporteIGV=arrSuma.filter(x=>x.descripcion==="I.G.V")[0];var _importeIGV=parseFloat(rowImporteIGV.importe);var _importeSubTotal=parseFloat(rowSubTotal.importe);if(_importeIGV>0){_importeIGV=parseFloat(_importeSubTotal*_importeIGV).toFixed(2);_importeSubTotal=_importeSubTotal-_importeIGV;rowImporteIGV.importe=_importeIGV;rowSubTotal.importe=_importeSubTotal.toFixed(2);}
if(arrSuma.length==1){arrSuma=[];}
arrSuma.push({'descripcion':'Total','importe':xMoneda(sumTotal),'visible':true,'visible_cpe':true});xLocal_xDtSubTotales=arrSuma;xSumCantImporte=sumTotal;return xSumCantImporte;}
function checkSubTotalQuitado(countPedidos,id,val){var rpt=false;if(!xLocal_SubTotal_Quitados){return;}
const CountIdTacahdos=xLocal_SubTotal_Quitados.toLowerCase().split(",").sort().filter(x=>x===id).length;const hbilitarTachado=parseInt(CountIdTacahdos)>=parseFloat(countPedidos);rpt=xLocal_SubTotal_Quitados.indexOf(id)>=0?true:false;if(!rpt){xSumCantImporte+=parseFloat(val);}
return hbilitarTachado?rpt:false;}
var groupBy=function(xs,key){return xs.reduce(function(rv,x){(rv[x[key]]=rv[x[key]]||[]).push(x);return rv;},{});};function darFormatoSubTotalesDelivery(arrTotales=null){var rowTotal=arrTotales[arrTotales.length-1];rowTotal.importe=arrTotales.filter(x=>x.id!==-2&&x.id!==-3&&x.descripcion.toUpperCase()!=='TOTAL').map(x=>parseFloat(x.importe)).reduce((a,b)=>a+b,0);xLocal_xDtSubTotales=arrTotales.filter(x=>x.id!==-2&&x.id!==-3);return xLocal_xDtSubTotales;}
function getImporteTotalSubTotalesDelivery(arrTotales=null){var rowTotal=arrTotales[arrTotales.length-1];return rowTotal.importe;}
function colocarDescuentoSubtotales(importeDesct,arrTotales,descripcionDesct='',isAgregaDesct=true){var rowTotal=arrTotales[arrTotales.length-1];var rowIgv=arrTotales.filter(x=>x.descripcion==='I.G.V')[0];var rowSubTotal=arrTotales[0];importeDesct=isAgregaDesct?importeDesct:0;var arrDescuento=arrTotales.filter(x=>x.id===6)[0];const importeDsc=arrDescuento?arrDescuento.importe*-1:0;rowTotal.importe=rowTotal.importe+importeDsc;if(arrDescuento){arrDescuento.importe=-importeDesct;arrDescuento.descripcion='Descuento '+descripcionDesct;}else{const postionInsert=arrTotales.length-1;const _row={descripcion:'Descuento '+descripcionDesct,esImpuesto:1,id:6,importe:-importeDesct,quitar:false,tachado:false,visible:false,visible_cpe:false};arrTotales.splice(postionInsert,0,_row);}
rowTotal.importe=rowTotal.importe-importeDesct;const xCartaSubtotalesIgv=xm_log_get('carta_subtotales');const _rowIGVConfig=xCartaSubtotalesIgv.filter(x=>x.descripcion==='I.G.V')[0];if(_rowIGVConfig.activo.toString()==="0"){var rowIgv=arrTotales.filter(x=>x.descripcion==='I.G.V')[0];var importeIgvRow=parseFloat(rowIgv.importe);const _importeIgvDsc=(rowTotal.importe*(parseFloat(_rowIGVConfig.monto)/100)).toFixed(2);rowIgv.importe=_importeIgvDsc;rowSubTotal.importe=parseFloat(rowTotal.importe)-_importeIgvDsc;if(importeDesct>0){}}else{rowTotal.importe=arrTotales.filter(x=>x.descripcion.toUpperCase()!=='TOTAL').map(x=>parseFloat(x.importe)).reduce((a,b)=>a+b,0);}
return arrTotales;}
function darFormatoSubTotalesComisionRepartidor(sedeInfo,arrTotales,costoEntrega,flagSolicitaRepartidor=false){if(sedeInfo.pwa_delivery_servicio_propio==='1'&&!flagSolicitaRepartidor){return arrTotales;}
if(sedeInfo.pwa_delivery_comercio_paga_entrega==='1'||flagSolicitaRepartidor){const postionInsert=arrTotales.length-1;const _row={descripcion:'Costo de Entrega',esImpuesto:0,id:-4,importe:-costoEntrega,quitar:false,tachado:false,visible:false,visible_cpe:false};arrTotales.splice(postionInsert,0,_row);}
const rowTotal=arrTotales[arrTotales.length-1];if(sedeInfo.pwa_delivery_servicio_propio==='1'){rowTotal.importe=arrTotales.filter(x=>x.descripcion.toUpperCase()!=='TOTAL').map(x=>parseFloat(x.importe)).reduce((a,b)=>a+b,0);return arrTotales;}else{rowTotal.importe=arrTotales.filter(x=>x.id!==-2&&x.id!==-3&&x.descripcion.toUpperCase()!=='TOTAL').map(x=>parseFloat(x.importe)).reduce((a,b)=>a+b,0);return arrTotales.filter(x=>x.id!==-2&&x.id!==-3);}}
function darFormatoSubTotalesParaFacturacion(arrTotales,isSumar=true){const rowCostoEntrega=arrTotales.filter(x=>x.id===-4)[0];if(rowCostoEntrega){if(isSumar){const rowTotal=arrTotales[arrTotales.length-1];rowTotal.importe+=(rowCostoEntrega.importe*-1);}
return arrTotales.filter(x=>x.id!==-4);}
return arrTotales;}