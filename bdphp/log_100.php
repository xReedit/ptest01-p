<?php

    //040622
    // corrige la opcion log.php op=100 // debe borrarse
    //     

    session_start();	
	header('content-type: text/html; charset: utf-8');
	header('Content-Type: text/event-stream');
	header('Cache-Control: no-cache');
	include "ManejoBD.php";
	$bd=new xManejoBD("restobar");

	date_default_timezone_set('America/Lima');

	$op = $_GET['op'];
	
	$g_ido = $_SESSION['ido'];
	$g_idsede = $_SESSION['idsede'];
	$g_us = $_SESSION['idusuario'];

    switch ($op) {
        case '1': // compras
            $sql = $_POST['xsql'];
            $sqlArray = explode(";", $sql);
            $buildSql = '';
            // compra_items 
            $sql_compra_items = "insert into compra_items (idcompra, idproducto, cantidad, punitario, ptotal) values ";
            $isFindPart = xSearchPartSql($sql_compra_items, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_compra_items, $isFindPart, 'values').$buildSql;
            }


            $sql_compra_tp = "insert into compra_pago (idcompra, idtipo_pago, importe) values ";
            $isFindPart = xSearchPartSql($sql_compra_tp, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_compra_tp, $isFindPart, 'values').$buildSql;
            }

            $sql_historial_precio_pro = "insert into producto_historial_precio (idproducto, fecha, idorg, idsede, precio, idproveedor) values ";
            $isFindPart = xSearchPartSql($sql_historial_precio_pro, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_historial_precio_pro, $isFindPart, 'values').$buildSql;
            }


            $sql_update_pro = "Update producto set precio_unitario";
            foreach ($sqlArray as $partSql) {
                $partSqlArray = [];
                $partSqlArray[] = $partSql;
                $isFindPart = xSearchPartSql($sql_update_pro, $partSqlArray);
                if ( $isFindPart != 'false' ) {
                    $buildSql = xBuildSqlUpdate($sql_update_pro, $isFindPart, 'precio_unitario=').$buildSql;
                }
            }

            // echo $buildSql;
            if ( $buildSql!= '' ) {
                $bd->xMultiConsulta($buildSql);
            }
            break;

        case '2': // PORCIONES
            $sql = $_POST['xsql'];
            $sqlArray = explode(";", $sql);
            $buildSql = '';
            // compra_items 
            $sql_insert = "insert into porcion (descripcion, peso, stock, idorg, idsede, idproducto_de) values ";            
            $isFindPart = xSearchPartSql($sql_insert, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_insert, $isFindPart, 'values').$buildSql;
            }

            $sql_update = "Update porcion set descripcion";
            foreach ($sqlArray as $partSql) {
                $partSqlArray = [];
                $partSqlArray[] = $partSql;
                $isFindPart = xSearchPartSql($sql_update, $partSqlArray);
                if ( $isFindPart != 'false' ) {
                    $buildSql = xBuildSqlUpdate($sql_update, $isFindPart, 'descripcion=').$buildSql;
                }
            }

            // echo $buildSql;

            if ( $buildSql!= '' ) {
                $bd->xMultiConsulta($buildSql);
            }
            break;

        
        case '3': // RECETAS
            $sql = $_POST['xsql'];
            $sqlArray = explode(";", $sql);
            $buildSql = '';            

            $sql_search = "insert into item_ingrediente (descripcion, cantidad, costo, iditem, idporcion, idproducto_stock, viene_de, necesario) values ";            
            $isFindPart = xSearchPartSql($sql_search, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_search, $isFindPart, 'values').$buildSql;
            }

            $sql_search = "delete from item_ingrediente where ";            
            $isFindPart = xSearchPartSql($sql_search, $sqlArray);
            if ( $isFindPart != 'false' ) {
                $buildSql = xBuildSql($sql_search, $isFindPart, 'where').$buildSql;
            } else {
                $sql_search = "delete where ";            
                $isFindPart = xSearchPartSql($sql_search, $sqlArray);
                if ( $isFindPart != 'false' ) {
                    $sql_search = "delete from item_ingrediente where ";            
                    $buildSql = xBuildSql($sql_search, $isFindPart, 'where').$buildSql;
                } else {
                    // 290723
                    $sql_search = "modify where";            
                    $isFindPart = xSearchPartSql($sql_search, $sqlArray);
                    if ( $isFindPart != 'false' ) {
                        $sql_search = "delete from item_ingrediente where ";            
                        $buildSql = xBuildSql($sql_search, $isFindPart, 'where').$buildSql;
                    }
                }
            }       
                        

            if ( $buildSql!= '' ) {
                $bd->xMultiConsulta($buildSql);
            }
            break;

    }


    function xSearchPartSql($findPart, $sqlArray) {
        $_rpt = 'false';
        foreach ($sqlArray as $part) {
            $pos = strpos($part, $findPart);
            if ($pos !== false) {
                $_rpt = $part;
                break;
            }
        }

        return $_rpt;
    }

    function xBuildSql($sqlEnca, $part, $divisor) {
        $_values = explode($divisor, $part);
        $_values = $_values[1];        
        return $sqlEnca.$_values."; ";
    }

    function xBuildSqlUpdate($sqlEnca, $part, $divisor) {
        $_values = explode($divisor, $part);
        $_values = $_values[1];        
        return $sqlEnca."=".$_values."; ";
    }

?>