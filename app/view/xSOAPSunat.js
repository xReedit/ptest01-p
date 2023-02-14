var dtSede=xm_log_get("datos_org_sede")[0];var url_api_fac_sede=dtSede.url_api_fac||'';var URL_COMPROBANTE=url_api_fac_sede===''?xm_log_get('app3_sys_const')[0].value:url_api_fac_sede;var URL_COMPROBANTE_DOWNLOAD_FILE=url_api_fac_sede===''?xm_log_get('app3_sys_const')[1].value:url_api_fac_sede.replace('.pe/api','.pe/downloads/document');async function xSoapSunat_getArrNoRegistrado(){var rpt=[];await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{'op':'301'}}).done(function(rptDate){data_response=$.parseJSON(rptDate);if(!data_response.success){alert(data_response.error);return;}
rpt=data_response.datos;});return rpt;}
async function xSoapSunat_getArrNoRegistradoSunat(){var rpt=[];await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{'op':'3011'}}).done(function(rptDate){data_response=$.parseJSON(rptDate);if(!data_response.success){alert(data_response.error);return;}
rpt=data_response.datos;});return rpt;}
async function xSoapSunat_ResumenDiario(fecha){const _fecha=xSoapSunat_cambiarFormatoFechaString(fecha);const jsonResumen={"fecha_de_emision_de_documentos":_fecha,"codigo_tipo_proceso":"1"}
const _url=URL_COMPROBANTE+"/summaries";let _headers=HEADERS_COMPROBANTE;_headers.Authorization="Bearer "+xm_log_get("datos_org_sede")[0].authorization_api_comprobante;const _jsonResumen=JSON.stringify(jsonResumen);let rpt={};let dataRes={};dataRes.fecha_resumen=_fecha;await fetch(_url,{method:'POST',headers:_headers,body:_jsonResumen,}).then(function(response){return response.json();}).then(function(res){console.log(res);if(res.success){rpt.ok=true;dataRes.external_id=res.data.external_id;dataRes.ticket=res.data.ticket;dataRes.estado_sunat=0;dataRes.msj='Resumen enviado.';CpeInterno_SaveResumenDiario(dataRes);}else{rpt.ok=false;rpt.error='Error al ingresar los datos';rpt.msj_error=res.message;dataRes.estado_sunat=1;dataRes.msj=res.message;CpeInterno_SaveResumenDiario(dataRes);}}).catch(function(error){rpt.ok=false;rpt.msj="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";});return rpt;}
async function xSoapSunat_getArrFechaBoletasNoAceptadas(){var rpt=[];await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{'op':'302'}}).done(function(rptDate){rptDate=$.parseJSON(rptDate);if(!rptDate.success){alert(rptDate.error);return;}
rpt=rptDate.datos;});return rpt;}
async function xSoapSunat_getListTicketResumenBoletas(){var rpt=[];await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{'op':'303'}}).done(function(rptResumen){rptResumen=$.parseJSON(rptResumen);if(!rptResumen.success){alert(rptResumen.error);return;}
rpt=rptResumen.datos;});return rpt;}
async function xSoapSunat_ConsultarTicketResumen(ticket){const _url=URL_COMPROBANTE+"/summaries/status";let _headers=HEADERS_COMPROBANTE;_headers.Authorization="Bearer "+xm_log_get("datos_org_sede")[0].authorization_api_comprobante;const _ticket={"external_id":ticket.external_id,"ticket":ticket.ticket}
const json_ticket=json_xml=JSON.stringify(_ticket);let rpt={};await fetch(_url,{method:"POST",headers:_headers,body:json_ticket}).then(function(response){return response.json();}).then(function(res){console.log(res);let data={};if(res.success){rpt.ok=true;data.ticket=ticket.ticket;data.cdr=res.links.cdr!=""?1:0;data.xml=res.links.xml!=""?1:0;data.fecha_resumen=xSoapSunat_cambiarFormatoFechaString2(ticket.fecha_resumen);if(res.response.description.indexOf('aceptado')>-1){data.estado_sunat=1;data.msj=res.response.description;}
CpeInterno_UpdateResumenDiario(data);}else{rpt.ok=false;rpt.error="Error en resumen de boletas";rpt.msj_error=res.message;data.ticket=ticket.ticket;data.cdr=0;data.xml=0;data.estado_sunat=2;data.msj=res.message;CpeInterno_UpdateResumenDiario(data);}}).catch(function(error){rpt.ok=false;rpt.msj_error="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";});return rpt;}
async function xSoapSunat_EnviarDocumentApi(json_xml,idce,numDoc='#'){const _url=URL_COMPROBANTE+'/documents';let _headers=HEADERS_COMPROBANTE;_headers.Authorization="Bearer "+xm_log_get("datos_org_sede")[0].authorization_api_comprobante;let rpt={};const numero_comp=json_xml.serie_documento+"-"+json_xml.numero_documento;json_xml=JSON.stringify(json_xml);await fetch(_url,{method:'POST',headers:_headers,body:json_xml,}).then(function(response){return response.json();}).then(function(res){console.log(res);if(res.success){rpt.ok=true;let data={};data.idce=idce;data.estado_api=0;data.estado_sunat=1;data.msj="Registrado";data.numero=numero_comp;data.external_id=res.data.external_id;if(res.response.length!=0){data.estado_sunat=res.response.code;data.msj=res.response.description;}
data.pdf=res.links.pdf!=""?1:0;data.cdr=res.links.cdr!=""?1:0;data.xml=res.links.xml!=""?1:0;CpeInterno_UpdateRegistro(data);}else{rpt.ok=false;rpt.error='Error al ingresar los datos';rpt.msj_error=res.message;const data={idce:idce,numero:numero_comp,external_id:'',estado_api:0,estado_sunat:1,anulado:1,msj:res.message,pdf:0,cdr:0,xml:0,}
CpeInterno_UpdateRegistro(data);}}).catch(function(error){rpt.ok=false;rpt.msj="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";rpt.msj_error="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";});return rpt;}
async function xSoapSunat_SendSunat(external_id,idce){const _url=URL_COMPROBANTE+'/send';let _headers=HEADERS_COMPROBANTE;_headers.Authorization="Bearer "+xm_log_get("datos_org_sede")[0].authorization_api_comprobante;let rpt={};const _json={"external_id":external_id}
await fetch(_url,{method:'POST',headers:_headers,body:JSON.stringify(_json)}).then(function(response){return response.json();}).then(function(res){console.log(res);const errSoap=res.response?res.response.error_soap?res.response.error_soap:false:false;if(res.success&&!errSoap){rpt.ok=true;let data={};data.idce=idce;data.estado_api=0;data.estado_sunat=0;data.msj="Aceptada";data.external_id=external_id;if(res.response.length!=0){data.msj=res.response.description;}
data.pdf=res.links.pdf!=""?1:0;data.cdr=res.links.cdr!=""?1:0;data.xml=res.links.xml!=""?1:0;CpeInterno_UpdateRegistro(data);}
else{rpt.ok=false;rpt.error='Problema de conexion Sunat persistente.';rpt.msj_error=res.message||res.response.description;}}).catch(function(error){rpt.ok=false;rpt.msj="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";rpt.msj_error="Error de conexion con el servicio Sunat: se intentara enviar nuevamente al proximo cierre.";});return rpt;}
async function xSoapSunat_AnularComprobante(dataAnulacion){const codsunat=dataAnulacion.codsunat;let _url=URL_COMPROBANTE;let _headers=HEADERS_COMPROBANTE;let evento='';_headers.Authorization="Bearer "+xm_log_get("datos_org_sede")[0].authorization_api_comprobante;const fechaDocumento=xSoapSunat_cambiarFormatoFechaString(dataAnulacion.fecha);let json_anulacion={};let rpt;switch(codsunat){case'01':evento="voided";json_anulacion={"fecha_de_emision_de_documentos":fechaDocumento,"documentos":[{"external_id":dataAnulacion.external_id,"motivo_anulacion":dataAnulacion.motivo}]}
break;case'03':evento="summaries";json_anulacion={"fecha_de_emision_de_documentos":fechaDocumento,"codigo_tipo_proceso":"3","documentos":[{"external_id":dataAnulacion.external_id,"motivo_anulacion":dataAnulacion.motivo}]};break;}
_url=_url+'/'+evento;json_anulacion=JSON.stringify(json_anulacion)
await fetch(_url,{method:'POST',headers:_headers,body:json_anulacion,}).then(function(response){return response.json();}).then(async function(res){console.log(res)
rpt=res.success;if(rpt){dataAnulacion.external_id_anulacion=res.data?res.data.external_id:res.external_id;dataAnulacion.ticket=res.data?res.data.ticket:res.ticket;const a=await CpeInterno_UpdateAnulacion(dataAnulacion);rpt=a;}else{console.log(res);alert(res.message);}}).catch(function(error){rpt=false;console.log(error);alert('No se pudo establecer conexion con el servicio Sunat');});return rpt;}
function xSoapSunat_cambiarFormatoFecha(input){const pattern=/(\d{4})\-(\d{2})\-(\d{2})/;if(!input||!input.match(pattern)){return null;}
return input.replace(pattern,"$3/$2/$1");}
function xSoapSunat_cambiarFormatoFechaString(sfecha){return sfecha.split("/").reverse().join("-");}
function xSoapSunat_cambiarFormatoFechaString2(sfecha){return sfecha.split("-").reverse().join("/");}
function xSoapSunat_DownloadFile(tipo,id){const _dtSede=xm_log_get("datos_org_sede")[0];const _userId=_dtSede.id_api_comprobante;const _url=`${URL_COMPROBANTE_DOWNLOAD_FILE}/${tipo}/${id}/${_userId}`;window.open(_url,"_blank");}