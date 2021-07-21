class socketService{dataSocket={};_socket=_socketSuperMaster;_idConexSocket;isSocketConnectSource=new rxjs.BehaviorSubject(false);isSocketConnect$=this.isSocketConnectSource.asObservable();constructor(){}
connectSocket(){this._idConexSocket=localStorage.getItem('app3_us_skt')||'';this.getDataClient();if(this._socket&&this._socket.connected){return this._socket;}
this._socket=io.connect(URL_SOCKET,{query:this.dataSocket});this.whenSocketIsConnect();this.isSocketConnectSource.next(true);}
whenSocketIsConnect(){this.listenStatus();console.log('socket master connect');_socketSuperMaster=this._socket;}
disconnectSocket(){this._socket.disconnect();console.log('socket master disconnect');_socketSuperMaster=null;}
getDataClient(){const dtUs=xm_log_get('app3_us');this.dataSocket={idorg:dtUs.ido,idsede:dtUs.idsede,idusuario:dtUs.idus,isFromApp:0}}
emit(evento,payload=null){this._socket.emit(evento,payload)}
listen(evento){return new rxjs.Observable(observer=>{this._socket.on(evento,(res)=>{observer.next(res);});});}
listenStatus(){window.addEventListener('online',()=>{this.statusConexSocket(true,'navigator_online');});window.addEventListener('offline',()=>{this.statusConexSocket(false,'navigator_offline');});this._socket.on('connect',()=>{console.log('socket connect');this.statusConexSocket(true,'connect');localStorage.setItem('app3_us_skt',_socketSuperMaster.id);});this._socket.on('connect_failed',(res)=>{console.log('itento fallido de conexion',res);this.statusConexSocket(false,'connect_failed');});this._socket.on('connect_error',(res)=>{console.log('error de conexion',res);this.statusConexSocket(false,'connect_error');});this._socket.on('disconnect',(res)=>{console.log('desconectado',res);this.statusConexSocket(false,'disconnect');});}
statusConexSocket(isConncet,evento){this.isSocketConnectSource.next(isConncet);}
onStatusConexSocket(){return new rxjs.Observable(observer=>{this.isSocketConnect$.subscribe(res=>{observer.next(res);});});}}