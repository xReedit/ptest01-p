// const dtUs = xm_log_get('app3_us');
// var dataSocket = {
//     idorg: dtUs.ido,
//     idsede: dtUs.idsede,
//     idusuario: dtUs.idus,
//     isFromApp: 0
// }
var socketZona = new socketService();

// isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;
// if (isSocket) {
//     // var socketZona = io.connect(URL_SOCKET, {
//     //     query: dataSocket
//     // });

//     this.socketZona.connectSocket();

//     this.socketZona.listen('nuevoPedido').subscribe(res => {        
//         console.log('nuevo pedido');
//         _zonaSocketPintarPedido(res);
//     });

//     this.socketZona.listen('printerOnly').subscribe(res => {        
//         _zonaSocketPintarPedido(res);
//     });

//     // socketZona.on('nuevoPedido', (data) => {   
//     //     _zonaSocketPintarPedido(data);    
//     //     console.log('nuevoPedido socket zona');
//     // });

//     // socketZona.on('printerOnly', (data) => {   
//     //     _zonaSocketPintarPedido(data);    
//     //     console.log('printerOnly socket zona');
//     // });
// }

function _zonaSocketConnect() {
    isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;
    if (isSocket) {
    
        this.socketZona.connectSocket();

        this.socketZona.listen('nuevoPedido').subscribe(res => {        
            console.log('nuevo pedido');
            _zonaSocketPintarPedido(res);
        });

        this.socketZona.listen('printerOnly').subscribe(res => {        
            _zonaSocketPintarPedido(res);
        });
    }
}

// notifica despachado para pintar en control de pedidos
function _zonaSocketprinterOnly(pedido) {
    if (!isSocket) { return; }
    this.socketZona.emit('printerOnly', pedido);
}

// function _zonaSocketIsConnect() {
//     isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;
//     if (!isSocket) { return; }

//     try {
//         if (!socketZona.connected) {
//             socketCP = io.connect(URL_SOCKET, {
//                 query: dataSocket
//             });
//         }   
//     } catch (error) {
//         socketZona = io.connect(URL_SOCKET, {
//             query: dataSocket
//         });
//     }    
// }

// function _zonaSocketIsConnect() {
//     isSocket = parseInt(xm_log_get('datos_org_sede')[0].pwa) === 0 ? false : true;
//     if (!isSocket) { return; }

//     try {
//         if (!socketZona.connected) {
//             socketZona = io.connect(URL_SOCKET, {
//                 query: dataSocket
//             });
//         }   
//     } catch (error) {
//         socketZona = io.connect(URL_SOCKET, {
//             query: dataSocket
//         });
//     }    
// }

function _zonaSocketClose() {
    if (!isSocket) { return; }
    try {        
        this.socketZona.disconnectSocket();
    } catch (error) {        
    }    
}

