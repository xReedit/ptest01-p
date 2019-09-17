var socketVR = io(URL_SOCKET);

socketVR.on('itemModificado', (item) => {
    _vrStockItemModificado(item);
});

function _vrSocketEmitItemModificado(item) {
    socketVR.emit('itemModificado', item);
}

function _vrSocketClose() {
    socketVR.disconnect(true);
}
