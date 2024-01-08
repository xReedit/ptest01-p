window.addEventListener('WebComponentsReady', () => {
    var router;
    function xIniRouter(){
        // alert('carga xrouter');
        if(router==undefined){router = document.querySelector('app-router');}
        router.addEventListener('state-change', function(xevent) {
        $.ajax({ type: 'POST', url: '../../bdphp/log.php?op=-108',data:{p:window.location.hash},async: false})
        .done( function (a) {
            if(a==1){
                xevent.preventDefault()
            }
        });
    
        xevent.stopImmediatePropagation();
        })
    }
    
    Polymer({
        is:'x-router',
        // dom: 'shadow',
        _go: function(a) {
            console.log(a);
            // alert('xrouter go');
            router.go(a);
        },
        attached:function(){
            xIniRouter();
        },
        ready: () => {
            xIniRouter();
        }
    });
	})
