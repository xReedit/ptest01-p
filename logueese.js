var xul,xdialog;window.onerror=function(o,a,e){console.log(o)},$(this).one("pageshow",function(){xdialog=document.querySelector("x-dialog"),xul=document.querySelector("x-user-login"),$.ajax({type:"POST",url:"bdphp/log.php?op=-103"}),$("#bta").click(function(){var o=window.localStorage.getItem("::app3_woDUS");$.ajax({type:"POST",url:"bdphp/log.php?op=-1112",data:{d:o}}).done(function(o){alert(o),"0"===o&&$.ajax({type:"POST",url:"bdphp/log.php?op=-1113"}).done(function(o){window.localStorage.setItem("::app3_woDUS",o)})})});setTimeout(function(){$("body").addClass("loaded")},1e3);xul.addEventListener("xSend",function(o){var a=o.detail.xRpts[0].us,e=o.detail.xRpts[0].p,p=o.detail.xRpts[0].co;switch(xdialog.xopen(),xul.xop){case 1:break;case 2:$.ajax({type:"POST",url:"bdphp/log.php?op=-1",data:{u:a,p:e}}).done(function(o){if(1==o){var a=window.localStorage.getItem("::app3_woIpPrintLoC");window.localStorage.clear(),document.location.href="app/page/m_panel.html",a&&window.localStorage.setItem("::app3_woIpPrintLoC",a)}else xdialog.xclose(),xul.xocurrencia(0)});break;case 3:$.ajax({type:"POST",url:"bdphp/log.php?op=-3",data:{u:a}}).done(function(o){1==o?(xdialog.xclose(),xul.xocurrencia(1)):$.ajax({type:"POST",url:"bdphp/log.php?op=-301",data:{u:a,p:e,c:p}}).done(function(o){$.ajax({type:"POST",url:"bdphp/log.php?op=-1",data:{u:a,p:e}}),document.location.href="app/page/m_panel.html"})})}})});