var xh_sys,
    // xLamarVoz=0,
    xIdUsuario="",
    xNomU,
    xNomUsario,
    xCargoU,
    xUsAc_Ini,
    xidCategoria=1,//cambiar luego desayuno/1:almuerzo/cena/
    xRowObj,
    xTableRow,
    xIdROw,
    xIdOrg,
    xIdSede;

//xTonoLLamada='notifica1',
//router,

// window.onerror = function(error, url, line) {
//   console.log(error);
//   // controller.sendLog({ acc: 'error', data: 'ERR:' + error + ' URL:' + url + ' L:' + line });
// };
window.addEventListener("error", function(e) {
	if (!e) { return }
  console.log(e);
//   alert(e.error);
  // You can send data to your server
  // sendError(data);
});

function xGetTT(ctr,t_limite){
	var xt_porcentaje='';
	//var xt_transcurrido;
	if(t_limite!==''){
		var t_l = new Date();
		var t_l2 = new Date();
		xt_porcentaje=t_limite.split(':');
		t_l.setHours(xt_porcentaje[0],xt_porcentaje[1],xt_porcentaje[2]);
		t_l2.setHours(0,0,0);
		t_l=t_l-t_l2;
	}else{t_limite=0;}
	xTiempoTranscurridos_2(xh_sys,function(a,b,c,d){
		if(t_limite!==0){
			xt_porcentaje=(parseFloat(d/t_l).toFixed(1))*100;
			switch(xt_porcentaje){
				case 70:$("#"+ctr).css('color','#F90');
					break;
				case 100:$("#"+ctr).css('color','#F00');
					break;
				}
			}
		$("#"+ctr).text(a+':'+b+':'+c);
		});
	}

function xFondoImg(ruta){
	var xRuta=ruta.slice(0,-1);
	xRuta=xRuta.split(';');
	$.backstretch(xRuta, {
        fade: 650,
        duration: 12000,
        //centeredX:true, centeredY:true
    });
}

function xFondoVideo(ruta){
	var xVideo='<iframe id="xvideo" src="//www.youtube.com/embed/'+ruta+'?enablejsapi=1&version=3&loop=1&playlist='+ruta+'&modestbranding=0&showinfo=0&rel=0&autoplay=1&iv_load_policy=3" frameborder="0" style="overflow:hidden;overflow-x:hidden;overflow-y:hidden;height:100%;width:100%;position:absolute;top:0px;left:0px;right:0px;bottom:0px" height="100%" width="100%"></iframe>';
	$(".Content_pantalla").append(xVideo).trigger('create');
}

function xAnimTk(idC,tp){
	//
	if(tp==3){
	$("#"+idC).animate({top:'0px'},1500,'swing',function(){
		document.getElementById('sonido_notifica').play();
		if(xLamarVoz==1){xVoz(idC);}
	});}else{
		$("#"+idC).animate({marginLeft:'0px'},1500,'swing',function(){
		document.getElementById('sonido_notifica').play();
		if(xLamarVoz==1){xVoz(idC);}
		});
	}
}

function xAnimHideTk(idC){
	$("#"+idC).animate({opacity:'0'},1800,'swing',function(){
		$("#"+idC).hide('slow', function(){
			$("#"+idC).remove();
			idC=idC.replace('t','');
			$.post('../../bdphp/log.php?op=503',{i:idC},function(){return false;});
		});
	});
}

