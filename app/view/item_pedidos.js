var xArrayPedidoObj,xidTipoConsumo,xidItem,xCambioCantidad=false,xLocal_xDtSubTotales,xLocal_TotalImporte=0,xErrorPrint=false,xArrayDesTipoConsumo=[],xVerificarImprimirComanda=false,xGeneralDataCarta,xGeneralRelojUpdateItemsSolo,xGeneralRelojUpdateItemsCambioBd,xDisparaUpdateItems=new Event('GeneralUpdateItemsSolo'),xGeneralNumPedidosActual=0,xGeneralUpdateSeccion=0,xGeneralDataSeccion,xNumPedidosBD=0,xGeneralArraySubTotales=[],itemPedidos_objItemSelected=[],objOptionItemSelect;$(document.body).on('click','#_xSubMenu_body div.xBtn',handlerFnMiPedido);$(document.body).on('click','#_xdlgBody div.xBtn',handlerFnMiPedido);function handlerFnMiPedido(e){const _thisObj=$(this);xCambioCantidad=true;xidItem=itemPedidos_objItemSelected.idcarta_lista
xidTipoConsumo=_thisObj.parent().attr('data-id');var xOperacion=_thisObj.text(),objCant=_thisObj.parent().find('.xCant_item'),objCant_cant=xArrayPedidoObj[xidTipoConsumo]?xArrayPedidoObj[xidTipoConsumo][xidItem]?xArrayPedidoObj[xidTipoConsumo][xidItem]['cantidad']:0:0,xStockActual=itemPedidos_objItemSelected.stock_actual,xidItem2=itemPedidos_objItemSelected.iditem===xidItem?itemPedidos_objItemSelected.iditem2?itemPedidos_objItemSelected.iditem2:itemPedidos_objItemSelected.iditem:itemPedidos_objItemSelected.iditem,xDesItem=itemPedidos_objItemSelected.des_item,xPrecioItem=itemPedidos_objItemSelected.precio,xIndicaciones=itemPedidos_objItemSelected.xindicaciones,xCantActual=parseInt(objCant_cant),xCantSeccion=parseInt(xArrayPedidoObj[xidTipoConsumo]['cantidad']),xCantTotalItem=0,xDesSeccion=xTituloDet||itemPedidos_objItemSelected.des_seccion,xIdSeccionItem=itemPedidos_objItemSelected.idseccion,xIdSeccionItem_index=itemPedidos_objItemSelected.idseccion_index,xRowidimpresora=itemPedidos_objItemSelected.idimpresora,xRowidporcion=itemPedidos_objItemSelected.idprocede,xRowProcede=itemPedidos_objItemSelected.procede,xRowProcede_index=itemPedidos_objItemSelected.procede_index,ximprmir_comanda=itemPedidos_objItemSelected.imprimir_comanda,xcant_descontar=itemPedidos_objItemSelected.cant_descontar,xidalmacen_items=itemPedidos_objItemSelected.idalmacen_items,xidDescontar=xRowidporcion,xPrecioTotal;xCantActual=isNaN(xCantActual)?0:xCantActual;xCantSeccion=isNaN(xCantSeccion)?0:xCantSeccion;_thisObj.parents('.xpedir_item').find('.xCant_item').each(function(index,element){var xval=parseInt($(element).text());if(isNaN(xval)){xval=0}
xCantTotalItem=parseInt(xCantTotalItem)+xval});if(isNaN(xCantTotalItem)){xCantTotalItem=0}
xCantActual=parseInt(xCantActual)+xOperacion+parseInt(1);xCantTotalItem=parseInt(xCantTotalItem)+xOperacion+parseInt(1);xCount_cant_ico=parseInt(xCount_cant_ico)+xOperacion+parseInt(1);xCantSeccion=parseInt(xCantSeccion)+xOperacion+parseInt(1);xCantActual=eval(xCantActual);xCantTotalItem=eval(xCantTotalItem);xCount_cant_ico=eval(xCount_cant_ico);xCantSeccion=eval(xCantSeccion);if(xCount_cant_ico<0){xCount_cant_ico=0;}
if(xStockActual!='ND'){if(xCantTotalItem>parseInt(xStockActual)){return;}}
if(xCantActual<=0){xCantActual=0;objCant.addClass('xInvisible');}else{objCant.removeClass('xInvisible');}
objCant.text(xCeroIzq(xCantActual,2));xPrecioTotal=parseFloat(xCantActual*xPrecioItem).toFixed(2);xArrayPedidoObj[xidTipoConsumo]["cantidad"]=xCantSeccion;xArrayPedidoObj[xidTipoConsumo][xidItem]={'idcategoria':xidCategoria,'idseccion':xIdSeccionItem,'idseccion_index':xIdSeccionItem_index,'des_seccion':xDesSeccion,'iditem':xidItem,'idtipo_consumo':xidTipoConsumo,'stock_actual':xStockActual,'cantidad':xCantActual,'precio':xPrecioItem,'des':xDesItem,'precio_total':xPrecioTotal,'precio_total_calc':xPrecioTotal,'precio_print':'','indicaciones':xIndicaciones,'iditem2':xidItem2,'idimpresora':xRowidimpresora,'idprocede':xRowidporcion,'procede':xRowProcede,'procede_index':xRowProcede_index,'imprimir_comanda':ximprmir_comanda,'iddescontar':xidDescontar,'cant_descontar':xcant_descontar,'idalmacen_items':xidalmacen_items,'visible':0};if(xCantActual<=0){delete xArrayPedidoObj[xidTipoConsumo][xidItem]}
window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))
window.localStorage.setItem("::app3_sys_dta_count_ico",xCount_cant_ico);const _objIcoPedido=$("#_xIco_MiPedido .xCantPedio_ico");if(xCount_cant_ico>0){_objIcoPedido.text(xCeroIzq(xCount_cant_ico,2));_objIcoPedido.removeClass('xInvisible');}else{_objIcoPedido.addClass('xInvisible');}
const xVistaMiPedido=document.getElementById('mipedido-view');if(xVistaMiPedido){xVistaMiPedido._outLoadPedido();}
e.stopPropagation();e.stopImmediatePropagation()
return false;}
$(document.body).on('click','#content_item_pedido div.xBtn_li',handlerFnMiPedidoControl);$(document.body).on('click','#accordion div.xBtn_contet_li2',handlerFnMiPedidoControl);$(document.body).on('click','#accordion div.content_li',handlerFnMiPedidoControl);function handlerFnMiPedidoControl(e){var _nomClassXcant_li='xcant_li';const _thisObj=e.target||e;const _objParentLi=_thisObj.dataset.index?_thisObj:_thisObj.parentElement.dataset.index?_thisObj.parentElement:_thisObj.parentElement.parentElement.dataset.index?_thisObj.parentElement.parentElement:_thisObj.parentElement.parentElement.parentElement;const _dataSetObj=_objParentLi.dataset;const _viene_venta_rapida=_dataSetObj.ventarapida;const _itemIndex=_dataSetObj.index
xidTipoConsumo=$("#select_ulTPC option:selected").val();itemPedidos_objItemSelected=xGeneralDataCarta[_itemIndex];if(_viene_venta_rapida==1){_nomClassXcant_li='xcant_li2';}
xidItem=itemPedidos_objItemSelected.idcarta_lista;var element_cant_li_sel=_objParentLi.getElementsByClassName(_nomClassXcant_li);element_cant_li_sel=$(element_cant_li_sel);var xsigno=_thisObj.textContent!='+'&&_thisObj.textContent!='-'?_dataSetObj.signo:_thisObj.textContent,objCant_cant=xArrayPedidoObj[xidTipoConsumo]?xArrayPedidoObj[xidTipoConsumo][xidItem]?xArrayPedidoObj[xidTipoConsumo][xidItem]['cantidad']:0:0,xcant=parseInt(objCant_cant),xcant_max=itemPedidos_objItemSelected.stock_actual,xli_tipoconsumo=xidTipoConsumo,xli_iditem=xidItem;var xli_des=itemPedidos_objItemSelected.des_item,xli_des_ref=itemPedidos_objItemSelected.indicaciones||'',xli_precio=itemPedidos_objItemSelected.precio,xli_idimpresora=itemPedidos_objItemSelected.idimpresora,xli_idprocede=itemPedidos_objItemSelected.idprocede,xli_Procede=itemPedidos_objItemSelected.procede,xli_Procede_index=itemPedidos_objItemSelected.procede_index,xidsecion=itemPedidos_objItemSelected.idseccion,xidsecion_index=itemPedidos_objItemSelected.idseccion_index,xdes_seccion=itemPedidos_objItemSelected.des_seccion,xidItem2=itemPedidos_objItemSelected.iditem===xidItem?itemPedidos_objItemSelected.iditem2?itemPedidos_objItemSelected.iditem2:itemPedidos_objItemSelected.iditem:itemPedidos_objItemSelected.iditem,xPrecioTotal=0,ximprmir_comanda=itemPedidos_objItemSelected.imprimir_comanda,xcant_descontar=itemPedidos_objItemSelected.cant_descontar,xidcategoria=itemPedidos_objItemSelected.idcategoria,xli_idalmacen_items=itemPedidos_objItemSelected.idalmacen_items,xStockActual=xcant_max;if(xsigno=='+'){if(xcant<xcant_max){xcant++;}}else{xcant--;xcant=xcant<=0?0:xcant;}
xPrecioTotal=parseFloat(parseFloat(xli_precio)*parseFloat(xcant)).toFixed(2);xArrayPedidoObj[xli_tipoconsumo][xli_iditem]={'idcategoria':xidcategoria,'idseccion':xidsecion,'idseccion_index':xidsecion_index,'des_seccion':xdes_seccion,'iditem':xli_iditem,'idtipo_consumo':xli_tipoconsumo,'stock_actual':xStockActual,'cantidad':xcant,'precio':xli_precio,'des':xli_des,'precio_total':xPrecioTotal,'precio_total_calc':xPrecioTotal,'precio_print':xPrecioTotal,'indicaciones':xli_des_ref,'iditem2':xidItem2,'idimpresora':xli_idimpresora,'idprocede':xli_idprocede,'procede':xli_Procede,'procede_index':xli_Procede_index,'imprimir_comanda':ximprmir_comanda,'cant_descontar':xcant_descontar,'idalmacen_items':xli_idalmacen_items,'visible':0};element_cant_li_sel.text(xcant);if(xcant<=0){xcant=0;element_cant_li_sel.removeClass('cant_fixed_li');delete xArrayPedidoObj[xli_tipoconsumo][xli_iditem];}else{element_cant_li_sel.addClass('cant_fixed_li')}
if(_viene_venta_rapida==1){xVerMipedidoVR();}
try{e.stopPropagation();e.stopImmediatePropagation()}catch(error){}}
function xBtnSumarRestarKey(xobj,xval){const nomClassBtn=xval>0?'.suma':'.resta';const element_cant_li_sel=$(xobj).parent().find(nomClassBtn)[0];handlerFnMiPedidoControl(element_cant_li_sel);}
$(document.body).on('keyup','.xMiTextReferencia',function(e){if(!xidTipoConsumo)return;const val_ref=e.target.value;itemPedidos_objItemSelected.indicaciones=val_ref;itemPedidos_objItemSelected.xindicaciones=val_ref;try{xArrayPedidoObj[xidTipoConsumo][xidItem].indicaciones=val_ref;}catch(error){}
window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))
e.stopPropagation();e.stopImmediatePropagation()
return false;})
function xClassEstadoItem(xCantItem){var xClassEstado='';var xClassEstadoStock='';if(xCantItem=='ND'){xClassEstado='xEstadoVerde';xClassEstadoStock='xFondoColorVerde';}
else{xCantItem=parseInt(xCantItem);if(xCantItem>=1){xClassEstado='xEstadoAmarillo';xClassEstadoStock='xFondoColorAmarillo';}
if(xCantItem>=10){xClassEstado='xEstadoVerde';xClassEstadoStock='xFondoColorVerde';}
if(xCantItem<=0){xClassEstado='xEstadoRojo';xClassEstadoStock='xFondoColorRojo';}}
return xClassEstado+'|'+xClassEstadoStock}
function xLoadArrayPedido(){xArrayPedidoObj=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));if(xArrayPedidoObj!==null){if(xArrayPedidoObj.length>0){return;}}
var xtpc_t=[];xArrayDesTipoConsumo=[];xArrayPedidoObj=[];xtpc_t=xm_log_get('estructura_pedido');xtpc_t.map(x=>{const indexTpc=x.idtipo_consumo;xArrayPedidoObj[indexTpc]={'id':x.idtipo_consumo,'des':x.descripcion,'titulo':x.titulo};xArrayDesTipoConsumo.push({'id':x.idtipo_consumo,'des':x.descripcion,'titulo':x.titulo});});window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedidoObj))}
function xVerificarSiSeImprimeComanda(xArray){if(xArray==null){xArray=xArrayPedidoObj}
xVerificarImprimirComanda=false;for(var i=0;i<xArray.length;i++){if(xArray[i]==null){continue;}
$.map(xArray[i],function(n,z){if(typeof n=="object"){if(n.cantidad!=0){if(n.imprimir_comanda==1){xVerificarImprimirComanda=true;return;}}}});}}
function xObtnerValSumArray(xArray,filter){var cuenta=0;for(var i=0;i<xArray.length;i++){if(xArray[i]==null){continue;}
$.map(xArray[i],function(n,z){if(typeof n=="object"){var xIdRowTb=n.idseccion;if(xIdRowTb===filter){cuenta=parseFloat(cuenta)+parseFloat(n.cantidad);}}});}
return cuenta;}
function xObtnerValSumTable(tb,BuscarEn,filter,subfind){var cuenta=0;$(tb).find(".row").each(function(index,element){var xIdRowTb=$(element).attr(BuscarEn);if(xIdRowTb===filter){cuenta=parseFloat(cuenta)+parseFloat($(element).find(subfind).text());}});return parseInt(cuenta);}
function xSumaCantArray(ArrySum){var suma=0;for(var i=0;i<ArrySum.length;i++){if(ArrySum[i]==null){continue;}
$.map(ArrySum[i],function(n,z){if(typeof n==="object"){const _xprecio_unitario=parseFloat(n.precio);let _xcantidad=n.cantidad;if(_xcantidad.toString().indexOf(",")>-1){_xcantidad=_xcantidad.split(',');_xcantidad=_xcantidad.reduce((a,b)=>parseFloat(a)+parseFloat(b));}
const importe_calculado_unitario=_xcantidad*_xprecio_unitario;let xp_print=n.precio_print===''?parseFloat(n.precio_total_calc):parseFloat(n.precio_print);let xprecio_p=xp_print>importe_calculado_unitario?_xprecio_unitario.toFixed(2):xp_print.toFixed(2);if(xprecio_p===""){xprecio_p=n.precio_total_calc;}
suma=suma+parseFloat(xprecio_p)}})}
return suma;}
function xMandarImprimirOtroDoc(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xidDoc){var xIdPrint=0;var xArrayBodyPrint=[];var xArrayImpresoras=xm_log_get('app3_woIpPrint');var xDtTipoDoc=xm_log_get('app3_woIpPrintO');var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");var xIpPrintDoc=xidDoc;var xpasePrint=false;for(var i=0;i<xDtTipoDoc.length;i++){if(xDtTipoDoc[i].idtipo_otro==-1){xIpPrintDoc=xDtTipoDoc[i].idimpresora;break;}};if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xArrayDatosPrint[0].ip_print=xPrintLocal.ip;xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font;xArrayDatosPrint[0].papel_size=xPrintLocal.papel_size;xArrayDatosPrint[0].num_copias=xPrintLocal.num_copias;xArrayDatosPrint[0].local=1;xpasePrint=true;}else{for(var i=0;i<xArrayImpresoras.length;i++){if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){xpasePrint=true;xIpPrintDoc=xArrayImpresoras[i].ip;xArrayDatosPrint[0].ip_print=xIpPrintDoc;xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;xArrayDatosPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;xArrayDatosPrint[0].papel_size=xArrayImpresoras[i].papel_size
xArrayDatosPrint[0].num_copias=xArrayImpresoras[i].num_copias;xArrayDatosPrint[0].copia_local=xArrayImpresoras[i].copia_local;xArrayDatosPrint[0].local=0;break;}}}
if(xpasePrint==false){return false;}
if(xArrayCuerpo.length==0){return false}
xArmarSubtotalesArray(xArrayCuerpo,xArrayDatosPrint)
xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xLocal_xDtSubTotales,function(rpt_print){if(rpt_print==false){return;}
xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);});}
function xMandarImprimirOtroDoc(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xArraySubTotales,xidDoc){var xArrayImpresoras=xm_log_get('app3_woIpPrint');var xDtTipoDoc=xm_log_get('app3_woIpPrintO');var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");var xIpPrintDoc=xidDoc;var xpasePrint=false;xArrayDatosPrint[0].var_size_font_tall_comanda=0;for(var i=0;i<xDtTipoDoc.length;i++){if(xDtTipoDoc[i].idtipo_otro==xidDoc){xIpPrintDoc=xDtTipoDoc[i].idimpresora;break;}};if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xArrayDatosPrint[0].ip_print=xPrintLocal.ip;xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font;xArrayDatosPrint[0].papel_size=xPrintLocal.papel_size;xArrayDatosPrint[0].num_copias=xPrintLocal.num_copias;xArrayDatosPrint[0].local=1;xpasePrint=true;}else{for(var i=0;i<xArrayImpresoras.length;i++){if(xArrayImpresoras[i].idimpresora==xIpPrintDoc){xpasePrint=true;xIpPrintDoc=xArrayImpresoras[i].ip;xArrayDatosPrint[0].ip_print=xIpPrintDoc;xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[i].var_margen_iz;xArrayDatosPrint[0].var_size_font=xArrayImpresoras[i].var_size_font;xArrayDatosPrint[0].papel_size=xArrayImpresoras[i].papel_size;xArrayDatosPrint[0].num_copias=xArrayImpresoras[i].num_copias;xArrayDatosPrint[0].copia_local=xArrayImpresoras[i].copia_local;xArrayDatosPrint[0].local=0;break;}}}
if(xpasePrint==false){return false;}
if(xArrayCuerpo.length==0){return false}
xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xArraySubTotales,function(rpt_print){if(rpt_print==false){return;}
xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);});}
function xMandarImprimir(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,responde){var xArrayImpresoras;var xIdPrint=0;var xArrayBodyPrint=new Array();var xCuentaImpresorasEvaluadas=0;var xcuentaSeccionesImpresas=0;var xPrintLocal=window.localStorage.getItem("::app3_woIpPrintLo");xArrayImpresoras=xm_log_get('app3_woIpPrint');if(xPrintLocal!=undefined&&xPrintLocal!=''){xPrintLocal=$.parseJSON(xPrintLocal);xArmarSubtotalesArray(xArrayCuerpo,xArrayDatosPrint)
xArrayDatosPrint[0].ip_print=xPrintLocal.ip;xArrayDatosPrint[0].var_margen_iz=xPrintLocal.var_margen_iz;xArrayDatosPrint[0].var_size_font=xPrintLocal.var_size_font;xArrayDatosPrint[0].local=1;xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xLocal_xDtSubTotales,function(rpt_print){if(rpt_print==false){return;}
xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);});}
for(var z=0;z<xArrayImpresoras.length;z++){xIdPrint=xArrayImpresoras[z].idimpresora;xArrayBodyPrint=new Array();xCuentaImpresorasEvaluadas++;for(var i=0;i<xArrayCuerpo.length;i++){if(xArrayCuerpo[i]==null){continue;}
$.map(xArrayCuerpo[i],function(xn_p,z){if(typeof xn_p=="object"){if(xIdPrint==xn_p.idimpresora){if(xArrayBodyPrint[i]===undefined){xArrayBodyPrint[i]={'des':xArrayCuerpo[i].des,'id':xArrayCuerpo[i].id,'titlo':xArrayCuerpo[i].titulo};}
xArrayBodyPrint[i][xn_p.iditem]=xn_p;}}})}
if(xArrayBodyPrint.length==0){continue}
xcuentaSeccionesImpresas++;xArmarSubtotalesArray(xArrayBodyPrint,xArrayDatosPrint)
xArrayDatosPrint[0].ip_print=xArrayImpresoras[z].ip;xArrayDatosPrint[0].var_margen_iz=xArrayImpresoras[z].var_margen_iz;xArrayDatosPrint[0].var_size_font=xArrayImpresoras[z].var_size_font;xArrayDatosPrint[0].local=0;xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayBodyPrint,xLocal_xDtSubTotales,function(rpt_print){if(xArrayImpresoras.length==xCuentaImpresorasEvaluadas&&rpt_print==false){setTimeout(function(){try{xNuevoPedidoMP();}catch(err){return false;}},2700);if(responde){responde(true)};}
else{if(responde){responde(false);}}});};if(xcuentaSeccionesImpresas==0){if(responde){responde(true)};}}
function xImprimirAhora(xArrayEncabezado,xArrayDatosPrint,xArrayCuerpo,xArraySubtotal,responde){xPopupLoad.titulo="Imprimiendo...";const _sys_local=parseInt(xm_log_get('datos_org_sede')[0].sys_local);xArrayEncabezado.nom_us=xm_log_get('app3_us').nomus;const _data={Array_enca:xArrayEncabezado,Array_print:xArrayDatosPrint,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal}
if(_sys_local===1){xPopupLoad.xopen();xSendDataPrintServer(_data,1,'pre cuenta');setTimeout(()=>{xPopupLoad.xclose();return responde(false);},1000);return;}
$.ajax({type:'POST',url:'../../print/print3.php',data:{Array_enca:xArrayEncabezado,Array_print:xArrayDatosPrint,ArrayItem:xArrayCuerpo,ArraySubTotales:xArraySubtotal}}).done(function(dtPbd){if(dtPbd.indexOf('Error')!=-1){xPopupLoad.xclose();$("#print_error").text(dtPbd);xErrorPrint=true;dialog_erro_print.open();}else{xErrorPrint=false;xPopupLoad.titulo="Imprimiendo...";xPopupLoad.xopen();setTimeout(function(){xPopupLoad.xclose()},3000);}
return responde(xErrorPrint);});}
function xBuscarCantidadPorSeccionArray(idtpc_ad,idseccion_ad,xArraySumT){var xcant_sec_ad=0;if(xArraySumT[idtpc_ad]==undefined){return xcant_sec_ad;}
$.map(xArraySumT[idtpc_ad],function(n,z){if(typeof n=="object"&&n!=null){if(n.idseccion==idseccion_ad){xcant_sec_ad=parseFloat(xcant_sec_ad)+parseFloat(n.cantidad);}}});return xcant_sec_ad;}
function xCargarCategoriaActual(responde){var xCategoriaActual;var xdtCat=xm_log_get('categorias');var xCadenaCartegoria='';for(var i=0;i<xdtCat.length;i++){xMenuCategoria={'id':xdtCat[i].idcategoria,'des':xdtCat[i].descripcion};}
if(xdtCat.length==1){xCategoriaActual=xdtCat[0].idcategoria}
return responde(xdtCat);}
function xGeneralLoadItems(xidCategoria,x_rpt){$.ajax({type:'POST',url:'../../bdphp/log.php?op=205',data:{'idcategoria':xidCategoria}}).done(function(dtCarta){var xdt_rpt=$.parseJSON(dtCarta)
xGeneralDataCarta=xdt_rpt.datos;if(x_rpt){return x_rpt(xGeneralDataCarta);}})}
function xGeneralSeccionMiPedido(xidCategoria,x_rpt){const ultima_categoria_cargada=localStorage.getItem('::app3_sys_last_cat_load');if(ultima_categoria_cargada===xidCategoria&&xGeneralDataSeccion!==undefined){return x_rpt(false);}
$.ajax({type:'POST',url:'../../bdphp/log.php?op=2041',data:{'idcategoria':xidCategoria}}).done(function(dtSecciones_mp){var xdtSecciones_mp=$.parseJSON(dtSecciones_mp)
if(!xdtSecciones_mp.success){alert(xdtSecciones_mp.error);return;}
xGeneralDataSeccion=xdtSecciones_mp.datos;localStorage.setItem('::app3_sys_last_cat_load',xidCategoria);if(x_rpt){return x_rpt(xGeneralDataSeccion);}})}
function xDisparaEventoLoadItemCambioBd(){clearInterval(xGeneralRelojUpdateItemsCambioBd);xGeneralRelojUpdateItemsCambioBd=setInterval(function(){xGeneralActualizarLoadItemCambioBd()},20000);}
function xGeneralActualizarLoadItemCambioBd(){$.ajax({type:'POST',url:'../../bdphp/log.php?op=3012'}).done(function(xCantPedidos){xNumPedidosBD=xCantPedidos;if(xGeneralNumPedidosActual!=xNumPedidosBD){xGeneralNumPedidosActual=1
xGeneralLoadItems();}})}
function xDisparaEventoLoadItemInactividad(){clearInterval(xGeneralRelojUpdateItemsSolo);xGeneralRelojUpdateItemsSolo=setInterval(function(){xGeneralActualizaItemInactividad()},110000);}
function xGeneralActualizaItemInactividad(){var xpaseRefreshItem=true;if(xArrayPedidoObj!=undefined){for(var y=0;y<xArrayPedidoObj.length;y++){if(xArrayPedidoObj[y]==null){continue;}
$.map(xArrayPedidoObj[y],function(n,z){if(typeof n=="object"){xpaseRefreshItem=false;}})
if(!xpaseRefreshItem){break;}}}
if(xpaseRefreshItem){if(xDisparaUpdateItems.cancelBubble==true){xDisparaUpdateItems=new Event('GeneralUpdateItemsSolo');}
document.dispatchEvent(xDisparaUpdateItems);}}
function xGeneralValidarRegalasCarta(xObjEvaluar,esarray){var xArrayRegla=xm_log_get('reglas_de_carta');var xSecc_bus='';var xSecc_detalle;var xCantidadBuscar=0;xVerificarImprimirComanda=false;if(xArrayPedidoObj!=undefined){for(var y=0;y<xArrayPedidoObj.length;y++){if(xArrayPedidoObj[y]==null){continue;}
$.map(xArrayPedidoObj[y],function(n,z){if(typeof n=="object"){n.precio_print='';n.precio_total_calc=n.precio_total;}})}}
if(!esarray){var xtb=$(xObjEvaluar);for(var i=0;i<xArrayRegla.length;i++){xSecc_bus=xArrayRegla[i].idseccion;xSecc_detalle=xArrayRegla[i].idseccion_detalle;xCantidadBuscar=xObtnerValSumTable($(xtb),'data-idbus',xSecc_bus,'#cant_descontar');if(xCantidadBuscar===0)continue;xCantidadBuscarSecc_detalle=xObtnerValSumTable($(xtb),'data-idbus',xSecc_detalle,'#cant_descontar');var diferencia=xCantidadBuscar-xCantidadBuscarSecc_detalle;diferencia=diferencia<0?xCantidadBuscar:diferencia;$(xtb).find(".row").each(function(index,element){var xIdRowTb=$(element).attr('data-idbus'),xIdtb_Item=$(element).attr('data-id'),xIdtb_tpc=$(element).attr('data-idtipocobus'),xPrecio_mostrado=parseFloat($(element).find('#ptotal').text()),xPrecio_item_bus=xMoneda(xPrecio_mostrado);if(xPrecio_mostrado===0)return;if(xIdRowTb===xSecc_detalle){const cant_item=parseInt($(element).find('#cant_descontar').text());if(xCantidadBuscar>=xCantidadBuscarSecc_detalle){xPrecio_item_bus='0.00';}else if(diferencia>0){const precioUnitario_item=parseFloat($(element).find('#punitario').text());xPrecio_item_bus=parseFloat(parseFloat(diferencia*precioUnitario_item));xPrecio_item_bus=xPrecio_mostrado-xPrecio_item_bus;xPrecio_item_bus=xPrecio_item_bus<0?'0.00':xMoneda(xPrecio_item_bus);diferencia=diferencia-cant_item<0?0:diferencia-cant_item;}
$(element).find('#ptotal').text(xPrecio_item_bus);$(element).attr('cant_descontado',cant_item);xArrayPedidoObj[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;}})}}
else{var xSqlTbSave='';var xArrayEv=xObjEvaluar;for(xi in xArrayRegla){xSecc_bus=xArrayRegla[xi].idseccion;xSecc_detalle=xArrayRegla[xi].idseccion_detalle;xCantidadBuscar=xObtnerValSumArray(xArrayEv,xSecc_bus);xCantidadBuscarSecc_detalle=xObtnerValSumArray(xArrayEv,xSecc_detalle);var diferencia=xCantidadBuscar-xCantidadBuscarSecc_detalle;diferencia=diferencia<0?xCantidadBuscar:diferencia;for(var y=0;y<xArrayEv.length;y++){if(xArrayEv[y]==null){continue;}
if(xCantidadBuscar<=0){break;}
$.map(xArrayEv[y],function(n,z){if(typeof n==="object"){var xIdRowTb=n.idseccion;var xIdtb_Item=n.iditem;var xIdtb_tpc=n.idtipo_consumo;var xPrecio_mostrado=parseFloat(n.precio_total_calc);var xPrecio_item_bus=xMoneda(xPrecio_mostrado);if(xPrecio_mostrado===0)return;if(xIdRowTb===xSecc_detalle){const cant_item=n.cantidad;if(xCantidadBuscar>=xCantidadBuscarSecc_detalle){xPrecio_item_bus='0.00';}else if(diferencia>0){const precioUnitario_item=n.precio;xPrecio_item_bus=parseFloat(parseFloat(diferencia*precioUnitario_item));xPrecio_item_bus=xPrecio_mostrado-xPrecio_item_bus;xPrecio_item_bus=xPrecio_item_bus<0?'0.00':xMoneda(xPrecio_item_bus);diferencia=diferencia-cant_item<0?0:diferencia-cant_item;}
n.precio_total_calc=xPrecio_item_bus;n.precio_print=xPrecio_item_bus;n.cant_descontado=cant_item;xArrayEv[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;xArrayPedidoObj[xIdtb_tpc][xIdtb_Item].precio_print=xPrecio_item_bus;}}});}};return xArrayEv;}}
function xGeneralSumarTotales(xArraySum){var xArrayFucRpt=new Array();var xGeSumTotal=0;var xprecio_item_sum=0;if(xArraySum==null){xArraySum=xArrayPedidoObj}
for(var a=0;a<xArraySum.length;a++){if(xArraySum[a]==null){continue;}
$.map(xArraySum[a],function(n,z){if(typeof n=="object"){if(n.precio_print==""){xprecio_item_sum=n.precio_total}else{xprecio_item_sum=n.precio_print;}
xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xprecio_item_sum);}})}
var xDtPrint=xm_log_get('sede_generales');var xdes_sb='';var xporcentaje_sb;var xCadenta_tt='';var xCadena_tt_ad='';var xtt_adicional=0;var xid_tp_consumo_ad;var xid_ad_seccion;xGeneralArraySubTotales=new Array();xGeneralArraySubTotales.push({'descripcion':'Sub Total','importe':xMoneda(xGeSumTotal),'visible':true});for(var i=0;i<xDtPrint.length;i++){xdes_sb=xDtPrint[i].des_detalle;if(xdes_sb!=''){xporcentaje_sb=parseFloat(parseFloat(xDtPrint[i].porcentaje)/100).toFixed(2);xporcentaje_sb=parseFloat(parseFloat(xGeSumTotal)*parseFloat(xporcentaje_sb)).toFixed(2);xCadenta_tt=String(xCadenta_tt+'<tr class="row"><td data-ColumName="descripcion" class="xPedidoSubTotal">'+xdes_sb+'</td><td data-ColumName="importe" align="right">'+xporcentaje_sb+'</td><td class="xInvisible" data-ColumName="estado">0</td><td class="xInvisible" data-ColumName="idpedido">?p</td></tr>');xGeneralArraySubTotales.push({'descripcion':xMayusculaPrimera(xdes_sb.toLowerCase()),'importe':xMoneda(xporcentaje_sb),'visible':true});xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xporcentaje_sb);}
xid_tp_consumo_ad=xDtPrint[i].ad_idtp_consumo;if(xid_tp_consumo_ad!=''){xid_ad_seccion=xDtPrint[i].ad_idseccion;xid_ad_seccion=xid_ad_seccion.split(',');var xCant_item_sec=0;var u_id_ad_seccion;for(var q=0;q<xid_ad_seccion.length;q++){u_id_ad_seccion=xid_ad_seccion[q];xCant_item_sec=parseFloat(xCant_item_sec)+xBuscarCantidadPorSeccion(xid_tp_consumo_ad,u_id_ad_seccion);};if(xCant_item_sec==0){continue;}
xtt_adicional=parseInt(xtt_adicional)+(parseFloat(xCant_item_sec)*parseFloat(xDtPrint[i].ad_importe));xtt_adicional=xMoneda(xtt_adicional);xCadena_tt_ad=String(xCadena_tt_ad+'<tr class="row"><td><div class="xPedidoSubTotal" data-iddes="'+xGeneralArraySubTotales.length+'" data-importe="'+xtt_adicional+'"><paper-checkbox class="noselect" onchange="xNoCobrarSubTotal(this);" checked title="no cobrar" id="check'+xDtPrint[i].ad_descripcion+'">'+xDtPrint[i].ad_descripcion+'</paper-checkbox></div></td><td class="xInvisible" data-ColumName="descripcion">'+xDtPrint[i].ad_descripcion+'</td><td data-ColumName="importe" align="right">'+xtt_adicional+'</td><td class="xInvisible" data-ColumName="estado" id="td_estado_subt">0</td></tr>');xGeneralArraySubTotales.push({'descripcion':xMayusculaPrimera(xDtPrint[i].ad_descripcion.toLowerCase()),'importe':xtt_adicional,'visible':true});xGeSumTotal=parseFloat(xGeSumTotal)+parseFloat(xtt_adicional);}}
if(xGeneralArraySubTotales.length==1){xGeneralArraySubTotales=new Array()}
xGeneralArraySubTotales.push({'descripcion':'Total','importe':xMoneda(xGeSumTotal),'visible':true});xCadenta_tt=xCadenta_tt+xCadena_tt_ad+'<tr class="row xBold"><td class="xInvisible" data-ColumName="descripcion">TOTAL</td><td class="xInvisible" data-ColumName="importe" id="td_total_subt_2">'+xMoneda(xGeSumTotal)+'</td><td colspan="2" align="right" class="xPedidoTotal xSinBorde"><h3 class="xBold" id="td_total_subt">'+xMoneda(xGeSumTotal)+'</h3></td><td class="xInvisible" data-ColumName="estado">0</td></tr>';xArrayFucRpt.push({'ImporteTotal':xGeSumTotal,'CadenaRow':xCadenta_tt});return xArrayFucRpt;}
function xGeneralArmarSubTotalesBD(xnummesa_bus,responde){var xcadena_tt='';$.ajax({type:'POST',url:'../../bdphp/log.php?op=2052',data:{m:xnummesa_bus}}).done(function(dtsub){var xdtSub=$.parseJSON(dtsub);if(!xdtSub.success){alert(xdtSub.error);return;}
xdtSub=xdtSub.datos;var count_row=xdtSub.length;var xtotal_sub_res=0;var xtotal_sub=0;xGeneralArraySubTotales=new Array()
for(var i=0;i<xdtSub.length;i++){if(i==0){xtotal_sub=xdtSub[i].importe;xtotal_sub_res=xtotal_sub;}
if(count_row>1&&i>0){xtotal_sub_res=xtotal_sub_res-xdtSub[i].importe;xGeneralArraySubTotales[i]={'descripcion':xdtSub[i].descripcion,'importe':xMoneda(xdtSub[i].importe),'visible':true};}}
if(count_row>1){xGeneralArraySubTotales[0]={'descripcion':'Sub Total','importe':xMoneda(xtotal_sub_res),'visible':true};xGeneralArraySubTotales[count_row]={'descripcion':'Total','importe':xMoneda(xtotal_sub),'visible':true};}else{xGeneralArraySubTotales[0]={'descripcion':'Total','importe':xMoneda(xtotal_sub),'visible':true};}
for(var a=0;a<xGeneralArraySubTotales.length-1;a++){xcadena_tt=xcadena_tt+'<tr class="row"><td>'+xGeneralArraySubTotales[a].descripcion+'</td><td align="right">'+xGeneralArraySubTotales[a].importe+'</td></tr>';}
xcadena_tt=xcadena_tt+'<tr class="row xBold"><td></td><td align="right"><h3 class="xBold">'+xMoneda(xtotal_sub)+'</h3></td></tr>';if(responde){return responde(xcadena_tt);}})}
function xBuscarCantidadPorSeccion(idtpc_ad,idseccion_ad){var xcant_sec_ad=0;$.map(xArrayPedidoObj[idtpc_ad],function(n,z){if(typeof n=="object"&&n!=null){if(n.idseccion==idseccion_ad){xcant_sec_ad=parseFloat(xcant_sec_ad)+parseFloat(n.cantidad);}}});return xcant_sec_ad;}
function xNoCobrarSubTotal(obj){var xid_ad_sec_restar=$(obj).parent().attr('data-iddes');var ximporte_ad_sec_restar=$(obj).parent().attr('data-importe');var ximpp_tt=$("#td_total_subt").text();var xestado_ad_item=0;xGeneralArraySubTotales[xid_ad_sec_restar].visible=obj.checked;if(obj.checked==false){xestado_ad_item=1;obj.title="cobrar";$(obj).addClass('check_red');ximpp_tt=xMoneda(parseFloat(ximpp_tt)-parseFloat(ximporte_ad_sec_restar))}else{obj.title="no cobrar";$(obj).removeClass('check_red');ximpp_tt=xMoneda(parseFloat(ximpp_tt)+parseFloat(ximporte_ad_sec_restar));}
$(obj).parents('tr').find('#td_estado_subt').text(xestado_ad_item);$("#td_total_subt").text(ximpp_tt);$("#td_total_subt_2").text(ximpp_tt);xGeneralArraySubTotales[xGeneralArraySubTotales.length-1].importe=ximpp_tt;}
function xArmarArrayDescontarStock(obj_row,op){var xarray_rpt=new Array();var xrpt_row='';var xrpt_row_importe='';var xid_row_descontar=$(obj_row).attr('data-iddescontar');var x_row_tabla_procede=$(obj_row).attr('data-procede');var x_row_cant_descontar=$(obj_row).attr('data-cant_descontar');var x_row_cant=parseInt($(obj_row).find(".xcant_li").text());var x_row_cant_array=x_row_cant;if(isNaN(x_row_cant)){x_row_cant=parseFloat($(obj_row).find('#td_cant').text());}
if(!isNaN(parseInt(x_row_tabla_procede))){x_row_tabla_procede=$(obj_row).attr('data-descontar');}
if(op=='+'){x_row_cant=1}
var x_id_p=$(obj_row).attr('data-idpedido');var x_id_pd=$(obj_row).attr('data-idpedidodetalle');var x_row_importe=parseFloat($(obj_row).attr('data-punitario'));x_row_cant_descontar=x_row_cant_descontar.split(',');xid_row_descontar=xid_row_descontar.split(',');if(x_id_pd!=undefined){xsql1="update pedido_detalle set cantidad=cantidad-1, estado=if((cantidad)<=0,1,0), ptotal=format(ptotal-"+x_row_importe+",2) where idpedido_detalle="+x_id_pd+"; \r";xsql2="update pedido set total=format(total-"+x_row_importe+",2),estado=if((total)<=0,3,0) where idpedido="+x_id_p+"; \r update pedido_subtotales set importe=format(importe-"+x_row_importe+",2) where idpedido="+x_id_p+" and descripcion='TOTAL'; \r";xrpt_row_importe=String(xsql1+' '+xsql2)}
for(xi in xid_row_descontar){xid_des=xid_row_descontar[xi];xcant_des=x_row_cant;if(x_row_tabla_procede=='porcion'){xcant_des=parseFloat(x_row_cant_descontar[xi])*parseFloat(x_row_cant);}
xcampo_procede="stock=stock"+op+xcant_des;if(x_row_tabla_procede=='carta_lista'){xcampo_procede="cantidad=if(cantidad='ND','ND',cantidad"+op+xcant_des+")"}
xrpt_row=xrpt_row+" update "+x_row_tabla_procede+" set "+xcampo_procede+" where id"+x_row_tabla_procede+"="+xid_des+";\r";}
xarray_rpt.push({'stock':xrpt_row,'importe':xrpt_row_importe})
return xarray_rpt;}
function xArmarTipoConsumo(){var xcadenaTC='';xArrayDesTipoConsumo=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));for(a in xArrayDesTipoConsumo){if(xArrayDesTipoConsumo[a]==null){continue;}
xcadenaTC=String(xcadenaTC+'<div class="xpedir_row" data-id="'+xArrayDesTipoConsumo[a].id+'">'+'<p>'+xArrayDesTipoConsumo[a].des+'</p>'+'<p class="xCant_item"></p>'+'<div class="xBtnIz xBtn">-</div>'+'<div class="xBtnDe xBtn">+</div>'+'</div>');}
xcadenaTC='<div class="xpedir_item">'+xcadenaTC+'</div>';return xcadenaTC;}
async function xVerificarStockItemPedidoBefore(){var _rpt=false;var _rptList=[];const _arrItems=xEstructuraItemsJsonComprobante(xArrayPedidoObj,[]).filter(x=>x.stock_actual!='ND').filter(x=>parseInt(x.stock_actual)<=5);const _arrItemsCartaLista=_arrItems.filter(x=>parseInt(x.procede)===1).map(x=>x.id).join(',');const _arrItemsProducto=_arrItems.filter(x=>parseInt(x.procede)===0).map(x=>x.id).join(',');if(_arrItemsCartaLista.length===0&&_arrItemsProducto.length===0)return _rpt=true;await $.ajax({type:"POST",url:"../../bdphp/log.php?op=3031",data:{i:_arrItemsCartaLista,p:_arrItemsProducto}}).done(function(res){let _res=$.parseJSON(res);_res=_res.datos;_res.map(x=>{const idFilter=x.idcarta_lista;_item=_arrItems.filter(i=>i.id===idFilter).map(i=>i);if(parseFloat(_item[0].stock_actual)>parseFloat(x.cantidad)){_item[0].stock_actual=parseFloat(x.cantidad);_rptList.push(_item[0]);}});});return _rptList;}
function xUpdateItemNoStock(op,items){$.ajax({type:"POST",url:"../../bdphp/log.php?op=2301",data:{p_from:op,i:items}}).done(x=>{console.log(x);})}