class apiConsulaCpe{getToken=async()=>{return await fetch('../../bdphp/log_api_consulta_sunat.php?op=1').then(response=>response.json()).catch(error=>console.log('error',error));}
getConsulta=async(params)=>{const _url='../../bdphp/log_api_consulta_sunat.php?op=2'
return await fetch(_url,{headers:{'Content-Type':'application/json'},method:'POST',body:JSON.stringify(params)}).then(response=>response.json()).catch(error=>console.log('error',error));}
regularizarEnvioCpe=()=>{}
getConsultaApi=async(params)=>{const _url='https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/20610029923/validarcomprobante'
return await fetch(_url,{mode:'no-cors',headers:{'Content-Type':'application/json','Authorization':'Bearer '+params.header.access_token},method:'POST',body:JSON.stringify(params.body)}).then(response=>response.json()).catch(error=>console.log('error',error));}}