function xMoneda(t){var r="";try{r=isNaN(parseFloat(t).toFixed(2))?"":parseFloat(t).toFixed(2)}catch(e){r=t}return r}function xRetornaMoneda(e){var t=parseFloat($(e).val());isNaN(t)&&(t=0),e.value=parseFloat(t).toFixed(2)}async function delay(t){return new Promise(e=>setTimeout(e,t))}function numeroConComas(e){return e.toString().replace(/\B(?=(\d{3})+(?!\d))/g,",")}function removeComas(e){return isNaN(e)?parseFloat(e.replace(/,/g,"")):parseFloat(e)}function xCeroIzq(e,t){var r=parseInt(e);if(!isNaN(r))for(e=e.toString();e.length<t;)e="0"+e;return e}function xCeroIzqNumComprobante(e){if(e){for(e=e.toString();e.length<7;)e="0"+e;return e}}function conMayusculas(e){e.value=e.value.toUpperCase()}function primeraConMayusculas(e){return e&&e.toLowerCase().replace(/\b(\w)/g,e=>e.toUpperCase())}function toTitleCase(e){return e.replace(/\w\S*/g,function(e){return e.charAt(0).toUpperCase()+e.substr(1).toLowerCase()})}function getUrlParameter(e,t){for(var r=window.location.href.replace("-"," ").split(t),n=0;n<r.length;n++){var a=r[n].split("=");if(a[0]==e)return a[1]}}function xRemoverDOM(e,t){$(e).find(t).remove().trigger("create")}function xxCalcularTotal(e,t){return xPrecioTT=0,e.find(t).each(function(e,t){xPrecioTT=parseFloat(xPrecioTT)+parseFloat($(t).text())}),xPrecioTT}function xDevolverFechaTrim(){var e=new Date;return xCeroIzq(e.getDate(),2)+xCeroIzq(e.getMonth()+1,2)+e.getFullYear()}function xDevolverFechaTrim2(){var e=new Date;return xCeroIzq(e.getDate(),2)+xCeroIzq(e.getMonth()+1,2)+e.getYear()}function xDevolverFecha(){var e=new Date;return xCeroIzq(e.getDate(),2)+"/"+xCeroIzq(e.getMonth()+1,2)+"/"+e.getFullYear()}function xDevolverFechaDDMMYYY(e){return xCeroIzq(e.getDate(),2)+"/"+xCeroIzq(e.getMonth()+1,2)+"/"+e.getFullYear()}function xDevolverFechaFormatInputDate(e){return e.split("-").reverse().join("/")}function xSetInputDate(e){return e.split("/").reverse().join("-")}function convertirHoraFormat12Hrs(e){var e=e.split(":"),t=parseInt(e[0]),r=e[1],e=e[2];return 0===t?"12:"+r+":"+e+" AM":t<12?t+":"+r+":"+e+" AM":12===t?"12:"+r+":"+e+" PM":t-12+":"+r+":"+e+" PM"}function obtenerDiaFecha(e){e=new Date(e);return["Domingo","Lunes","Martes","Miércoles","Jueves","Viernes","Sábado"][e.getDay()]+" "+e.getDate()}function xDevolverFechaParte(e){var t,r=new Date;switch(e){case"dd":t=xCeroIzq(r.getDate(),2);break;case"mm":t=xCeroIzq(r.getMonth()+1,2);break;case"mmInt":t=r.getMonth()+1;break;case"mmInt0":t=r.getMonth();break;case"mmmm":t=xDesMes(r.getMonth());break;case"yy":t=r.getFullYear()}return t}function xDevolverFechaDada2(e){return new Date(e).toISOString().slice(0,10).split("-").reverse().join("/")}function xDevolverFechaParte_Dada(e,t){var r,n=new Date(e.split("/").reverse().join("/"));switch(t){case"dd":r=xCeroIzq(n.getDate(),2);break;case"mm":r=xCeroIzq(n.getMonth()+1,2);break;case"mmInt":r=n.getMonth()+1;break;case"mmInt0":r=n.getMonth();break;case"mmmm":r=xDesMes(n.getMonth());break;case"yy":r=n.getFullYear()}return r}function xDevolverHora(){var e=new Date;return xCeroIzq(e.getHours(),2)+":"+xCeroIzq(e.getMinutes(),2)+":"+xCeroIzq(e.getSeconds(),2)}function xDiasTranscurridos(e,t){return""==e&&(e=xDevolverFecha(),e=new Date(e.split("/").reverse().join("/"))),t=new Date(t.split("/").reverse().join("/")),Math.floor((e-t)/864e5)}function xDiasTranscurridos_f1(e,t){return""==e&&(e=xDevolverFecha()),(e=(e=new Date(e.split("/").reverse().join("/")))-(t=new Date(t.split("/").reverse().join("/"))))<0&&(e*=-1),e=Math.floor(e/864e5)}function xTiempoTranscurridos(e,t,r){var e=e.split(":"),t=t.split(":"),n=new Date,a=new Date,e=(null==t[2]&&(t[2]="00"),null==e[2]&&(e[2]="00"),n.setHours(e[0],e[1],e[2]),a.setHours(t[0],t[1],t[2]),a-n),t=Math.floor(e/1e3),a=t%60,n=Math.floor(t/60),t=n%60;r(xCeroIzq(Math.floor(n/60)%24,2),xCeroIzq(t,2),xCeroIzq(a,2),e)}function xTiempoTranscurridos_2(e,t){var r=xDevolverHora().split(":"),e=e.split(":"),n=new Date,a=new Date,r=(null==e[2]&&(e[2]="00"),a.setHours(r[0],r[1],r[2]),n.setHours(e[0],e[1],e[2]),a-n),e=Math.floor(r/1e3),a=e%60,n=Math.floor(e/60),e=n%60;t(xCeroIzq(Math.floor(n/60)%24,2),xCeroIzq(e,2),xCeroIzq(a,2),r)}function xTiempoMS(e){e=e.split(":");return t1=new Date,t2=new Date,t1.setHours(e[0],e[1],e[2]),t1.setHours(0,0,0),t1-t2}function xTiempoDe_MS_Hora(e){var e=Math.floor(e/1e3),t=e%60,e=Math.floor(e/60),r=e%60;return xCeroIzq(Math.floor(e/60)%24,2)+":"+xCeroIzq(r,2)+":"+xCeroIzq(t,2)}function xTiempoDe_MS_minutos(e){var e=Math.floor(e/1e3),t=e%60,e=Math.floor(e/60),r=e%60,e=Math.floor(e/60)%24;return 0<e&&(r+=e*=60),xCeroIzq(r,2)+":"+xCeroIzq(t,2)}function xTiempoTrascurridoYYMMDD(e){var t=e%365,r=t%31,e=(year=(e-t)/365,month=(t-r)/31,0<year?year+" AÑOS ":"");return e+(0<month?month+" MESES Y ":"")+r+" DIAS"}function xSumaDiasFecha(e,t){e=(e=new Date(e.split("/").reverse().join("/"))).setDate(e.getDate()+t);return xCeroIzq((d=new Date(e)).getDate(),2)+"/"+xCeroIzq(d.getMonth()+1,2)+"/"+d.getFullYear()}function xHorasQueFaltan(e,t,r){var n,t=t.split(":"),a="",e=(e=new Date(e.split("/").reverse().join("/"))).setDate(e.getDate()+1),t=((e=new Date(e)).setHours(t[0],t[1],t[2]),new Date),o=(e.getTime()-t.getTime())/1e3,a="";return(n=dias=Math.floor(o/86400))<0&&(a="-",o=(t.getTime()-e.getTime())/1e3,dias=Math.floor(o/86400)),o=(o=(o-=86400*dias)-3600*(horas=Math.floor(o/3600)))-60*(minutos=Math.floor(o/60)),segundos=Math.floor(o),xRpt=xCeroIzq(horas,2)+":"+xCeroIzq(minutos,2)+":"+xCeroIzq(segundos,2),r(n,horas,xRpt),a+xRpt}function xReturnSumaFechaDada(e,t,r){var n;switch(e=e||new Date,t){case"dd":n=e.getDate()+r;break;case"mm":n=e.getMonth()+r;break;case"yy":n=e.getFullYear()+r}return n}function xDiasDeUnMes(e,t){return dias=[31,29,31,30,31,30,31,31,30,31,30,31],ultimo=(ultimo=0)==(ultimo=1==e&&(fecha=new Date(t,1,29),(vermes=fecha.getMonth())!=e)?28:ultimo)?dias[e]:ultimo}function xDesMes(e){var t;switch(e){case 0:t="ENERO";break;case 1:t="FEBRERO";break;case 2:t="MARZO";break;case 3:t="ABRIL";break;case 4:t="MAYO";break;case 5:t="JUNIO";break;case 6:t="JULIO";break;case 7:t="AGOSTO";break;case 8:t="SETIEMBRE";break;case 9:t="OCTUBRE";break;case 10:t="NOVIEMBRE";break;case 11:t="DICIEMBRE"}return t}function xDesDiaSemana(e,t=!1){var r=["Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sabado"];return t?(r=["Lunes","Martes","Miercoles","Jueves","Viernes","Sabado","Domingo"])[new Date(e).getDay()]:r[e-1]}function xMayusculaPrimera(e){return e.charAt(0).toUpperCase()+e.slice(1)}function xSoloMayusculas(e){return e.toUpperCase()}function xSoloMinuscula(e){return e.toLowerCase()}function conMayusculas(e){e.value=e.value.toUpperCase()}function xImprSelec(e){var e=document.getElementById(e),t=window.open(" ","popimpr");t.document.write(e.innerHTML),t.document.close(),t.print(),t.close()}function xImprSelec2(e){var e=document.getElementById(e),t=window.open(" ","popimpr");t.document.write(e.innerHTML),t.document.close(),t.print(),t.close()}function xImprSelec3(e){var e=document.getElementById(e),t=window.open(" ","popimpr");t.document.write("<style>@page{margin-left:0px;} .xpage{position:relative;} span{position:absolute; float:left;}</style>"+e.innerHTML),t.document.close(),t.print(),t.close()}function ImprBoletaConCSS(e,t){var e=document.getElementById(e),r=window.open(" ","popimpr");r.document.write('<html><head><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><script>jQuery(document).ready(function() { setTimeout(function() { window.close(); }, 1); }); <\/script><style type="text/css" media="print">table{background-color:#FFF;} @page{ margin: 0px;}</style>'+(t=""!=t?"<h3>"+t+"</h3><br>":t)+e.innerHTML+"</body></html>"),r.print(),r.document.close(),r.close()}function xImprBoletaConCSS2(e,t){var e=document.getElementById(e),r=window.open(" ","popimpr");r.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}</style>'+(t=""!=t?"<h3>"+t+"</h3><br>":t)+" "+e.innerHTML+"</body></html>"),r.print(),r.close()}function ImprBoletaConCSS2(e,t){var e=document.getElementById(e),r=window.open(" ","popimpr");r.document.write('<html><meta charset="utf-8"><link rel="stylesheet" href="../../css/micss.css"/></head><body onload="window.print()"><style>table{background-color:#FFF;}table tr td img{opacity:1;} </style>'+(t=""!=t?"<h3>"+t+"</h3><br>":t)+e.innerHTML+"</body></html>"),r.print(),r.document.close(),r.close()}function xArmarInsertDetalle(e,o,i,s,c){var u,l="",d="",f="",p=(""==c&&(c=null),""!=(s=s||"")&&(t=s.split(" "),l="#"+t[0],f=t[1],d=t[2]),""),m="",h="",g="",x=new Array,t="",v="",w="",b=e.attr("data-TablaName"),D="",M=0,I=0,r=(e.find("tr").each(function(e,t){if(m="",$(t).hasClass("nomarcar")||1!=$(t).attr("data-yaguardo")){if($(t).attr("data-yaguardo",1),""!=s){var r=$(t).find(l).text();if(r.length)switch(f){case">":if(parseInt(r)<parseInt(d))return!0;break;case"<":if(parseInt(r)>parseInt(d))return!0;break;case">=":if(parseInt(r)<=parseInt(d))return!0;break;case"<=":if(parseInt(r)>=parseInt(d))return!0;break;case"=":if(parseInt(r)!=parseInt(d))return!0;break;case"!=":if(parseInt(r)==parseInt(d))return!0;break;case"==":if(r!=d)return!0}}var n;w="",n=$(t).attr("data-update"),u="id"+b+"="+n,$(t).find("td").each(function(e,t){if(D="",p=$(t).attr("data-ColumName"),xCampoOperacion=$(t).attr("data-operacion"),null!=n&&null!=p)if(m=0<$(t)[0].childElementCount?$(t).find("input").val():$(t).text(),null!=xCampoOperacion){switch(xCampoOperacion){case"resta":p=p+"="+p+"-";break;case"suma":p=p+"="+p+"+"}w=String(w+p+"'"+m+"',")}else w=String(w+p+"='"+m+"',");else null==c&&p&&(h=h+p+(D=0<=M?", ":D),m=0<$(t)[0].childElementCount?m+"'"+$(t).find("input").val()+"'"+D:m+"'"+$(t).text()+"'"+D,M++)}),""!=w?(w=w.slice(0,-1),v=String(v+" Update "+b+" set "+w+" where "+u+"; ")):""!=m&&(m=m.substr(0,m.length-2),h=h.substr(0,h.length-2),m=o?"("+i+","+m+")":"("+m+")",g=h=o?"("+o+","+h+")":"("+h+")",x[I]=m,I++,h="")}}),"");for(a in x)D=",",a==x.length-1&&(D=""),r=r+x[a]+D;return""!=g?t=""!=v?"insert into "+b+" "+g+" values "+r+"; "+v:"insert into "+b+" "+g+" values "+r:""!=v&&(t=v),t}function xBuscarArray(e,t){for(var r=0;r<e.length;r++)if(e[r]==value)return 1;return-1}function xBuscarTbData(e,t){new RegExp(t,"gi");$(e).find(".row").each(function(){$(this).text().search(new RegExp(t,"i"))<0?$(this).hide():$(this).show()})}function xBuscarTbDataVisible(e,t){new RegExp(t,"gi");$(e).find(".row").each(function(){$(this).is(":visible")&&($(this).text().search(new RegExp(t,"i"))<0?$(this).hide():$(this).show())})}function xBuscarAttrTbData(e,r,n){var a=!1;return $(e).find(".row").each(function(e,t){$(t).attr(r)==n&&(a=$(t))}),a}function xFiltrarRowAttr(e,r,n,a){$(e).find(".row").each(function(e,t){$(t).attr(r)==n&&(a?$(this).show():$(this).hide())})}function xFiltrarRowAttr2(e,r,n){$(e).find(".row").each(function(e,t){$(t).attr(r)==n?$(this).show():$(this).hide()})}function xBuscarAttrTbData2(e,r,n,t){var a=!1;return $(e).find(t).each(function(e,t){$(t).attr(r)==n&&(a=$(t))}),a}function xOrdernarArray(e,t){}async function inputFileImgToBase64(e,s,c){var u=e.target.files[0];return new Promise((i,t)=>{var e=new FileReader;e.readAsDataURL(u),e.onload=e=>{var o=document.createElement("img");o.src=e.target.result,o.onload=e=>{var t=document.createElement("canvas"),r=(t.getContext("2d").drawImage(o,0,0),o.width),n=o.height,a=Math.min(s/r,c/n),r=s<r?r*a:r,a=c<n?n*a:n;t.width=r,t.height=a;t.getContext("2d").drawImage(o,0,0,r,a);n=t.toDataURL(u.type);i(n)}},e.onerror=e=>t(e)})}function isSynOsWinOrMac(){var e=getOS();return"Windows"===e||"Mac"===e}function getOS(){var e=window.navigator.userAgent,t=window.navigator.platform,r=null;return-1!==["Macintosh","MacIntel","MacPPC","Mac68K"].indexOf(t)?r="Mac":-1!==["iPhone","iPad","iPod"].indexOf(t)?r="iOS":-1!==["Win32","Win64","Windows","WinCE"].indexOf(t)?r="Windows":/Android/.test(e)?r="Android":!r&&/Linux/.test(t)&&(r="Linux"),r}function PrintElemDiv(e,t){var e=document.getElementById(e).innerHTML,r=document.body.innerHTML;document.body.innerHTML="<html><head><title></title></head><body>"+e+"</body>",window.print(),document.body.innerHTML=r}function removeSpecialChar(e){return e.replace(/[&\/\\,~'"?]/g,"")}function removeSpecialCharObj(e){e.value=e.value.replace(/[&\/\\,~'"?]/g,"")}document.addEventListener("WebComponentsReady",function(){$(".xPasarEnter").on("keyup",function(e){var e=e.which;13!=e&&186!=e||(e=$("input:text").index(this)+1,$("input:text")[e].focus())})}),$.fn.reset=function(){$(this).each(function(){this.reset()})};const arrGroupBy=function(e,r){return e.reduce(function(e,t){return(e[t[r]]=e[t[r]]||[]).push(t),e},{})};function disableScrollRefresh(e){$("body").css("overscroll-behavior","contain")}function enableScrollRefresh(){$("body").css("overscroll-behavior","auto")}function getColorTipoPago(e){return["badge badge-secondary","badge badge-primary","badge bg-papaya","badge","badge badge-warning","badge badge-info","badge badge-dark"][e-1]}function removeSpecialCharString(e){return e.replace(/['`]/g,"")}function searchStringInPageActive(e){var t,r,n;if(document.querySelectorAll(".show-element-located-in-page").forEach(function(e){e.classList.remove("show-element-located-in-page")}),!(e.length<3))return n=document.body.innerText||document.body.textContent,t=new RegExp(e,"i"),e=n.match(t),r=0,e&&(r=0,n=document.querySelector(".grid-contente-pedidos"))&&Array.from(n.querySelectorAll("p, span")).filter(function(e){(e.innerText||e.textContent).match(t)&&(r++,e.classList.add("show-element-located-in-page"))}),r}async function pingServerPrintLocal(e){return!0}function showInfoOpenServerPrint(){var e=paramsSwalAlert;e.html=`<div class="p-1" style="overflow: hidden;"><i class="fa fa-info fa-2x text-warning" aria-hidden="true"></i>
                                <p class="mt-1 fs-18 text-warning">Por favor, inicie Laragon para que el servidor de impresión esté activo.</p>
								<p class="fs-14">Ubique el icono en su escritorio, de doble clic y luego abra nuevamente el servidor de impresion.</p>
								<img style="max-width: 340px;" src="../../images/gif/open_laragon_clic.gif">
                                </div>`,e.confirmButtonText="Entendido",e.showCancelButton=!1,showAlertSwalHtmlDecision(e)}