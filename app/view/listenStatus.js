
var status_isUsChangeSedeSource, status_isUsChangeSede$;

status_CreateVariablesListen = () => {
    status_isUsChangeSedeSource = new rxjs.BehaviorSubject(false);
    status_isUsChangeSede$ = status_isUsChangeSedeSource.asObservable();
}


status_SetUsChangeSede = () => {   
    status_isUsChangeSedeSource.next(true);
}


onStatusSetUsChangeSede = () => {
    return new rxjs.Observable(observer => {
        try {
            status_isUsChangeSede$.subscribe(res => {
                observer.next(res);
            });        
        } catch (error) {console.log(error)};
    });
}



