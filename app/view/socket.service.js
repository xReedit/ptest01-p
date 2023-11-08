var socketMonitoreo=socketService.getInstance();;function _monitoreoSocketOpen(){isSocket=parseInt(xm_log_get('datos_org_sede')[0].pwa)===0?false:true;if(!isSocket){return;}
this.socketMonitoreo.connectSocket();this.socketMonitoreo.listen('finishLoadDataInitial').subscribe(res=>{console.log('finishLoadDataInitial');_monitoreoStockUpdateCarta(res);});this.socketMonitoreo.listen('itemModificado').subscribe(res=>{console.log('itemModificado');_monitoreoStockItemModificado(res);});this.socketMonitoreo.listen('nuevoPedido').subscribe(res=>{console.log('nuevoPedido');_monitoreoStockNuevoPedido(res);});this.socketMonitoreo.listen('itemResetCant').subscribe(res=>{console.log('itemResetCant');_monitoreoStockItemModificado(res);});this.socketMonitoreo.onStatusConexSocket().subscribe(res=>{if(!isSocket){return;}
console.log('status socket ',res);if(res){$('body').addClass('loaded');}else{$('body').removeClass('loaded');}});}
function _monitoreoSocketIsConnect(){isSocket=parseInt(xm_log_get('datos_org_sede')[0].pwa)===0?false:true;if(!isSocket){return;}
this._monitoreoSocketOpen();}
function _monitoreoSocketEmitNewItemInCarta(item){if(!isSocket){return;}
this.socketMonitoreo.emit('nuevoItemAddInCarta',item);}
function _monitoreoSocketEmitItemModificado(item){if(!isSocket){return;}
item.sumar=0;this.socketMonitoreo.emit('itemModificado',item);}
function _monitoreoSocketEmitItemModificadoFromSubItems(item){if(!isSocket){return;}
this.socketMonitoreo.emit('itemModificadoFromMonitorSubItems',item);}
function _monitoreoSocketClose(){if(!isSocket){return;}
try{this.socketMonitoreo.disconnectSocket(true);}catch(error){}}