function xVoz(tk,txt){
	var xVoz_lenguaje='es-ES';
	var xVoz_text;
	if(tk!==null){
		var xCtrl=$("#"+tk);
		var xNumTk=xCtrl.find('#tk_num').text();
		var xDesV=xCtrl.find('#tk_titulo2').text();
		var xNumV=parseInt(xCtrl.find('#tk_m_num').text());
		var xDesTk=xNumTk.replace(xNumTk.match(/[0-9]+/g)[0],'');
		var xDesTkLetra='';

		xNumTk=xNumTk.match(/[0-9]+/g)[0];
		xDesTk=xDesTk.split("");

		for (var i = 0; i < xDesTk.length; i++) {
			xDesTkLetra=xDesTkLetra+'-'+xDesTk[i];
		}

		xVoz_text='Ticket "'+xDesTkLetra+'" "'+xNumTk+'", pase, a '+xDesV+' "'+xNumV+'"';

	}
	else{xVoz_text=txt;}

	if ('speechSynthesis' in window) {
		var speech = new SpeechSynthesisUtterance();
		speech.voiceURI = 'native'; speech.volume = 1; speech.rate = 1.00; speech.pitch = 1;speech.lang = xVoz_lenguaje;
		speech.text=xVoz_text;
		window.speechSynthesis.speak(speech);
	}
	else{
		var audio = new Audio();
		audio.src = "../../bdphp/vozx.php?txt="+xVoz_text+"&lenguaje="+xVoz_lenguaje;
		console.log(audio.src);
		audio.type = "audio/mpeg";
		audio.play();

	}
}

function xvalidateForm(des,responde) {
	var obj=$('#'+des);
	var a=true;
	obj.find('paper-input').each(function(e,element){
		if(!element.validate()){a=false;}
	});
	responde(a);
}

function xvalidateFormInput(des,responde) {
	var obj=$('#'+des),
      a=true,
      b;
	obj.find('input').each(function(e,element){
		element.checkValidity();
		b=element.validity;
		if(b.valid===false){
			a=false;
			$(element).addClass('invalido');
		}
	});
	responde(a);
}
function xvalidateObjFormInput(obj,responde) {
	var a=true,
      b;
	obj.find('input').each(function(e,element){
		element.checkValidity();
		b=element.validity;
		if(b.valid===false){
			a=false;
			$(element).addClass('invalido');
		}
	});
	responde(a);
}


function xBorrarRegistroFisico(tabla,i){
	$.post('../../bdphp/log.php?op=101', {t:tabla, id:i});
}
function xBorrarRegistro(tabla,i){
	$.post('../../bdphp/log.php?op=103', {t:tabla, id:i});
}
function xBorrarRegistroEnAnulado(tabla,i){
	$.post('../../bdphp/log.php?op=104', {t:tabla, id:i});
}
function xBorrarItem(obj){
	if(obj!==null){
		xRowObj=obj.parentNode.parentNode;
		xTableRow=$(xRowObj).attr('data-t');
		xIdROw=$(xRowObj).attr('data-id');
	}
	xBorrarRegistro(xTableRow,xIdROw);
	$(xRowObj).fadeTo(550, 0, function () {$(this).remove();});
}
function xBorrarItemLocal(obj){
	$(obj).parent().fadeTo(550, 0, function () {$(this).remove();});
}
function xScrollIrA(xIdControl){$('body,html').stop(true,true).animate({scrollTop: $(xIdControl).offset().top-5},1000);}
 //Element.prototype.remove = function() { this.parentElement.removeChild(this); } NodeList.prototype.remove = HTMLCollection.prototype.remove = function() { for(var i = this.length - 1; i >= 0; i--) { if(this[i] && this[i].parentElement) { this[i].parentElement.removeChild(this[i]); } } }}

function xLoadPageTerminal(i, responde){
 	var xi=xStorageId();
 	$.ajax({ type: 'POST', url: 'bdphp/log.php?op=10', data:{i:i, xi:xi}})
	.done( function (dt) {
		var xdt=$.parseJSON(dt);
		if(!xdt.success){alert(xdt.error); return;}
		//
		if(xdt.datos.length>0){
			window.localStorage.setItem('xweb::i',xdt.datos[0].xi);
			responde(xdt.datos[0]);
		}
		else{
			responde(null);
		}
	});
 }

function xStorageId(){
	return window.localStorage.getItem('xweb::i');
}

function xRowFocusInput(xRow,xmoneda){
	if($(xRow).find('.xTextRow').length>0){return;}
	var xValRow=$(xRow).text();
	$(xRow).html('<input type="text" onblur="xRowFocusInput_blur(this,'+xmoneda+')" class="xTextRow" value="'+xValRow+'" focus>').trigger('create');
}
function xRowFocusInput_blur(obj,xmoneda){
	var xval='';
	if(xmoneda=='1'){xRetornaMoneda(obj);}
	xval=$(obj).val();
	$(obj).parent().text(xval);
	$(obj).remove();
}

