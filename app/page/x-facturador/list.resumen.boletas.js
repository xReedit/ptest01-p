function xListRe_ini() {

    xThisCpe.$.paginator_re.currentPage = 1;
    xThisCpe.$.paginator_re.pageRows = p_rows_re;
    xThisCpe.$.paginator_re.listRows = [10, 20, 30, 40];

    xThisCpe.$.paginator_re.addEventListener('page-limit-change', (e) => {
        console.log('page-limit-change', e.detail.value);
        data_pagination_re = e.detail.value; // datos del componente
        p_desde = data_pagination_re.pageDesde; // para el # de fila
        xListRe_LoadData();
        
    });
}

function xfiltrarDatos_re() {
    if (xe_debounce_re) return;
    xe_debounce_re = true;
    clearTimeout(xdebounce);

    xdebounce = setTimeout(() => {
        xListRe_LoadData();
        // clearTimeout(xdebounce);
        xe_debounce_re = false;
        xThisCpe.$.paginator_re.currentPage = 1;
        // xIniPagination();
    }, 900);
}

function xListRe_LoadData() {
    xThisCpe.ListCpeResumen = [];
    const p_filter = xThisCpe.$.txt_buscar_re.value;

    data_pagination_re.pageFilter = p_filter;    

    $.ajax({ type: 'POST', url: '../../bdphp/log_002.php', data: { op: '7', pagination: data_pagination_re } })
    .done(function (res) {
        res = res.split('**');
        p_rows = res[1];
        res = $.parseJSON(res[0]);
        xThisCpe.ListCpeResumen = res.datos;

        console.log(xThisCpe.ListCpeResumen);

        xThisCpe.ListCpeResumen.map(x => {

            switch (parseInt(x.estado_sunat)) {
                case 0: class_estado = "xColorRow_Azul"; break;
                case 1: class_estado = "xColorRow_verde"; break;
                case 2: class_estado = "xFondoRowRojo"; break;                
            }

            class_estado += ' xBold xfont11';            
            x.ico_xml = x.xml === '1' ? true : false;
            x.ico_cdr = x.cdr === '1' ? true : false;
            x.class_estado = class_estado;

        });
        
        xThisCpe.$.paginator_re.pageRows = p_rows;   
    });
}