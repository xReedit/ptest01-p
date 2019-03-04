var xul;
var xdialog;

window.onerror = function (error, url, line) {
	console.log(error);
	// controller.sendLog({ acc: 'error', data: 'ERR:' + error + ' URL:' + url + ' L:' + line });
};

$(this).one('pageshow',function(){
	//$('body').addClass('loaded');
	xdialog = document.querySelector('x-dialog');
	xul=document.querySelector('x-user-login');
	//destruye sessuion
	$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-103'})

	$("#bta").click(function(){
		var xdta=window.localStorage.getItem("::app3_woDUS");
		$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-1112', data:{d:xdta}})
		.done( function (xdt) {
			alert(xdt)
			if(xdt==='0'){
				$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-1113'})
				.done( function (dt) {
					window.localStorage.setItem("::app3_woDUS", dt);
				})
			}
			//window.localStorage.setItem("::app3_woDUS", dt);
		})
	})
	/////////

	var t = setTimeout(function(){
		$('body').addClass('loaded');
	},1000);

	xul.addEventListener('xSend', function (e) {

	var u=e.detail.xRpts[0].us;
	var p=e.detail.xRpts[0].p;
	var c=e.detail.xRpts[0].co;

	xdialog.xopen()
	switch(xul.xop){
		case 1://
			break;
		case 2:
			$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-1', data:{u:u, p:p}})
			.done( function (dt) {
				if(dt==1){
					var printL = window.localStorage.getItem('::app3_woIpPrintLoC');
					window.localStorage.clear();
					document.location.href='app/page/m_panel.html';

					if (printL) {window.localStorage.setItem('::app3_woIpPrintLoC', printL)};
				}else{
					xdialog.xclose();
					//alert('Usuario o clave incorrecto');
					xul.xocurrencia(0);
				}
			});
			break;
		case 3://registrar
			//verificar disponibilidad usuario
			$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-3', data:{u:u}})
			.done( function (dt) {
				if(dt==1){
					xdialog.xclose();
					xul.xocurrencia(1);
				}else{
					$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-301', data:{u:u, p:p, c:c}})
					.done( function (dt) {
						$.ajax({ type: 'POST', url: 'bdphp/log.php?op=-1', data:{u:u, p:p}})
						document.location.href='app/page/m_panel.html';
					})
				}
			})
			break;
		}
	});
});
