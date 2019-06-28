function CpeInterno_Registrar(data){if(data.success){let dataSave={}
dataSave.jsonxml=data.data.jsonxml;dataSave.pdf=data.links.pdf!=''?1:0;dataSave.cdr=data.links.cdr!=''?1:0;dataSave.xml=data.links.xml!=''?1:0;dataSave.nomcliente=data.data.nomcliente;dataSave.idcliente=data.data.idcliente===""?0:data.data.idcliente;dataSave.total=data.data.total;dataSave.totales_json=data.data.totales_json;dataSave.numero=xCeroIzqNumberComprobante(data.data.number);dataSave.external_id=data.data.external_id;dataSave.hash=data.data.hash;dataSave.idregistro_pago=data.data.idregistro_pago;dataSave.viene_facturador=data.data.viene_facturador;dataSave.idtipo_comprobante_serie=data.data.idtipo_comprobante_serie;dataSave.estado_api=0;dataSave.estado_sunat=1;dataSave.msj='Registrado';dataSave.msj_error='';if(data.response.length!=0){const _estado_sunat=data.response.error_soap&&dataSave.numero.indexOf('F')>-1?1:data.response.code?data.response.code:0;dataSave.estado_sunat=_estado_sunat;dataSave.msj=data.response.description;}
CpeInterno_SaveBD(dataSave);}}
function xCeroIzqNumberComprobante(_number){const _arrNumber=_number.split('-');const _num=xCeroIzq(_arrNumber[1],7);return _arrNumber[0]+'-'+_num;}
async function CpeInterno_Error(data,_idregistro_p,_viene_facturador,idtipo_comprobante_serie){let dataSave={};dataSave=data;dataSave.estado_api=1;dataSave.estado_sunat=1;dataSave.msj="Sin registrar";dataSave.error_api=1;if(_idregistro_p!=0){dataSave.idregistro_pago=_idregistro_p;}
return await CpeInterno_SaveBD(dataSave);}
function CpeInterno_ErrorValidacionSunat(_idregistro_p,dataSave){if(_idregistro_p!=0){dataSave.idregistro_pago=_idregistro_p;}
CpeInterno_SaveBD(dataSave);};async function CpeInterno_SaveBD(dataSave){let rptSave='';await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'1',data:dataSave}}).done(function(res){rptSave=JSON.parse(res).datos[0];console.log(res);});return rptSave;}
function CpeInterno_UpdateRegistro(dataUpdate){$.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'2',data:dataUpdate}}).done(function(res){});}
function CpeInterno_SaveResumenDiario(dataResumen){$.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'202',data:dataResumen}}).done(function(res){});}
async function CpeInterno_UpdateAnulacion(dataAnulacion){let rpt=false;await $.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'8',data:dataAnulacion}}).done(function(res){rpt=true;});return rpt;}
function CpeInterno_UpdateAnulacionFactura(dataAnulacion){$.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'8',data:dataAnulacion}}).done(function(res){});}
function CpeInterno_UpdateResumenDiario(dataUpdateResumen){$.ajax({type:'POST',url:'../../bdphp/log_002.php',data:{op:'203',data:dataUpdateResumen}}).done(function(res){});}