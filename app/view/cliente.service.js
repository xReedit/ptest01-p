async function ClienteService_Guardar(xarr_cliente){var rpt=0;const nomClie=xarr_cliente.nombres||xarr_cliente.nombre||'';xarr_cliente.nombres=nomClie;if(xarr_cliente.idcliente===''&&nomClie===''){return rpt;}
await $.ajax({type:'POST',url:'../../bdphp/log_001.php',data:{p_from:'d',p_cliente:xarr_cliente}}).done(function(idC){rpt=idC;return rpt;});return rpt;}