function xGeneraCod(){
	var chars = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ",
      code = "",
      lon=5,
      rand;
	for (var x=0; x < lon; x++){
		rand = Math.floor(Math.random()*chars.length);
		code += chars.substr(rand, 1);
	}
	return code;
}

function xEnumerarTbRow(tb,nomCol){
	var cuenta=1;
	$(tb).find(nomCol).each(function(index, element){
		$(element).text(xCeroIzq(cuenta,2));
		cuenta++;
	});
}

function xSumaCantRow(tb,nomCol){
	var suma=0;
	$(tb).find(nomCol).each(function(index, element){
		if(!isNaN(parseInt($(element).text()))){
			suma=suma+parseFloat($(element).text());
		}
	});
	return suma;
}
function xSumaCantRowVisible(tb,nomCol){
	var suma=0;
	$(tb).find(nomCol).each(function(index, element){
		if($(element).is(':hidden')){return;}
		if(!isNaN(parseInt($(element).text()))){
			suma=suma+parseFloat($(element).text());
		}
	});
	return suma;
}


function xContarCantRow(tb,nomCol){
	var cuenta=0;
	$(tb).find(nomCol).each(function(index, element){
		cuenta++;
	});
	return cuenta;
}

function xContarCantRowVisible(tb,nomCol){
	var cuenta=0;
	// cuenta=$(tb).find(nomCol).filter(":visible").size();
	cuenta = $(tb).find(nomCol).filter(":visible").length;
	/*$(tb).find(nomCol).each(function(index, element){
		if($(element).is(':hidden')){return};
		cuenta++;
	});*/
	return cuenta;
}
function xContarCantRowAttr(tb,BuscarEn,filter){
	var cuenta=0;
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){
			cuenta++;
		}
    });
    return cuenta;
}
//obtener la suma del total row segun attr
//subfind = td donde esta el valor
function xObtnerValSumRowAttr(tb,BuscarEn,filter,subfind){
	var cuenta=0;
	$(tb).find(".row").each(function (index, element) {
		var xIdRowTb=$(element).attr(BuscarEn);
		if(xIdRowTb==filter){
			cuenta=parseFloat(cuenta)+parseFloat($(element).find(subfind).text());
		}
    });
    return cuenta;
}

/*function xLoadHoraChekOut(){
	//solo una vez al iniciar session
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=903'})
	.done( function (dth) {window.localStorage.setItem('::app2_wo::out',dth) ;})
	//habilitar fecha check_in en habitacion
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=9033'})
	.done( function (dth) {window.localStorage.setItem('::app2_wo::fci',dth) ;})
}*/
function xLoadSetDatosSession(responde){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-101'})
	.done( function (dtS) {
		var xdtS=$.parseJSON(dtS);
		if(xdtS.success===false){alert(xdtS.error); return;}
		xIdOrg=xdtS.datos[0].ido;
		xIdSede=xdtS.datos[0].idse;
		xIdUsuario=xdtS.datos[0].idu;
		xNomU=xdtS.datos[0].nomU;
		xNomUsario=xdtS.datos[0].nomUs;
		xCargoU=xdtS.datos[0].cargoU;

		window.localStorage.setItem("::app3_wo", xIdOrg);
		window.localStorage.setItem("::app3_woS", xIdSede);
		window.localStorage.setItem("::app3_woU", xIdUsuario);
		window.localStorage.setItem("::app3_woA", xdtS.datos[0].acc);
		window.localStorage.setItem("::app3_woNus", xdtS.datos[0].nomUs);
		responde(xdtS.datos[0].acc);
		});
}

function xVerificarSession(){
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-104',async: false})
	.done( function (a) {
		if(a==1){
			setClearLocalStorage();
			// var printL = window.localStorage.getItem('::app3_woIpPrintLo');
			// window.localStorage.clear();

			// window.localStorage.setItem('::app3_woIpPrintLo', printL);

			// document.location.href='../../logueese.html';
			//window.location.href="../../index.html";
		}
	});
}

