
// guarda cliente primero
async function ClienteService_Guardar(xarr_cliente) {
    var rpt = 0;

    if ( xarr_cliente.idcliente === '' &&  xarr_cliente.nombres === '') {return rpt;}

    await $.ajax({ type: 'POST', url: '../../bdphp/log_001.php', data:{p_from:'d', p_cliente:xarr_cliente}})
    .done( function (idC) {
        rpt = idC;
    });

    return rpt;
}