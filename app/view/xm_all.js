var xh_sys,xNomU,xNomUsario,xCargoU,xUsAc_Ini,xRowObj,xTableRow,xIdROw,xIdOrg,xIdSede,_socketSuperMaster=null,xIdUsuario="",xidCategoria=1;function xGetTT(n,i){var r,e,s="";""!==i?(r=new Date,e=new Date,s=i.split(":"),r.setHours(s[0],s[1],s[2]),e.setHours(0,0,0),r-=e):i=0,xTiempoTranscurridos_2(xh_sys,function(e,o,t,a){if(0!==i)switch(s=100*parseFloat(a/r).toFixed(1)){case 70:$("#"+n).css("color","#F90");break;case 100:$("#"+n).css("color","#F00")}$("#"+n).text(e+":"+o+":"+t)})}function xFondoImg(e){e=(e=e.slice(0,-1)).split(";");$.backstretch(e,{fade:650,duration:12e3})}function xFondoVideo(e){e='<iframe id="xvideo" src="//www.youtube.com/embed/'+e+"?enablejsapi=1&version=3&loop=1&playlist="+e+'&modestbranding=0&showinfo=0&rel=0&autoplay=1&iv_load_policy=3" frameborder="0" style="overflow:hidden;overflow-x:hidden;overflow-y:hidden;height:100%;width:100%;position:absolute;top:0px;left:0px;right:0px;bottom:0px" height="100%" width="100%"></iframe>';$(".Content_pantalla").append(e).trigger("create")}function xAnimTk(e,o){3==o?$("#"+e).animate({top:"0px"},1500,"swing",function(){document.getElementById("sonido_notifica").play(),1==xLamarVoz&&xVoz(e)}):$("#"+e).animate({marginLeft:"0px"},1500,"swing",function(){document.getElementById("sonido_notifica").play(),1==xLamarVoz&&xVoz(e)})}function xAnimHideTk(e){$("#"+e).animate({opacity:"0"},1800,"swing",function(){$("#"+e).hide("slow",function(){$("#"+e).remove(),e=e.replace("t",""),$.post("../../bdphp/log.php?op=503",{i:e},function(){return!1})})})}function xVoz(e,o){var t="es-ES";if(null!==e){for(var e=$("#"+e),a=e.find("#tk_num").text(),n=e.find("#tk_titulo2").text(),e=parseInt(e.find("#tk_m_num").text()),i=a.replace(a.match(/[0-9]+/g)[0],""),r="",a=a.match(/[0-9]+/g)[0],i=i.split(""),s=0;s<i.length;s++)r=r+"-"+i[s];a='Ticket "'+r+'" "'+a+'", pase, a '+n+' "'+e+'"'}else a=o;"speechSynthesis"in window?((n=new SpeechSynthesisUtterance).voiceURI="native",n.volume=1,n.rate=1,n.pitch=1,n.lang=t,n.text=a,window.speechSynthesis.speak(n)):((e=new Audio).src="../../bdphp/vozx.php?txt="+a+"&lenguaje="+t,e.type="audio/mpeg",e.play())}function xvalidateForm(e,o){var e=$("#"+e),t=!0;e.find("paper-input").each(function(e,o){o.validate()||(t=!1)}),o(t)}function xvalidateFormInput(e,o){var e=$("#"+e),t=!0;e.find("input").each(function(e,o){o.checkValidity(),!1===o.validity.valid&&(t=!1,$(o).addClass("invalido"))}),o(t)}function xvalidateObjFormInput(e,o){var t=!0;e.find("input").each(function(e,o){o.checkValidity(),!1===o.validity.valid&&(t=!1,$(o).addClass("invalido"))}),o(t)}function xBorrarRegistroFisico(e,o){$.post("../../bdphp/log.php?op=101",{t:e,id:o})}function xBorrarRegistroFisico2(e,o,t){$.post("../../bdphp/log.php?op=10101",{t:e,id:o,campo:t})}function xBorrarRegistro(e,o){$.post("../../bdphp/log.php?op=103",{t:e,id:o})}function xBorrarRegistroCampo(e,o,t,a){$.post("../../bdphp/log.php?op=106",{t:e,id:o,campo:t,c:a})}function xBorrarRegistroEnAnulado(e,o){$.post("../../bdphp/log.php?op=104",{t:e,id:o})}function xBorrarItem(e){null!==e&&(xRowObj=e.parentNode.parentNode,xTableRow=$(xRowObj).attr("data-t"),xIdROw=$(xRowObj).attr("data-id")||xRowObj.dataId),xBorrarRegistro(xTableRow,xIdROw),$(xRowObj).fadeTo(550,0,function(){$(this).remove()})}function xBorrarItemLogicoCampo(e,o){null!==e&&(xRowObj=e.parentNode.parentNode,xTableRow=$(xRowObj).attr("data-t"),xIdROw=$(xRowObj).attr("data-id")||xRowObj.dataId,xCampo=$(xRowObj).attr("data-campo")||xRowObj.dataCampo),xBorrarRegistroCampo(xTableRow,xIdROw,xCampo,o),$(xRowObj).fadeTo(550,0,function(){$(this).remove()})}function xBorrarItemLocal(e){$(e).parent().fadeTo(550,0,function(){$(this).remove()})}function xScrollIrA(e){$("body,html").stop(!0,!0).animate({scrollTop:$(e).offset().top-5},1e3)}function xLoadPageTerminal(e,o){var t=xStorageId();$.ajax({type:"POST",url:"bdphp/log.php?op=10",data:{i:e,xi:t}}).done(function(e){e=$.parseJSON(e);e.success?0<e.datos.length?(window.localStorage.setItem("xweb::i",e.datos[0].xi),o(e.datos[0])):o(null):alert(e.error)})}function xStorageId(){return window.localStorage.getItem("xweb::i")}function xRowFocusInput(e,o){var t;0<$(e).find(".xTextRow").length||(t=$(e).text(),$(e).html('<input type="text" onblur="xRowFocusInput_blur(this,'+o+')" class="xTextRow" value="'+t+'" focus>').trigger("create"))}function xRowFocusInput_blur(e,o){"1"==o&&xRetornaMoneda(e),o=$(e).val(),$(e).parent().text(o),$(e).remove()}function xGeneraCod(){for(var e,o="0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ",t="",a=0;a<5;a++)e=Math.floor(Math.random()*o.length),t+=o.substr(e,1);return t}function xEnumerarTbRow(e,o){var t=1;$(e).find(o).each(function(e,o){$(o).text(xCeroIzq(t,2)),t++})}function xSumaCantRow(e,o){var t=0;return $(e).find(o).each(function(e,o){isNaN(parseInt($(o).text()))||(t+=parseFloat($(o).text()))}),t}function xSumaCantRowVisible(e,o){var t=0;return $(e).find(o).each(function(e,o){$(o).is(":hidden")||isNaN(parseInt($(o).text()))||(t+=parseFloat($(o).text()))}),t}function xContarCantRow(e,o){var t=0;return $(e).find(o).each(function(e,o){t++}),t}function xContarCantRowVisible(e,o){return $(e).find(o).filter(":visible").length}function xContarCantRowAttr(e,t,a){var n=0;return $(e).find(".row").each(function(e,o){$(o).attr(t)==a&&n++}),n}function xObtnerValSumRowAttr(e,t,a,n){var i=0;return $(e).find(".row").each(function(e,o){$(o).attr(t)==a&&(i=parseFloat(i)+parseFloat($(o).find(n).text()))}),i}function xLoadSetDatosSession(o){$.ajax({type:"POST",url:"../../bdphp/log.php?op=-101"}).done(function(e){e=$.parseJSON(e);!1===e.success?alert(e.error):(xIdOrg=e.datos[0].ido,xIdSede=e.datos[0].idse,xIdUsuario=e.datos[0].idu,xNomU=e.datos[0].nomU,xNomUsario=e.datos[0].nomUs,xCargoU=e.datos[0].cargoU,window.localStorage.setItem("::app3_wo",xIdOrg),window.localStorage.setItem("::app3_woS",xIdSede),window.localStorage.setItem("::app3_woU",xIdUsuario),window.localStorage.setItem("::app3_woA",e.datos[0].acc),window.localStorage.setItem("::app3_woNus",e.datos[0].nomUs),o(e.datos[0].acc))})}function xVerificarSession(){$.ajax({type:"POST",url:"../../bdphp/log.php?op=-104"}).done(function(e){1==e&&setClearLocalStorage()})}function xLoadImpresoras(){for(var e=xm_log_get("app3_woIpPrint"),o=window.localStorage.getItem("::app3_woIpPrintLoC"),t=0;t<e.length;t++)if(1==e[t].local&&o===e[t].descripcion)return window.localStorage.setItem("::app3_woIpPrintLoC",e[t].descripcion),void window.localStorage.setItem("::app3_woIpPrintLo",JSON.stringify(e[t]))}function xCerrarSessionAll(){$("body").removeClass("loaded"),$.ajax({type:"POST",url:"../../bdphp/log.php?op=-103"}).done(function(e){setClearLocalStorage()})}function setClearLocalStorage(e=!0){var o=window.localStorage.getItem("::app3_woIpPrintLoC"),t=window.localStorage.getItem("::app3_sys_vr_touch"),a=window.localStorage.getItem("::app3_sys_last_s"),n=window.localStorage.getItem("::app3_sys_vr_show_opcion"),i=window.localStorage.getItem("::app3_sys_vr_touch"),r=window.localStorage.getItem("::app3_woZD"),s=window.localStorage.getItem("::app3_woZD_TP"),c=window.localStorage.getItem("::app3_woZD_orderItemVerticalZD");window.localStorage.clear(),o&&window.localStorage.setItem("::app3_woIpPrintLoC",o),t&&window.localStorage.setItem("::app3_sys_vr_touch",t),a&&window.localStorage.setItem("::app3_sys_last_s",a),n&&window.localStorage.setItem("::app3_sys_vr_show_opcion",n),i&&window.localStorage.setItem("::app3_sys_vr_touch",i),r&&window.localStorage.setItem("::app3_woZD",r),s&&window.localStorage.setItem("::app3_woZD_TP",s),c&&window.localStorage.setItem("::app3_woZD_orderItemVerticalZD",c),e&&(document.location.href="../../logueese-9e9140c8.html")}function getCookie(e){e=("; "+document.cookie).split("; "+e+"=");if(2==e.length)return e.pop().split(";").shift()}function xm_LogIni(o){$.ajax({type:"POST",url:"../../bdphp/log.php?op=-1111"}).done(function(e){"0"==e?(xVerificarSession(),o(!1)):(window.localStorage.setItem("::app3_woDUS",e),o(!0))})}function xm_LogChequea(o){var e=window.localStorage.getItem("::app3_woDUS"),e=null===e?"undefined":e;$.ajax({type:"POST",url:"../../bdphp/log.php?op=-1112",data:{d:e}}).done(function(e){switch(e){case"0":xm_LogIni(function(e){e&&o(!0)});break;case"1":o(!0);break;case"2":xVerificarSession();break;default:return window.localStorage.setItem("::app3_woDUS",e),o(!0)}})}function xm_log_get(e){var o,t=window.localStorage.getItem("::app3_woDUS");try{t=window.atob(t),t=JSON.parse(t)}catch(e){return}switch(e){case"app3_us":o=t.us;break;case"ini_us":xIdOrg=t.us.ido,xIdSede=t.us.idsede,xIdUsuario=t.us.idus,xNomU=t.us.nombre,xNomUsario=t.us.nomus,xCargoU=t.us.cargo,xUsAc_Ini=t.us.acc;break;case"app3_Us_home":o=t.sistema.url;break;case"app3_sys_const":o=t.sistema.constantes;break;case"cpe_alerts":o=t.sistema.cpe_alert;break;case"app3_woA":o=t.us.acc;break;case"app3_woPer":o=t.us.per;break;case"app3_woIpPrint":o=t.dispositivos.dispositivo;break;case"app3_woIpPrintO":o=t.dispositivos.otros_print_doc;break;case"sede_generales":o=t.sede.generales;break;case"sede_otros_datos":o=t.sede.otros_datos;break;case"categorias":o=t.carta.categorias;break;case"carta_subtotales":o=t.carta.subtotales;break;case"estructura_pedido":o=t.carta.estructura_pedido;break;case"reglas_de_carta":o=t.carta.regla_carta;break;case"datos_org_sede":o=t.sede.datos_org_sede;break;case"datos_org_all_sede":o=t.sede.datos_org_all_sede;break;case"datos_sede_variables":o=t.sede.datos_sede_variables}return o}function getVariableSede(e){let o=xm_log_get("datos_sede_variables");try{o?((o=o[0]).switch1="1"===o.switch1,o.switch2="1"===o.switch2,o.update_stock_after="1"===o.update_stock_after):o={switch1:!1,switch2:!1,num_intentos_cierre:3,update_stock_after:!1,hora_cierre:"0:00"}}catch(e){o={switch1:!1,switch2:!1,num_intentos_cierre:3,update_stock_after:!1,hora_cierre:"0:00"}}return o[e]}async function xGetFindCliente(s,c,e,l){var o,d=[],t="XXeyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.1tLS4vhIGufCW5H5vJ4bmNxhf43x-Vaik4oRwaDXi7E",a="dni"===c?"ndni":"ruc",n=s,p=!1;e?xVerificarRucChangeSunat(s,e=>{l(e)}):(e=await $.ajax({type:"POST",url:"../../bdphp/log.php?op=602",data:{doc:s}}),0<(e=JSON.parse(e)).datos.length?(e=e.datos[0],p="ruc"==a,d={success:!0,idcliente:e.idcliente,nombres:e.nombres,direccion:e.direccion,num_doc:e.ruc,telefono:e.telefono,msg:"ok",buscarSunat:p,f_nac:e.f_nac},l(d)):xValidarToken(t,e=>{"error"===(t=e)&&l(d={success:!0,idcliente:"",nombres:"",direccion:"",num_doc:"",telefono:"",msg:"ok"}),o="../../consulta/"+c+"/api/service.php?"+a+"="+s+"&token="+t,$.ajax({type:"POST",url:o}).done(function(e){e=JSON.parse(e);var o,t,a="",n="",i=s,r="";e.success&&e.haydatos?"ruc"===c?(a=e.result.RazonSocial,n=e.result.Direccion):(t=e.result.ApellidoPaterno||"",o=e.result.ApellidoMaterno||"",t=""===t?e.result.apellidos||"":t+" "+o,a=" "===(a=e.result.Nombres+" "+t)?"":a,n="",r=e.result.FechaNacimiento||""):l(d={success:!0,idcliente:"",nombres:"",direccion:"",num_doc:i,telefono:"",msg:"ok"}),d={success:e.haydatos,idcliente:"",nombres:a,direccion:n,num_doc:i,f_nac:r,telefono:"",msg:e.msg,buscarSunat:p},l(d)}).fail((e,o)=>{l(d={success:!1,idcliente:"",nombres:"",direccion:"",f_nac:"",num_doc:n,telefono:"",msg:"Problemas de conexion. intente nuevamente en un momento."})})}))}function xVerificarRucChangeSunat(i,r){xValidarToken("XXeyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.1tLS4vhIGufCW5H5vJ4bmNxhf43x-Vaik4oRwaDXi7E",e=>{var n={};_url_servicio="../../consulta/ruc/api/service.php?ruc="+i+"&token="+e,$.ajax({type:"POST",url:_url_servicio}).done(function(e){e=JSON.parse(e);var o="",t="",a=i;e.success&&e.haydatos?(o=e.result.RazonSocial,t=e.result.Direccion):r(n={success:!0,idcliente:"",nombres:"",direccion:"",num_doc:a,telefono:"",msg:"ok"}),n={success:e.haydatos,idcliente:"",nombres:o,direccion:t,num_doc:a,f_nac:"",telefono:"",msg:e.msg,buscarSunat:!1},r(n)})})}function xValidarToken(e,t){var o=e,e="../../consulta/dni/api/validar.php?token="+o;$.ajax({type:"POST",url:e,timeout:3e3}).done(function(e){(e=JSON.parse(e)).success?t(o):xRefreshToken(e=>{t(o=e)})}).fail((e,o)=>{t("error")})}function xRefreshToken(e){e("eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.1tLS4vhIGufCW5H5vJ4bmNxhf43x-Vaik4oRwaDXi7E")}function xm_all_SetResponseLog_001(e){var e=e.split("|"),t={};return e.map(e=>{if(""!==e){const o=JSON.parse(e);Object.keys(o).map(e=>{t[e]=o[e]})}}),t}function xm_all_xToastOpen(e,o=0,t=!0,a=!1){e=null===e?"Cargando...":e,t?$("#toast #loading").removeClass("xInvisible"):$("#toast #loading").addClass("xInvisible"),a?$("#toast #icon-wifi").removeClass("xInvisible"):$("#toast #icon-wifi").addClass("xInvisible"),(toast=document.getElementById("toast")).duration=o,toast.text=e,toast.show()}function xm_all_xToastClose(){(toast=document.getElementById("toast")).hide()}function delay(t,a){var n=0;return function(){var e=this,o=arguments;clearTimeout(n),n=setTimeout(function(){t.apply(e,o)},a||0)}}function xDelay(o){return new Promise(e=>{setTimeout(()=>{e(2)},o)})}function setImportHTML(e){e=e.trim().split(",");let o=document.createElement("link");o.rel="import",e.map(e=>{o.href=e,o.onload=onload,document.head.appendChild(o)})}function objectifyForm(e){for(var o={},t=0;t<e.length;t++)o[e[t].name]=e[t].value;return o}function pantallaCompleta(){var e=document.documentElement;window.screenTop||window.screenY?document.mozCancelFullScreen?document.mozCancelFullScreen():document.webkitExitFullscreen&&document.webkitExitFullscreen():e.mozRequestFullScreen?e.mozRequestFullScreen():e.webkitRequestFullscreen&&e.webkitRequestFullscreen()}async function xSendEmailClienteSES(e){var o=URL_SERVER+"delivery/send-email-ses";await $.ajax({type:"POST",url:o,data:{msj:e}}).done(e=>e)}function xCalcMontoBaseIGV(e,o){return parseFloat(parseFloat(e)/(1+o)).toFixed(2)}function getDataUsRRHH(){xm_log_get("app3_us")[0];var e=xm_log_get("datos_org_sede")[0],o=xm_log_get("datos_org_all_sede")[0];return{user:{usuario:xNomU,pass:"",idusuario_restobar:parseInt(xIdUsuario),nombres:xNomUsario,cargo:xCargoU},org:{idorg_restobar:parseInt(e.idorg),nombre:e.nombre,direccion:e.direccion,ruc:e.ruc,telefono:e.telefono},sede:{idsede_restobar:parseInt(o.idsede),ruc:e.ruc,razon_social:e.nombre,ciudad:o.ciudad,direccion:o.direccion,telefono:o.telefono,nombre:o.nombre}}}async function xVerificarCodeResponseCPE(e){var o=xm_log_get("cpe_alerts");const t=e.code;o=o.filter(e=>e.code==t)[0];if(o){if("1"!=o.nivel)return!1;o=new httpFecht,e={tipo:"error",mensaje:e.description||e.message,codigo:e.code},o=(await o.postJson("../../bdphp/log_009.php?op=31",e),paramsSwalAlert),e=(o.html=`<div class="p-1"> 
											<p class="fw-600 fs-20 text-danger">Problemas con la facturación electrónica.</p>
											<p class="fw-100 fs-14">${e.mensaje}</p>
											<p class="fw-600 fs-14 text-warning">Comuniquese con soporte técnico.</p>
										</div>`,o.showCancelButton=!1,o.showConfirmButton=!0,o.confirmButtonText="Entendido.",await showAlertSwalHtmlDecision(o));e.isConfirmed&&setTimeout(()=>{window.location.reload()},1500)}}function checkAlertCPESede(){try{var e,o=xm_log_get("datos_org_all_sede")[0];"1"==o.is_bloqueado_facturacion&&((e=paramsSwalAlert).html=`<div class="p-1">
										<p class="fw-600 fs-20 text-danger">Problemas con la facturación electrónica.</p>
										<p class="fw-100 fs-15">${o.msj_cpe_alert}.</p>	
										<p class="fw-100 fs-15 text-warning">Comuniquese con soporte técnico.</p>
									</div>`,e.showCancelButton=!1,e.showConfirmButton=!0,e.confirmButtonText="Entendido.",showAlertSwalHtmlDecision(e))}catch(e){}}window.addEventListener("error",function(e){});