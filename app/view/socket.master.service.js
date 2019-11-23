class socketService {
    dataSocket = {};
    _socket;

    isSocketConnectSource = new rxjs.BehaviorSubject(false);
    isSocketConnect$ = this.isSocketConnectSource.asObservable();

    constructor() {
        // this.connectSocket();
    }

    connectSocket(){
        this.getDataClient();
        
        if ( this._socket && this._socket.connected)  {
            this.whenSocketIsConnect();
            return this._socket;
        }

        this._socket = io.connect(URL_SOCKET, {
            query: this.dataSocket
        });

        this.whenSocketIsConnect();
    }

    whenSocketIsConnect() { // cuando esta conectado
        this.listenStatus();
        this.isSocketConnectSource.next(true);

        console.log('socket master connect'); 
    }

    disconnectSocket() {
        this._socket.disconnect();
        console.log('socket master disconnect'); 
    }

    getDataClient() {
        const dtUs = xm_log_get('app3_us');
        this.dataSocket = {
            idorg: dtUs.ido,
            idsede: dtUs.idsede,
            idusuario: dtUs.idus,
            isFromApp: 0
        }
    }

    emit(evento, payload = null) {
        this._socket.emit(evento, payload)
    }

    listen(evento) {
        return new rxjs.Observable(observer => {
            this._socket.on(evento, (res) => {
                observer.next(res);
            });
        });
    }

    listenStatus() {
        // escuchamos los cambios del navegador
        window.addEventListener('online', () => {
            this.statusConexSocket(true, 'navigator_online');
        });
        window.addEventListener('offline', () => {
           this.statusConexSocket(false, 'navigator_offline');
        });

        // escuchamos los cambios de conexion en el socket
        this._socket.on('connect', () => {
            console.log('socket connect');
          this.statusConexSocket(true, 'connect');
        });

        this._socket.on('connect_failed', (res) => {
          console.log('itento fallido de conexion', res);
          this.statusConexSocket(false, 'connect_failed');
        });
    
        this._socket.on('connect_error', (res) => {
          console.log('error de conexion', res);
          this.statusConexSocket(false, 'connect_error');
        });
    
        this._socket.on('disconnect', (res) => {
            console.log('desconectado', res);
          this.statusConexSocket(false, 'disconnect');
        });
    }

    statusConexSocket(isConncet, evento) {
        this.isSocketConnectSource.next(isConncet);        
    }    

    onStatusConexSocket() {
        return new rxjs.Observable(observer => {
            this.isSocketConnect$.subscribe(res => {
                observer.next(res);
            });        
        });
    }

}