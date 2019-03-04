async function xCocinarResumenBoletas() {

    //resetea dialog sunat

    $("#dgl_sunat_img").removeClass("xInvisible");
    $("#dgl_sunat_progress").removeClass("xInvisible");
    $("#dlg_sunat_btn").addClass("xInvisible");
    $("#dgl_sunat_msj").removeClass("xInvisible");
    $("#dgl_sunat_msj2").addClass("xInvisible");
    $("#dgl_sunat_msj3").text("...");

    //

    var rptSoap = '';
    let hayError = false;
    
    // verifica si hay facturacion electronica
    var _arrSedes = xm_log_get('datos_org_sede');
    const isFacturacionElectronica = _arrSedes[0].facturacion_e_activo === "0" ? false : true; // si se emiten comprobantes electronicos    

    if (!isFacturacionElectronica) {
        return rptSoap;
    }

    // cocinar registro faltantes - enviar documnentos que no fueron enviados al servicio api por algun error de conexion
    // estado_api = 1; //no se subieron al api
    $("#dgl_sunat_msj3").text("Verificando comprobantes...");
    const arrDocNoRegistrado = await xSoapSunat_getArrNoRegistrado();
    let error = false;
    for (const i in arrDocNoRegistrado) {
        
        $("#dgl_sunat_msj3").text("Verificando comprobantes..." + xCeroIzq(i) );
        const jsonxml = JSON.parse(arrDocNoRegistrado[i].json_xml.replace('"{', '{').replace('}"', '}'))
        // const rpt = await xSoapSunat_EnviarDocumentApi(jsonxml, arrDocNoRegistrado[i].idregistro_pago, arrDocNoRegistrado[i].codsunat);
        const rpt = await xSoapSunat_EnviarDocumentApi(jsonxml, arrDocNoRegistrado[i].idce);
        if (!rpt.ok) { 
            // continuar con el siguiente
            this.hayError=true;            
            $("#xTituloRpt").append('<p style="color: red">' + rpt.msj_error + "</p>"); 
            //error = true; 
            //dialog_enviando_sunat.close();
            //return; 
        }
    };
    
    if ( error ) return;

    
    // cocinar resumen
    $("#dgl_sunat_msj3").text('Preparando resumen de boletas...');
    const arrFechas = await xSoapSunat_getArrFechaBoletasNoAceptadas();
    for (const f in arrFechas) {
        $("#dgl_sunat_msj3").text("Preparando resumen de boletas..." + xCeroIzq(f));
        const rpt = await xSoapSunat_ResumenDiario(arrFechas[f].fecha);
        if (!rpt.ok) { 
            this.hayError = true;
            rptSoap = rpt.msj_error;
            $("#xTituloRpt").append('<p style="color: red">' + rptSoap + '</p>');            
        }
        // console.log(rpt);        
    }
    
    // consultamos los ticket de resumen de boletas generados el dia anterior            
    // y actualizar el estado_sunat = 0 de las boletas => acpetadas
    $("#dgl_sunat_msj3").text("Consultando resumen de boletas...");
    const arrTickets = await xSoapSunat_getListTicketResumenBoletas();
    for (const t in arrTickets) {
        $("#dgl_sunat_msj3").text("Consultando resumen de boletas..." + xCeroIzq(t));
        const rpt = await xSoapSunat_ConsultarTicketResumen(arrTickets[t]);
        if (!rpt.ok) {
            this.hayError = true;
            rptSoap = rpt.msj_error;
            $("#xTituloRpt").append('<p style="color: red">' + rptSoap + '</p>');
        }
        // console.log(rpt);
    }


    
    if ( !this.hayError ) {
        // proceso concluido con exito
        $("#dgl_sunat_msj3").text("...");
        $('#dgl_sunat_img').addClass('xInvisible');
        $("#dgl_sunat_progress").addClass("xInvisible");
        $("#dlg_sunat_btn").removeClass("xInvisible");
        $("#dgl_sunat_msj").addClass("xInvisible");        
        $("#dgl_sunat_msj2").removeClass("xInvisible");        
        $("#xTituloRpt").append('<p style="color: blue">Proceso concluido con exito!.</p>');
        rptSoap = '';
    } else {
        dialog_enviando_sunat.close();        
    }    

    return rptSoap;


}

