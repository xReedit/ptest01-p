var importe;
var purchasenumber;
var cargando_transaction = false;
var dataCliente;

// 240120 - last update.
// DESARROLLO
// var user = "integraciones.visanet@necomplus.com";
// var password = "d5e7nk$M";
// var merchantId = '522591303';
// var urlApiSeguridad = "https://apitestenv.vnforapps.com/api.security/v1/security";
// var urlApiSesion = "https://apitestenv.vnforapps.com/api.ecommerce/v2/ecommerce/token/session/";
// var urlApiAutorization =  "https://apitestenv.vnforapps.com/api.authorization/v3/authorization/ecommerce/";
// var urlJs = "https://static-content-qas.vnforapps.com/v2/js/checkout.js?qa=true";
// var logo = 'http://web-p.test:8080/images/l-pay-2.png';

// aW50ZWdyYWNpb25lcy52aXNhbmV0QG5lY29tcGx1cy5jb206ZDVlN25rJE0=


// PROD
var user = "macraze.info@gmail.com";
var password = "j34Oz!nB";
var merchantId = '650149801';
var urlApiSeguridad = "https://apiprod.vnforapps.com/api.security/v1/security";
var urlApiSesion = "https://apiprod.vnforapps.com/api.ecommerce/v2/ecommerce/token/session/";
var urlApiAutorization =  "https://apiprod.vnforapps.com/api.authorization/v3/authorization/ecommerce/";
var urlJs = "https://static-content.vnforapps.com/v2/js/checkout.js"; 
var logo = 'https://papaya.com.pe/images/l-pay-2.png';

// bWFjcmF6ZS5pbmZvQGdtYWlsLmNvbTpqMzRPeiFuQg==


function pagar(_importe, _purchasenumber, _dataClie) {
  importe = parseFloat(_importe).toFixed(2).toString();  
  purchasenumber = _purchasenumber;
  dataCliente = _dataClie;
  // dataCliente.email_token = dataCliente.idcliente +'@apitoken.com'; // para guardar las tarjetas - userToken
  dataCliente.email_token = dataCliente.email;
  
  // loaderTransaction(0);
  // loaderTransactionResponse(null, false);  
  getIpCliente();
  // generarToken();
}

function getIpCliente() {

  // antifraude
  dataCliente.antifraud = {
    "clientIp": dataCliente.ip,
    "merchantDefineData":{
      "MDD4": dataCliente.email,            
      "MDD32": dataCliente.idcliente,
      "MDD75": "Invitado",
      "MDD77": dataCliente.diasRegistrado,
      "MDD89": "1"
      // "MDD70": "1", // correo electronico confirmado
    }
  };
  generarToken();
}


function generarToken() {
  var settings = {
    "async": true,
    "crossDomain": true,
    "url": urlApiSeguridad,
    "method": "POST",
    "headers": {
      "Authorization": "Basic bWFjcmF6ZS5pbmZvQGdtYWlsLmNvbTpqMzRPeiFuQg==",
      "Accept": "*/*"
    }
  };

  $.ajax(settings).done(function (response) {
    // console.log(response);
    generarSesion(response);
    localStorage.setItem("token", response);
  });
}

function generarSesion(token) {
  // console.log('importe: ', importe);

  var data = {
    "amount": importe,
    "antifraud": null,
    "channel": "web",
    "recurrenceMaxAmount": null
  };

  var settings = {
    "async": true,
    "crossDomain": true,
    "url": urlApiSesion + merchantId,
    "method": "POST",
    "headers": {
      "Authorization": token,
      "Content-Type": "application/json",
    },
    "dataMap": {
      "userToken": dataCliente.email
    },
    "processData": false,
    "data": JSON.stringify(data)
  };

  $.ajax(settings).done(function (response) {    
    generarBoton(response['sessionKey']);
  });
}

function generarBoton(sessionKey) {  
  var moneda = 'PEN';  
  
  /// DEV
  // var nombre = 'Integraciones';
  // var apellido = 'VisaNet';
  // var email = 'integraciones.visanet@necomplus.com';
  
  // PROD
  var nombre = dataCliente.nombre;
  var apellido = dataCliente.apellido;
  var email = dataCliente.email; 

  var json = {
    "merchantId": merchantId,
    "moneda": moneda,
    "nombre": nombre,
    "apellido": apellido,
    "importe": importe,
    "email": email
  };

  localStorage.setItem("data", JSON.stringify(json));  
  

  var form = document.createElement("form");
  form.setAttribute('method', "post");      
  form.setAttribute('action', 'javascript:responseForm(self)');
  form.setAttribute('id', "boton_pago");  
  document.getElementById("btn_pago").appendChild(form);

  var scriptEl = document.createElement('script');
  scriptEl.setAttribute('src', urlJs);
  scriptEl.setAttribute('data-sessiontoken', sessionKey);
  scriptEl.setAttribute('data-channel', 'web');
  scriptEl.setAttribute('data-merchantid', merchantId);
  
  scriptEl.setAttribute('data-purchasenumber', purchasenumber);
  scriptEl.setAttribute('data-amount', importe);
  
  scriptEl.setAttribute('data-merchantlogo', logo);  
    
  scriptEl.setAttribute('data-expirationminutes', 8);
  scriptEl.setAttribute('data-timeouturl', "javascript:responseForm(self)");

  scriptEl.setAttribute('data-cardholdername', nombre);
  scriptEl.setAttribute('data-cardholderlastname', apellido);
  scriptEl.setAttribute('data-cardholderemail', email);
  scriptEl.setAttribute('data-usertoken', email);

  document.getElementById("boton_pago").appendChild(scriptEl);

  document.getElementById("loader_btn_pago").classList.add("xInvisible");

}


function responseForm(event) {
  // console.log('respuesta', event.message.args[0]);
  loaderTransaction(true);

  var data = this.message.args[0];
  transactionToken  = data.token;
  
  generateAutorizacion(transactionToken);

}

function generateAutorizacion(transactionToken) {
  cargando_transaction = true;
  var token = localStorage.getItem("token");
  var  data = { 
        "antifraud" : dataCliente.antifraud,
        "captureType" : "manual",
        "channel" : "web",
        "countable" : false,
        "order" : {
            "amount" : importe,
            "currency" : "PEN",
            "purchaseNumber" : purchasenumber,
            "tokenId" : transactionToken,
        }
  };

  var _url = urlApiAutorization + merchantId;

  fetch(_url, {
      method: 'POST',
      headers: {
        "Authorization": token,
        "Content-Type": 'application/json'
      },    
      body: JSON.stringify(data)
    })
    .then((response) => {
      return response.json();
    })
    .then((res) => {
      // console.log(res);
      var hayError = res.errorCode ? true : false;
      // loaderTransaction(0);           
      loaderTransaction(false); 
      loaderTransactionResponse(res, hayError);
      loaderTransactionResponseOut(res, hayError);
    })
    .catch((error) => {      
      // loaderTransaction(0);
      loaderTransaction(false); 
      loaderTransactionResponse(error, true);
      loaderTransactionResponseOut(res, hayError);
      // console.log(error);
    });

}

// 0 = false 1 = true;
function loaderTransaction(val) {
  localStorage.setItem('sys::transaction-load', val);
}

function loaderTransactionResponse(res, isError) {  
  if ( res ) {
    res.error = isError;


    var elem = document.querySelector('#visaNetWrapper');
    elem.parentNode.removeChild(elem); 
  }
  localStorage.setItem('sys::transaction-response', JSON.stringify(res));   
}