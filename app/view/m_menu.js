var xIdOrg;var xIdSede;var xNomU;var xArrayPedido=new Array();var xArrayDesTipoConsumo=new Array();var xToglePanel=0;var xMenuArray;var xidCategoria;var xidCategoriaSeccion;var xidCatProcedencia=0;var xPopupLoad;var xOnlyAccPedido;var xCount_cant_ico=0;window.addEventListener("error",function(a){if(!a){return}console.log(a)});document.addEventListener("WebComponentsReady",function componentsReady(){$("#PanelDe").on("transitionend",function(b){if(this.selected=="main"){$("#PanelDe").css("z-index","0")}});xIniMenAAA();setGalleta();listenCookieChange(function(){dialog_inactividad.open()})});function xIniMenAAA(){xVerificarSession();setInterval(function(){xVerificarSession()},1080000);xPopupLoad=document.getElementById("xLoad");xm_LogChequea(function(){xm_log_get("ini_us");xLoadArrayPedidoAquiMenuJS();if(xUsAc_Ini=="A2,"){xOnlyAccPedido=0}else{xOnlyAccPedido=2}})}function xOpenPageCarta(b,c){if(c==null){c=""}var a="";switch(b){case 0:a="/categoria";break;case 1:a="/menu";break;case 2:a="/sub_menu";break;case 3:a="/mipedido";break;case 4:document.location.href="m_panel.html";return;case 5:a="/buscar_item_menu";break}a=a+c;window.localStorage.setItem("::app3_sys_scroll_pos",$(window).scrollTop());router.go(a);PanelDe.closeDrawer()}function xLoadArrayPedidoAquiMenuJS(){xArrayPedido=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));if(xArrayPedido===null){xArrayPedido=[]}var b=xm_log_get("estructura_pedido");for(var a=0;a<b.length;a++){xArrayPedido[b[a].idtipo_consumo]={id:b[a].idtipo_consumo,des:b[a].descripcion,titulo:b[a].titulo};xArrayDesTipoConsumo.push({id:b[a].idtipo_consumo,des:b[a].descripcion,titulo:b[a].titulo})}window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedido));xOpenPageCarta(0)}function xScrolUp(a){if(a!="0"){var b=document.getElementById(a);a=b.offsetTop}$("#xContenedoPaginas").stop(true,true).animate({scrollTop:a},600)}function xScrolUpObj(a){xelement=$(a).offset().top;$("#xContenedoPaginas").stop(true,true).animate({scrollTop:xelement},600)}function xOpenPanelDe(){PanelDe.openDrawer();$("#PanelDe").css("z-index","20")}function xArmarMenuLateral(e){var b="";var d="";var a='<li onClick="xCerrarSession();"><p>CERRAR SESSION </p></li>';switch(e){case 1:xMenuArray=$.parseJSON(window.localStorage.getItem("::app3_sys_dt_mlc"));if(xOnlyAccPedido==0){d=""}else{if(xIdUsuario!=""){d='<li onClick="xOpenPageCarta(4)"><p>SALIR AL PANEL </p></li>'}}break;case 2:xMenuArray=$.parseJSON(window.localStorage.getItem("::app3_sys_dt_mlm"));break}if(xMenuArray===null){return}for(var c=0;c<xMenuArray.length;c++){b=String(b+'<li data-id="'+xMenuArray[c].id+'" onClick="xVerDetalleMenu('+c+","+e+')"><p>'+xMenuArray[c].des+"</p></li>")}b='<ul class="noselect xCursor"><li onClick="btn_lateral_inicio();"><p>INICIO</p></li>'+b+'<li onClick="xOpenPageCarta(3);"><p>VER MI PEDIDO</p></li>'+d+a+"</ul>";$(".xBtnPanel ul").remove();$(".xBtnPanel").append(b).trigger("create")}function btn_lateral_inicio(){localStorage.removeItem("::app3_sys_descat");xOpenPageCarta(0)}function xVerDetalleMenu(b,e){var a="sub_menu";var d=xMenuArray[b].des;xidCategoriaSeccion=xMenuArray[b].id;xidCatProcedencia=xMenuArray[b].procede;window.localStorage.setItem("::app3_sys_dt_c",d);if(e==1){var a="menu"}var c=location.href.indexOf(a);if(c!=-1){if(e==2){PanelDe.closeDrawer();xRegDataLoadBack();xLoadItems()}return}xOpenPageCarta(e)}function xRegDataLoadBack(){const a={i:xidCategoria,s:xidCategoriaSeccion,p:xidCatProcedencia};window.localStorage.setItem("::app3_sys_dt_back",JSON.stringify(a))}function xCerrarSession(){$("body").removeClass("loaded");$.ajax({type:"POST",url:"../../bdphp/log.php?op=-103"}).done(function(b){setClearLocalStorage()})}function goBack(){window.history.back()}function xMantenerSession(){restaurarGalleta();dialog_inactividad.close();xOpenPageCarta(1)};