var socketCP=socketService.getInstance();function _cpSocketOpen(){;if(!this.socketCP._socket){console.log('socket connect new');this.socketCP.connectSocket();this.listenSocketP();}else{console.log('socket exist');if(!this.socketCP._socket.connected){console.log('socket desconetacdo');this.socketCP.disconnectSocket();setTimeout(()=>{console.log('socket conectado');this.socketCP.connectSocket();this.listenSocketP();},2000);}}}
function listenSocketP(){setTimeout(()=>{$.ajax({type:'POST',url:'../../bdphp/log_005.php?op=14',data:{socketId:this.socketCP._socket.id}}).done(function(res){});},1200);this.socketCP.listen('nuevoPedido').subscribe(res=>{try{_cpSocketPintarPedido(res);if(res.p_header.delivery.toString()=='1'){xCDAddItemRow(res);}}catch(error){}});this.socketCP.listen('itemModificado').subscribe(res=>{try{_cpStockItemModificado(res);}catch(error){}});this.socketCP.listen('itemResetCant').subscribe(res=>{try{_cpStockItemModificado(res);}catch(error){}});this.socketCP.listen('printerOnly').subscribe(res=>{try{_cpSocketPintarPedido(res);if(res.delivery.toString()=='1'){xCDAddItemRow(res);}}catch(error){}});this.socketCP.listen('notificar-pago-pwa').subscribe(res=>{try{if(!res.isdelivery){_cpSocketPintarPedido(null);}
_cpSocketPintarNumerosPagosNotificados();pNotificaPago(res);}catch(error){}});this.socketCP.listen('notificar-cliente-llamado').subscribe(res=>{try{pNotificaPersonal(res);}catch(error){}});this.socketCP.listen('notifica-impresion-precuenta').subscribe(res=>{try{_cpSocketPintarPedido(null);}catch(error){}});this.socketCP.listen('notifica-impresion-comanda').subscribe(pedido=>{});this.socketCP.listen('restobar-notifica-pay-pedido-res').subscribe(res=>{try{xRefreshPedidoPaySocket(res);}catch(error){}});this.socketCP.listen('restobar-permiso-cierre-remoto-respuesta').subscribe(res=>{try{xCajaResPermisoRemoto(res);}catch(error){}});this.socketCP.listen('restobar-venta-registrada-res').subscribe(res=>{this.socketCP.isRegistroVentaSource.next(true);});this.socketCP.listen('repartidor-notifica-cliente-acepto-pedido-res').subscribe(res=>{xCDUpdateViewItem('repartidor',res);});this.socketCP.listen('repartidor-notifica-fin-pedido').subscribe(res=>{const _res={idpedido:res};xCDUpdateViewItem('repartidor-entregado',_res);});}
function _cpSocketprinterOnly(pedido){this.socketCP.emit('printerOnly',pedido);}
function _cpSocketEmitItemModificado(item){this.socketCP.emit('itemModificado',item);}
function _cpSocketEmitPrinterOnly(item){this.socketCP.emit('printerOnly',item);}
function _cpSocketEmitPrinterPrecuenta(item){this.socketCP.emit('notificar-impresion-precuenta',item);}
function _cpSocketClose(){try{if(!isSocket){return;}}catch(error){}
try{this.socketCP.disconnectSocket();}catch(error){}}
function _cpSocketNoiticaRepartidorFromComercio(pedido){this.socketCP.emit('set-repartidor-pedido-asigna-comercio',pedido);}
function _cpSocketNoiticaClienteRepartidorAsignado(repartidor){this.socketCP.emit('repartidor-notifica-cliente-acepto-pedido',repartidor);}
function _cpSocketComercioLLamaRepartidorPapaya(){this.socketCP.emit('set-solicitar-repartidor-papaya',null);}
function _cpSocketEmitPedidoPagoCliente(listIdCliente){this.socketCP.emit('pedido-pagado-cliente',listIdCliente);}
function _cpSocketSavePedidoStorage(pedido){if(!isSocket){return;}
localStorage.setItem('::app3_sys_dta_pe_sk',JSON.stringify(pedido));}
function _cpSocketComprobanteWhatApp(payload){this.socketCP.emit('restobar-send-comprobante-url-ws',payload);}
function _cpSocketRestoreFromPedidoStorage(){if(!isSocket){return;}
var pedido=localStorage.getItem('::app3_sys_dta_pe_sk')?JSON.parse(localStorage.getItem('::app3_sys_dta_pe_sk')):null;var pedidoSend=[],_subItemView=[];if(pedido){pedido.filter(x=>x!==null).map(x=>{_subItemView=[];Object.values(x).filter(a=>typeof a==='object').map(item=>{if(item.isporcion!='ND'){item.idcarta_lista=item.iditem;item.cantidad_seleccionada=item.cantidad;item.isalmacen=item.procede.toString()==='0'?1:0;item.isporcion=item.isporcion!='SP'?item.cantidad:item.isporcion;item.subitems_view=JSON.parse(JSON.stringify(item.subitems_view));pedidoSend.push(item);}});});}
if(pedidoSend.length>0){this.socketCP.emit('resetPedido',pedidoSend);}
localStorage.removeItem('::app3_sys_dta_pe_sk');pedidoSend=[];}
function _cpSocketClearStorage(){if(!isSocket){return;}
localStorage.removeItem('::app3_sys_dta_pe_sk');}
function _cpASocketNotifyPayPedido(payload){this.socketCP.emit('restobar-notifica-pay-pedido',payload);payload.idpedido=payload.num_pedido;xCDRemoveItemRow(payload);}
function _cpSocketPermisoCierreRemotoEnviarRpt(payload){this.socketCP.emit('restobar-permiso-cierre-remoto-respuesta',payload);}
function _cpSocketVentaRegistrado(){this.socketCP.emit('restobar-venta-registrada');this.socketCP.isRegistroVentaSource.next(true);}