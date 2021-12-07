class apiConsulaCpe {

    // constructor () {}    

    getToken = async () => {
        return await fetch('../../bdphp/log_api_consulta_sunat.php?op=1')
        .then(response => response.json())
        .catch(error => console.log('error', error));
    }

    getConsulta = async(params) => {
        // console.log(params, params);
        const _url = '../../bdphp/log_api_consulta_sunat.php?op=2'
        return await fetch(_url, 
            {
                headers: {
                    'Content-Type': 'application/json'
                },
                method: 'POST',
                body:JSON.stringify(params)
            }
        )
        .then(response => response.json())
        .catch(error => console.log('error', error));
    }

    // busca comprobantes no enviados de hace 2 dias y los envia
    regularizarEnvioCpe = () => {
        // verificar facturas

        // verificar boletas y hacer el resumen de boletas
    }

}