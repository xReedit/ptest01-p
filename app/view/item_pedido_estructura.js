// xArrayTpC=[], //ini en xLoadMipedidoBD tambien para formar array o estructura de  impresion en precuenta o factura, estructura de pedido={tipo consumo > seccion > items}

// tranforma un array pedido plano a una estructura de impresion {tipo consumo > seccion > items}
// devuelve la estructura de pedidos para impresion, {tipo consumo > seccion > items}
// _SubItems
// > precuenta > factura
function xCargarDatosAEstructuraImpresion (_SubItems) {
    var _arrEstructura = xm_log_get('estructura_pedido'); // get estructura_pedido
    var _arrRpt=[];

    // enumero los id desde segun el idtipoconsumo
    _arrEstructura.forEach(element => {
        _arrRpt[element.idtipo_consumo]=element
    });
    // _arrRpt=_arrEstructura.slice();
    _arrRpt=JSON.parse(JSON.stringify(_arrRpt).replace(/descripcion/g,'des'));
    
    for (b in _arrEstructura) {
        for (var i in _SubItems) {
            if(_arrEstructura[b].idtipo_consumo==_SubItems[i].idtipo_consumo){
                    //fue juntado
                    if(_SubItems[i].visible==1){continue;}
                    if(_arrEstructura[b]==null){continue}
                    // _SubItems[i].punitario = _SubItems[i].precio,
                    // _SubItems[i].precio_total = parseFloat(_SubItems[i].precio_total_calc).toFixed(2),
                    // _SubItems[i].precio_print = _SubItems[i].precio_print,
                    _SubItems[i].precio_print=_SubItems[i].ptotal;//coloca precio para impresion
                    _SubItems[i].precio_total=_SubItems[i].ptotal;
                    _SubItems[i].des=_SubItems[i].descripcion;
                    _arrRpt[_arrEstructura[b].idtipo_consumo][i]=_SubItems[i];
            }
        }
    };

    return _arrRpt;

}



/// cocina datos a la estructura de items para impresion de comprobante
/// junta o agrupa por items en 2 secciones: items y servicios adicionales (si hubiera {taper, delivery etc}) 
/// _SubItems = xArrayCuerpo; items que se envian en el formato anterior
/// cpe => si es comprobante electronico o no, esto para calcular el subtotal + adicionales
function xEstructuraItemsJsonComprobante(_SubItems, xArraySubTotales, cpe=false){

    let itemsObj = [];
    // items en una sola lista
    _SubItems
        .filter(x => x !== null)
        .map(items => {
            
            Object.keys(items).map(x=>{
                if ( typeof items[x] === 'object' ) { 
                    const item = items[x]; 
                    item.grupo = item.iditem;
                    itemsObj.push(item); 
                }
            })
        });
    
    // agrupa y suma
    let group = itemsObj
        .filter(x => x.grupo)
        .reduce((rv, x) => {
            grupo = x.grupo;
            if (!rv[grupo]) {
                // cuando sepran la cuenta
                _total = x.precio_total_calc || x.total;
                _total = _total.toString().indexOf(',') > -1 ? x.precio_total : _total; // cuando juntan la cuenta
                _total = parseFloat(_total).toFixed(2);

                _cantidad = x.cantidad;
                if (_cantidad.toString().indexOf(",") > -1 ) { // caso de que se junte los items
                    __cantidad = _cantidad.split(',');
                    _cantidad = __cantidad.reduce((a, b) => parseFloat(a) + parseFloat(b));
                }                
                
                rv[grupo] = {
                    id: x.iditem,
                    cantidad: parseFloat(_cantidad),
                    des: x.des,
                    punitario: x.precio,
                    precio_total: _total,
                    precio_print: parseInt(x.precio_print) != 0 ? _total : x.precio_print,
                    seccion: x.des_seccion
                    // precio_total: parseFloat(x.precio_total).toFixed(2),
                }
                return rv
            }

            rv[grupo].cantidad = parseFloat(rv[grupo].cantidad) + parseFloat(x.cantidad);
            rv[grupo].precio_total = parseFloat( parseFloat(rv[grupo].precio_total) + parseFloat(x.precio_total) ).toFixed(2);
            rv[grupo].precio_print = rv[grupo].precio_total;
            return rv;
        }, []);

    
    // ordena
    group
        .sort((a, b) => (a.des > b.des) - (a.des < b.des) )
        .sort((a, b) => (a.seccion > b.seccion) - (a.seccion < b.seccion) );
    
    group = Object.values(group);
    
    // agreagar adicionales si los hay y los suma a subtotal
    let cantAddSubtotal = 0;
    xArraySubTotales.map(x => {
        if (x.id === undefined) { return; } // id remplaza a tachado es decir no se aceptan subtotales
        if (x.tachado === true) { return; }
        if (x.esImpuesto === "1") { return; }

        const seccion = x.id.indexOf('a') >= 0 ? 'ADICIONALES' : 'SERVICIOS';
        // const cantidad = x.cantidad ? x.cantidad : 1;
        const cantidad = parseInt(x.importe / x.punitario);
        const index = group.length+1; // en facturacion electronica el id debe ser numero

        cantAddSubtotal = x.importe; // para aumentar al subtotal xArraySubTotales

        group.push({
          id: index.toString(),
          cantidad: cantidad,
          des: x.descripcion,
          punitario: x.punitario,
          precio_total: x.importe,
          seccion: seccion
        });
    });

    if (cpe) { //si es comprobante electronico o no, esto para calcular el subtotal + adicionales
        cantAddSubtotal = parseFloat(xArraySubTotales[0].importe) + parseFloat(cantAddSubtotal);
        xArraySubTotales[0].importe = xMoneda(cantAddSubtotal);
    }
        
    // const aa = xEstructuraItemsGroupFormatoImpresion(group, "seccion");
    // console.log(aa);
    // console.log('group: ',group);
    return group;
}

// pasar a estructura de impresion de comprobante donde no se tendra en cuenta el tipo consumo, solo seccion e item
function xEstructuraItemsAgruparPrintJsonComprobante(items) {
    return xEstructuraItemsGroupFormatoImpresion(items, "seccion");
}


function xEstructuraItemsGroupFormatoImpresion( xs , key) {
    // const arr_rpt_json =  xs.reduce(function (rv, x) {
    //     (rv[x[key]] = rv[x[key]] || []).push(x);
    //     // rv[x[key]].des_seccion = x[key];
    //     return rv;
    // }, {});

    const arr_rpt_json = xs;
    arr_rpt_json.des = '**';
    let rpt = {}
    rpt[0] = arr_rpt_json;
    console.log(rpt);
    return rpt;
}