var xIdOrg;
var xIdSede;
var xNomU;
var xArrayPedido = new Array();
var xArrayDesTipoConsumo = new Array();
var xToglePanel = 0;
var xMenuArray;
var xidCategoria;
var xidCategoriaSeccion;
var xidCatProcedencia = 0; //0  procede de la carta !=0 procede bodega
var xPopupLoad;
var xOnlyAccPedido;
var xCount_cant_ico = 0;

window.addEventListener("error", function (e) {
  if (!e) {return}
  console.log(e);
  // alert(e.error + ' ->' + e.error.stack);
  // You can send data to your server
  // sendError(data);
})

// $(document).one("ready", function() {
//   xIniMenu();
//   $("#PanelDe").on("transitionend", function(a) {
//     if (this.selected == "main") {
//       $("#PanelDe").css("z-index", "0");
//     }
//   });
// });

// $(document).ready(function () {  
//   $("#PanelDe").on("transitionend", function(a) {
//     if (this.selected == "main") {
//       $("#PanelDe").css("z-index", "0");
//     }
//   });

  

  // setTimeout(() => {  
  //   xIniMenAAA();  
  // }, 1500);
// });

document.addEventListener("WebComponentsReady", function componentsReady() {
  $("#PanelDe").on("transitionend", function (a) {
    if (this.selected == "main") {
      $("#PanelDe").css("z-index", "0");
    }
  });

  xIniMenAAA();  
  // inactivityTime();
  setGalleta();
  listenCookieChange(function() {
    dialog_inactividad.open();
  });
  
});

// window.addEventListener('DOMContentLoaded', function () {
//   $("#PanelDe").on("transitionend", function (a) {
//     if (this.selected == "main") {
//       $("#PanelDe").css("z-index", "0");
//     }
//   });
//   // setInterval(() => {
    
//   // }, 500);
//   xIniMenAAA();
// })



//window.onload = function(){setTimeout( function(){ xIniMenu(); }, 600); };
function xIniMenAAA() {
  //session activa
  xVerificarSession();
  setInterval(function() {
    xVerificarSession();
  }, 1080000);

  xPopupLoad = document.getElementById("xLoad");
  xm_LogChequea(function() {
    xm_log_get("ini_us");

    xLoadArrayPedidoAquiMenuJS();

    if (xUsAc_Ini == "A2,") {
      xOnlyAccPedido = 0;
    } else {
      xOnlyAccPedido = 2;
    }
  });
  //xVerificarAccKey()

  //xLoadArrayPedidoAquiMenuJS();
  //xLoadRegla();
  //xLoadDtPrint();

  /*xOnlyAccPedido=window.localStorage.getItem('::app3_woUOn');
	if(xIdUsuario==''){	xIdUsuario=window.localStorage.getItem('::app3_woU');}
	if(xOnlyAccPedido==null){xOnlyAccPedido=1;}else{xOnlyAccPedido=0;}*/
}
function xOpenPageCarta(xop, parametro) {
  if (parametro == null) {
    parametro = "";
  }
  var xruta = "";
  switch (xop) {
    case 0:
      xruta = "/categoria";
      break;
    case 1:
      xruta = "/menu";
      break;
    case 2:
      xruta = "/sub_menu";
      break;
    case 3:
      xruta = "/mipedido";
      break;
    case 4:
      document.location.href = "m_panel.html";
      return;
    case 5:
      xruta = "/buscar_item_menu";
      break;      
  }
  // x_router_menu = document.querySelector("app-router");
  // x_router_menu = document.querySelector("x-router");

  xruta = xruta + parametro;

  // grabar scrollpos => al regresar mostrar en la posicion donde se quedo
  window.localStorage.setItem("::app3_sys_scroll_pos", $(window).scrollTop());

  //setTimeout( function(){ router.go(xruta); }, 50);
  // x_router_menu._go(xruta);
  // router.go(xruta);
  // setTimeout(() => {
  //   router.go(xruta);
  // }, 2000);

  router.go(xruta);
  
  PanelDe.closeDrawer();
}

function xLoadArrayPedidoAquiMenuJS() {
  xArrayPedido = JSON.parse(window.localStorage.getItem("::app3_sys_dta_pe"));
  if (xArrayPedido === null) {
    xArrayPedido = [];
  } //else{if(xArrayPedido.length>0){xOpenPageCarta(0);return;}}
  //$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=3'})
  //.done( function (DtArray) {
  //var xDtArray=$.parseJSON(DtArray);

  var xDtArray = xm_log_get("estructura_pedido"); //xDtArray.datos;
  for (var i = 0; i < xDtArray.length; i++) {
    //xArrayPedido[xDtArray[i].idtipo_consumo]=new Array();
    xArrayPedido[xDtArray[i].idtipo_consumo] = {
      id: xDtArray[i].idtipo_consumo,
      des: xDtArray[i].descripcion,
      titulo: xDtArray[i].titulo
    };
    xArrayDesTipoConsumo.push({
      id: xDtArray[i].idtipo_consumo,
      des: xDtArray[i].descripcion,
      titulo: xDtArray[i].titulo
    });
  }
  window.localStorage.setItem(
    "::app3_sys_dta_pe",
    JSON.stringify(xArrayPedido)
  );

  //window.localStorage.removeItem("::app3_sys_dta_tct");
  //window.localStorage.setItem("::app3_sys_dta_tct",JSON.stringify(xArrayDesTipoConsumo))

  xOpenPageCarta(0);
  //})
}
function xScrolUp(xelement) {
  if (xelement != "0") {
    var elem = document.getElementById(xelement);
    xelement = elem.offsetTop;
  }
  $("#xContenedoPaginas")
    .stop(true, true)
    .animate({ scrollTop: xelement }, 600);
}
function xScrolUpObj(obj) {
  xelement = $(obj).offset().top;
  $("#xContenedoPaginas")
    .stop(true, true)
    .animate({ scrollTop: xelement }, 600);
}

