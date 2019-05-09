function setLocalSotrage(key,val){localStorage.setItem(key,val);}
function getLocalStorage(key){return localStorage.getItem(key);}
function removeLocalStorage(key){localStorage.removeItem(key);}
function removeAllLocalStorage(){localStorage.clear();}
function bkLocalStorage(){var _bkLocalStorage=[];Object.keys(localStorage).forEach(function(key){if(key!='::app3_sys_backup'){_bkLocalStorage.push({id:key,content:localStorage.getItem(key)});}});localStorage.setItem('::app3_sys_backup',JSON.stringify(_bkLocalStorage));localStorage.setItem('::app3_sys_backup_time',new Date());}
function resotreLocalSotrage(){const _dataRestore=JSON.parse(localStorage.getItem('::app3_sys_backup'));_dataRestore.map(x=>{if(!localStorage.getItem(x.id)){setLocalSotrage(x.id,x.content);}});}
function updateapp3_woDUS_changeSede(o,i){var _c_xdt_log=window.localStorage.getItem("::app3_woDUS");var _c_xdt_log=window.atob(_c_xdt_log);_c_xdt_log=JSON.parse(_c_xdt_log)
var _data_res=atob(_c_xdt_log.us._sys_sessid).split(':');_c_xdt_log.us.idorg=o;_c_xdt_log.us.idsede=i;_data_res[3]=o;_data_res[4]=i;_data_res=_data_res.join(':');_data_res=btoa(_data_res);_c_xdt_log.us._sys_sessid=_data_res;_c_xdt_log=btoa(JSON.stringify(_c_xdt_log));window.localStorage.setItem("::app3_woDUS",_c_xdt_log)}