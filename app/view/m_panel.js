var xIdOrg,xIdSede,xNomU,xNomUsario,xIdUsuario,xCargoU,xPopupLoad,xIdROw,xTableRow,xRowObj,xselectIdSedeGeneral=0,xdialogus;var xMenuOp='',xAcc,xIdAccDirecto,verCambioClave=false;var xparam_time_ruter=false;var componentsLoadPanel=false;if('registerElement'in document&&'import'in document.createElement('link')){console.log('no polyfills needed');}else{console.log('si necestia polyfills');}
window.addEventListener('WebComponentsReady',function(e){console.log('WebComponentsReady');$("#PanelDe").on("transitionend",function(a){if(this.selected=="main"){$("#PanelDe").css("z-index","0");}});this.componentsLoadPanel=true;console.log('cargado en 4s - desde WebComponentsReady');xIniDocument();});window.onload=()=>{setTimeout(()=>{if(this.componentsLoadPanel){return;}
$("#PanelDe").on("transitionend",function(a){if(this.selected=="main"){$("#PanelDe").css("z-index","0");}});console.log('cargado en 4s - desde ready');xIniDocument();},4000);};window.addEventListener("error",function(e){console.log(e.error.message,"from",e.error.stack);});function xIniDocument(){xVerificarSession();xPopupLoad=document.getElementById('xLoad');xdialogus=document.getElementById('dialog_us');xm_LogChequea(function(){xm_log_get('ini_us');xLoadImpresoras();xDatosUs();xNewUs();});}
window.onhashchange=function(){xLiberarRouter();}
function xLiberarRouter(){setLocalSotrage('::app3_sys_route',0);xparam_time_ruter=false;console.log('time router ',0);}
function xOneOptionPage(_codOne){xLiberarRouter();xOpenPage(_codOne);}
var aapasa=0;function xOpenPage(xop,parametro){var _route_count=getLocalStorage('::app3_sys_route')||0;if(parseInt(_route_count)===1){if(xparam_time_ruter)return
xparam_time_ruter=true;setTimeout(()=>{xLiberarRouter();},200);return;}
setLocalSotrage('::app3_sys_route',1);aapasa++;console.log('paso el router ',aapasa);xop=parseInt(xop);if(parametro==null){parametro='';}
var xruta='';switch(xop){case 1:xruta='/home';break;case 2:xruta='/elaborar_carta';break;case 3:if(parseInt(xm_log_get('datos_org_sede')[0].pwa)===1){window.localStorage.removeItem("::app3_sys_first_load");document.location.href='https://app.restobar.papaya.com.pe';xLiberarRouter();return;}else{window.localStorage.removeItem("::app3_sys_first_load");document.location.href='m_menu.html';xLiberarRouter();return;}
break;case 6:xruta='/usuarios';break;case 7:xruta='/configuraciones';break;case 8:xruta='/caja';break;case 9:document.location.href='m_control_pedidos.html';xLiberarRouter();return;break;case 10:xruta='/detalle_pedido';break;case 11:h=window.screen.availHeight-100;window.open('m_menu.html',"Carta","width=400,height="+h);xLiberarRouter();return;break;case 12:xruta='/compras';break;case 13:xruta='/distribuicion';break;case 14:xruta='/porcionar';break;case 15:xruta='/recetas';break;case 16:if(window.innerWidth<=850){xruta='/venta_rapida';}else{window.open('#/venta_rapida',"Venta rapida");return;}
console.log(window.innerWidth);break;case 17:xruta='/producto_porcion';break;case 18:xruta='/ie_almacen';break;case 19:xruta='/monitor_pedidos';break;case 20:xruta='/historial_ventas';break;case 21:xruta='/inventario';break;case 22:xruta='/resumen_caja';break;case 23:xruta='/zona_despacho';break;case 24:xruta='/items_borrados';break;case 25:xruta='/facturador';break;case 26:xruta='/c_electronico';break;case 28:xruta='/adm_dashboard';break;case 29:xruta='/us_contador';break;case 30:xruta='/cuentas_p_c';break;case 31:xruta='/gastos_fijos';break;case 32:xruta='/gastos_variables';break;case 33:xruta='/otros_ingresos';break;case 34:xruta='/recursos_humanos';break;case 35:xruta='/clientes';break;case 36:xruta='/panel_contador';break;case 37:window.open('https://restobar.papaya.com.pe/analitica',"Analitica");break;case 38:xruta='/promociones';break;case 39:xruta='/encuesta';break;case 40:const dataE={o:xIdOrg,s:xIdSede,r:true}
const _urlEncuesta='http://appx.papaya.com.pe/encuesta/?o='+btoa(JSON.stringify(dataE));window.location.replace(_urlEncuesta);return;break;case 27:const demo=window.location.href.indexOf('demo')>-1?'d':'';const _xdataOrg={o:xIdOrg,s:xIdSede,d:demo}
const _xr=btoa(JSON.stringify(_xdataOrg));const versionPrintServer='print-server'
const _urlPrintServver='http://appx.papaya.com.pe/'+versionPrintServer+'/print-server.html?o='+_xr
window.open(_urlPrintServver,"Servidor de Impresion");return;case 41:xruta='/indicadores';break;case 42:xruta='/mozo_virtual';break;case 43:xruta='/pago_desde_app';break;case 44:const url_s='https://comercio.papaya.com.pe/';window.open(url_s,"Seguimiento de Delivery");break;case 45:xruta='/orden_pedido';break;}
xruta=xruta+parametro;if(router==undefined){router=document.querySelector('app-router');}
router.go(xruta+parametro);xScrolUp(0);}
function xBtn_Regresar(){parent.history.back();xScrolUp(0);}
function xScrolUp(xelement){if(xelement!="0"){var elem=document.getElementById(xelement);xelement=elem.offsetTop;}
$("#xMainContent1").stop(true,true).animate({scrollTop:xelement},600);}
function xScrolUpObj(obj){xelement=$(obj).offset().top;$("#xMainContent1").stop(true,true).animate({scrollTop:xelement},600);}
function xAbrirDialogUs(obj){xdialogus.open()}
function xOpenDialog(nomId,idRow){xRowObj=$(idRow).parent().parent();xIdROw=$(idRow).parent().parent().attr('data-id');xTableRow=$(idRow).parent().parent().attr('data-t');nomId.open();}
function xOpenDialog2(nomId){nomId.open();}
function xCerrarDialog2(nomId){nomId.close();}
function xCerrarSession(){$('body').removeClass('loaded');$.ajax({type:'POST',url:'../../bdphp/log.php?op=-103'}).done(function(a){setClearLocalStorage();});}
function xDatosUs(){$("#xnomu").text(xNomUsario);$("#xcargou").text(xCargoU);}
function xOpenPanelDe(){$("#PanelDe").css('z-index','20');}
function xGenerarMenu(op){}
function showCambiarClave(){$("#datosGeneralesUs").addClass('xInvisible');$("#cambioClaveUs").removeClass('xInvisible');}
function cambiarClaveUs(){const pa=$("#txtpa")[0].value;const pn=$("#txtpn")[0].value;$("#msj_1").addClass("xInvisible");$("#msj_2").addClass("xInvisible");if(pa.length<6){$("#msj_1").text('Minimo 6 caracteres.');$("#msj_1").removeClass("xInvisible");}
$.ajax({type:'POST',url:'../../bdphp/log.php?op=-304',data:{pa:pa,pn:pn}}).done(function(dtC){if(dtC==="0"){$("#msj_1").text('Claves incorrecta. No coiciden.');$("#msj_1").removeClass("xInvisible");}else{$("#msj_2").removeClass("xInvisible");}});}
function keyChangePass(){if(event.keyCode===13)changePass();}
function changePass(){const p1=pass1.value;const p2=pass2.value;if(p1===''){msj_pass_clave.textContent="Tiene que ingresar una clave";return;}
if(p1!=p2){msj_pass_clave.textContent="Las claves no son iguales";return;}
if(p1.length<6){msj_pass_clave.textContent="Debe tener minimo 6 caracteres.";return;}
if(p1==="123456"){msj_pass_clave.textContent="La clave no puede ser la que pusiste.";return;}
$.ajax({type:'POST',url:'../../bdphp/log.php?op=-3041',data:{pn:p2}}).done(function(dtC){msj_pass_clave.textContent='';dialog_requiere_cambio_pas.close();localStorage.setItem('::app3_woUSN',1);xPasarAMenuAcc();});}
function xNewUs(){if(!localStorage.getItem('::app3_woUSN')){const xNuevo=parseInt(xm_log_get('app3_us').nuevo);if(xNuevo===0){xNuevoUs=true;$('body').addClass('loaded');dialog_requiere_cambio_pas.open();}else{xPasarAMenuAcc();}}}
function xPasarAMenuAcc(){if(xUsAc_Ini=='A2,'){window.localStorage.setItem('::app3_woUOn',1);xOpenPage(3);}else{xOpenPage(1);}}