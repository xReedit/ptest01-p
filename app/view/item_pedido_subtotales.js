var xLocal_xDtSubTotales,xLocal_SubTotal_Quitados="",arrVerifySubTotalTachados=[];function xArmarSubtotalesFromTotal(o){var t,e,a;if(o)return t=o.secciones.split(","),e=o.subtotales_tachados,a=[],t.map((o,t)=>{o=o.split(":");a.push({tipoconsumo:o[0],seccion:o[1],cantidad:o[2],subtotales_tachados:e})}),xCalcTotalSubArray(a,o.importe)}function xArmarSubtotalesArray(o=null,t=0){var i=[];o=o||xArrayPedidoObj;for(var t=0===t?xSumaCantArray(o):t,e=0;e<o.length;e++)null!=o[e]&&$.map(o[e],function(o,t){var e,a;"object"==typeof o&&(e=void 0!==o.subtotales_tachados?o.subtotales_tachados:xLocal_SubTotal_Quitados,a=o.idpedido||0,i.push({tipoconsumo:o.idtipo_consumo,seccion:o.idseccion,cantidad:o.cantidad,subtotales_tachados:e,idpedido:a}))});return xCalcTotalSubArray(i,t)}function xCalcTotalSubArray(o,i){var d,t,e,a,r,s=xm_log_get("carta_subtotales"),p=o||[],u=[];(xLocal_xDtSubTotales=[]).push({descripcion:"Sub Total",importe:xMoneda(i),visible:!0,quitar:!1}),u.push({descripcion:"Sub Total",importe:xMoneda(i),visible:!0,quitar:!1,visible_cpe:!0}),xSumTotalPorcentaje=0,xSumCantImporte=0,p.length;if(0!==p.length&&void 0!==p[0].subtotales_tachados)return d=p.map(o=>(o.grupo=o.tipoconsumo+o.seccion,o)).reduce((o,t)=>{var e=t.grupo;return o[e]?(o[e].grupo=t.grupo,o[e].grupoTipoconsumo=t.tipoconsumo,o[e].cantidad=parseFloat(o[e].cantidad)+parseFloat(t.cantidad)):(o[e]=[],o[e].grupo=t.grupo,o[e].grupoTipoconsumo=t.tipoconsumo,o[e].cantidad=t.cantidad),o[e].subtotales_tachados=o[e].subtotales_tachados?o[e].subtotales_tachados+t.subtotales_tachados:t.subtotales_tachados,o},[]),t="",d.map(o=>{t+=o.subtotales_tachados}),d.all_subtotales_tachados=t,s.map(o=>(o.grupo=o.idtipo_consumo+o.idseccion,o)).map(l=>{switch(l.tipo){case"a":p.map(o=>{var t=l.tipo+l.id;const e=l.descripcion;var a=parseInt(l.nivel);let i=0,r=0;var s="";if(xLocal_SubTotal_Quitados=""!=d[o.grupo].subtotales_tachados?d[o.grupo].subtotales_tachados:xLocal_SubTotal_Quitados,0===a){if(o.grupo!==l.grupo)return;if(0===(i=parseFloat(o.cantidad)*parseFloat(l.monto)))return}else{if(o.tipoconsumo!==l.idtipo_consumo)return;i=parseFloat(l.monto)}r=i,s=o.subtotales_tachados,o=d[o.grupo].cantidad,0<=s.indexOf(t)?i=0:r=0;var p,s=checkSubTotalQuitado(o,t,i),c=null;u.map((o,t)=>{o.descripcion.toLowerCase()===e.toLowerCase()&&(c=t)}),c?(o=parseFloat(u[c].importe),p=parseFloat(u[c].importe_tachado),u[c].importe=(0===a?parseFloat(o+parseFloat(i)):parseFloat(i)).toFixed(2),u[c].importe_tachado=(0===a?parseFloat(p+parseFloat(r)):parseFloat(r)).toFixed(2)):u.push({id:t,descripcion:l.descripcion,importe_tachado:xMoneda(r),importe:xMoneda(i),punitario:l.monto,esImpuesto:0,visible:!0,quitar:!0,tachado:s,visible_cpe:!1})});break;case"p":var o=l.tipo+l.id,t=l.es_impuesto,e="0"===l.activo?l.monto:0,a="1"===t,e=parseFloat(parseFloat(e)/100).toFixed(2),e="I.G.V"===l.descripcion?e:parseFloat(parseFloat(i)*parseFloat(e)).toFixed(2);u.push({id:o,descripcion:l.descripcion,importe:xMoneda(e),esImpuesto:t,visible:!0,quitar:!1,tachado:!1,visible_cpe:a})}}),(o=localStorage.getItem("::app3_woDUS::cxe"))&&((rpt={id:-2,descripcion:"ENTREGA",isDeliveryApp:!0,esImpuesto:0,visible:!0,quitar:!1,tachado:!1,visible_cpe:!1}).importe=parseFloat(o.toString()).toFixed(2),u.push(rpt)),s=u.filter(o=>"I.G.V"!==o.descripcion).map(o=>o.importe).reduce((o,t)=>parseFloat(o)+parseFloat(t)),o=u.filter(o=>"Sub Total"===o.descripcion)[0],e=u.filter(o=>"I.G.V"===o.descripcion)[0],a=parseFloat(e.importe),r=parseFloat(o.importe),0<a&&(r=parseFloat(xCalcMontoBaseIGV(r,a)),a=parseFloat(s-r).toFixed(2),e.importe=a,o.importe=r.toFixed(2)),(u=1==u.length?[]:u).push({descripcion:"Total",importe:xMoneda(s),visible:!0,visible_cpe:!0}),xLocal_xDtSubTotales=u,xSumCantImporte=s}function checkSubTotalQuitado(o,t,e){var a;if(xLocal_SubTotal_Quitados)return a=xLocal_SubTotal_Quitados.toLowerCase().split(",").sort().filter(o=>o===t).length,a=parseInt(a)>=parseFloat(o),(o=0<=xLocal_SubTotal_Quitados.indexOf(t))||(xSumCantImporte+=parseFloat(e)),a&&o}var groupBy=function(o,e){return o.reduce(function(o,t){return(o[t[e]]=o[t[e]]||[]).push(t),o},{})};function darFormatoSubTotalesDelivery(o=null){return o[o.length-1].importe=o.filter(o=>-2!==o.id&&-3!==o.id&&"TOTAL"!==o.descripcion.toUpperCase()).map(o=>parseFloat(o.importe)).reduce((o,t)=>o+t,0),xLocal_xDtSubTotales=o.filter(o=>-2!==o.id&&-3!==o.id)}function addCostoDeliveryArrTotal(e,o,t=!1,a=!1,i=!1,r=!1,s=!1){var p,c=0,l=e.pop();return e.map(t=>{i&&(-1<t.descripcion.toLowerCase().indexOf("entrega")||-1<t.descripcion.toLowerCase().indexOf("delivery"))&&(e=e.filter(o=>o.id!==t.id),p=t)}),o.filter(o=>o.id<0).map(o=>{t&&!a&&!i&&"entrega"===o.descripcion.toLowerCase()||(c+=parseFloat(o.importe),e.push(o))}),r&&!s&&p&&e.push(p),l.importe=(parseFloat(l.importe)+c).toFixed(2),e.push(l),e}function getImporteTotalSubTotalesDelivery(o=null){var t=o[o.length-1],o=(o=o.filter(o=>o),t.importe=o.filter(o=>"total"!==o.descripcion.toLowerCase()).map(o=>parseFloat(o.importe)).reduce((o,t)=>o+t,0),o.filter(o=>o.tachado).map(o=>parseFloat(o.importe_tachado)).reduce((o,t)=>o+t,0));return t.importe=(parseFloat(t.importe)-o).toFixed(2),t.importe}function colocarDescuentoSubtotales(o,t,e="",a=!0){var i=t[t.length-1],r=t.filter(o=>"I.G.V"===o.descripcion)[0],s=t[0],a=(o=a?o:0,t.filter(o=>6===o.id)[0]),p=a?-1*a.importe:0;i.importe=i.importe+p,a?(a.importe=-o,a.descripcion="Descuento "+e):(p=t.length-1,t.splice(p,0,{descripcion:"Descuento "+e,esImpuesto:1,id:6,importe:-o,quitar:!1,tachado:!1,visible:!1,visible_cpe:!1})),i.importe=i.importe-o;a=xm_log_get("carta_subtotales").filter(o=>"I.G.V"===o.descripcion)[0];return"0"===a.activo.toString()?(r=t.filter(o=>"I.G.V"===o.descripcion)[0],p=parseFloat(parseFloat(a.monto)/100),e=xCalcMontoBaseIGV(i.importe,p),o=parseFloat(i.importe-e).toFixed(2),r.importe=parseFloat(o).toFixed(2),s.importe=(parseFloat(i.importe)-o).toFixed(2)):i.importe=t.filter(o=>"TOTAL"!==o.descripcion.toUpperCase()).map(o=>parseFloat(o.importe)).reduce((o,t)=>o+t,0),t}function darFormatoSubTotalesComisionRepartidor(o,t,e,a=!1){return"1"!==o.pwa_delivery_servicio_propio||a?("1"!==o.pwa_delivery_comercio_paga_entrega&&!a||(a=t.length-1,t.splice(a,0,{descripcion:"Costo de Entrega",esImpuesto:0,id:-4,importe:-e,quitar:!0,tachado:!1,visible:!1,visible_cpe:!1})),a=t[t.length-1],"1"===o.pwa_delivery_servicio_propio?(a.importe=t.filter(o=>"TOTAL"!==o.descripcion.toUpperCase()).map(o=>parseFloat(o.importe)).reduce((o,t)=>o+t,0),t):(a.importe=t.filter(o=>-2!==o.id&&-3!==o.id&&"TOTAL"!==o.descripcion.toUpperCase()).map(o=>parseFloat(o.importe)).reduce((o,t)=>o+t,0),t.filter(o=>-2!==o.id&&-3!==o.id))):t}function darFormatoSubTotalesParaFacturacion(o,t=!0){var e,a,i=o.filter(o=>-4===o.id)[0];return i?(e=o[o.length-1],a=parseFloat(e.importe),t&&(a+=-1*i.importe),e.importe=a.toFixed(2),o.filter(o=>-4!==o.id)):o}