function xLoadImpresoras(){
	//$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-105',async: false})
	//.done( function (DtbdPrint) {
		//var xDtbdPrint=$.parseJSON(DtbdPrint);
	var xDtbdPrint_eval=xm_log_get('app3_woIpPrint');
	var xPrintLocal = window.localStorage.getItem('::app3_woIpPrintLoC');
		
		//var xPrintLocal;
		//var xDtbdPrint_eval=xDtbdPrint.datos;
		//xDtbdPrint=JSON.stringify(xDtbdPrint_eval);
		//window.localStorage.setItem("::app3_woIpPrint", xDtbdPrint);
		//window.localStorage.setItem("::app3_woIpPrintLo", '');
		//busca si hay impresora local y si pertenece a este terminal

		//xPrintLocal=getCookie('app3_print_ID');
	// const dt_print_local = xDtbdPrint_eval.filter(x => x.local === "1").map(x => x);
	// const cant_print_local_bd = dt_print_local.length;
	
	// verificar si existe alguna impresora registrada en la bd si solo hay una lo registra	
	// ESTA SOLUCION AFECTARIA A TODOS LOS USUARIOS // EL USUARIO DE CAJA DEBE IR A CONFIGURACION Y ASIGNARSE LA IMPRESORA QUE LE CORRESPONDE
	// if (xPrintLocal === null && cant_print_local_bd === 1) { // si no co
	// 	window.localStorage.setItem("::app3_woIpPrintLoC", dt_print_local[0].descripcion);// para comparar si existe impresora local
	// 	window.localStorage.setItem("::app3_woIpPrintLo", JSON.stringify(dt_print_local[0]));
	// 	return;
	// }
	
		
	for (var i = 0; i < xDtbdPrint_eval.length; i++) {		
		if(xDtbdPrint_eval[i].local==1){
			if(xPrintLocal === xDtbdPrint_eval[i].descripcion){//impresora local detec
				window.localStorage.setItem("::app3_woIpPrintLoC", xDtbdPrint_eval[i].descripcion);// para comparar si existe impresora local
				window.localStorage.setItem("::app3_woIpPrintLo", JSON.stringify(xDtbdPrint_eval[i]));
				return;
			}
		}
	}
	//});
	//load conf_print_otros //precuenta comprobante
	//$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-106',async: false})
	//.done( function (DtbdPrintx) {
		//var xDtbdPrintx=$.parseJSON(DtbdPrintx);
		//xDtbdPrintx=xDtbdPrintx.datos;
		//xDtbdPrintx=JSON.stringify(xDtbdPrintx);
		//window.localStorage.setItem("::app3_woIpPrintO", xDtbdPrintx);
	//});
}

function xCerrarSessionAll(){
	$('body').removeClass('loaded');
	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-103'})
	.done( function (a) {
		setClearLocalStorage()
		// var printL = window.localStorage.getItem('::app3_woIpPrintLo');
		// window.localStorage.clear();

		// window.localStorage.setItem('::app3_woIpPrintLo', printL);
		// document.location.href='../../logueese.html';
	});
}

function setClearLocalStorage() {
	var printL = window.localStorage.getItem('::app3_woIpPrintLoC');
	window.localStorage.clear();

	if (printL) {window.localStorage.setItem('::app3_woIpPrintLoC', printL)};
	document.location.href='../../logueese.html';
};

function getCookie(name) {
  var value = "; " + document.cookie;
  var parts = value.split("; " + name + "=");
  if (parts.length == 2) return parts.pop().split(";").shift();
}

