const dtUs=xm_log_get('app3_us');var dataSocket={idorg:dtUs.idorg,idsede:dtUs.idsede,idusuario:dtUs.idus,isFromApp:0}
isSocket=parseInt(xm_log_get('datos_org_sede')[0].pwa)===0?false:true;if(isSocket){var socketCP=io.connect(URL_SOCKET,{query:dataSocket});socketCP.on('nuevoPedido',(data)=>{_cpSocketPintarPedido(data);console.log('nuevoPedido socket cp');});socketCP.on('itemModificado',(item)=>{_cpStockItemModificado(item);});socketCP.on('printerOnly',(item)=>{_cpSocketPintarPedido(item);});}
function _cpSocketIsConnect(){if(!isSocket){return;}
if(!socketCP.connected){socketCP=io.connect(URL_SOCKET,{query:dataSocket});}}
function _cpSocketEmitItemModificado(item){if(!isSocket){return;}
socketCP.emit('itemModificado',item);}
function _cpSocketEmitPrinterOnly(item){if(!isSocket){return;}
socketCP.emit('printerOnly',item);}
function _cpSocketClose(){if(!isSocket){return;}
socketCP.disconnect(true);}
function _cpSocketSavePedidoStorage(pedido){if(!isSocket){return;}
localStorage.setItem('::app3_sys_dta_pe_sk',JSON.stringify(pedido));}
function _cpSocketRestoreFromPedidoStorage(){if(!isSocket){return;}
var pedido=localStorage.getItem('::app3_sys_dta_pe_sk')?JSON.parse(localStorage.getItem('::app3_sys_dta_pe_sk')):null;var pedidoSend=[];if(pedido){pedido.filter(x=>x!==null).map(x=>{Object.values(x).filter(a=>typeof a==='object').map(item=>{item.idcarta_lista=item.iditem;item.cantidad_seleccionada=item.cantidad;item.isalmacen=item.procede==='1'?0:1;pedidoSend.push(item);})});}
socketCP.emit('resetPedido',pedidoSend);localStorage.removeItem('::app3_sys_dta_pe_sk');pedidoSend=[];}
function _cpSocketClearStorage(){if(!isSocket){return;}
localStorage.removeItem('::app3_sys_dta_pe_sk');}