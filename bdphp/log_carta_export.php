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
            $idcarta = $_POST['idcarta'];
            
            // Verificar que la carta pertenezca a la sede actual
            $sql_check = "SELECT idcarta FROM carta WHERE idcarta = $idcarta AND idorg = $g_ido AND idsede = $g_idsede";
            $carta_existe = $bd->xDevolverUnDato($sql_check);
            
            if (!$carta_existe) {
                echo json_encode(array('success' => false, 'mensaje' => 'La carta no existe o no pertenece a esta sede'));
                exit;
            }
            
            // Obtener información de la carta
            $sql_carta = "SELECT idcarta, idorg, idsede, idcategoria, fecha, estado FROM carta WHERE idcarta = $idcarta";
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
                WHERE cl.idcarta = $idcarta AND s.estado = 0
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
                           i.is_recomendacion, i.visible_cliente
                    FROM carta_lista AS cl
                    INNER JOIN item AS i ON cl.iditem = i.iditem
                    WHERE cl.idcarta = $idcarta AND cl.idseccion = $idseccion
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
            $datos = json_decode($_POST['datos'], true);
            
            if (!$datos || !isset($datos['carta']) || !isset($datos['secciones'])) {
                echo json_encode(array('success' => false, 'mensaje' => 'Formato de datos inválido'));
                exit;
            }
            
            // Iniciar transacción
            $bd->xConsulta_NoReturn("START TRANSACTION");
            
            try {
                // Crear nueva carta
                $fecha_actual = date("d/m/Y");
                $sql_nueva_carta = "
                    INSERT INTO carta (idorg, idsede, idcategoria, fecha, estado)
                    VALUES ($g_ido, $g_idsede, {$datos['carta']['idcategoria']}, '$fecha_actual', 0)
                ";
                $bd->xConsulta_NoReturn($sql_nueva_carta);
                
                // Obtener el ID de la nueva carta
                $sql_id_carta = "SELECT LAST_INSERT_ID() as id";
                $result_id_carta = $bd->xConsulta3($sql_id_carta);
                $id_carta_nueva = json_decode($result_id_carta, true)[0]['id'];
                
                if (!$id_carta_nueva) {
                    throw new Exception("No se pudo crear la nueva carta");
                }
                
                // Procesar cada sección y sus items
                foreach ($datos['secciones'] as $seccion) {
                    // Verificar si la sección ya existe
                    $sql_check_seccion = "
                        SELECT idseccion FROM seccion 
                        WHERE idorg = $g_ido AND idsede = $g_idsede AND descripcion = '{$seccion['descripcion']}'
                        AND estado = 0
                    ";
                    $id_seccion = $bd->xDevolverUnDato($sql_check_seccion);
                    
                    // Si no existe, crear la sección
                    if (!$id_seccion) {
                        $sql_nueva_seccion = "
                            INSERT INTO seccion (idorg, idsede, descripcion, idimpresora, sec_orden, img, imprimir, estado)
                            VALUES ($g_ido, $g_idsede, '{$seccion['descripcion']}', {$seccion['idimpresora']}, 
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
                        // Verificar si el item ya existe
                        $sql_check_item = "
                            SELECT iditem FROM item 
                            WHERE idorg = $g_ido AND idsede = $g_idsede AND descripcion = '{$item['descripcion']}'
                            AND estado = 0
                        ";
                        $id_item = $bd->xDevolverUnDato($sql_check_item);
                        
                        // Si no existe, crear el item
                        if (!$id_item) {
                            $detalle = isset($item['detalle']) ? "'{$item['detalle']}'" : "''";
                            $img = isset($item['img']) ? "'{$item['img']}'" : "''";
                            $is_recomendacion = isset($item['is_recomendacion']) ? $item['is_recomendacion'] : "0";
                            $visible_cliente = isset($item['visible_cliente']) ? $item['visible_cliente'] : "1";
                            
                            $sql_nuevo_item = "
                                INSERT INTO item (idorg, idsede, descripcion, detalle, img, is_recomendacion, visible_cliente, estado)
                                VALUES ($g_ido, $g_idsede, '{$item['descripcion']}', $detalle, $img, $is_recomendacion, $visible_cliente, 0)
                            ";
                            $bd->xConsulta_NoReturn($sql_nuevo_item);
                            
                            // Obtener el ID del nuevo item
                            $sql_id_item = "SELECT LAST_INSERT_ID() as id";
                            $result_id_item = $bd->xConsulta3($sql_id_item);
                            $id_item = json_decode($result_id_item, true)[0]['id'];
                            
                            if (!$id_item) {
                                throw new Exception("No se pudo crear el item {$item['descripcion']}");
                            }
                        }
                        
                        // Crear entrada en carta_lista
                        $id_carta_lista = $g_ido . $g_idsede . $id_carta_nueva . $id_seccion . $id_item;
                        $sql_carta_lista = "
                            INSERT INTO carta_lista (idcarta_lista, idcarta, idseccion, iditem, precio, cantidad, cant_preparado, sec_orden)
                            VALUES ('$id_carta_lista', $id_carta_nueva, $id_seccion, $id_item, '{$item['precio']}', 
                                    '{$item['cantidad']}', '{$item['cant_preparado']}', {$item['sec_orden']})
                        ";
                        $bd->xConsulta_NoReturn($sql_carta_lista);
                    }
                }
                
                // Confirmar transacción
                $bd->xConsulta_NoReturn("COMMIT");
                
                echo json_encode(array('success' => true, 'idcarta' => $id_carta_nueva));
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