function xm_LogIni(responde){
  $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-1111'})
		.done( function (dt) {
      if(dt=="0"){xVerificarSession();responde(false);}else{
        window.localStorage.setItem("::app3_woDUS", dt);
        responde(true);
      }
	})
}
function xm_LogChequea(responde){
  var xdt_log=window.localStorage.getItem("::app3_woDUS");
  if (xdt_log === null){xdt_log="undefined";}
  $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-1112',data:{d:xdt_log}})
		.done( function (rpt) {
      switch (rpt) {
        case "0":
          xm_LogIni(function(a){if(a){responde(true)}});
          break;
        case "1":
          responde(true)
          break;
        case "2":
          xVerificarSession();
          break;
        default:
          window.localStorage.setItem("::app3_woDUS",rpt); return responde(true);
          break;
      }
      //if(rpt==="1"){xm_LogIni(function(a){if(a){responde(true)}});}else{window.localStorage.setItem("::app3_woDUS",rpt); return responde(false);}
		})
}
function xm_log_get(seccion){
	var xdt_log=window.localStorage.getItem("::app3_woDUS"),xdt_rpt;
	try {
		xdt_log=window.atob(xdt_log);
		xdt_log=JSON.parse(xdt_log)
		
	} catch (error) {
		console.log(error);
		return;
	}

  switch (seccion) {
	case 'app3_us':
	  xdt_rpt = xdt_log.us;
	  break;
	case 'ini_us':
      xIdOrg=xdt_log.us.ido;
      xIdSede=xdt_log.us.idsede;
      xIdUsuario=xdt_log.us.idus;
      xNomU=xdt_log.us.nombre;
      xNomUsario=xdt_log.us.nomus;
      xCargoU=xdt_log.us.cargo;
      xUsAc_Ini=xdt_log.us.acc;
      break;
    case 'app3_Us_home':
      xdt_rpt=xdt_log.sistema.url ;
      break;
    case 'app3_woA':
      xdt_rpt=xdt_log.us.acc;
      break;
    case 'app3_woIpPrint':
      xdt_rpt=xdt_log.dispositivos.dispositivo;
      break;
    case 'app3_woIpPrintO':
      xdt_rpt=xdt_log.dispositivos.otros_print_doc;
      break;
    case 'sede_generales'://app3_sys_dta_prt
      xdt_rpt=xdt_log.sede.generales;
      break;
    case 'sede_otros_datos'://app3_sys_dta_other
      xdt_rpt=xdt_log.sede.otros_datos;
      break;
    case 'categorias'://app3_sys_dt_mlc
      xdt_rpt=xdt_log.carta.categorias;
	  break;
	case 'carta_subtotales'://
      xdt_rpt=xdt_log.carta.subtotales;
      break;
    case 'estructura_pedido'://amar array con tipo de consumo // amar estructura, del pedido tambien para imoresion ::app3_sys_dta_pe = {tipo consumo > seccion > item} // ::app3_sys_dta_tct //::app3_sys_dta_tct_estructura
      xdt_rpt=xdt_log.carta.estructura_pedido;
      break;
	case 'reglas_de_carta'://app3_sys_dta_rec
      xdt_rpt=xdt_log.carta.regla_carta;
	  break;
	case 'datos_org_sede':// datos org y sede para facturacion
      xdt_rpt=xdt_log.sede.datos_org_sede;
	  break;
	case 'datos_org_all_sede':// * sedes
      xdt_rpt=xdt_log.sede.datos_org_all_sede;
	  break;
	
		// xDtUS(3)
  }
  return xdt_rpt;
}

