<?php
    //log comprobantes electronicos
    // session_set_cookie_params('4000'); // 1 hour
    // session_regenerate_id(true); 
    session_start();	
	//header("Cache-Control: no-cache,no-store");
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');
	
    $op = $_POST['op'];

    switch ($op) {
        case '1': //registrar envio cpe 
            $obj = $_POST['data'];

            $ce_anulado = array_key_exists('anulado', $obj) ? $obj['anulado'] : 0; 
            $ce_totales_json = array_key_exists('totales_json', $obj) ? $obj['totales_json'] : ''; 
            
            $sqlCpe = "
                insert into ce (external_id, idorg, idsede, idusuario, idtipo_comprobante_serie, numero, fecha, hora, json_xml, estado_api, estado_sunat, viene_facturador, msj, anulado, idcliente, nomcliente, total, pdf, xml, cdr, totales_json)
                values ('".$obj['external_id']."',".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$obj['idtipo_comprobante_serie'].",
                '".$obj['numero']."', DATE_FORMAT(now(),'%d/%m/%Y'), DATE_FORMAT(now(),'%H:%i:%s'), '".$obj['jsonxml']."', ".$obj['estado_api'].",".$obj['estado_sunat'].",".$obj['viene_facturador'].",'".$obj['msj']."',".$ce_anulado.",".$obj['idcliente'].",'".$obj['nomcliente']."','".$obj['total']."',".$obj['pdf'].",".$obj['xml'].",".$obj['cdr'].",'".$ce_totales_json."')";

            echo $sqlCpe;
            $idce = $bd->xConsulta_UltimoId($sqlCpe);
            
            // si el documento no es anulado // por validacion sunat
            if ( $ce_anulado === 0 ) {
                if (array_key_exists('idregistro_pago', $obj)) {
                    if ( $obj['viene_facturador'] === "1") {
                        $sqlRp= "update cpe_facturador set idce =".$idce ." where idcpe_facturador=".$obj['idregistro_pago'];
                        $bd->xConsulta_NoReturn($sqlRp);
                    } else {
                        $sqlRp= "update registro_pago set idce =".$idce ." where idregistro_pago=".$obj['idregistro_pago'];
                        $bd->xConsulta_NoReturn($sqlRp);
                    }
                }
            }

            break;
        case '101': // registra documentos que 
            $objRegistro = $_POST['data'];
            $sql = "update registro_pago_cpe set external_id_comprobante = '".$objRegistro['idexternal']."', registrado=".$objRegistro['enviado']." where idregistro_pago = ".$objRegistro['idregistro_pago'];
            $bd->xConsulta($sql);
            break;
        case '102': // registrar en cpe_facturador emitido desde facturador
            $objRegistro = $_POST['data'];
            $arrItems = $_POST['arrItems'];
            $sqlCpe="insert into cpe_facturador (idorg, idsede, idusuario, idcliente, idcomprobante, num_comprobante, fecha, subtotal, igv, total) 
                    values (".$_SESSION['ido'].",".$_SESSION['idsede'].",".$_SESSION['idusuario'].",".$objRegistro['idcliente'].",'".$objRegistro['idcomprobante']."',
                    '".$objRegistro['num_comprobante']."',now(),'".$objRegistro['jsonsubtotalXml']."','".$objRegistro['igv']."','".$objRegistro['total']."')";
            
            // echo $sqlCpe;
            $idcpe_facturador = $bd->xConsulta_UltimoId($sqlCpe);

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
        case '2': // actualiza el estado de comprabantes reenviados (desde cierre caja): si fue aceptada = 1 o fue anulada = 1
            $obj = $_POST['data'];
            $ce_anulado = array_key_exists('anulado', $obj) ? $obj['anulado'] : 0; 

            $sql = "
            update ce 
                set estado_api=".$obj['estado_api'].", 
                estado_sunat=".$obj['estado_sunat'].", 
                msj='".$obj['msj']."', 
                external_id='".$obj['external_id']."',
                numero='".$obj['numero']."',
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
            echo $sql;
            $bd->xConsulta($sql);

            // actualizar boletas estado_sunat = 0 -> aceptadas
            if ( $estado_sunat === "1" ) {
                $sql_bl = "
                    UPDATE ce as c
                        inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie = c.idtipo_comprobante_serie
                        inner join tipo_comprobante as tp on tp.idtipo_comprobante = tps.idtipo_comprobante
                    set c.estado_sunat=0
                    where (c.idorg=1 and c.idsede=1) and c.fecha = '".$obj['fecha_resumen']."' and tp.codsunat='03' and c.anulado=0";

                echo $sql_bl;
                $bd->xConsulta_NoReturn($sql_bl);
            }
            break;
        case '3': // consulta de boletas que fueron registradas pero no aceptadas(por cualquier motivo), devuelve fechas no aceptadas
            $sql = "SELECT * from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado_api=0 and estado_sunat = 1 and anulado=0";            
            $bd->xConsulta($sql);
            break;
        case '301': // lista documentos no registrados - documnentos que no fueron enviados al servicio api por algun error de conexion
            $sql = "SELECT * from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado_api = 1 and anulado=0";
            $bd->xConsulta($sql);
            break;
        case '302':// resumen de boletas: consulta fecha de boletas no enviadas 
            $sql="SELECT fecha from ce where (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado_sunat=1 and (estado=0 and anulado=0) GROUP BY fecha";
            $bd->xConsulta($sql);
            break;
        case '303': // lista de tickets de resumen boleta por confitmar aceptacion // que se generaron un dia anterior
            $sql= "SELECT fecha_resumen, ticket, external_id 
                from ce_resumen 
                where ( idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede']." ) 
                and (fecha_envio = DATE_FORMAT(now(),'%d/%m/%Y')) and estado_sunat=0 and estado=0";
            $bd->xConsulta($sql);
            break;        
        case '4' : // guardar cpe y obtener correlativo
            $x_array_comprobante = $_POST['p_comprobante'];
            $correlativo_comprobante = 0; 
            $idtipo_comprobante_serie = $x_array_comprobante['idtipo_comprobante_serie'];
            if ($x_array_comprobante['idtipo_comprobante'] != "0"){ // 0 = ninguno | no imprimir comprobante

        
                $sql_doc_correlativo="select (correlativo + 1) as d1  from tipo_comprobante_serie where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;		
                $correlativo_comprobante = $bd->xDevolverUnDato($sql_doc_correlativo);

                // guardamos el correlativo
                $sql_doc_correlativo = "update tipo_comprobante_serie set correlativo = ".$correlativo_comprobante." where idtipo_comprobante_serie = ".$idtipo_comprobante_serie;
                $bd->xConsulta_NoReturn($sql_doc_correlativo);
            } else {
                $correlativo_comprobante='0';
            }            


            print $correlativo_comprobante;
            break;
        case '5': // optiene las impresoras habilitadas para seleccionar donde se imprime el comprobante electronico
            $sql="SELECT * FROM impresora WHERE (idorg=".$_SESSION['ido']." and idsede=".$_SESSION['idsede'].") and estado=0";
            $bd->xConsulta($sql);
            break;
        case '6': // cpe-emitidos
            $pagination = $_POST['pagination'];
            $fecha = $pagination['pageFecha'];
            $filtroFecha = $fecha === '' ? '' : " HAVING c.fecha = '".$fecha."'";
            $filtroFechaCount = $fecha === '' ? '' : " and (c.fecha = '".$fecha."')";
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.hora,tp.descripcion,c.numero,c.nomcliente,c.fecha,(
                if (c.anulado=1,'Anulado',
						CASE
							WHEN c.estado_api = 0 and c.estado_sunat = 0 THEN 'Aceptado'
							WHEN (c.estado_api = 1 and c.estado_sunat = 1) THEN 'Sin registrar'
							WHEN (tp.codsunat='03' and  c.estado_api = 0 and c.estado_sunat = 1) THEN 'Boleta registrada'
							WHEN (tp.codsunat!='03' and c.estado_api = 0 and c.estado_sunat = 1) THEN 'Boleta no aceptada'
						END)
            )) LIKE '%".$pagination['pageFilter']."%' ";

            $sql="
                SELECT tp.descripcion as nom_comprobante, tp.codsunat , c.* from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
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
            $filtroFecha = $fecha === '' ? '' : " HAVING c.fecha = '".$fecha."'";            
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(c.hora,tp.descripcion,c.numero,c.nomcliente,c.fecha,(
                if (c.anulado=1,'Anulado',
						CASE
							WHEN c.estado_api = 0 and c.estado_sunat = 0 THEN 'Aceptado'
							WHEN (c.estado_api = 1 and c.estado_sunat = 1) THEN 'Sin registrar'
							WHEN (tp.codsunat='03' and  c.estado_api = 0 and c.estado_sunat = 1) THEN 'Boleta registrada'
							WHEN (tp.codsunat!='03' and c.estado_api = 0 and c.estado_sunat = 1) THEN 'Boleta no aceptada'
						END)
            )) LIKE '%".$pagination['pageFilter']."%' ";

            $sql="
                SELECT tp.descripcion as nom_comprobante, tp.codsunat , c.* from ce as c
                    inner join tipo_comprobante_serie as tps on tps.idtipo_comprobante_serie=c.idtipo_comprobante_serie
                    inner join tipo_comprobante as tp on tp.idtipo_comprobante=tps.idtipo_comprobante	
                where (c.idorg=".$_SESSION['ido']." and c.idsede=".$_SESSION['idsede'].") ".$filtro." ".$filtroFecha." 
                ORDER BY c.idce desc";
            
            $bd->xConsulta($sql);
            break;
        case '7': // resumen de boletas
            $pagination = $_POST['pagination'];
            $filtro = $pagination['pageFilter'] === '' ? '' : " and CONCAT(u.usuario,cr.fecha_envio,ticket,external_id,msj) like '%".$pagination['pageFilter']."%'";
            $sql="
                SELECT u.nombres, u.usuario, cr.* 
                from ce_resumen as cr
                    inner join usuario as u on u.idusuario=cr.idusuario
                where (cr.idorg=".$_SESSION['ido']." and cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0 order by cr.idce_resumen desc limit ".$pagination['pageLimit']." OFFSET ".$pagination['pageDesde'];
            
            
            $sqlCount = "
                SELECT count(cr.idce_resumen) as d1
                from ce_resumen as cr
                    inner join usuario as u on u.idusuario=cr.idusuario
                where (cr.idorg=".$_SESSION['ido']." and cr.idsede=".$_SESSION['idsede'].")".$filtro ." and cr.estado=0
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
        default:
            # code...
            break;
    }
    
?>