class socketService{static instance=null;static socket=null;dataSocket={};_socket=_socketSuperMaster;_idConexSocket;isSocketConnectSource=new rxjs.BehaviorSubject(!1);isSocketConnect$=this.isSocketConnectSource.asObservable();isRegistroVentaSource=new rxjs.BehaviorSubject(!1);isRegistroVenta$=this.isRegistroVentaSource.asObservable();constructor(){if(socketService.instance)return socketService.instance;socketService.instance=this}static getInstance(){return socketService.instance||(socketService.instance=new socketService),socketService.instance}connectSocket(){if(this._idConexSocket=localStorage.getItem("app3_us_skt")||"",this.getDataClient(),this._socket&&this._socket.connected)return this._socket;this._socket=io.connect(URL_SOCKET,{query:this.dataSocket}),this.whenSocketIsConnect(),this.isSocketConnectSource.next(!0)}whenSocketIsConnect(){this.listenStatus(),_socketSuperMaster=this._socket}disconnectSocket(){this._socket.disconnect(),_socketSuperMaster=null}getDataClient(){var e=xm_log_get("app3_us");this.dataSocket={idorg:e.ido,idsede:e.idsede,idusuario:e.idus,isFromApp:0}}emit(e,t=null){this._socket.emit(e,t)}listen(e){return new rxjs.Observable(t=>{this._socket.on(e,e=>{t.next(e)})})}_listen(e,t){this._socket.on(e,t)}listenStatus(){window.addEventListener("online",()=>{this.statusConexSocket(!0,"navigator_online"),xm_all_xToastOpen("Conexion Recuperada",2e3,!1)}),window.addEventListener("offline",()=>{this.statusConexSocket(!1,"navigator_offline"),xm_all_xToastOpen("Sin Conexion a internet",2e4,!0,!0)}),this._socket.on("connect",()=>{this.statusConexSocket(!0,"connect"),localStorage.setItem("app3_us_skt",_socketSuperMaster.id)}),this._socket.on("connect_failed",e=>{this.statusConexSocket(!1,"connect_failed")}),this._socket.on("connect_error",e=>{this.statusConexSocket(!1,"connect_error")}),this._socket.on("disconnect",e=>{this.statusConexSocket(!1,"disconnect")})}statusConexSocket(e,t){this.isSocketConnectSource.next(e)}onStatusConexSocket(){return new rxjs.Observable(t=>{this.isSocketConnect$.subscribe(e=>{t.next(e)})})}}window.socketService=socketService;