//3
async function xGetFindCliente(valor, servicio, callback) {
	var esFacturacionElectronica=false;
	var rpt = [];
	var token = "XXeyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.1tLS4vhIGufCW5H5vJ4bmNxhf43x-Vaik4oRwaDXi7E";
	var label_num = servicio === "dni" ? "ndni" : "ruc"; 
	var _url_servicio;
		
		//primero busca en local
		var dt = await $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=602', data:{doc: valor}})
		// .done( function (dt) {
			dt = JSON.parse(dt);			
			if (dt.datos.length > 0) { // si tiene los datos en el local
				dt = dt.datos[0];
				rpt = {success: true, idcliente:dt.idcliente, nombres: dt.nombres, direccion: dt.direccion, num_doc: dt.ruc, msg: 'ok'};
				// responde(rpt); 
				callback(rpt);
				// return rpt;
			} else {
							
				xValidarToken(token, (t)=> {
					if (t==="error"){ // algo paso con el servicio
						if (!esFacturacionElectronica) { // si no esta habilitado para facturacion electronica 
							rpt = {success: true, idcliente:'', nombres:'', direccion:'',num_doc:'', msg: 'ok'};
							// responde(rpt); return;							
						} else {
							rpt = {success: false, idcliente:'', nombres:'', direccion:'',num_doc:'', msg: 'Problemas de conexion. intente nuevamente en un momento.'};
							// responde(rpt); return;
						}
						
						// return rpt;
						callback(rpt);
					}
			
					token = t;
					_url_servicio = "http://consulta.viudanegra.com.pe/"+servicio+"/api/service.php?"+label_num+"="+valor+"&token="+token;
								
					$.ajax({ type: 'POST', url: _url_servicio})
					.done( function (dt) {
						// responde (JSON.parse(dt));
						dt = JSON.parse(dt);
						var nombres='', direccion='';
						var num_doc = valor;
						var fnacimiento = '';

						if (dt.success && dt.haydatos) {				
							if (servicio === 'ruc') {
								nombres = dt.result.RazonSocial;
								direccion = dt.result.Direccion;
							} else {
								
								const ap_paterno = dt.result.ApellidoPaterno || '';
								const ap_materno = dt.result.ApellidoMaterno || '';
								const apellidos = ap_paterno === '' ? dt.result.apellidos || '' : ap_paterno + ' ' + ap_materno;
								nombres = dt.result.Nombres + " " + apellidos;
								nombres = nombres===' '? '' : nombres;
								direccion = '';
								fnacimiento = dt.result.FechaNacimiento || '';
							}
						} else {
							if (!esFacturacionElectronica) { // si no esta habilitado para facturacion electronica 
								rpt = {success: true, idcliente:'', nombres:'', direccion:'',num_doc:num_doc, msg: 'ok'};
								// responde(rpt); return;
								// return rpt;
								callback(rpt);
							}
						}

						rpt = { success: dt.haydatos, idcliente: "", nombres: nombres, direccion: direccion, num_doc: num_doc, f_nac: fnacimiento, msg: dt.msg };

						// responde(rpt);
						callback(rpt);
					})
					.fail((jqXHR, textStatus)=>{
						rpt = { success: false, idcliente: "", nombres: "", direccion: "", f_nac: "", num_doc: num_doc, msg: "Problemas de conexion. intente nuevamente en un momento." };
						// responde(rpt); return;
						// return rpt;
						callback(rpt);
					});

				});
			}
		// });
		// });
	// });
}

//1
function xValidarToken(token, callback) {
	var _token = token;
	var _url_servicio = "http://consulta.viudanegra.com.pe/dni/api/validar.php?token="+_token;
	$.ajax({ type: 'POST', url: _url_servicio, timeout:3000})
	.done( function (ValRpt) {
		ValRpt = JSON.parse(ValRpt);
		if (!ValRpt.success) {
			xRefreshToken((t)=>{
				_token = t;
				callback(_token);
			})
		} else {
			callback(_token);
		}
	}).fail((jqXHR, textStatus)=>{
		callback("error");
	});
}

//*2
function xRefreshToken(callback) {	
	var token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.1tLS4vhIGufCW5H5vJ4bmNxhf43x-Vaik4oRwaDXi7E";
	callback(token);
}


// recibe los array de respuesta que emite log_001 los convierte a un solo array para poder leerlo
function xm_all_SetResponseLog_001(response) {
	const arr = response.split('|');
	var _concat = {};
	arr.map(x => {
		if (x === "") {return;}		
		const arr_row = JSON.parse(x);
		Object.keys(arr_row).map(k => {
			_concat[k] = arr_row[k];			
		})
	});

	return _concat;
}

function xm_all_xToastOpen (msj, duracion=0, loading=true) {	
	msj = msj === null? 'Cargando...': msj;
	if (!loading) {
		$("#toast #loading").addClass("xInvisible");
	} else {
		$("#toast #loading").removeClass("xInvisible");
	}
	toast = document.getElementById("toast");
	toast.duration = duracion;
  	toast.text = msj;
  	toast.show();
}

function xm_all_xToastClose() {
	toast = document.getElementById("toast");
	toast.hide();
}

function delay(callback, ms) {
	var timer = 0;
	return function () {
		var context = this, args = arguments;
		clearTimeout(timer);
		timer = setTimeout(function () {
			callback.apply(context, args);
		}, ms || 0);
	};
}
