// notifica acciones del cliete desde pwa
// pago de cuenta y Solicitar atencion

function pNotificaPago(res) {

  var hora = pTimeNow();

  var _datos = {
    nummesa: res.mesa,
    nom_cliente: res.objCliente.nombres,
    importe: parseFloat(res.importe).toFixed(2),
    brand: res.objTransaction.brand,
  };

	if (typeof window.stackBottomRight === 'undefined') {
    window.stackBottomRight = {
      'dir1': 'up',
      'dir2': 'left',
      'firstpos1': 25,
      'firstpos2': 25
    };
  }

PNotify.success({
  title: 'Confirmar Pago',
  text: '<strong>'+ hora + '</strong> En la mesa <strong>' + _datos.nummesa + '</strong> '+ _datos.nom_cliente +' canceló <strong>S/. '+ _datos.importe  +'</strong> con tarjeta '+ _datos.brand +'.',
  textTrusted: true,
  // icon: 'fas fa-info-circle',
  hide: false,
  stack: window.stackBottomRight,
  modules: {
    Confirm: {
      confirm: true,
      buttons: [{
        text: 'Confirmar',
        primary: true,
        click: function(notice) {
          notice.close();
        }
      }]
    },
    // Buttons: {
    //   closer: false,
    //   sticker: false
    // },
    // History: {
    //   history: false
    // }
  }
});

PlaySound('../../sound/notifica-pago.mp3');

}


function pNotificaPersonal(mesa) {
  var hora = pTimeNow();

	if (typeof window.stackBottomRight === 'undefined') {
    window.stackBottomRight = {
      'dir1': 'up',
      'dir2': 'left',
      'firstpos1': 25,
      'firstpos2': 25
    };
  }

  var notice = PNotify.notice({  
    text: ''+ hora + ' Mesa <span><strong>05</strong> solicita atención.',
    textTrusted: true,  
    hide: false,
    stack: window.stackBottomRight,
    modules: {
      History: {
        maxInStack: 5
      }
    } 
  });  

  notice.on('click', function() {
  	notice.close();
	});


  PlaySound('../../sound/notifica-llamado.mp3');

}


function pTimeNow() {
  var d = new Date(),
    h = (d.getHours()<10?'0':'') + d.getHours(),
    m = (d.getMinutes()<10?'0':'') + d.getMinutes();
  return h + ':' + m;
}



function PlaySound(Path){
  var audioElement = document.createElement('audio');
  if (navigator.userAgent.match('Firefox/'))
      audioElement.setAttribute('src', Path);
  else
      audioElement.setAttribute('src', Path);
  

  $.get();
  audioElement.addEventListener("load", function() {
      audioElement.play();
  }, true);

  audioElement.pause();
  audioElement.play();
}