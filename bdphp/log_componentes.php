<?php
    //log registrar peidod y pago
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

    $g_ido = isset($_SESSION['ido']) ? $_SESSION['ido'] : 0;
    $g_idsede = isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0;
    
    switch($_GET['op'])
    {
        case 1:// load sedes
            $sql = "select idorg, idsede, nombre, ciudad from sede where idorg = $g_ido and estado=0 ";
            $bd->xConsulta($sql);
            break;
        case 2:// load comprobantes generales
            // $sql = "SELECT * FROM tipo_comprobante where estado=0";
            $sql = "
            SELECT tpcs.idtipo_comprobante_serie, tpcs.serie, tpcs.correlativo, tpcs.facturacion_correlativo_api, tp.*
            from tipo_comprobante_serie tpcs
                inner join tipo_comprobante tp using(idtipo_comprobante)
                inner join sede s on s.idsede = tpcs.idsede
            where (tpcs.idorg=$g_ido and tpcs.idsede=$g_idsede) and tpcs.estado=0
            ";            
            $bd->xConsulta($sql);
            break;
        case '201':// solo de tipo_comprobantes
            $sql="SELECT * from tipo_comprobante where estado=0";
            $bd->xConsulta($sql);
            break;
        case 3:// load categoria
            $sql="SELECT idcategoria, descripcion FROM categoria WHERE (idorg=$g_ido AND idsede=$g_idsede) AND estado=0";
            $bd->xConsulta($sql);
            break;
        case 4:// load tipo de gasto
            $sql="SELECT idtipo_gasto_detalle, descripcion FROM tipo_gasto_detalle WHERE idtipo_gasto=".$_POST['id']." and estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        case 5:// load tipo de ingreos
            $sql="SELECT idtipo_ingreso, descripcion FROM tipo_ingreso WHERE estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        case 6:// load tipo pago
            $sql="SELECT * FROM tipo_pago WHERE estado=0 and idtipo_pago != 4";
            $bd->xConsulta($sql);
            break;
        case 601:// load tipo pago from app
            $sql="SELECT * FROM tipo_pago WHERE estado=0 and idtipo_pago = 4";
            $bd->xConsulta($sql);
            break;
        case 602:// load tipo pago from app todos
            $sql="SELECT * FROM tipo_pago WHERE estado=0 order by orden";
            $bd->xConsulta($sql);
            break;
        case 7://load clientes
            // $sql="SELECT * FROM cliente where (idorg=$g_ido) AND estado=0 order by nombres";
            // $sql = "select c.* from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede and nombres != '' order by nombres";
            $sql = "select c.idcliente, c.idorg, c.nombres, c.f_nac, c.ruc, c.direccion, c.telefono, c.credito, c.estado, c.pwa_id, c.email, c.calificacion, c.dni_num_verificador, c.direccion_delivery_no_map, c.referencia from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede and nombres != '' order by nombres";
            $bd->xConsulta($sql);
            break;
        case 701://load clientes - input autocomplete
            // $sql="SELECT * FROM cliente where (idorg=$g_ido) AND estado=0 order by nombres";
            // $sql = "select c.* from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede and nombres != '' order by nombres";
            $sql = "select c.idcliente, c.idorg, c.nombres, c.f_nac, c.ruc, c.direccion, c.telefono, c.credito, c.estado, c.pwa_id, c.email, c.calificacion, c.dni_num_verificador, c.direccion_delivery_no_map, c.referencia from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede and nombres != '' order by nombres";
            $bd->xConsulta($sql);
            break;
        case 70101://load clientes - input autocomplete search val input            
            $inputValue = $_POST['val'];
            $sql = "select c.idcliente, c.idorg, c.nombres, c.f_nac, c.ruc, c.direccion, c.telefono, c.credito, c.estado, c.pwa_id, c.email, c.calificacion, c.dni_num_verificador, c.direccion_delivery_no_map, c.referencia from cliente_sede cs inner join cliente c on cs.idcliente = c.idcliente where cs.idsede = $g_idsede and nombres like '%$inputValue%' order by nombres";
            $bd->xConsulta($sql);
            break;
        case 8://load cargos
            $sql="SELECT * FROM cargo where (idorg=$g_ido) AND estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        case 9://load colaboradores
            $sql="SELECT * FROM colaborador where (idorg=$g_ido and idsede=$g_idsede) AND estado=0 order by nombres";
            $bd->xConsulta($sql);
            break;
        case 10://load planilla_periodo
            $sql="SELECT * FROM planilla_periodo where estado=0";
            $bd->xConsulta($sql);
            break;
        case 11: //almacenes
            $sql="SELECT * FROM almacen where (idorg=$g_ido and idsede=$g_idsede) and estado=0";
            $bd->xConsulta($sql);
            break;
        case 12:// load direccion cliente
            $idcliente = $_POST['idcliente'];
            $sql = "SELECT cpd.* from cliente_pwa_direccion cpd where cpd.idcliente =".$idcliente." and cpd.estado=0";
            $bd->xConsulta($sql);
            break;
        case 13: // lista de notificaciones inicio
            $sql = "call procedure_list_notificaciones_restobar($g_idsede)";
            $bd->xConsulta($sql);
            break;
        case 1301: // check calificaciones vistas
            $sql = "update sede_calificacion set notificado=1 where idsede = $g_idsede and notificado = 0";
            $bd->xConsulta($sql);
            break;
        case 14: // lista de tipos de descuento
            $sql = "select * from tipo_descuento where estado=0";
            $bd->xConsulta($sql);
            break;
        case 15: // notificacion de cambios
            if ( !empty($_SESSION['idusuario']) ) {
                $sql = "select COALESCE(last_notificacion_change_sys, 0) as d1 from usuario where idusuario = ".$_SESSION['idusuario'];
                $fechaUs = $bd->xDevolverUnDato($sql);
    
                $sql = "SELECT * from notificacion_cambios_sistema where fecha >= '".$fechaUs."' and estado = 0  order by idnotificacion_cambios_sistema desc";
                $bd->xConsulta($sql);
            }

            break;
        case 1501: // guarda fecha de notificacion
            $sql = "update usuario set last_notificacion_change_sys = curdate() where idusuario = ".$_SESSION['idusuario'];
            $bd->xConsulta($sql);
            break;
        
        case 16: // componente alert pago servicio
            $sql = "update usuario set last_notificacion_change_sys = curdate() where idusuario = ".$_SESSION['idusuario'];
            $bd->xConsulta($sql);
            break;    
            
        case 17: // select planilla 
            $sql = "select * from sede_plan_contratado where estado = 0";
            $bd->xConsulta($sql);
            break; 

        case 18: // canal de consumo vr
            $sql = "select tc.*, tci.img from tipo_consumo tc 
            left join tipo_cosnumo_img tci on POSITION(UPPER(tci.descripcion) in UPPER(tc.descripcion)) > 0
            where idsede = $g_idsede and tc.estado = 0
            order by tc.idtipo_consumo";
            $bd->xConsulta($sql);
            break; 
        
        case 19: //load productos y alamacen
            $seacrh = $_POST["search"];
            $sql="
                SELECT ps.idproducto_stock as value, concat(a.descripcion, ' | ', p.descripcion) as label 
                FROM producto AS p
                    inner join producto_stock ps on ps.idproducto = p.idproducto 
                    inner join almacen a on a.idalmacen = ps.idalmacen
                WHERE (p.idsede=".$g_idsede.") AND p.estado=0 AND p.descripcion like '%$seacrh%'
            ";
            $bd->xConsulta($sql);
            break;

        case 1901: // guarda los productos relacionados
            $data = $_POST['data'];
            $sql="insert into item_producto_relacionado (idtipo_consumo, iditem, idproducto_stock, cantidad) 
                        values (".$data['idtipo_consumo'].", ".$data['iditem'].", ".$data['idproducto_stock'].", ". $data['cantidad'].")";
            echo $bd->xConsulta_UltimoId($sql);
            break;
        
        case 1902: // load productos relacionados
            $id = $_POST['id'];
            $sql = "select ipr.iditem_producto_relacionado, tc.descripcion des_tipo_consumo, p.descripcion des_producto, cantidad from item_producto_relacionado ipr 
            inner join tipo_consumo tc using(idtipo_consumo)
            inner join producto_stock ps using(idproducto_stock)
            inner join producto p using(idproducto)
            where ipr.iditem = ".$id."  and ipr.estado = 0";
            $bd->xConsulta($sql);
            break;
        
        case 20: //load productos filtro almacen
            $seacrh = $_POST["search"];
            $idAlmacenFiltro = $_POST["idfiltro"];
            $_filtro = '';
            if ( $idAlmacenFiltro !== '0' ) {
                $_filtro = " and a.idalmacen = ".$idAlmacenFiltro;
            }


            $sql="
                SELECT ps.idproducto_stock as value, concat(a.descripcion, ' | ', pf.descripcion , ' | ', p.descripcion) as label 
                    ,ps.stock, ps.idproducto
                FROM producto AS p
                    inner join producto_stock ps on ps.idproducto = p.idproducto 
                    inner join almacen a on a.idalmacen = ps.idalmacen
                    inner join producto_familia pf on pf.idproducto_familia = p.idproducto_familia 
                WHERE (p.idsede=".$g_idsede.") AND p.estado=0 $_filtro AND p.descripcion like '%$seacrh%'
            ";
            $bd->xConsulta($sql);
            break;

        case 2001: // buscar item carta_lista
            $seacrh = $_POST["search"];
            $sql = "
            SELECT cl.idcarta_lista value, concat(s.descripcion,' | ',i.descripcion, COALESCE(subIt.des, '')) label, cl.cantidad stock, cl.iditem, format(cl.precio + COALESCE(subIt.precio,0), 2) precio, subIt.iditem_subitem 
            , '0' procede, s.idimpresora
            FROM carta_lista cl 
            inner join item i on cl.iditem = i.iditem 
            inner join seccion s on cl.idseccion = s.idseccion 
            left join (select itcd.iditem,  isub.iditem_subitem, concat(' - ', isub.descripcion) des , isub.precio
                from item_subitem_content ic        
                    inner join item_subitem isub on isub.iditem_subitem_content = ic.iditem_subitem_content
                    inner join item_subitem_content_detalle itcd on ic.iditem_subitem_content = itcd.iditem_subitem_content and itcd.estado=0 -- and itcd.iditem = ic.iditem
                where itcd.controlable = 1 and ic.estado=0) subIt on subIt.iditem = cl.iditem 
            where s.idsede = ".$g_idsede." AND i.descripcion like '%$seacrh%'
            UNION all
            SELECT ps.idproducto_stock as value, concat(a.descripcion, ' | ', pf.descripcion , ' | ', p.descripcion) as label 
                                ,ps.stock, ps.idproducto_stock iditem, p.precio_venta precio, null iditem_subitem, '1' procede, pf.idimpresora
                            FROM producto AS p
                                inner join producto_stock ps on ps.idproducto = p.idproducto 
                                inner join almacen a on a.idalmacen = ps.idalmacen
                                inner join producto_familia pf on pf.idproducto_familia = p.idproducto_familia 
                            WHERE (p.idsede=".$g_idsede.") AND p.estado=0 and a.bodega = 1 AND p.descripcion like '%$seacrh%'
            ";
            $bd->xConsulta($sql);
            break;
        
        case 2002: // buscar secciones
            $sql="select * from seccion where idsede=$g_idsede and estado=0 order by descripcion";
            $bd->xConsulta($sql);
            break;
        
        case 21: // componente meta
            $sql = "call procedure_get_meta($g_idsede)";
            $bd->xConsulta($sql);
            break;
    }