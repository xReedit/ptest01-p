var xIdOrg;var xIdSede;var xNomU;var xArrayPedido=new Array();var xArrayDesTipoConsumo=new Array();var xToglePanel=0;var xMenuArray;var xidCategoria;var xidCategoriaSeccion;var xidCatProcedencia=0;var xPopupLoad;var xOnlyAccPedido;var xCount_cant_ico=0;var componentsLoadMenu=false;window.addEventListener('WebComponentsReady',function(e){console.log('WebComponentsReady');if(this.componentsLoadMenu){return;}
this.componentsLoadMenu=true;setTimeout(()=>{console.log('cargado en 4s');InitMenu.onReady();},3000);});var InitMenu={onReady:function(){console.log('PI onReady');$("#PanelDe").on("transitionend",InitMenu.PanelDeTransition());xIniMenAAA();setGalleta();listenCookieChange(function(){dialog_inactividad.open();});},PanelDeTransition:()=>{if(this.selected=="main"){$("#PanelDe").css("z-index","0");}}}
window.addEventListener("error",function(e){if(!e){return}
console.log(e);});window.onload=()=>{if(this.componentsLoadMenu){return;}
this.componentsLoadMenu=true;setTimeout(()=>{console.log('cargado en 4s');InitMenu.onReady();},3000);})
function xIniMenAAA(){xVerificarSession();xPopupLoad=document.getElementById("xLoad");xm_LogChequea(function(){xm_log_get("ini_us");xLoadArrayPedidoAquiMenuJS();if(xUsAc_Ini=="A2,"){xOnlyAccPedido=0;}else{xOnlyAccPedido=2;}});}
function xOpenPageCarta(xop,parametro){if(parametro==null){parametro="";}
var xruta="";switch(xop){case 0:xruta="/categoria";break;case 1:xruta="/menu";break;case 2:xruta="/sub_menu";break;case 3:xruta="/mipedido";break;case 4:window.localStorage.removeItem("::app3_sys_first_load");document.location.href="m_panel.html";return;case 5:xruta="/buscar_item_menu";break;}
xruta=xruta+parametro;window.localStorage.setItem("::app3_sys_scroll_pos",$(window).scrollTop());router.go(xruta);xClosePanelDe();}
function xLoadArrayPedidoAquiMenuJS(){xArrayPedido=JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));if(xArrayPedido===null){xArrayPedido=[];}
var xDtArray=xm_log_get("estructura_pedido");for(var i=0;i<xDtArray.length;i++){xArrayPedido[xDtArray[i].idtipo_consumo]={id:xDtArray[i].idtipo_consumo,des:xDtArray[i].descripcion,titulo:xDtArray[i].titulo};xArrayDesTipoConsumo.push({id:xDtArray[i].idtipo_consumo,des:xDtArray[i].descripcion,titulo:xDtArray[i].titulo});}
const _sys_first_load=window.localStorage.getItem("::app3_sys_first_load");if(window.innerWidth<720||_sys_first_load===null){xOpenPageCarta(0);}else{$("body").removeClass("loaded");setTimeout(()=>{if(window.location.href.indexOf('sub_menu')>-1){xLoadItems();}else{if(window.location.href.indexOf('menu')>-1)return;goBack();}
$("body").addClass("loaded");xLoadMipedido();},500);}
window.localStorage.setItem("::app3_sys_first_load",1);window.localStorage.setItem("::app3_sys_dta_pe",JSON.stringify(xArrayPedido));}
function xScrolUp(xelement){if(xelement!="0"){var elem=document.getElementById(xelement);xelement=elem.offsetTop;}
$("#xContenedoPaginas").stop(true,true).animate({scrollTop:xelement},600);}
function xScrolUpObj(obj){xelement=$(obj).offset().top;$("#xContenedoPaginas").stop(true,true).animate({scrollTop:xelement},600);}
function xOpenPanelDe(){PanelDe.openDrawer();PanelDe.classList.add('xOnZindexPanelDe');}
function xClosePanelDe(){PanelDe.closeDrawer();PanelDe.classList.remove('xOnZindexPanelDe');}
function xArmarMenuLateral(op){var xCadenaMenuL="";var xOpSalirPanel="";var xCadenadaCerrarSession='<li onclick="xCerrarSession();"><p>CERRAR SESSION </p></li>';switch(op){case 1:xMenuArray=$.parseJSON(window.localStorage.getItem("::app3_sys_dt_mlc"));if(xOnlyAccPedido==0){xOpSalirPanel="";}else{if(xIdUsuario!=""){xOpSalirPanel='<li onclick="xOpenPageCarta(4)"><p>SALIR AL PANEL </p></li>';}}
break;case 2:xMenuArray=$.parseJSON(window.localStorage.getItem("::app3_sys_dt_mlm"));break;}
if(xMenuArray===null){return;}
for(var i=0;i<xMenuArray.length;i++){xCadenaMenuL=String(xCadenaMenuL+'<li data-id="'+
xMenuArray[i].id+'" onclick="xVerDetalleMenu('+
i+","+
op+')"><p>'+
xMenuArray[i].des+"</p></li>");}
xCadenaMenuL='<ul class="noselect xCursor"><li onclick="btn_lateral_inicio();"><p>INICIO</p></li>'+
xCadenaMenuL+'<li onclick="xOpenPageCarta(3);"><p>VER MI PEDIDO</p></li>'+
xOpSalirPanel+
xCadenadaCerrarSession+"</ul>";$(".xBtnPanel ul").remove();$(".xBtnPanel").append(xCadenaMenuL).trigger("create");}
function btn_lateral_inicio(){localStorage.removeItem("::app3_sys_descat");xOpenPageCarta(0);}
function xVerDetalleMenu(i,op){var xBus="sub_menu";var xdt=xMenuArray[i].des;xidCategoriaSeccion=xMenuArray[i].id;xidCatProcedencia=xMenuArray[i].procede;window.localStorage.setItem("::app3_sys_dt_c",xdt);if(op==1){var xBus="menu";}
var xPos=location.href.indexOf(xBus);if(xPos!=-1){if(op==2){xClosePanelDe();const _DataLoadBack={i:xidCategoria,s:xidCategoriaSeccion,p:xidCatProcedencia}
window.localStorage.setItem("::app3_sys_dt_back",JSON.stringify(_DataLoadBack));xLoadItems();}
return;}
xOpenPageCarta(op);}
function xRegDataLoadBack(){const _DataLoadBack={i:xidCategoria,s:xidCategoriaSeccion,p:xidCatProcedencia}
window.localStorage.setItem("::app3_sys_dt_back",JSON.stringify(_DataLoadBack));}
function xCerrarSession(){$("body").removeClass("loaded");$.ajax({type:"POST",url:"../../bdphp/log.php?op=-103"}).done(function(a){setClearLocalStorage();});}
function goBack(){window.history.back();}
function xMantenerSession(){restaurarGalleta();dialog_inactividad.close();xOpenPageCarta(1);}