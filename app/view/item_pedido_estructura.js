function xCargarDatosAEstructuraImpresion(_SubItems,sumCantidad=false){var _arrEstructura=xm_log_get('estructura_pedido');var _arrRpt=[];_arrEstructura.forEach(element=>{_arrRpt[element.idtipo_consumo]=element});_arrRpt=JSON.parse(JSON.stringify(_arrRpt).replace(/descripcion/g,'des'));for(b in _arrEstructura){for(var i in _SubItems){if(_arrEstructura[b].idtipo_consumo==_SubItems[i].idtipo_consumo){if(_SubItems[i].visible==1){continue;}
if(_arrEstructura[b]==null){continue}
_SubItems[i].precio_print=_SubItems[i].ptotal;_SubItems[i].precio_total=_SubItems[i].ptotal;_SubItems[i].des=_SubItems[i].descripcion;if(sumCantidad){var _cantidad=_SubItems[i].cantidad;if(_cantidad.toString().indexOf(",")>-1){__cantidad=_cantidad.split(',');_cantidad=__cantidad.reduce((a,b)=>parseFloat(a)+parseFloat(b));}
_SubItems[i].cantidad=_cantidad;}
_arrRpt[_arrEstructura[b].idtipo_consumo][i]=_SubItems[i];}}};return _arrRpt.filter(x=>x);}
function xEstructuraItemsJsonComprobante(_SubItems,xArraySubTotales,cpe=false){let itemsObj=[];_SubItems.filter(x=>x!==null).map(items=>{Object.keys(items).map(x=>{if(typeof items[x]==='object'){const item=items[x];item.grupo=item.iditem;itemsObj.push(item);}})});let group=itemsObj.filter(x=>x.grupo).reduce((rv,x)=>{grupo=x.grupo;if(!rv[grupo]){_total=x.precio_total_calc||x.total;_total=_total.toString().indexOf(',')>-1?x.precio_total:_total;_total=parseFloat(_total).toFixed(2);_cantidad=x.cantidad;if(_cantidad.toString().indexOf(",")>-1){__cantidad=_cantidad.split(',');_cantidad=__cantidad.reduce((a,b)=>parseFloat(a)+parseFloat(b));}
rv[grupo]={id:x.iditem,cantidad:parseFloat(_cantidad),des:x.des,punitario:x.precio,precio_total:_total,precio_print:parseInt(x.precio_print)!=0?_total:x.precio_print,seccion:x.des_seccion,tipo_consumo:x.idtipo_consumo,procede:x.procede,idprocede:x.idprocede,stock_actual:x.stock_actual}
return rv}
rv[grupo].cantidad=parseFloat(rv[grupo].cantidad)+parseFloat(x.cantidad);rv[grupo].precio_total=parseFloat(parseFloat(rv[grupo].precio_total)+parseFloat(x.precio_total)).toFixed(2);rv[grupo].precio_print=rv[grupo].precio_total;return rv;},[]);group.sort((a,b)=>(a.des>b.des)-(a.des<b.des)).sort((a,b)=>(a.seccion>b.seccion)-(a.seccion<b.seccion));group=Object.values(group);let cantAddSubtotal=0;xArraySubTotales.map(x=>{if(x.id===undefined){return;}
if(x.id===0){return;}
if(x.tachado===true){return;}
if(x.esImpuesto.toString()==="1"){return;}
const seccion=x.id.toString().indexOf('a')>=0?'ADICIONALES':'SERVICIOS';const _pUnitario=x.punitario?parseFloat(x.punitario):parseFloat(x.importe);const cantidad=parseInt(parseFloat(x.importe)/_pUnitario);const index=group.length+1;cantAddSubtotal=x.importe;group.push({id:index.toString(),cantidad:cantidad,des:x.descripcion.toUpperCase(),punitario:x.punitario,precio_total:x.importe,seccion:seccion});});if(cpe){cantAddSubtotal=parseFloat(xArraySubTotales[0].importe)+parseFloat(cantAddSubtotal);xArraySubTotales[0].importe=xMoneda(cantAddSubtotal);}
return group;}
function xEstructuraItemsAgruparPrintJsonComprobante(items){return xEstructuraItemsGroupFormatoImpresion(items,"seccion");}
function xEstructuraItemsGroupFormatoImpresion(xs,key){const arr_rpt_json=xs;arr_rpt_json.des='**';let rpt={}
rpt[0]=arr_rpt_json;console.log(rpt);return rpt;}