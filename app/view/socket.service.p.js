// var socketCP;
var socketCP = new socketService();
function _cpSocketOpen() { 
    if (isSocket) {
        isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;

        this.socketCP.connectSocket();

        // restore si hay

        this.socketCP.listen('nuevoPedido').subscribe(res => {
            try {                
                _cpSocketPintarPedido(res);
            } catch (error) {}            
        });


        // cliente ha pagado desde el aplicativo
        // this.socketCP.listen('notificar-pago-pwa').subscribe(res => {
        //     try {                
        //         _cpSocketPintarPedido(res);
        //     } catch (error) {}            
        // });

        this.socketCP.listen('itemModificado').subscribe(res => {
            try { // puede venir de zona de despacho             
                _cpStockItemModificado(res);
            } catch (error) {}
        });

        this.socketCP.listen('itemResetCant').subscribe(res => {
            try { // puede venir de zona de despacho             
                _cpStockItemModificado(res);
            } catch (error) {}
        });

        this.socketCP.listen('printerOnly').subscribe(res => {
            try { // puede venir de zona de despacho             
                _cpSocketPintarPedido(res);
            } catch (error) {}
        });

        // NOTIFICAR PAGO CLIENTE FROM APP
        this.socketCP.listen('notificar-pago-pwa-success').subscribe(res => {
            try { // puede venir de zona de despacho                             
                _cpSocketPintarPedido(null);
                pNotificaPago(res);
            } catch (error) {}
        });


        // NOTIFICAR LLAMADO DEL CLIENTE SOLICITANDO ATENCION
        this.socketCP.listen('notificar-cliente-llamado').subscribe(res => {
            try {
                pNotificaPersonal(res);
            } catch (error) {}
        });

    }
}

// function _cpSocketIsConnect() {
//     isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;
//     if (!isSocket) { return; }

//     try {
//         if (!socketCP.connected) {
//             _cpSocketOpen();
//         }   
//     } catch (error) {
//         // socketCP = io.connect(URL_SOCKET, {
//         //     query: dataSocket
//         // });
//         _cpSocketOpen();
//     }    
// }

// notifica nuevo pedido
function _cpSocketprinterOnly(pedido) {
    if (!isSocket) { return; }
    this.socketCP.emit('printerOnly', pedido);
}

function _cpSocketEmitItemModificado(item) {
    if (!isSocket) { return; }
    this.socketCP.emit('itemModificado', item);
}

function _cpSocketEmitPrinterOnly(item) {
    if (!isSocket) { return; }
    this.socketCP.emit('printerOnly', item);
}

function _cpSocketClose() {
    if (!isSocket) { return; }
    try {        
        this.socketCP.disconnectSocket();
    } catch (error) {        
    }
    // this.socketCP.disconnectSocket();
}

// cuando se paga la cuenta en caja
// requiere idcliente
function _cpSocketEmitPedidoPagoCliente(listIdCliente) {
    if (!isSocket) { return; }
    console.log('listIdCliente', listIdCliente);
    this.socketCP.emit('pedido-pagado-cliente', listIdCliente);
}

function _cpSocketSavePedidoStorage(pedido) {
    if (!isSocket) { return; }
    localStorage.setItem('::app3_sys_dta_pe_sk', JSON.stringify(pedido));
}




// solo para el caso de nuevo pedido en venta rapida o al cerrar panel lateral
function _cpSocketRestoreFromPedidoStorage() {
    if (!isSocket) { return; }
    var pedido = localStorage.getItem('::app3_sys_dta_pe_sk') ? JSON.parse(localStorage.getItem('::app3_sys_dta_pe_sk')) : null;
    var pedidoSend = [], _subItemView = [];
    if ( pedido ) {
        pedido.filter(x => x !== null).map(x => {
            _subItemView = [];
            Object.values(x).filter(a => typeof a === 'object')
                .map(item => {
                    item.idcarta_lista = item.iditem;
                    item.cantidad_seleccionada = item.cantidad;
                    item.isalmacen = item.procede === '1' ? 0 : 1;
                    item.isporcion = item.cantidad;                     
                    item.subitems_view = JSON.parse(JSON.stringify(item.subitems_view));                     
                    console.log('resetPedido item', JSON.stringify(item));
                    pedidoSend.push(item);
                })
        });
    }    

    this.socketCP.emit('resetPedido', pedidoSend);
    localStorage.removeItem('::app3_sys_dta_pe_sk');
    pedidoSend = [];
}

function _cpSocketClearStorage() {
    if (!isSocket) { return; }
    localStorage.removeItem('::app3_sys_dta_pe_sk');
}
