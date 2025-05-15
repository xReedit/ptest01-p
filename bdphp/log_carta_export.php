<?php
    session_start();
    header('Content-Type: application/json;charset=utf-8');
    header('content-type: text/html; charset: utf-8');
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    include "ManejoBD.php";
    $bd = new xManejoBD("restobar");

    $op = $_GET['op'];
    if (!isset($op)) { 
        $op = $_POST['op'];
    }
    if (!isset($op)) {
        $postBody = json_decode(file_get_contents('php://input'));
        $op = $postBody->op;
    }

    $g_ido = isset($_SESSION['ido']) ? $_SESSION['ido'] : 0;
    $g_idsede = isset($_SESSION['idsede']) ? $_SESSION['idsede'] : 0;
    $g_us = isset($_SESSION['idusuario']) ? $_SESSION['idusuario'] : 0;

    switch ($op) {
        case 'exportar-carta':
            // Obtener el ID de la carta a exportar
            $idcategoria= $_POST['idcategoria'];

            // Verificar que la carta pertenezca a la sede actual
            $sql_check = "SELECT idcarta FROM carta WHERE idcategoria = $idcategoria AND idsede = $g_idsede and estado=0";
            $carta_existe = $bd->xDevolverUnDato($sql_check);
            
            if (!$carta_existe) {
                echo json_encode(array('success' => false, 'mensaje' => 'La carta no existe o no pertenece a esta sede', 'categorias' => $categorias));
                exit;
            }
            
            // Obtener información de la carta
            $sql_carta = "SELECT idcarta, idorg, idsede, idcategoria, fecha, estado FROM carta WHERE idcarta = $carta_existe";
            $result_carta = $bd->xConsulta3($sql_carta);
            $carta = json_decode($result_carta, true);
            
            if (empty($carta)) {
                echo json_encode(array('success' => false, 'mensaje' => 'No se pudo obtener la información de la carta'));
                exit;
            }
            
            // Obtener secciones de la carta
            $sql_secciones = "
                SELECT DISTINCT s.idseccion, s.descripcion, s.idimpresora, s.sec_orden, s.img, s.imprimir
                FROM seccion AS s
                INNER JOIN carta_lista AS cl ON s.idseccion = cl.idseccion
                WHERE cl.idcarta = $carta_existe AND s.estado = 0
                ORDER BY s.sec_orden
            ";
            $result_secciones = $bd->xConsulta3($sql_secciones);
            $secciones = json_decode($result_secciones, true);
            
            if (empty($secciones)) {
                echo json_encode(array('success' => false, 'mensaje' => 'La carta no tiene secciones'));
                exit;
            }
            
            // Para cada sección, obtener sus items
            foreach ($secciones as &$seccion) {
                $idseccion = $seccion['idseccion'];
                
                $sql_items = "
                    SELECT cl.idcarta_lista, cl.iditem, i.descripcion, cl.precio, cl.cantidad, 
                           cl.cant_preparado, cl.sec_orden, i.detalle, i.img, 
                           i.is_recomendacion, i.is_visible_cliente
                    FROM carta_lista AS cl
                    INNER JOIN item AS i ON cl.iditem = i.iditem
                    WHERE cl.idcarta = $carta_existe AND cl.idseccion = $idseccion
                    ORDER BY cl.sec_orden
                ";
                $result_items = $bd->xConsulta3($sql_items);                
                $seccion['items'] = json_decode($result_items, true);
            }
            
            // Preparar datos para exportar
            $datos_exportar = array(
                'carta' => $carta[0],
                'secciones' => $secciones                
            );
            
            echo json_encode(array('success' => true, 'datos' => $datos_exportar));
            break;
            
        case 'importar-carta':
            // Obtener los datos a importar
            $data = json_decode($_POST['datos'], true);
            $idcategoria = $data['idcategoria'];
            $datos = $data['data_import'];
            
            if (!$datos || !isset($datos['secciones'])) {
                echo json_encode(array('success' => false, 'mensaje' => 'Formato de datos inválido'));
                exit;
            }
            
            // Iniciar transacción
            // $bd->xConsulta_NoReturn("START TRANSACTION");
            
            try {
                // Crear nueva carta
                $fecha_actual = date("d/m/Y");                

                
                // get idcarta from carta
                $sql_idcarta = "select idcarta from carta where idcategoria = $idcategoria and idsede = $g_idsede";
                $idcarta = $bd->xDevolverUnDato($sql_idcarta);

                if (!$idcarta) {
                    // si la carta no existe la creamos                    
                    // throw new Exception("No se pudo obtener el idcarta");                    
                    $sql_nueva_carta = "
                        INSERT INTO carta (idorg, idsede, idcategoria, fecha, estado)
                        VALUES ($g_ido, $g_idsede, $idcategoria, '$fecha_actual', 0)
                    ";
                    $bd->xConsulta_NoReturn($sql_nueva_carta);                    
                    $idcarta = $bd->xDevolverUnDato("SELECT idcarta FROM carta WHERE idcategoria = $idcategoria AND idsede = $g_idsede AND fecha = '$fecha_actual'");
                } else {
                    $sql_carta = "update carta set fecha = '$fecha_actual' where idcarta = $idcarta";
                    $bd->xConsulta_NoReturn($sql_carta);
                }

                $idimpresora_guardar = '0';

                // Procesar cada sección y sus items
                foreach ($datos['secciones'] as $seccion) {
                    // Convertir descripción a mayúsculas para la búsqueda
                    $descripcion_seccion = mb_strtoupper($seccion['descripcion'], 'UTF-8');
                    
                    // Verificar si la sección ya existe
                    $sql_check_seccion = "
                        SELECT idseccion FROM seccion 
                        WHERE idsede = $g_idsede AND UPPER(descripcion) = '$descripcion_seccion'
                        AND estado = 0
                    ";
                    $id_seccion = $bd->xDevolverUnDato($sql_check_seccion);

                    $idimpresora_seccion = $seccion['idimpresora'];                    

                    if ( $idimpresora_guardar == '0' ) {
                        if ( $idimpresora_seccion == '0' ) {
                            // buscar impresora y crearla sino existe
                            $sql_check_impresora = "
                                SELECT idimpresora FROM impresora 
                                WHERE idsede = $g_idsede|
                                AND estado = 0 limit 1
                            ";
    
                            $id_impresora = $bd->xDevolverUnDato($sql_check_impresora);
                            if (!$id_impresora) {
                                $sql_nueva_impresora = "
                                    INSERT INTO impresora (idorg, idsede, descripcion, ip, estado)
                                    VALUES ($g_ido, $g_idsede, 'CAJA', '0', 0)
                                ";
                                $bd->xConsulta_NoReturn($sql_nueva_impresora);
                                $id_impresora = $bd->xDevolverUnDato("SELECT idimpresora FROM impresora WHERE idorg = $g_ido AND idsede = $g_idsede AND descripcion = '{$seccion['descripcion']}' AND estado = 0");
                            }
                            $idimpresora_seccion = $id_impresora;
                        }

                        $idimpresora_guardar = $idimpresora_seccion;
                    }

                    $seccion['idimpresora'] = $idimpresora_guardar;
                    
                    // Si no existe, crear la sección
                    if (!$id_seccion) {
                        // Convertir descripción a mayúsculas
                        $descripcion_seccion = mb_strtoupper($seccion['descripcion'], 'UTF-8');
                        
                        $sql_nueva_seccion = "
                            INSERT INTO seccion (idorg, idsede, descripcion, idimpresora, sec_orden, img, imprimir, estado)
                            VALUES ($g_ido, $g_idsede, '$descripcion_seccion', {$seccion['idimpresora']}, 
                                    {$seccion['sec_orden']}, '{$seccion['img']}', {$seccion['imprimir']}, 0)
                        ";
                        $bd->xConsulta_NoReturn($sql_nueva_seccion);
                        
                        // Obtener el ID de la nueva sección
                        $sql_id_seccion = "SELECT LAST_INSERT_ID() as id";
                        $result_id_seccion = $bd->xConsulta3($sql_id_seccion);
                        $id_seccion = json_decode($result_id_seccion, true)[0]['id'];
                        
                        if (!$id_seccion) {
                            throw new Exception("No se pudo crear la sección {$seccion['descripcion']}");
                        }
                    }
                    
                    // Procesar cada item de la sección
                    foreach ($seccion['items'] as $item) {
                        // Convertir descripción a mayúsculas para la búsqueda
                        $descripcion_item = mb_strtoupper($item['descripcion'], 'UTF-8');
                        
                        // Verificar si el item ya existe
                        $sql_check_item = "
                            SELECT iditem FROM item 
                            WHERE idorg = $g_ido AND idsede = $g_idsede AND UPPER(descripcion) = '$descripcion_item'
                            AND estado = 0
                        ";
                        $id_item = $bd->xDevolverUnDato($sql_check_item);
                        
                        // Si no existe, crear el item
                        if (!$id_item) {
                            // Convertir descripción a mayúsculas
                            $descripcion_item = mb_strtoupper($item['descripcion'], 'UTF-8');
                            
                            $detalle = isset($item['detalle']) ? "'{$item['detalle']}'" : "''";
                            $img = isset($item['img']) ? "'{$item['img']}'" : "''";
                            $is_recomendacion = isset($item['is_recomendacion']) ? $item['is_recomendacion'] : "0";
                            $is_visible_cliente = isset($item['is_visible_cliente']) ? $item['is_visible_cliente'] : "1";
                            
                            $sql_nuevo_item = "
                                INSERT INTO item (idorg, idsede, descripcion, precio, detalle, img, is_recomendacion, is_visible_cliente, estado)
                                VALUES ($g_ido, $g_idsede, '$descripcion_item', {$item['precio']}, $detalle, $img, $is_recomendacion, $is_visible_cliente, 0)
                            ";
                            $bd->xConsulta_NoReturn($sql_nuevo_item);
                            
                            // Obtener el ID del nuevo item
                            $sql_id_item = "SELECT LAST_INSERT_ID() as id";
                            $result_id_item = $bd->xConsulta3($sql_id_item);
                            $id_item = json_decode($result_id_item, true)[0]['id'];
                            
                            if (!$id_item) {
                                throw new Exception("No se pudo crear el item {$item['descripcion']}, sql: $sql_nuevo_item");
                            }
                        }
                        
                        // Verificar si ya existe el registro por iditem, idseccion y idcarta
                        $id_carta_lista = $g_ido . $g_idsede . $idcarta . $id_seccion . $id_item;

                        $sql_check_carta_lista = "
                            SELECT idcarta_lista FROM carta_lista 
                            WHERE idcarta_lista = '$id_carta_lista'
                        ";
                        $carta_lista_existe = $bd->xDevolverUnDato($sql_check_carta_lista);
                        
                        // Solo crear si no existe
                        if (!$carta_lista_existe) {
                            // Crear entrada en carta_lista
                            // Asegurar que existan los valores necesarios o usar valores por defecto
                            $item_cantidad = isset($item['cantidad']) ? $item['cantidad'] : 'ND';
                            $item_cant_preparado = isset($item['cant_preparado']) ? $item['cant_preparado'] : '0';
                            $item_sec_orden = isset($item['sec_orden']) ? $item['sec_orden'] : '0';
                            
                            $sql_carta_lista = "
                                INSERT INTO carta_lista (idcarta_lista, idcarta, idseccion, iditem, precio, cantidad, cant_preparado, sec_orden)
                                VALUES ('$id_carta_lista', $idcarta, $id_seccion, $id_item, '{$item['precio']}', 
                                        '$item_cantidad', '$item_cant_preparado', $item_sec_orden)
                            ";
                            $bd->xConsulta_NoReturn($sql_carta_lista);
                        }
                    }
                }
                
                // Confirmar transacción
                $bd->xConsulta_NoReturn("COMMIT");
                
                echo json_encode(array('success' => true, 'idcarta' => $idcarta));
            } catch (Exception $e) {
                // Revertir transacción en caso de error
                $bd->xConsulta_NoReturn("ROLLBACK");
                echo json_encode(array('success' => false, 'mensaje' => $e->getMessage()));
            }
            break;
            
        default:
            echo json_encode(array('success' => false, 'mensaje' => 'Operación no reconocida'));
            break;
    }
?>