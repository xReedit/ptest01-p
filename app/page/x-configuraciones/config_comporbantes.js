var idSedeSel = 0; 
var idComprobanteSel = 0; 
var cnf_SedeSel; 
var cnf_ComprobanteSel; 


function config_comprobante_add(){
    config_comprobante_find_row();

    $("#form_emision_comprobante #idsede").val(idSedeSel);
	$("#form_emision_comprobante #idtipo_comprobante").val(idComprobanteSel);
	xPopupLoad.xopen();
	$.post("../../bdphp/ManejoBD_IUD.php?tb=tipo_comprobante_serie",$("#form_emision_comprobante").serialize(),function(a){
        xPopupLoad.xclose();
        const idorg = $("#form_emision_comprobante #idorg").val();
        $("#form_emision_comprobante").reset();
        $("#form_emision_comprobante #idorg").val(idorg);
		xPopupLoad.xclose();
	    config_comprobante_getall();
	})
}

function config_comprobante_find_row() {
    $("#tb_emision_comprobantes .row").each((index, element) => {
        if (!element.textContent) {return;}
        if (element.textContent.indexOf(this.cnf_ComprobanteSel.descripcion)>-1 && element.textContent.indexOf(this.cnf_SedeSel.nombre)>-1) {
            return;
        }
    })

}

function config_comprobante_getall() {
    $("#tb_emision_comprobantes .row").remove();
    $("#tb_emision_comprobantes").append('<tr class="row"><td colspan="4"><paper-spinner active></paper-spinner></td></tr>');
    
    $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=1607'})
	.done( function (dtComprobante) {
        var xdtComprobante=$.parseJSON(dtComprobante);
		var xcadena_tr_comp='';


        xdtComprobante=xdtComprobante.datos;
        xdtComprobante.map(x => {
            xcadena_tr_comp=String(xcadena_tr_comp+'<tr class="row" data-id="'+x.idtipo_comprobante_serie+'" data-t="tipo_comprobante_serie"><td>'+x.dsc_sede+'</td>'+
                '<td>'+x.dsc_comprobante+'</td>'+
                '<td>'+x.serie+'</td>'+
                '<td>'+x.correlativo+'</td>'+
                '<td><span class="xDeleteRow" title="Borrar" onclick="xDialogBorrarObj(this);"></span></td>'+
				'</tr>')
        })

        $("#tb_emision_comprobantes .row").remove();
		$("#tb_emision_comprobantes").append(xcadena_tr_comp).trigger('create');
		// $("#tb_emision_comprobantes").html(xcadena_sel_almacen_file).trigger('create');

    })


    this.config_valoresInicialesComponente();
    this.config_comprobante_load_sede_cpe();
    
}

function config_comprobante_load_sede_cpe() { // load sedes comprobante electronico
    var _arrSedes = xm_log_get('datos_org_all_sede'); // todas las sedes
    var cadena_tr = '';
    _arrSedes.map(x => {
        checkActivo = x.facturacion_e_activo === "0" ? '' : 'checked';
        cadena_tr += '<tr class="row" data-t="sede" data-id="'+x.idsede+'">'+
        '<td>'+x.nombre+'</td>'+
        '<td data-campo="authorization_api_comprobante">'+
        '<input type="password" class="xMiInput" onblur="config_comprobante_update_token(this)" value="'+ x.authorization_api_comprobante +'"></td>'+
        '<td data-campo="id_api_comprobante">'+
        '<input type="password" class="xMiInput" onblur="config_comprobante_update_id(this)" value="'+ x.id_api_comprobante +'"></td>'+
        '<td><paper-checkbox class="check_cpe" '+ checkActivo +'></paper-checkbox></td>'+
        '</tr>' 
    });

    $("#tb_comprobante_electronico tbody").append(cadena_tr).trigger('create');
}

function config_comprobante_update_token(obj){
    // guarda el token del servicio y asi activar facturacion electronica
    var xvalObj=$(obj).val(),
        xid_row=$(obj).parents('.row').attr('data-id'),
        xcampo_row=$(obj).parent().attr('data-campo');

	$.ajax({ type: 'POST', url: '../../bdphp/log.php?op=2108',data:{campo:xcampo_row,valor:xvalObj,id:xid_row}})
	.done( function (dtPrint) {
		if(dtPrint.indexOf('Error')!=-1){alert(dtPrint)}
	})
}

function config_comprobante_update_id(obj) {
    // guarda el token del servicio y asi activar facturacion electronica
    var xvalObj = $(obj).val(),
        xid_row = $(obj).parents('.row').attr('data-id'),
        xcampo_row = $(obj).parent().attr('data-campo');

    $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=2108', data: { campo: xcampo_row, valor: xvalObj, id: xid_row } })
        .done(function (dtPrint) {
            if (dtPrint.indexOf('Error') != -1) { alert(dtPrint) }
        })
}

function _getSede($event) {
    this.cnf_SedeSel = $event;
    this.idSedeSel = $event.idsede;
}

function _getComprobante($event) {
    this.cnf_ComprobanteSel = $event;
    this.idComprobanteSel = $event.idtipo_comprobante;
}

function config_valoresInicialesComponente() {
    this.cnf_SedeSel = $("#compFindSede")[0].__data__.sedes; // valores iniciales
    this.cnf_ComprobanteSel = $("#compFindComprobante")[0].__data__.comprobantes; // valores iniciales

    this.idSedeSel = cnf_SedeSel.idsede;
    this.idComprobanteSel = cnf_ComprobanteSel.idtipo_comprobante;

    // console.log(this.cnf_SedeSel);
    // console.log(this.cnf_ComprobanteSel);
}