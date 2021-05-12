var socketCP=new socketService();function _cpSocketOpen(){if(!this.socketCP._socket){this.socketCP.connectSocket();this.listenSocketP();}else{if(!this.socketCP._socket.connected){this.socketCP.connectSocket();this.listenSocketP();}}}
function listenSocketP(){setTimeout(()=>{$.ajax({type:'POST',url:'../../bdphp/log_005.php?op=14',data:{socketId:this.socketCP._socket.id}}).done(function(res){});},1200);this.socketCP.listen('nuevoPedido').subscribe(res=>{try{_cpSocketPintarPedido(res);}catch(error){}});this.socketCP.listen('itemModificado').subscribe(res=>{try{_cpStockItemModificado(res);}catch(error){}});this.socketCP.listen('itemResetCant').subscribe(res=>{try{_cpStockItemModificado(res);}catch(error){}});this.socketCP.listen('printerOnly').subscribe(res=>{try{_cpSocketPintarPedido(res);}catch(error){}});this.socketCP.listen('notificar-pago-pwa').subscribe(res=>{try{if(!res.isdelivery){_cpSocketPintarPedido(null);}
_cpSocketPintarNumerosPagosNotificados();pNotificaPago(res);}catch(error){}});this.socketCP.listen('notificar-cliente-llamado').subscribe(res=>{try{pNotificaPersonal(res);}catch(error){}});this.socketCP.listen('notifica-impresion-precuenta').subscribe(res=>{try{_cpSocketPintarPedido(null);}catch(error){}});this.socketCP.listen('notifica-impresion-comanda').subscribe(pedido=>{try{_cpSocketPintarFlagPedidoImpreso(pedido);}catch(error){}});}
function _cpSocketprinterOnly(pedido){this.socketCP.emit('printerOnly',pedido);}
function _cpSocketEmitItemModificado(item){this.socketCP.emit('itemModificado',item);}
function _cpSocketEmitPrinterOnly(item){this.socketCP.emit('printerOnly',item);}
function _cpSocketEmitPrinterPrecuenta(item){this.socketCP.emit('notificar-impresion-precuenta',item);}
function _cpSocketClose(){if(!isSocket){return;}
try{this.socketCP.disconnectSocket();}catch(error){}}
function _cpSocketNoiticaRepartidorFromComercio(pedido){this.socketCP.emit('set-repartidor-pedido-asigna-comercio',pedido);}
function _cpSocketComercioLLamaRepartidorPapaya(){this.socketCP.emit('set-solicitar-repartidor-papaya',null);}
function _cpSocketEmitPedidoPagoCliente(listIdCliente){this.socketCP.emit('pedido-pagado-cliente',listIdCliente);}
function _cpSocketSavePedidoStorage(pedido){if(!isSocket){return;}
localStorage.setItem('::app3_sys_dta_pe_sk',JSON.stringify(pedido));}
function _cpSocketRestoreFromPedidoStorage(){if(!isSocket){return;}
var pedido=localStorage.getItem('::app3_sys_dta_pe_sk')?JSON.parse(localStorage.getItem('::app3_sys_dta_pe_sk')):null;var pedidoSend=[],_subItemView=[];if(pedido){pedido.filter(x=>x!==null).map(x=>{_subItemView=[];Object.values(x).filter(a=>typeof a==='object').map(item=>{if(item.isporcion!='ND'){item.idcarta_lista=item.iditem;item.cantidad_seleccionada=item.cantidad;item.isalmacen=item.procede.toString()==='0'?1:0;item.isporcion=item.isporcion!='SP'?item.cantidad:item.isporcion;item.subitems_view=JSON.parse(JSON.stringify(item.subitems_view));pedidoSend.push(item);}});});}
if(pedidoSend.length>0){this.socketCP.emit('resetPedido',pedidoSend);}
localStorage.removeItem('::app3_sys_dta_pe_sk');pedidoSend=[];}
function _cpSocketClearStorage(){if(!isSocket){return;}
localStorage.removeItem('::app3_sys_dta_pe_sk');}