var xImpresoraPrint;async function xCocinarImprimirComprobante(xArrayCuerpo,xArraySubTotales,xArrayComprobante,xArrayCliente,idregistro_pago,xidDoc,showPrint=true){let rptPrint={}
if(xArrayComprobante&&xArrayComprobante.idtipo_comprobante==="0"){rptPrint.imprime=false;return rptPrint;}
if(showPrint){if(!xgetComprobanteImpresora(xidDoc)){rptPrint.imprime=false;}}
if(xArrayCuerpo.length==0){rptPrint.imprime=false;rptPrint.ok=false;rptPrint.msj='Los datos del comprobante no son correctos.';return rptPrint;}
var xArrayEncabezado=xm_log_get('datos_org_sede');const index_total=xArraySubTotales.length-1;const total_pagar=parseFloat(xArraySubTotales[index_total].importe);xArraySubTotales[index_total].importe_letras=numeroALetras(total_pagar);if(showPrint){xArrayComprobante.pie_pagina_comprobante=xImpresoraPrint[0].pie_pagina_comprobante;}
rptPrint=await xJsonSunatCocinarDatos(xArrayCuerpo,xArraySubTotales,xArrayComprobante,xArrayCliente,idregistro_pago);if(!rptPrint.ok){alert(rptPrint.msj_error+". Mande a imprimir el comprobante desde Registro de pagos");xPopupLoad.close;return rptPrint;}
console.log(rptPrint);xArrayEncabezado[0].hash=rptPrint.hash;xArrayEncabezado[0].external_id=rptPrint.external_id;xArrayComprobante.correlativo=rptPrint.correlativo_comprobante||xArrayComprobante.correlativo;xArrayComprobante.facturacion_correlativo_api=rptPrint.facturacion_correlativo_api||xArrayComprobante.facturacion_correlativo_api;if(!showPrint){return xArrayEncabezado;}
xImprimirComprobanteAhora(xArrayEncabezado,xArrayCuerpo,xArraySubTotales,xArrayComprobante,xArrayCliente,function(rpt_print){if(rpt_print==false){return false;}
xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);return true;});}
function xImprimirComprobanteAhora(xArrayEncabezado,xArrayCuerpo,xArraySubtotal,xArrayComprobante,xArrayCliente,callback){xPopupLoad.titulo="Imprimiendo...";let _arrBodyComprobante=xEstructuraItemsJsonComprobante(xArrayCuerpo,xArraySubtotal,true);_arrBodyComprobante=xEstructuraItemsAgruparPrintJsonComprobante(_arrBodyComprobante);const _sys_local=parseInt(xm_log_get('datos_org_sede')[0].sys_local);xArrayEncabezado[0].nom_us=xm_log_get('app3_us').nomus;const _data={Array_enca:xArrayEncabezado,Array_print:xImpresoraPrint,ArrayItem:_arrBodyComprobante,ArraySubTotales:xArraySubtotal,ArrayComprobante:xArrayComprobante,ArrayCliente:xArrayCliente}
if(_sys_local===1){xPopupLoad.xopen();xSendDataPrintServer(_data,2,'comprobante');setTimeout(()=>{xPopupLoad.xclose();callback(false);},1000);return;}
$.ajax({type:'POST',url:'../../print/print5.php',data:{Array_enca:xArrayEncabezado,Array_print:xImpresoraPrint,ArrayItem:_arrBodyComprobante,ArraySubTotales:xArraySubtotal,ArrayComprobante:xArrayComprobante,ArrayCliente:xArrayCliente}}).done(function(dtPbd){if(dtPbd.indexOf('Error')!=-1){xPopupLoad.xclose();$("#print_error").text(dtPbd);xErrorPrint=true;dialog_erro_print.open();}else{xErrorPrint=false;xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);}
return callback(xErrorPrint);});}
function xImprimirComprobanteAhoraPrintPreSelect(xArrayEncabezado,xArrayCuerpo,xArraySubtotal,xArrayComprobante,xArrayCliente,xArrImpresora,callback){xPopupLoad.titulo="Imprimiendo...";const _sys_local=parseInt(xm_log_get('datos_org_sede')[0].sys_local);xArrayEncabezado[0].nom_us=xm_log_get('app3_us').nomus;const _data={Array_enca:xArrayEncabezado,Array_print:xArrImpresora,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal,ArrayComprobante:xArrayComprobante,ArrayCliente:xArrayCliente}
if(_sys_local===1){xPopupLoad.xopen();xSendDataPrintServer(_data,2,'comprobante');setTimeout(()=>{xPopupLoad.xclose();callback(false);},1000);return;}
$.ajax({type:'POST',url:'../../print/print5.php',data:{Array_enca:xArrayEncabezado,Array_print:xArrImpresora,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal,ArrayComprobante:xArrayComprobante,ArrayCliente:xArrayCliente}}).done(function(dtPbd){if(dtPbd.indexOf('Error')!=-1){xPopupLoad.xclose();$("#print_error").text(dtPbd);xErrorPrint=true;dialog_erro_print.open();}else{xErrorPrint=false;xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);}
return callback(xErrorPrint);});}
function xgetComprobanteImpresora(xidDoc){var xArrayImpresoras=xm_log_get('app3_woIpPrint');var xDtTipoDoc=xm_log_get('app3_woIpPrintO');var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");xImpresoraPrint=xm_log_get("sede_generales");const num_copias_all=xImpresoraPrint[0].num_copias;var xIpPrintDoc=xidDoc;var xpasePrint=false;for(var i=0;i<xDtTipoDoc.length;i++){if(xDtTipoDoc[i].idtipo_otro==xidDoc){xIpPrintDoc=xDtTipoDoc[i].idimpresora;break;}};if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xImpresoraPrint[0].ip_print=xPrintLocal.ip;xImpresoraPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xImpresoraPrint[0].var_size_font=xPrintLocal.var_size_font
xImpresoraPrint[0].local=1;xImpresoraPrint[0].num_copias=xPrintLocal.num_copias;xImpresoraPrint[0].copia_local=xPrintLocal.copia_local;xImpresoraPrint[0].img64=xPrintLocal.img64;xImpresoraPrint[0].papel_size=xPrintLocal.papel_size;xpasePrint=true;}else{for(var i=0;i<xArrayImpresoras.length;i++){if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){xpasePrint=true;xIpPrintDoc=xArrayImpresoras[i].ip;xImpresoraPrint[0].ip_print=xIpPrintDoc;xImpresoraPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;xImpresoraPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;xImpresoraPrint[0].local=0;xImpresoraPrint[0].num_copias=num_copias_all;xImpresoraPrint[0].copia_local=0;xImpresoraPrint[0].img64=xArrayImpresoras[i].img64;xImpresoraPrint[0].papel_size=xArrayImpresoras[i].papel_size;break;}}}
return xpasePrint;}
function xgetImpresora(xidDoc){var xArrayImpresoras=xm_log_get('app3_woIpPrint');var xDtTipoDoc=xm_log_get('app3_woIpPrintO');var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");xImpresoraPrint=xm_log_get("sede_generales");const num_copias_all=xImpresoraPrint[0].num_copias;const papel_size=xImpresoraPrint[0].papel_size;var xIpPrintDoc=xidDoc;var xpasePrint=false;for(var i=0;i<xDtTipoDoc.length;i++){if(xDtTipoDoc[i].idtipo_otro==xidDoc){xIpPrintDoc=xDtTipoDoc[i].idimpresora;break;}};if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xImpresoraPrint[0].ip_print=xPrintLocal.ip;xImpresoraPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xImpresoraPrint[0].var_size_font=xPrintLocal.var_size_font
xImpresoraPrint[0].local=1;xImpresoraPrint[0].num_copias=xPrintLocal.num_copias;xImpresoraPrint[0].copia_local=xPrintLocal.copia_local;xImpresoraPrint[0].img64=xPrintLocal.img64;xImpresoraPrint[0].papel_size=xPrintLocal.papel_size;xpasePrint=true;}else{for(var i=0;i<xArrayImpresoras.length;i++){if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){xpasePrint=true;xIpPrintDoc=xArrayImpresoras[i].ip;xImpresoraPrint[0].ip_print=xIpPrintDoc;xImpresoraPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;xImpresoraPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;xImpresoraPrint[0].local=0;xImpresoraPrint[0].num_copias=num_copias_all;xImpresoraPrint[0].copia_local=0;xImpresoraPrint[0].img64=xArrayImpresoras[i].img64;xImpresoraPrint[0].papel_size=xArrayImpresoras[i].papel_size;break;}}}
const print_return=xpasePrint?xImpresoraPrint:null;return print_return;}
function xCocinarImprimirComanda(xArrayEnca,xArrayCuerpo,xArraySubTotales,callback){if(xArrayCuerpo.length===0)return;var xArrayImpresoras=xm_log_get('app3_woIpPrint');var xDtTipoDoc=xm_log_get('app3_woIpPrintO');var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");var xImpresoraPrint=xm_log_get('sede_generales');var xcuentaSeccionesImpresas=0;var xCuentaImpresorasEvaluadas=0;const num_copias_all=xImpresoraPrint[0].num_copias;const var_size_font_tall_comanda=xImpresoraPrint[0].var_size_font_tall_comanda;if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xArmarSubtotalesArray(xArrayCuerpo,xImpresoraPrint)
xImpresoraPrint[0].ip_print=xPrintLocal.ip;xImpresoraPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xImpresoraPrint[0].var_size_font=xPrintLocal.var_size_font;xImpresoraPrint[0].local=1;xImpresoraPrint[0].num_copias=xPrintLocal.num_copias;xImpresoraPrint[0].copia_local=xPrintLocal.copia_local;xImpresoraPrint[0].img64=xPrintLocal.img64;xImpresoraPrint[0].papel_size=xPrintLocal.papel_size;if(xPrintLocal.img64==="0"){xImpresoraPrint[0].logo64='';}
if(parseInt(xPrintLocal.num_copias)!=0||parseInt(xPrintLocal.copia_local)!=0){xImprimirComandaAhora(xArrayEnca,xImpresoraPrint,xArrayCuerpo,xArraySubTotales,(res)=>{callback(res);});}}
for(var z=0;z<xArrayImpresoras.length;z++){xIdPrint=xArrayImpresoras[z].idimpresora;xArrayBodyPrint=[];xCuentaImpresorasEvaluadas++;for(var i=0;i<xArrayCuerpo.length;i++){if(xArrayCuerpo[i]==null){continue;}
$.map(xArrayCuerpo[i],function(xn_p,z){if(typeof xn_p=="object"){if(xn_p.imprimir_comanda==='0')return;if(xIdPrint==xn_p.idimpresora){if(xArrayBodyPrint[i]===undefined){xArrayBodyPrint[i]={'des':xArrayCuerpo[i].des,'id':xArrayCuerpo[i].id,'titlo':xArrayCuerpo[i].titulo};}
xArrayBodyPrint[i][xn_p.iditem]=xn_p;}}})}
if(xArrayBodyPrint.length==0){continue}
xcuentaSeccionesImpresas++;xArmarSubtotalesArray(xArrayBodyPrint,xImpresoraPrint)
xImpresoraPrint[0].ip_print=xArrayImpresoras[z].ip;xImpresoraPrint[0].var_margen_iz=xArrayImpresoras[z].var_margen_iz;xImpresoraPrint[0].var_size_font=xArrayImpresoras[z].var_size_font;xImpresoraPrint[0].local=0;xImpresoraPrint[0].num_copias=num_copias_all;xImpresoraPrint[0].var_size_font_tall_comanda=var_size_font_tall_comanda;xImpresoraPrint[0].copia_local=0;xImpresoraPrint[0].img64=xArrayImpresoras[z].img64;xImpresoraPrint[0].papel_size=xArrayImpresoras[z].papel_size;if(xArrayImpresoras[z].img64==="0"){xImpresoraPrint[0].logo64='';}
xImprimirComandaAhora(xArrayEnca,xImpresoraPrint,xArrayBodyPrint,xArraySubTotales,function(rpt_print){if(xArrayImpresoras.length==xCuentaImpresorasEvaluadas&&rpt_print==false){if(callback){callback(false);};}
else{if(callback){callback(true);}}});};if(xcuentaSeccionesImpresas===0){if(callback){callback(false)};}}
function xImprimirComandaAhora(xArrayEncabezado,xImpresoraPrint,xArrayCuerpo,xArraySubtotal,callback){xPopupLoad.titulo="Imprimiendo...";const _sys_local=parseInt(xm_log_get('datos_org_sede')[0].sys_local);xArrayEncabezado.nom_us=xm_log_get('app3_us').nomus;const _data={Array_enca:xArrayEncabezado,Array_print:xImpresoraPrint,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal}
if(_sys_local===1){xSendDataPrintServer(_data,1,'comanda');setTimeout(()=>{callback(false);},300);return;}
$.ajax({type:'POST',url:'../../print/print3.php',data:{Array_enca:xArrayEncabezado,Array_print:xImpresoraPrint,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal},success:function(dtPbd){if(dtPbd.indexOf('Error')!=-1){xPopupLoad.xclose();$("#print_error").text(dtPbd);xErrorPrint=true;}else{xErrorPrint=false;xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);}
callback(xErrorPrint);},error:function(XMLHttpRequest,textStatus,errorThrown){console.log(errorThrown);xPopupLoad.xclose();$("#print_error").text("Error, Verifique que la ticketera este prendida y que tenga papel.");xErrorPrint=true;callback(xErrorPrint);}});}
function xSendDataPrintServer(_data,_idprint_server_estructura,_tipo){switch(_idprint_server_estructura){case 3:break;case 4:if(_data.Array_enca[0].ip_print==='')return;break;default:if(_data.Array_print[0].ip_print==='')return;_data.Array_print[0].logo64='';_data.Array_print[0].logo='';break;}
_data=JSON.stringify(_data);$.ajax({url:'../../bdphp/log_003.php?op=1',type:'POST',data:{datos:_data,idprint_server_estructura:_idprint_server_estructura,tipo:_tipo}}).done((UltimoIdPrint)=>{setTimeout(()=>{$.ajax({url:'../../bdphp/log_003.php?op=101',type:'POST',data:{id:UltimoIdPrint}}).done((x)=>{if(parseInt(x)===1){alert('Error con la impresora: Verifique el ip, que este prendida y que tenga papel');}})},2500);});}
function xReturnCorrelativoComprobante(_obj){let _rpt;if(_obj.codsunat==='0'){_rpt=parseInt(_obj.correlativo)+1;_rpt=xCeroIzq(_rpt,7);$.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'103',i:_obj.idtipo_comprobante_serie}});}else{const tomaDelApi=parseInt(_obj.facturacion_correlativo_api)===0?false:true;_rpt=tomaDelApi?'#':parseInt(_obj.correlativo)+1;if(!tomaDelApi){_obj.facturacion_correlativo_api=1;}}
return _rpt;}