async function xCocinarResumenBoletas(){$("#dgl_sunat_img").removeClass("xInvisible");$("#dgl_sunat_progress").removeClass("xInvisible");$("#dlg_sunat_btn").addClass("xInvisible");$("#dgl_sunat_msj").removeClass("xInvisible");$(".dgl_sunat_msj2").addClass("xInvisible");$(".dgl_sunat_msj3").text("...");var rptSoap='';let hayError=false;var _arrSedes=xm_log_get('datos_org_sede');const isFacturacionElectronica=_arrSedes[0].facturacion_e_activo==="0"?false:true;if(!isFacturacionElectronica){return rptSoap;}
$(".dgl_sunat_msj3").text("Verificando comprobantes...");const arrDocNoRegistrado=await xSoapSunat_getArrNoRegistrado();let error=false;let jsonxml=''
for(const i in arrDocNoRegistrado){$(".dgl_sunat_msj3").text("Verificando comprobantes B..."+xCeroIzq(i));try{jsonxml=JSON.parse(arrDocNoRegistrado[i].json_xml);}catch(error){try{jsonxml=JSON.parse(arrDocNoRegistrado[i].json_xml.replace(/\n/g,''));}catch(error){console.log('json error',arrDocNoRegistrado[i].json_xml);break;}}
const rpt=await xSoapSunat_EnviarDocumentApi(jsonxml,arrDocNoRegistrado[i].idce);if(!rpt.ok){this.hayError=true;$("#xTituloRpt").append('<p style="color: red">'+rpt.msj_error+"</p>");}
if(error)continue;};await xEnviarRegistradosASunat('F');$(".dgl_sunat_msj3").text('Preparando resumen de boletas...');const arrFechas=await xSoapSunat_getArrFechaBoletasNoAceptadas();for(const f in arrFechas){$(".dgl_sunat_msj3").text("Preparando resumen de boletas..."+xCeroIzq(f));const rpt=await xSoapSunat_ResumenDiario(arrFechas[f].fecha);if(!rpt.ok){this.hayError=true;rptSoap=rpt.msj_error;$("#xTituloRpt").append('<p style="color: red">'+rptSoap+'</p>');}}
$(".dgl_sunat_msj3").text("Consultando resumen de boletas...");const arrTickets=await xSoapSunat_getListTicketResumenBoletas();for(const t in arrTickets){$(".dgl_sunat_msj3").text("Consultando resumen de boletas..."+xCeroIzq(t));const rpt=await xSoapSunat_ConsultarTicketResumen(arrTickets[t]);if(!rpt.ok){this.hayError=true;rptSoap=rpt.msj_error;$("#xTituloRpt").append('<p style="color: red">'+rptSoap+'</p>');await xEnviarRegistradosASunat('B');}}
if(!this.hayError){$(".dgl_sunat_msj3").text("...");$('#dgl_sunat_img').addClass('xInvisible');$("#dgl_sunat_progress").addClass("xInvisible");$("#dlg_sunat_btn").removeClass("xInvisible");$("#dgl_sunat_msj").addClass("xInvisible");$(".dgl_sunat_msj2").removeClass("xInvisible");$("#xTituloRpt").append('<p style="color: blue">Proceso concluido con exito!.</p>');rptSoap='';}else{dialog_enviando_sunat.close();}
return rptSoap;async function xEnviarRegistradosASunat(onlyTipo){const arrDocNoRegistradoSunat=await xSoapSunat_getArrNoRegistradoSunat();error=false;for(const i in arrDocNoRegistradoSunat){if(arrDocNoRegistradoSunat[i].numero.indexOf(onlyTipo)===-1){continue;}
$(".dgl_sunat_msj3").text("Verificando comprobantes "+onlyTipo+"..."+xCeroIzq(i));const rpt=await xSoapSunat_SendSunat(arrDocNoRegistradoSunat[i].external_id,arrDocNoRegistradoSunat[i].idce);if(!rpt.ok){this.hayError=true;$("#xTituloRpt").append('<p style="color: red">'+rpt.msj_error+"</p>");}};}}