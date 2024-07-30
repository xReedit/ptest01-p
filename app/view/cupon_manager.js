async function loadCuponesActivos(){var o=await(new httpFecht).axiosExecuteJSON({url:"../../bdphp/log_009.php?op=6004",method:"GET"});0<o.datos.length?window.localStorage.setItem("::app3_sys_cupones_activos",JSON.stringify(o.datos)):window.localStorage.removeItem("::app3_sys_cupones_activos")}function checkCuponesActivos(t){var o=window.localStorage.getItem("::app3_sys_cupones_activos");o&&JSON.parse(o).forEach(async o=>{await printerCupon(o,t.cliente)})}async function generarCuponAutomatico(o){o=await(new httpFecht).axiosExecuteJSON({url:"../../bdphp/log_009.php?op=6005",method:"POST",data:{idcupon:o}});return o.codigo}async function printerCupon(t,e){var a=xgetComprobanteImpresora(-2),i=a?xImpresoraPrint[0]:{},i=a?{des_sede:i.des_sede,ciudad:i.ciudad,ip_print:i.ip_print}:{},d=""!==e.telefono;if(a||d){let o=t.cupon_manual;"1"===t.is_automatico&&(o=await generarCuponAutomatico(t.idcupon));var n=xm_log_get("sede_generales")[0],c=(t.codigo=o,"0"==t.cantidad_maxima?"No hay límite de canjes":`Los primeros ${t.cantidad_maxima} cupones pueden ser canjeados`),c=`*${t.titulo}* 
 ${t.descripcion} 
 *Fecha de canje: ${t.fecha_inicio} al ${t.fecha_termina}*. 
 ${c}.`,n={sede:{nombre:n.des_sede,ciudad:n.ciudad},codigo:o,body:c},c={cupon:n,Array_print:[i]};a&&xSendDataPrintServer(c,3,"cupon"),d&&(c.telefono=e.telefono,c.msj=`*CUPÓN DE DESCUENTO* 

 ${n.sede.nombre} ${n.sede.ciudad} 
 ${n.body} 

 *Código:* ${n.codigo} 

 *Gracias por preferirnos.*`,_cpSocketCuponWhatsApp(c)),(new httpFecht).axiosExecuteJSON({url:"../../bdphp/log_009.php?op=6008",method:"POST",data:{idcupon:t.idcupon}})}}async function validarCodigoCupon(t){var e=window.localStorage.getItem("::app3_sys_cupones_activos");if(e){e=JSON.parse(e);let o=e.find(o=>o.cupon_manual===t);if(o)return o.idcupon_codigo=null,o.activado="0",o;var a=await(new httpFecht).axiosExecuteJSON({url:"../../bdphp/log_009.php?op=6006",method:"POST",data:{codigo:t}});if(0<a.datos.length){let t=a.datos[0].idcupon;return!!(o=e.find(o=>o.idcupon===t))&&(o.idcupon_codigo=a.datos[0].idcupon_codigo,o.activado=a.datos[0].activado,o)}}return!1}function getAllItemsCuentaPedido(o){const a="tb_det_pedidos"==o?"#td_importe":"#ptotal";let i=[];return $("#"+o).find(".row").each(function(o,t){var e=$(t).data("procede"),e={iditem:$(t).data("iditem"),idseccion:$(t).data("idseccion"),idproducto_stock:0!=e&&$(t).data("idproducto_stock")||0,procede:$(t).data("procede"),ptotal:$(t).data("ptotal"),rowImporte:$(t).find(a)};i.push(e)}),i}function showMsjCuponNoAplica(){var o=paramsSwalAlert;o.html=`<div class="p-1" style="overflow: hidden;"><i class="fa fa-ticket fa-2x text-danger" aria-hidden="true"></i>
                                <p class="mt-1">No hay productos en la compra actual que apliquen para este cupón.</p>
                            </div>`,showAlertSwalHtml(o)}function showMsjCuponCanjeado(){var o=paramsSwalAlert;o.html=`<div class="p-1" style="overflow: hidden;"><i class="fa fa-meh-o fa-2x text-danger" aria-hidden="true"></i>
                                <p class="mt-1">Este cupón ya ha sido utilizado en una compra anterior y no puede ser canjeado nuevamente.</p>
                            </div>`,showAlertSwalHtml(o)}async function aplicarCuponDsct(o,e="tb_det_pedidos"){o=await validarCodigoCupon(o);let a=!1,d=0;if(o){if("1"==o.activado)return showMsjCuponCanjeado(),{aplica:!1,importeDsct:0,cupon:null};var i=JSON.parse(o.cupon_detalle);i.map(o=>{o.idproducto_stock=0==o.idproducto_stock?null:o.idproducto_stock,o.idseccion=0==o.idseccion?null:o.idseccion,o.iditem=0==o.iditem?null:o.iditem});let t=getAllItemsCuentaPedido(e);i.filter(i=>{var o=t.filter(o=>o.idproducto_stock==i.idproducto_stock||o.idseccion==i.idseccion||o.iditem==i.iditem);0<o.length&&(a=!0,o.forEach(o=>{var t,e,a;"0"==i.tipo_dsct?(t=(a=parseFloat(o.ptotal))-(e=a*parseFloat(i.dsct)/100),d+=e,o.rowImporte.html(`<s style="color: red;">${a.toFixed(2)}</s> <span id="importe_descuento" class="text-success fw-600 animate__animated animate__flash">${t.toFixed(2)}</span>`)):(a=(e=parseFloat(o.ptotal))-parseFloat(i.dsct),d+=importeDsct,o.rowImporte.html(`<s style="color: red;">${e.toFixed(2)}</s> <span id="importe_descuento" class="text-success fw-600 animate__animated animate__flash">${a.toFixed(2)}</span>`))}))});a||showMsjCuponNoAplica()}return{aplica:a,importeDsct:d,cupon:o}}function removeCuponDsct(o){const a="tb_det_pedidos"==o?"#td_importe":"#ptotal";$("#"+o).find(".row").each(function(o,t){var e;0<$(t).find("#importe_descuento").length&&($(t).find("#importe_descuento").remove(),e=$(t).find("s").text(),$(t).find("s").remove(),$(t).find(a).text(e))})}async function setCountCuponCanjeado(o){await(new httpFecht).axiosExecuteJSON({url:"../../bdphp/log_009.php?op=6007",method:"POST",data:{idcupon_codigo:o.idcupon_codigo,idcupon:o.idcupon}})}