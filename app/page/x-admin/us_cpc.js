var _num_us_new_cpc='',_id_num_us_new_cpc;function xLoadCpc(){$.ajax({url:'../../bdphp/log_004.php?op=4'}).done((res)=>{const _res=$.parseJSON(res);xThisAdmin.listCpc=_res.datos;});}
function xCpcLoad_Uusario_Cpc(obj){xPopupLoad.titulo="Cargado..."
xPopupLoad.xopen();const index=obj.parentElement.dataId;xThisAdmin.UsarioCpc_sedes=xThisAdmin.listCpc[index];usuario_cpc.textContent=xThisAdmin.UsarioCpc_sedes.usuario;console.log(xThisAdmin.UsarioCpc_sedes);const _idCPC=xThisAdmin.UsarioCpc_sedes.idus_cpc;$.ajax({type:'POST',url:'../../bdphp/log_004.php?op=401',data:{id:_idCPC}}).done((res)=>{const _res=$.parseJSON(res);xThisAdmin.listCpc_sedes=_res.datos;xPopupLoad.xclose();dialog_add_us_cpc.open();});}
function xCpeValidarFormSave(){xvalidateFormInput('frm_cpc',async function(a){if(a===false){alert('Complete los campos requeridos');return;}
xCpcSave_Usuario_Cpc();});}
async function xCpcSave_Usuario_Cpc(){_id_num_us_new_cpc=idusuario.value;if(_id_num_us_new_cpc==""){_id_num_us_new_cpc=await xCpcRegistrarNuevoUsuarioCPC();idusuario.value=_id_num_us_new_cpc;}
xPopupLoad.xopen();$.post("../../bdphp/ManejoBD_IUD.php?tb=us_cpc",$("#frm_cpc").serialize(),function(id){dialog_add_us_cpc.close();xPopupLoad.xclose();xNuevoUsuarioCPC();xLoadCpc();});}
async function xCpcRegistrarNuevoUsuarioCPC(){var _idNewUsCpc=0;const nom_cpc=nombre_cpc.value;await $.ajax({type:'POST',url:'../../bdphp/log_004.php?op=403',data:{'n':nom_cpc,'u':_num_us_new_cpc}}).done((res)=>{_idNewUsCpc=res;});return _idNewUsCpc;}
function xNuevoUsuarioCPC(){$('#frm_cpc').reset();xThisAdmin.UsarioCpc_sedes={};xThisAdmin.listCpc_sedes=[];}
function xCpcNewUs(){xNuevoUsuarioCPC();xGetUsNew();dialog_add_us_cpc.open();}
function xGetUsNew(){$.ajax({url:'../../bdphp/log_004.php?op=402'}).done((res)=>{_num_us_new_cpc='CONTA'+xCeroIzq(res,3);usuario_cpc.textContent=_num_us_new_cpc;});}