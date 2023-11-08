var socketZona=socketService.getInstance();function _zonaSocketConnect(){isSocket=parseInt(xm_log_get('datos_org_sede')[0].pwa)===0?false:true;if(isSocket){this.socketZona.connectSocket();this.socketZona.listen('nuevoPedido').subscribe(res=>{console.log('nuevo pedido');_zonaSocketPintarPedido(res);});this.socketZona.listen('printerOnly').subscribe(res=>{_zonaSocketPintarPedido(res);});}}
function _zonaSocketprinterOnly(pedido){if(!isSocket){return;}
this.socketZona.emit('printerOnly',pedido);}
function _zonaSocketPedidoEntregado(idcliente){if(!isSocket){return;}
this.socketZona.emit('delivery-pedido-estado',idcliente);}
function _zonaSocketClose(){if(!isSocket){return;}
try{this.socketZona.disconnectSocket();}catch(error){}}