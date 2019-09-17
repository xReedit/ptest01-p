const dtUs = xm_log_get('app3_us');
var dataSocket = {
    idorg: dtUs.idorg,
    idsede: dtUs.idsede,
    idusuario: dtUs.idus,
    isFromApp: 0
}

var socketMonitoreo;
isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;

/// monitoreo de stock ///
function _monitoreoSocketOpen() {    
    if (!isSocket) { return; }

    socketMonitoreo = io.connect('http://localhost:5819', {
        query: dataSocket
    });


    socketMonitoreo.on('itemModificado', (item) => {    
        console.log('itemModificado');
        _monitoreoStockItemModificado(item);
    });

    socketMonitoreo.on('nuevoPedido', (pedido) => {        
        console.log('nuevoPedido');
        _monitoreoStockNuevoPedido();
    });

}

function _monitoreoSocketIsConnect() {
    if (!isSocket) { return; }
    this._monitoreoSocketOpen();
    // if (!socketMonitoreo.connected) {
    //     socketMonitoreo = io.connect('http://localhost:5819', {
    //         query: dataSocket
    //     });
    // }
}

function _monitoreoSocketEmitNewItemInCarta(item) {
    if (!isSocket) { return; }
    socketMonitoreo.emit('nuevoItemAddInCarta', item);
}

function _monitoreoSocketEmitItemModificado(item) {
    if (!isSocket) { return; }
    socketMonitoreo.emit('itemModificado', item);
}

function _monitoreoSocketClose() {
    if (!isSocket) { return; }
    socketMonitoreo.disconnect(true);
}

/// monitoreo de stock ///