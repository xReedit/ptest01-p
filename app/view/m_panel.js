var xIdOrg,xIdSede,xNomU,xNomUsario,xIdUsuario,xCargoU,xPopupLoad,xIdROw,xTableRow,xRowObj,xdialogus,xAcc,xIdAccDirecto,dialogListNotification,xselectIdSedeGeneral=0,xMenuOp="",verCambioClave=!1,xparam_time_ruter=!1,componentsLoadPanel=!1,ipPrintServerLocal="";function xIniDocument(){xVerificarSession(),xPopupLoad=document.getElementById("xLoad"),xdialogus=document.getElementById("dialog_us");var e=document.getElementById("xtitulo_bar");(dialogListNotification=document.getElementById("dialogListNotification")).addEventListener("xShowNotifications",e=>{console.log("recibi alerta de notifiacion desde el panel")}),xm_LogChequea(function(){xm_log_get("ini_us"),xLoadImpresoras(),xDatosUs(),xNewUs(),ipPrintServerLocal=xm_log_get("datos_org_sede")[0].ip_server_local,localStorage.setItem("::app3_sys_last_s",btoa(xm_log_get("datos_org_sede")[0].idsede)),e.init(),status_CreateVariablesListen()})}function showNotificationList(){var e=document.getElementById("alertaNotificaciones");e.autobuffer=!0,e.muted=!1,e.load(),e.play(),dialogListNotification.xopen()}function xLiberarRouter(){setLocalSotrage("::app3_sys_route",0),xparam_time_ruter=!1,console.log("time router ",0)}function xOneOptionPage(e){xLiberarRouter(),xOpenPage(e)}"registerElement"in document&&"import"in document.createElement("link")?console.log("no polyfills needed"):console.log("si necestia polyfills"),window.addEventListener("WebComponentsReady",function(e){console.log("WebComponentsReady"),$("#PanelDe").on("transitionend",function(e){"main"==this.selected&&$("#PanelDe").css("z-index","0")}),this.componentsLoadPanel=!0,console.log("cargado en 4s - desde WebComponentsReady"),xIniDocument(),status_CreateVariablesListen()}),window.onload=()=>{setTimeout(()=>{this.componentsLoadPanel||($("#PanelDe").on("transitionend",function(e){"main"==this.selected&&$("#PanelDe").css("z-index","0")}),console.log("cargado en 4s - desde ready"),xIniDocument())},4e3)},window.addEventListener("error",function(e){console.log(e.error.message,"from",e.error.stack)}),window.onhashchange=function(){xLiberarRouter()};var aapasa=0;async function xOpenPage(e,a){try{xPopupLoad.xopen(),xPopupLoad.titulo="Abriendo ..."}catch(e){}await xDelayHere(100),aapasa++,setTimeout(()=>{xPopupLoad.xclose(),xPopupLoad.titulo="Guardando ..."},500),null==a&&(a="");var o="";switch(e=parseInt(e)){case 1:o="/home";break;case 2:o="/elaborar_carta";break;case 3:return 1===parseInt(xm_log_get("datos_org_sede")[0].pwa)?(window.localStorage.removeItem("::app3_sys_first_load"),document.location.href="https://app.restobar.papaya.com.pe"):(window.localStorage.removeItem("::app3_sys_first_load"),document.location.href="m_menu.html"),void xLiberarRouter();case 6:o="/usuarios";break;case 7:o="/configuraciones";break;case 8:o="/caja";break;case 9:return document.location.href="m_control_pedidos.html",clearStorageVariablesComp(),void xLiberarRouter();case 10:o="/detalle_pedido";break;case 11:return h=window.screen.availHeight-100,window.open("m_menu.html","Carta","width=400,height="+h),void xLiberarRouter();case 12:o="/compras";break;case 13:o="/distribuicion";break;case 14:o="/porcionar";break;case 15:o="/recetas";break;case 16:if(isSynOsWinOrMac())return void window.open("#/venta_rapida","Pundo de Venta");o="/venta_rapida";break;case 17:o="/producto_porcion";break;case 18:o="/ie_almacen";break;case 19:o="/monitor_pedidos";break;case 20:o="/historial_ventas";break;case 21:o="/inventario";break;case 22:o="/resumen_caja";break;case 23:o="/zona_despacho";break;case 24:o="/items_borrados";break;case 25:return void window.open("#/facturador","Facturador");case 26:o="/c_electronico";break;case 28:o="/adm_dashboard";break;case 29:o="/us_contador";break;case 30:o="/cuentas_p_c";break;case 31:o="/gastos_fijos";break;case 32:o="/gastos_variables";break;case 33:o="/otros_ingresos";break;case 34:o="/recursos_humanos";break;case 35:o="/clientes";break;case 36:o="/panel_contador";break;case 37:window.open("https://restobar.papaya.com.pe/analitica","Analitica");break;case 38:o="/promociones";break;case 39:o="/encuesta";break;case 40:var t="http://appx.papaya.com.pe/encuesta/?o="+btoa(JSON.stringify({o:xIdOrg,s:xIdSede,r:!0}));return void window.location.replace(t);case 27:t=-1<window.location.href.indexOf("demo")?"d":"",t=btoa(JSON.stringify({o:xIdOrg,s:xIdSede,d:t})),t="http://"+ipPrintServerLocal+"/restobar/print/client/index.html?o="+t;return void window.open(t,"Servidor de Impresion");case 41:o="/indicadores";break;case 42:o="/mozo_virtual";break;case 43:o="/pago_desde_app";break;case 44:window.open("https://comercio.papaya.com.pe/","Seguimiento de Delivery");break;case 45:o="/orden_pedido";break;case 46:o="/indicadores2";break;case 47:o="/facturacion";break;case 48:o="/produccion_producto";break;case 49:o="/promociones";break;case 50:o="/solicitud_remoto";break;case 51:o="/ticket_rapido";break;case 52:o="/porciones";break;case 53:t=btoa(JSON.stringify(getDataUsRRHH()));window.open("https://recursos-humanos.papaya.com.pe/login?us="+t,"RRHH");break;case 54:o="/proveedores";break;case 55:t=btoa(JSON.stringify(getDataUsRRHH()));window.open("http://localhost:5173/login?us="+t,"Papaya Chat Bot");break;case 56:o="/distribuicion_recibe"}o+=a,(router=null==router?document.querySelector("app-router"):router).go(o),xScrolUp(0)}function clearStorageVariablesComp(){localStorage.removeItem("::app3_comp_cat"),localStorage.removeItem("::::app3_comp_tpp_all")}function xBtn_Regresar(){parent.history.back(),xScrolUp(0)}function xScrolUp(e){"0"!=e&&(e=document.getElementById(e).offsetTop),$("#xMainContent1").stop(!0,!0).animate({scrollTop:e},600)}function xScrolUpObj(e){xelement=$(e).offset().top,$("#xMainContent1").stop(!0,!0).animate({scrollTop:xelement},600)}function xAbrirDialogUs(e){xdialogus.open()}function xOpenDialog(e,a){xRowObj=$(a).parent().parent(),xIdROw=$(a).parent().parent().attr("data-id"),xTableRow=$(a).parent().parent().attr("data-t"),e.open()}function xOpenDialog2(e){e.open()}function xCerrarDialog2(e){e.close()}function xCerrarSession(){$("body").removeClass("loaded"),$.ajax({type:"POST",url:"../../bdphp/log.php?op=-103"}).done(function(e){setClearLocalStorage()})}function xDatosUs(){$("#xnomu").text(xNomUsario),$("#xcargou").text(xCargoU)}function xOpenPanelDe(){$("#PanelDe").css("z-index","20")}function xGenerarMenu(e){}function showCambiarClave(){$("#datosGeneralesUs").addClass("xInvisible"),$("#cambioClaveUs").removeClass("xInvisible")}function cambiarClaveUs(){var e=$("#txtpa")[0].value,a=$("#txtpn")[0].value;$("#msj_1").addClass("xInvisible"),$("#msj_2").addClass("xInvisible"),e.length<6&&($("#msj_1").text("Minimo 6 caracteres."),$("#msj_1").removeClass("xInvisible")),$.ajax({type:"POST",url:"../../bdphp/log.php?op=-304",data:{pa:e,pn:a}}).done(function(e){("0"===e?($("#msj_1").text("Claves incorrecta. No coiciden."),$("#msj_1")):$("#msj_2")).removeClass("xInvisible")})}function keyChangePass(e){13===e.keyCode&&changePass()}function changePass(){var e=pass1.value,a=pass2.value;""===e?msj_pass_clave.textContent="Tiene que ingresar una clave":e!=a?msj_pass_clave.textContent="Las claves no son iguales":e.length<6?msj_pass_clave.textContent="Debe tener minimo 6 caracteres.":"123456"===e?msj_pass_clave.textContent="La clave no puede ser la que pusiste.":$.ajax({type:"POST",url:"../../bdphp/log.php?op=-3041",data:{pn:a}}).done(function(e){msj_pass_clave.textContent="",dialog_requiere_cambio_pas.close(),localStorage.setItem("::app3_woUSN",1),xPasarAMenuAcc()})}function xNewUs(){!localStorage.getItem("::app3_woUSN")&&0===parseInt(xm_log_get("app3_us").nuevo)?(xNuevoUs=!0,$("body").addClass("loaded"),dialog_requiere_cambio_pas.open()):xPasarAMenuAcc()}function xPasarAMenuAcc(){xLiberarRouter(),"A2,"==xUsAc_Ini?(window.localStorage.setItem("::app3_woUOn",1),xOpenPage(3)):(setLocalSotrage("::app3_sys_route",0),xOpenPage(1))}function showNotificationPago(){xOpenPage(47)}function xDelayHere(a){return new Promise(e=>{setTimeout(()=>{e(2)},a)})}