function xOpenPanelDe() {
  PanelDe.openDrawer();
  $("#PanelDe").css("z-index", "20");
}
function xArmarMenuLateral(op) {
  var xCadenaMenuL = "";
  var xOpSalirPanel = "";
  var xCadenadaCerrarSession =
    '<li onClick="xCerrarSession();"><p>CERRAR SESSION </p></li>';
  switch (op) {
    case 1: //categoria
      xMenuArray = $.parseJSON(
        window.localStorage.getItem("::app3_sys_dt_mlc")
      );
      if (xOnlyAccPedido == 0) {
        xOpSalirPanel = "";
      } else {
        if (xIdUsuario != "") {
          xOpSalirPanel =
            '<li onClick="xOpenPageCarta(4)"><p>SALIR AL PANEL </p></li>';
        }
      }
      break;
    case 2: //menu
      xMenuArray = $.parseJSON(
        window.localStorage.getItem("::app3_sys_dt_mlm")
      );
      break;
  }

  if (xMenuArray===null) {return;}

  for (var i = 0; i < xMenuArray.length; i++) {
    xCadenaMenuL = String(
      xCadenaMenuL +
        '<li data-id="' +
        xMenuArray[i].id +
        '" onClick="xVerDetalleMenu(' +
        i +
        "," +
        op +
        ')"><p>' +
        xMenuArray[i].des +
        "</p></li>"
    );
  }
  xCadenaMenuL =
    '<ul class="noselect xCursor"><li onClick="btn_lateral_inicio();"><p>INICIO</p></li>' +
    xCadenaMenuL +
    '<li onClick="xOpenPageCarta(3);"><p>VER MI PEDIDO</p></li>' +
    xOpSalirPanel +
    xCadenadaCerrarSession +
    "</ul>";
  $(".xBtnPanel ul").remove();
  $(".xBtnPanel")
    .append(xCadenaMenuL)
    .trigger("create");
}

function btn_lateral_inicio() {
  localStorage.removeItem("::app3_sys_descat");
  xOpenPageCarta(0);
}

function xVerDetalleMenu(i, op) {
  var xBus = "sub_menu";
  var xdt = xMenuArray[i].des;
  xidCategoriaSeccion = xMenuArray[i].id;
  xidCatProcedencia = xMenuArray[i].procede;
  window.localStorage.setItem("::app3_sys_dt_c", xdt);

  if (op == 1) {
    var xBus = "menu";
  }
  var xPos = location.href.indexOf(xBus);
  if (xPos != -1) {
    if (op == 2) {
      PanelDe.closeDrawer();
      xLoadItems();
    }
    return;
  }
  //var xParamU='?c='+xidCategoria+'?it='+xidt;
  xOpenPageCarta(op);
}
function xCerrarSession() {
  $("body").removeClass("loaded");
  $.ajax({ type: "POST", url: "../../bdphp/log.php?op=-103" })
  .done(function(a) {
    setClearLocalStorage();
    // var printL = window.localStorage.getItem('::app3_woIpPrintLo');
    // window.localStorage.clear();

    // window.localStorage.setItem('::app3_woIpPrintLo', printL)
    // document.location.href='../../logueese.html';
  });
}

function goBack() {
  window.history.back();
}

// var inactivityTime = function () {
//   // alert("You are now logged out.");
//   var t;
//   // window.onload = resetTimer;
//   resetTimer;
//   // DOM Events
//   document.onmousemove = resetTimer;
//   document.onkeypress = resetTimer;
//   document.ontouchstart = resetTimer;
//   document.onclick = resetTimer;     // touchpad clicks
//   document.onscroll = resetTimer; 

//   function logout() {
//     dialog_inactividad.open();
//     // alert("You are now logged out.");
//     //location.href = 'logout.php'
//   }

//   function resetTimer() {
//     clearTimeout(t);
//     t = setTimeout(logout, 3000)
//     // 1000 milisec = 1 sec
//   }
  
// };

function xMantenerSession() {
  restaurarGalleta();
  dialog_inactividad.close();
  xOpenPageCarta(1);
}




