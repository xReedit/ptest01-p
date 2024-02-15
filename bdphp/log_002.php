<?php
    //log comprobantes electronicos
    // session_set_cookie_params('4000'); // 1 hour
    // session_regenerate_id(true); 
    session_start();	
    //header("Cache-Control: no-cache,no-store");

    // header('Access-Control-Allow-Origin: *');
    // header("Content-type: application/json; charset=utf-8");
    // header("Content-type: application/json; charset=utf-8");
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');
	
    $op = $_POST['op'];

    $g_idorg = $_SESSION['ido'];
    $g_idsede = $_SESSION['idsede'];
    $g_idusuario = $_SESSION['idusuario'];

    $dias_consulta = 3;

    switch ($op) {
        case '1': //registrar envio cpe 
            $obj = $_POST['data'];

            $ce_anulado = array_key_exists('anulado', $obj) ? $obj['anulado'] : 0; 
            $ce_totales_json = array_key_exists('totales_json', $obj) ? $obj['totales_json'] : ''; 

            // procedure
            $obj['ce_anulado'] = $ce_anulado;
            $obj['totales_json'] = $ce_totales_json;
            $obj['idregistro_pago'] = array_key_exists('idregistro_pago', $obj) ? $obj['idregistro_pago'] : '';
            $obj['error_api'] = array_key_exists('error_api', $obj) ? $obj['error_api'] : '0'; // si el api no responde igual tiene que emitir comprobante offline

            $arrItem=addslashes(json_encode($obj));
            // echo $arrItem;            
            $sql = "CALL procedure_cpe_registro(".$g_idorg.",".$g_idsede.",".$g_idusuario.",'".$arrItem."')";
            $bd->xConsulta($sql);
            // //

            
            // $sqlCpe = "
            //     insert into ce (external_id, idorg, idsede, idusuario, idtipo_comprobante_serie, numero, fecha, hora, json_xml, estado_api, estado_sunat, viene_facturador, msj, anulado, idcliente, nomcliente, total, pdf, xml, cdr, totales_json)
            //     values ('".$obj['external_id']."',".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$obj['idtipo_comprobante_serie'].",
            //     '".$obj['numero']."', DATE_FORMAT(now(),'%d/%m/%Y'), DATE_FORMAT(now(),'%H:%i:%s'), '".$obj['jsonxml']."', ".$obj['estado_api'].",".$obj['estado_sunat'].",".$obj['viene_facturador'].",'".$obj['msj']."',".$ce_anulado.",".$obj['idcliente'].",'".$obj['nomcliente']."','".$obj['total']."',".$obj['pdf'].",".$obj['xml'].",".$obj['cdr'].",'".$ce_totales_json."')";

            // echo $sqlCpe;
            // $idce = $bd->xConsulta_UltimoId($sqlCpe);
            
            // // si el documento no es anulado // por validacion sunat
            // if ( $ce_anulado === 0 ) {
            //     if (array_key_exists('idregistro_pago', $obj)) {
            //         if ( $obj['viene_facturador'] === "1") {
            //             $sqlRp= "update cpe_facturador set idce =".$idce." where idcpe_facturador=".$obj['idregistro_pago'];
            //             $bd->xConsulta_NoReturn($sqlRp);
            //         } else {
            //             $sqlRp= "update registro_pago set idce =".$idce." where idregistro_pago=".$obj['idregistro_pago'];
            //             $bd->xConsulta_NoReturn($sqlRp);
            //         }
            //     }
            // }

            break;
        case '101': // registra documentos que 
            $objRegistro = $_POST['data'];
            $sql = "update registro_pago_cpe set external_id_comprobante = '".$objRegistro['idexternal']."', registrado=".$objRegistro['enviado']." where idregistro_pago = ".$objRegistro['idregistro_pago'];
            $bd->xConsulta($sql);
            break;
        case '102': // registrar en cpe_facturador emitido desde facturador
            $objRegistro = $_POST['data'];
            $arrItems = $_POST['arrItems'];
            $subTotal = isset($objRegistro['jsonsubtotalXml']) ? $objRegistro['jsonsubtotalXml'] : '';
            $sqlCpe="insert into cpe_facturador (idorg, idsede, idusuario, idcliente, idcomprobante, num_comprobante, fecha, subtotal, igv, total) 
                    values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$objRegistro['idcliente'].",'".$objRegistro['idcomprobante']."',
                    '".$objRegistro['num_comprobante']."',now(),'".$subTotal."','".$objRegistro['igv']."','".$objRegistro['total']."')";
            
            // echo $sqlCpe;
            $idcpe_facturador = $bd->xConsulta_UltimoId($sqlCpe);

            // guardar correlativo comprobante
            // $idtipo_comprobante = $objRegistro['idcomprobante'];
            // $idtipo_comprobante_serie = $objRegistro['idtipo_comprobante_serie'];
            // if ( isset($idtipo_comprobante_serie) ) {
            //     $sql_correlativo = "update tipo_comprobante_serie set correlativo = correlativo + 1 where idtipo_comprobante_serie=$idtipo_comprobante_serie";
            // } else {
            //     $sql_correlativo = "update tipo_comprobante_serie set correlativo = correlativo + 1 where idsede=$g_idsede and idtipo_comprobante=$idtipo_comprobante and estado=0";
            // }
            // $bd->xConsulta_NoReturn($sql_correlativo);
            

            // detalles del comprobante
            $sql_dt_item = '';
            foreach ($arrItems as $item){
                $sql_dt_item=$sql_dt_item."(".$idcpe_facturador.",".$item['iditem'].",'".$item['descripcion']."','".$item['cantidad']."','".$item['punitario']."','".$item['ptotal']."'),";
            }
            $sql_dt_item=substr($sql_dt_item,0,-1);
            $sql_dt_item = "insert into cpe_facturador_detalle (idcpe_facturador, iditem, descripcion, cantidad, punitario, ptotal) values ".$sql_dt_item;
            $bd->xConsulta_NoReturn($sql_dt_item);

            print $idcpe_facturador;
            break;
        case '103': // sumar +1 correlativo otros comprobante no declarados como: tickets, entradas etc
            $sql = "update tipo_comprobante_serie set correlativo = correlativo + 1 where idtipo_comprobante_serie = ".$_POST['i'];
            $bd->xConsulta_NoReturn($sql);
            break;
        case '103001': // obtener correlativo comprobante
            $id = $_POST['i'];
            $sql = "call procedure_get_num_comprobante($id)";
            $bd->xConsulta($sql);
            break;
        case '2': // actualiza el estado de comprabantes reenviados (desde cierre caja): si fue aceptada = 1 o fue anulada = 1
            $obj = $_POST['data'];
            $ce_anulado = array_key_exists('anulado', $obj) ? $obj['anulado'] : 0; 

            $numComprobante = array_key_exists('numero', $obj) ? "numero='".$obj['numero']."'," : "";
            $sql = "
            update ce 
                set estado_api=".$obj['estado_api'].", 
                estado_sunat=".$obj['estado_sunat'].", 
                msj='".$obj['msj']."', 
                external_id='".$obj['external_id']."',
                ".$numComprobante."
                anulado=".$ce_anulado.",
                pdf=".$obj['pdf'].",
                xml=".$obj['xml'].",
                cdr=".$obj['cdr']."
            where idce=".$obj['idce'];
                        
            $bd->xConsulta($sql);

            // $objRegistro = $_POST['data'];
            // $sql="update registro_pago_cpe set ".$objRegistro['campo']."=".$objRegistro['valor']." where external_id_comprobante = '".$objRegistro['idexternal']."'";
            // $bd->xConsulta($sql);
            break;
        case '201': // actualiza el estado de las boletas enviadas segun fecha // y registrar en cpe_resumen_boletas
            $objRegistro = $_POST['data'];
            // registra resumen
            $sqlResumen = "insert into cpe_resumen_boletas (fecha, id, ticket, msj, registrado) values ('".$objRegistro['fecha']."', ".$objRegistro['id'].",'".$objRegistro['ticket']."','".$objRegistro['msj']."',1)";
            // cambia de estado aceptdo a todas las boletas segun fecha
            $sql="update registro_pago_cpe set ".$objRegistro['campo']."=".$objRegistro['valor']." where fecha = '".$objRegistro['fecha']."'";
            $sql = $sqlResumen."; ".$sql;         
            $bd->xMultiConsulta($sql);
            break;
        case '202': //registra resumen diario de boletas
            $obj = $_POST['data'];
            
            $sql = "INSERT INTO ce_resumen
                    (idorg, idsede, idusuario, fecha_resumen, fecha_envio, ticket, external_id, estado_sunat, msj, estado)
                    VALUES(".$_SESSION['ido'].", ".$_SESSION['idsede'].", ".$_SESSION['idusuario'].", '".$obj['fecha_resumen']."', DATE_FORMAT(now(),'%d/%m/%Y')
                    , '".$obj['ticket']."', '".$obj['external_id']."', ".$obj['estado_sunat'].", '".$obj['msj']."', 0);";
            
                        
            $bd->xConsulta($sql);
            break;
        case '203': // update resumen boleta luego de ser consultado con el ticket
            $obj = $_POST['data'];
            $estado_sunat = $obj['estado_sunat'];
            $sql="update ce_resumen 
                    set estado_sunat=".$estado_sunat.",
                    msj='".$obj['msj']."',
                    xml=".$obj['xml'].",
                    cdr=".$obj['cdr']." 
                    where ticket='".$obj['ticket']."'";
            // echo $sql;
            $bd->xConsulta($sql);

            // actualizar boletas estado_sunat = 0 -> aceptadas
            if ( $estado_sunat === "1" ) {
                $sql_bl = "
                    UPDATE ce as c
                        inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie = c.idtipo_comprobante_serie
                        inner join tipo_comprobante as tp on tp.idtipo_comprobante = tps.idtipo_comprobante
                    set c.estado_sunat=0, c.msj = 'Aceptado'
                    where (c.idsede=".$_SESSION['idsede'].") and c.fecha = '".$obj['fecha_resumen']."' and tp.codsunat='03' and c.anulado=0";

                // echo $sql_bl;
                $bd->xConsulta_NoReturn($sql_bl);
            }
            break;
        case '3': // consulta de boletas que fueron registradas pero no aceptadas(por cualquier motivo), devuelve fechas no aceptadas
            $sql = "SELECT * from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and str_to_date(fecha, '%d/%m/%Y') between DATE_SUB( CURDATE() , INTERVAL $dias_consulta DAY ) AND CURDATE() and estado_api=0 and (estado_sunat != 0 or msj='Registrado') and anulado=0";            
            $bd->xConsulta($sql);
            break;
        case '301': // lista documentos no registrados - documnentos que no fueron enviados al servicio api por algun error de conexion

            // 030921 // actualizar es estado de estado_sunat si el msj = registrado
            // $sql = "update ce set estado_sunat=1 where idsede = ".$_SESSION['idsede']." and msj='Registrado'";
            // $bd->xConsulta_NoReturn($sql);
            
            // 050221 // actualiza las boletas o facturas no enviados > 15, para que no siga notificando            
            // $sql = "update ce set estado_api = 0 where idsede = ".$_SESSION['idsede']." and str_to_date(fecha, '%d/%m/%Y') < DATE_SUB( CURDATE() , INTERVAL $dias_consulta DAY ) and estado_api = 1 and anulado=0";
            // $bd->xConsulta_NoReturn($sql);

            // $sql = "SELECT * from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado_api = 1 and anulado=0";
            $sql = "SELECT * from ce where idsede = ".$_SESSION['idsede']." and str_to_date(fecha, '%d/%m/%Y') between DATE_SUB( CURDATE() , INTERVAL $dias_consulta DAY ) AND CURDATE() and estado_api = 1 and anulado=0";
            $bd->xConsulta($sql);
            break;
        case '3011': // facturas// lista documentos no registrados en suant - documnentos que no fueron enviados a sunat por algun error de conexion
            $sql = "SELECT * from ce where (idsede=".$_SESSION['idsede'].") and str_to_date(fecha, '%d/%m/%Y') between DATE_SUB( CURDATE() , INTERVAL $dias_consulta DAY ) AND CURDATE() and estado_api = 0 and (estado_sunat != 0 or msj='Registrado') and anulado=0";

            // facturas y boletas, a las boletas revisa si esta sin registrar y las registra
            // $sql = "SELECT tcs.idtipo_comprobante, ce.* from ce
            //     inner join tipo_comprobante_serie tcs on tcs.idtipo_comprobante_serie = ce.idtipo_comprobante_serie
            //     where (ce.idsede=".$_SESSION['idsede'].") and tcs.idtipo_comprobante = 3
            //     and str_to_date(ce.fecha, '%d/%m/%Y') between DATE_SUB( CURDATE() , INTERVAL 10 DAY ) AND CURDATE() 
            //     and ce.estado_api = 0 
            //     and (ce.estado_sunat != 0 or msj='Registrado')
            //     and ce.anulado=0;";
            $bd->xConsulta($sql);
            break;
        case '302':// resumen de boletas: consulta fecha de boletas no enviadas 
            $sql="SELECT fecha from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and str_to_date(fecha, '%d/%m/%Y') between DATE_SUB( CURDATE() , INTERVAL $dias_consulta DAY ) AND CURDATE() and (estado_sunat != 0 or msj='Registrado') and (estado=0 and anulado=0) GROUP BY fecha";
            $bd->xConsulta($sql);
            break;
        case '303': // lista de tickets de resumen boleta por confitmar aceptacion // que se generaron un dia anterior
            $sql= "SELECT fecha_resumen, ticket, external_id 
                from ce_resumen 
                where ( idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." ) 
                and (STR_TO_DATE(fecha_envio, '%d/%m/%Y') >= (curdate() - INTERVAL $dias_consulta DAY)) and estado_sunat=0 and estado=0 order by idce_resumen asc";
            $bd->xConsulta($sql);
            break;        
        // case '4' : // guardar cpe y obtener correlativo
        //     $x_array_comprobante = $_POST['p_comprobante'];
        //     $correlativo_comprobante = 0; 
        //     $idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
        //     if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

        
        //         $sql_doc_correlativo="select (correlativo + 1) as d1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;		
        //         $correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);

        //         // guardamos el correlativo
        //         $sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
        //         $bd->xConsulta_NoReturn($sql_doc_correlativo);
        //     } else {
        //         $correlativo_comprobante='0';
        //     }            


        //     print $correlativo_comprobante;
        //     break;
        case '5': // optiene las impresoras habilitadas para seleccionar donde se imprime el comprobante electronico
            $sql="SELECT * FROM impresora WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado=0";
            $bd->xConsulta($sql);
            break;
        case '6': // cpe-emitidos
            $pagination = $_POST['pagination'];
            $fecha = $pagination['pageFecha'];
            $filtroAplicarFecha = $fecha === '' ? '' : "(MONTH(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = MONTH(STR_TO_DATE('$fecha' ,'%d/%m/%Y')) and YEAR(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = YEAR(STR_TO_DATE('$fecha' ,'%d/%m/%Y')))";
            $filtroFecha = $fecha === '' ? '' : " HAVING $filtroAplicarFecha";
            // $filtroFechaCount = $fecha === '' ? '' : " and (c.fecha = '".$fecha."')";
            $filtroFechaCount = $fecha === '' ? '' : " and $filtroAplicarFecha";
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.hora,tp.descripcion,c.numero,c.nomcliente,c.fecha,(
                if (c.anulado=1,'Anulado',
						CASE
							WHEN c.estado_api = 0 and c.estado_sunat = 0 THEN 'Aceptado'
							WHEN (c.estado_api = 1 and c.estado_sunat != 0) THEN 'Sin registrar'
							WHEN (tp.codsunat='03' and  c.estado_api = 0 and c.estado_sunat != 0) THEN 'Boleta registrada'
							WHEN (tp.codsunat!='03' and c.estado_api = 0 and c.estado_sunat != 0) THEN 'Boleta no aceptada'
						END)
            )) LIKE '%".$pagination['pageFilter']."%' ";

            $sql="
                SELECT tp.descripcion as nom_comprobante, tp.codsunat, cnc.numero nota_credito , c.* from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
                    left join ce_nota_credito cnc on c.idce_nota_credito = cnc.idce_nota_credito
                where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].") ".$filtro." ".$filtroFecha." 
                ORDER BY c.idce desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
                      
                
            $sqlCount="
                SELECT count(c.idce) as d1 from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
                where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].") ".$filtro." ".$filtroFechaCount;            
            
            $rowCount = $bd->xDevolverUnDato($sqlCount);

            $rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
            break;
        case '601': // reporte export excel
            $pagination = $_POST['pagination'];
            $fecha = $pagination['pageFecha'];
            $filtroAplicarFecha = $fecha === '' ? '' : "(MONTH(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = MONTH(STR_TO_DATE('$fecha' ,'%d/%m/%Y')) and YEAR(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = YEAR(STR_TO_DATE('$fecha' ,'%d/%m/%Y')))";
            $filtroFecha = $fecha === '' ? '' : " HAVING $filtroAplicarFecha";            
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.hora,tp.descripcion,c.numero,c.nomcliente,c.fecha,(
                if (c.anulado=1,'Anulado',
						CASE
							WHEN c.estado_api = 0 and c.estado_sunat = 0 THEN 'Aceptado'
							WHEN (c.estado_api = 1 and c.estado_sunat != 0) THEN 'Sin registrar'
							WHEN (tp.codsunat='03' and  c.estado_api = 0 and c.estado_sunat != 0) THEN 'Boleta registrada'
							WHEN (tp.codsunat!='03' and c.estado_api = 0 and c.estado_sunat != 0) THEN 'Boleta no aceptada'
						END)
            )) LIKE '%".$pagination['pageFilter']."%' ";

            $sql="
                SELECT ifnull(clie.ruc,'') as ruc, tp.descripcion as nom_comprobante, tp.codsunat , c.* from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
                    left join cliente as clie on c.nomcliente=clie.nombres
                where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].") ".$filtro." 
                GROUP by c.idce
                ".$filtroFecha."
                ORDER BY c.idce desc";
            
            $bd->xConsulta($sql);
            break;
        
        case '602': // resumen de comprobantes emitidos totales
            $fecha = $_POST['fecha_mes'];
            if ($fecha === '') {
                $fecha = date('d/m/Y');
            }
            $filtroAplicarFecha = $fecha === '' ? '' : " and (MONTH(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = MONTH(STR_TO_DATE('$fecha' ,'%d/%m/%Y')) and YEAR(STR_TO_DATE(c.fecha ,'%d/%m/%Y')) = YEAR(STR_TO_DATE('$fecha' ,'%d/%m/%Y')))";
            $sql="SELECT tp.descripcion as nom_comprobante, count(c.idtipo_comprobante_serie) cantidad, format(SUM(total),2) total from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
                where (c.idsede=".$_SESSION['idsede'].") $filtroAplicarFecha                
                group by c.idtipo_comprobante_serie";
            $bd->xConsulta($sql);
            break;
        
        case '7': // resumen de boletas
            $pagination = $_POST['pagination'];
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(cr.fecha_envio,ticket,external_id,msj) like '%".$pagination['pageFilter']."%'";
            // $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(u.usuario,cr.fecha_envio,ticket,external_id,msj) like '%".$pagination['pageFilter']."%'";
            
            // $sql="
            //     SELECT u.nombres, u.usuario, cr.* 
            //     from ce_resumen as cr
            //         inner join usuario as u on u.idusuario=cr.idusuario
            //     where (cr.idorg=".$_SESSION['ido']." and cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0 order by cr.idce_resumen desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];

            // $sqlCount = "
            //     SELECT count(cr.idce_resumen) as d1
            //     from ce_resumen as cr
            //         inner join usuario as u on u.idusuario=cr.idusuario
            //     where (cr.idorg=".$_SESSION['ido']." and cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0
            //     ";

                $sql="
                SELECT cr.* 
                from ce_resumen as cr                    
                where (cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0 order by cr.idce_resumen desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];                
            
            
            $sqlCount = "
                SELECT count(cr.idce_resumen) as d1
                from ce_resumen as cr            
                where (cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0
                ";

            $rowCount = $bd->xDevolverUnDato($sqlCount);

            $rpt = $bd->xConsulta($sql);            
            print $rpt."**".$rowCount;
            
            break;
        case '8': // anulaciones
            $obj = $_POST['data'];
            $sql="update ce set anulado=1, external_id_anulacion='".$obj['external_id_anulacion']."', ticket_anulacion='".$obj['ticket']."', motivo_anulacion='".$obj['motivo']."' where external_id='".$obj['external_id']."'";
            $bd->xConsulta($sql);
            break;
        
        // regularizar comprobantes no enviados hace 2 dias
        case '9':
            break;

        // registrar nota de credito
        case '10':            
            $arrItem = json_encode($_POST['data']);
            $sql = "CALL procedure_cpe_registro_nc(".$g_idsede.",".$g_idusuario.",'".$arrItem."')";
            $bd->xConsulta($sql);
            break;
        case '1001': // load notas de credito
            $sql="select u.usuario, c.numero comprobante, cn.* from ce_nota_credito cn
            inner join ce c on c.idce = cn.idce 
            inner join usuario u on u.idusuario = cn.idusuario 
            where cn.idsede = ".$g_idsede."
            order by cn.idce_nota_credito desc";
            $bd->xConsulta($sql);
            break;
            
        case '11': // guarda ingreso varios        
            $arrItem = json_encode($_POST['data']);    
            $sql="call procedure_registra_ingreso_varios(".$g_idsede.",".$g_idusuario.",'".$arrItem."')";
            $bd->xConsulta($sql);
            break;

        case '12': // guarda num comprobante en json delivery
            $id = $_POST['id'];
            $num = $_POST['num_comprobante'];
            $sql = "update pedido set json_datos_delivery = JSON_SET(json_datos_delivery, '$.p_header.num_comprobante', '$num') where idpedido = $id";
            $bd->xConsulta($sql);
        default:
            # code...
            break;
    }
    
?>