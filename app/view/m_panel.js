var xIdOrg,xIdSede,xNomU,xNomUsario,xIdUsuario,xCargoU,xPopupLoad,xIdROw,xTableRow,xRowObj,xselectIdSedeGeneral=0,xdialogus;var xMenuOp="",xAcc,xIdAccDirecto,verCambioClave=false;$(document).ready(function(){$("#PanelDe").on("transitionend",function(b){if(this.selected=="main"){$("#PanelDe").css("z-index","0")}})});window.addEventListener("error",function(a){console.log(a.error.message,"from",a.error.stack)});document.addEventListener("WebComponentsReady",function componentsReady(){$("#PanelDe").on("transitionend",function(b){if(this.selected=="main"){$("#PanelDe").css("z-index","0")}});xIniDocument()});window.addEventListener("WebComponentsReady",function(a){xIniDocument()});function xIniDocument(){xVerificarSession();xPopupLoad=document.getElementById("xLoad");xdialogus=document.getElementById("dialog_us");xm_LogChequea(function(){xm_log_get("ini_us");xLoadImpresoras();xDatosUs();if(xUsAc_Ini=="A2,"){window.localStorage.setItem("::app3_woUOn",1);xOpenPage(3)}else{xOpenPage(1)}})}function xOpenPage(b,c){b=parseInt(b);if(c==null){c=""}var a="";switch(b){case 1:a="/home";break;case 2:a="/elaborar_carta";break;case 3:document.location.href="m_menu.html";return;break;case 6:a="/usuarios";break;case 7:a="/configuraciones";break;case 8:a="/caja";break;case 9:document.location.href="m_control_pedidos.html";return;break;case 10:a="/detalle_pedido";break;case 11:h=window.screen.availHeight-100;var d=window.open("m_menu.html","Carta","width=400,height="+h);return;break;case 12:a="/compras";break;case 13:a="/distribuicion";break;case 14:a="/porcionar";break;case 15:a="/recetas";break;case 16:if(window.innerWidth<=850){a="/venta_rapida"}else{var d=window.open("#/venta_rapida","Venta rapida");return}console.log(window.innerWidth);break;case 17:a="/producto_porcion";break;case 18:a="/ie_almacen";break;case 19:a="/monitor_pedidos";break;case 20:a="/historial_ventas";break;case 21:a="/inventario";break;case 22:a="/resumen_caja";break;case 23:a="/zona_despacho";break;case 24:a="/items_borrados";break;case 25:a="/facturador";break;case 26:a="/c_electronico";break;case 27:window.open("http://192.168.1.64/restobar-print-server/print-server.html","Serrvidor de Impresion");return}a=a+c;router.go(a+c);xScrolUp(0)}function xBtn_Regresar(){parent.history.back();xScrolUp(0)}function xScrolUp(a){if(a!="0"){var b=document.getElementById(a);a=b.offsetTop}$("#xMainContent1").stop(true,true).animate({scrollTop:a},600)}function xScrolUpObj(a){xelement=$(a).offset().top;$("#xMainContent1").stop(true,true).animate({scrollTop:xelement},600)}function xAbrirDialogUs(a){xdialogus.open()}function xOpenDialog(a,b){xRowObj=$(b).parent().parent();xIdROw=$(b).parent().parent().attr("data-id");xTableRow=$(b).parent().parent().attr("data-t");a.open()}function xOpenDialog2(a){a.open()}function xCerrarDialog2(a){a.close()}function xCerrarSession(){$("body").removeClass("loaded");$.ajax({type:"POST",url:"../../bdphp/log.php?op=-103"}).done(function(b){setClearLocalStorage()})}function xDatosUs(){$("#xnomu").text(xNomUsario);$("#xcargou").text(xCargoU)}function xOpenPanelDe(){$("#PanelDe").css("z-index","20")}function xGenerarMenu(a){}function showCambiarClave(){$("#datosGeneralesUs").addClass("xInvisible");$("#cambioClaveUs").removeClass("xInvisible")}function cambiarClaveUs(){const b=$("#txtpa")[0].value;const a=$("#txtpn")[0].value;$.ajax({type:"POST",url:"../../bdphp/log.php?op=-304",data:{pa:b,pn:a}}).done(function(c){$("#msj_1").addClass("xInvisible");$("#msj_2").addClass("xInvisible");if(c==="0"){$("#msj_1").removeClass("xInvisible")}else{$("#msj_2").removeClass("xInvisible")}})};