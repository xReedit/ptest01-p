<?php

require_once __DIR__ . '/SecurityGuard.php';
SecurityGuard::verificarAcceso();
header('Content-Type: application/json;charset=utf-8');
header('content-type: text/html; charset: utf-8');
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
include "ManejoBD.php";
$bd = new xManejoBD("restobar");

if (isset($_POST['op'])) {
    $op = $_POST['op'];
} else if (isset($_GET['op'])) {
    $op = $_GET['op'];
} else {
    $postBody = json_decode(file_get_contents('php://input'));
    if (isset($postBody->op)) {
        $op = $postBody->op;
    }
}

$g_ido = $_SESSION['ido'];
$g_idsede = $_SESSION['idsede'];
$g_us = $_SESSION['idusuario'];

switch ($op) {
    
    case '1': // Obtener sugerencias de productos con stock bajo
        $sql = "
            SELECT 
                'porcion' AS tipo,
                p.idporcion AS id,
                p.idporcion_codigo_unico,
                pcu.codigo AS codigo_unico,
                p.descripcion,
                p.stock,                
                CASE 
                    WHEN p.stock < 10 THEN 'rojo'
                    WHEN p.stock >= 10 AND p.stock <= 30 THEN 'amarillo'
                    ELSE 'verde'
                END AS semaforo
            FROM porcion p
            LEFT JOIN porcion_codigo_unico pcu ON p.idporcion_codigo_unico = pcu.idporcion_codigo_unico
            WHERE p.idsede = $g_idsede 
            AND p.estado = 0
            AND p.stock < 50            
            UNION ALL            
            SELECT 
                'producto' AS tipo,
                ps.idproducto AS id,
                NULL AS idporcion_codigo_unico,
                NULL AS codigo_unico,
                CONCAT(a.descripcion, ' | ', p.descripcion) AS descripcion,
                CAST(ps.stock AS DECIMAL(10,2)) AS stock,                
                CASE 
                    WHEN CAST(ps.stock AS DECIMAL(10,2)) < 10 THEN 'rojo'
                    WHEN CAST(ps.stock AS DECIMAL(10,2)) >= 10 AND CAST(ps.stock AS DECIMAL(10,2)) <= 30 THEN 'amarillo'
                    ELSE 'verde'
                END AS semaforo
            FROM producto_stock ps
            INNER JOIN producto p ON ps.idproducto = p.idproducto
            INNER JOIN almacen a ON ps.idalmacen = a.idalmacen
            WHERE a.idsede = $g_idsede 
            AND ps.estado = 0
            AND CAST(ps.stock AS DECIMAL(10,2)) < 50            
            ORDER BY stock ASC
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '2': // Obtener lista de sedes disponibles para solicitar
        $sql = "
            SELECT 
                s.idsede,
                s.nombre AS descripcion,
                s.ciudad,
                s.direccion
            FROM sede s
            WHERE s.idorg = $g_ido 
            AND s.idsede != $g_idsede
            AND s.estado = 0
            ORDER BY s.nombre
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '3': // Crear nueva solicitud        
        $postData = json_decode(file_get_contents('php://input'));
        
        $idsede_destino = $postData->idsede_destino;
        $nota = isset($postData->nota) ? $postData->nota : '';
        $detalle = json_encode($postData->detalle);
        $detalle = str_replace("'", "''", $detalle);
        
        $sql = "CALL sp_crear_solicitud_producto($g_ido,$g_idsede,$idsede_destino,'$nota',$g_us,'$detalle')";

        // echo json_encode(array(
        //     'success' => false,
        //     'message' => 'Solicitud creada exitosamente',
        //     'query' => $sql
        // ));
        
        try {
            $bd->xConsulta_NoReturn($sql);           
            echo json_encode(array(
                'success' => true,
                'message' => 'Solicitud creada exitosamente',
                'query' => $sql
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al crear solicitud: ' . $e->getMessage(),
                'query' => $sql
            ));
        }
        break;
        
    case '4': // Listar solicitudes (enviadas por esta sede)
        $sql = "
            SELECT 
                sp.idsolicitud_producto,
                sp.idsede_destino,
                s.nombre AS sede_destino,
                sp.fecha_solicitud,
                sp.fecha_visto,
                sp.fecha_atendido,
                sp.fecha_despachado,
                sp.estado,
                sp.nota,
                COUNT(spd.idsolicitud_producto_detalle) AS total_items,
                SUM(CASE WHEN spd.tipo_producto = 'porcion' THEN 1 ELSE 0 END) AS total_porciones,
                SUM(CASE WHEN spd.tipo_producto = 'almacen' THEN 1 ELSE 0 END) AS total_productos_almacen
            FROM solicitud_producto sp
            LEFT JOIN sede s ON sp.idsede_destino = s.idsede
            LEFT JOIN solicitud_producto_detalle spd ON sp.idsolicitud_producto = spd.idsolicitud_producto
            WHERE sp.idsede_solicita = $g_idsede
            AND sp.idorg = $g_ido
            GROUP BY sp.idsolicitud_producto
            ORDER BY sp.fecha_solicitud DESC
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '5': // Listar solicitudes recibidas (para esta sede)
        $sql = "
            SELECT 
                sp.idsolicitud_producto,
                sp.idsede_solicita,
                s.nombre AS sede_solicita,
                s.nombre AS sede_origen,
                sp.fecha_solicitud,
                sp.fecha_visto,
                sp.fecha_atendido,
                sp.fecha_despachado,
                sp.estado,
                sp.nota,
                COUNT(spd.idsolicitud_producto_detalle) AS total_items,
                SUM(CASE WHEN spd.tipo_producto = 'porcion' THEN 1 ELSE 0 END) AS total_porciones,
                SUM(CASE WHEN spd.tipo_producto = 'almacen' THEN 1 ELSE 0 END) AS total_productos_almacen,
                u.nombres AS usuario_solicita
            FROM solicitud_producto sp
            LEFT JOIN sede s ON sp.idsede_solicita = s.idsede
            LEFT JOIN solicitud_producto_detalle spd ON sp.idsolicitud_producto = spd.idsolicitud_producto
            LEFT JOIN usuario u ON sp.idusuario_solicita = u.idusuario
            WHERE sp.idsede_destino = $g_idsede
            AND sp.idorg = $g_ido
            GROUP BY sp.idsolicitud_producto
            ORDER BY sp.fecha_solicitud DESC
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '6': // Obtener detalle de una solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        $idsolicitud = $postData['idsolicitud_producto'];
        
        $sql = "
            SELECT 
                spd.*,
                CASE 
                    WHEN spd.tipo_producto = 'porcion' THEN pcu.codigo
                    ELSE NULL
                END AS codigo_unico
            FROM solicitud_producto_detalle spd
            LEFT JOIN porcion_codigo_unico pcu ON spd.idporcion_codigo_unico = pcu.idporcion_codigo_unico
            WHERE spd.idsolicitud_producto = $idsolicitud
            ORDER BY spd.idsolicitud_producto_detalle
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '7': // Cambiar estado de solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        
        $idsolicitud = $postData['idsolicitud_producto'];
        $nuevo_estado = $postData['estado'];
        $observacion = isset($postData['observacion']) ? $bd->antiInyeccion($postData['observacion']) : '';
        
        $sql = "CALL sp_cambiar_estado_solicitud(
            $idsolicitud,
            '$nuevo_estado',
            $g_us,
            '$observacion'
        )";
        
        try {
            $bd->xConsultaSinDatos($sql);
            echo json_encode(array(
                'success' => true,
                'message' => 'Estado actualizado correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al actualizar estado: ' . $e->getMessage()
            ));
        }
        break;
        
    case '8': // Obtener historial de cambios de una solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        $idsolicitud = $postData['idsolicitud_producto'];
        
        $sql = "
            SELECT 
                sph.*,
                u.nombres AS usuario_nombre
            FROM solicitud_producto_historial sph
            LEFT JOIN usuario u ON sph.idusuario = u.idusuario
            WHERE sph.idsolicitud_producto = $idsolicitud
            ORDER BY sph.fecha_cambio DESC
        ";
        $bd->xConsulta($sql);
        break;
        
    case '9': // Actualizar cantidades despachadas
        $postData = json_decode(file_get_contents('php://input'), true);
        
        $idsolicitud = $postData['idsolicitud_producto'];
        $detalles = $postData['detalles'];
        
        try {
            foreach ($detalles as $detalle) {
                $iddetalle = $detalle['idsolicitud_producto_detalle'];
                $cantidad_despachada = $detalle['cantidad_despachada'];
                
                $sql = "UPDATE solicitud_producto_detalle 
                        SET cantidad_despachada = $cantidad_despachada 
                        WHERE idsolicitud_producto_detalle = $iddetalle";
                $bd->xConsultaSinDatos($sql);
            }
            
            echo json_encode(array(
                'success' => true,
                'message' => 'Cantidades actualizadas correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al actualizar cantidades: ' . $e->getMessage()
            ));
        }
        break;
        
    case '10': // Buscar productos/porciones para agregar a solicitud
        $postBody = json_decode(file_get_contents('php://input'));
        $busqueda = $postBody->busqueda;        
        
        $sql = "
            SELECT 
                'porcion' AS tipo,
                p.idporcion AS id,
                p.idporcion_codigo_unico,
                pcu.codigo AS codigo_unico,
                p.descripcion,
                p.stock,       
                CASE 
                    WHEN p.stock < 10 THEN 'rojo'
                    WHEN p.stock >= 10 AND p.stock <= 30 THEN 'amarillo'
                    ELSE 'verde'
                END AS semaforo
            FROM porcion p
            LEFT JOIN porcion_codigo_unico pcu ON p.idporcion_codigo_unico = pcu.idporcion_codigo_unico
            WHERE p.idsede = $g_idsede 
            AND p.estado = 0
            AND (p.descripcion LIKE '%$busqueda%' OR pcu.codigo LIKE '%$busqueda%')
            
            UNION ALL
            
            SELECT 
                'producto' AS tipo,
                ai.idproducto AS id,
                NULL AS idporcion_codigo_unico,
                NULL AS codigo_unico,
                CONCAT(a.descripcion, ' | ', p.descripcion) descripcion,
                CAST(ai.stock AS DECIMAL(10,2)) AS stock,                
                CASE 
                    WHEN CAST(ai.stock AS DECIMAL(10,2)) < 10 THEN 'rojo'
                    WHEN CAST(ai.stock AS DECIMAL(10,2)) >= 10 AND CAST(ai.stock AS DECIMAL(10,2)) <= 30 THEN 'amarillo'
                    ELSE 'verde'
                END AS semaforo
            FROM producto_stock ai
            INNER JOIN producto p ON ai.idproducto = p.idproducto
            INNER JOIN almacen a ON ai.idalmacen = a.idalmacen
            WHERE a.idsede = $g_idsede 
            AND ai.estado = 0
            AND p.descripcion LIKE '%$busqueda%'
            
            ORDER BY stock ASC
            LIMIT 50
        ";
        $result = json_decode($bd->xConsulta3($sql), true);
        echo json_encode(array('success' => true, 'datos' => $result ? $result : array()));
        break;
        
    case '11': // Cancelar solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        $idsolicitud = $postData['idsolicitud_producto'];
        $observacion = isset($postData['observacion']) ? $bd->antiInyeccion($postData['observacion']) : 'Solicitud cancelada';
        
        $sql = "CALL sp_cambiar_estado_solicitud(
            $idsolicitud,
            'cancelado',
            $g_us,
            '$observacion'
        )";
        
        try {
            $bd->xConsultaSinDatos($sql);
            echo json_encode(array(
                'success' => true,
                'message' => 'Solicitud cancelada correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al cancelar solicitud: ' . $e->getMessage()
            ));
        }
        break;
        
    case '12': // Obtener estadísticas de solicitudes
        $sql = "
            SELECT 
                COUNT(*) AS total_solicitudes,
                SUM(CASE WHEN estado = 'enviado' THEN 1 ELSE 0 END) AS enviadas,
                SUM(CASE WHEN estado = 'visto' THEN 1 ELSE 0 END) AS vistas,
                SUM(CASE WHEN estado = 'atendido' THEN 1 ELSE 0 END) AS atendidas,
                SUM(CASE WHEN estado = 'despachado' THEN 1 ELSE 0 END) AS despachadas,
                SUM(CASE WHEN estado = 'cancelado' THEN 1 ELSE 0 END) AS canceladas
            FROM solicitud_producto
            WHERE idsede_solicita = $g_idsede
            AND idorg = $g_ido
        ";
        $bd->xConsulta($sql);
        break;
        
    case '13': // Obtener stock disponible en sede actual para despachar una solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        $idsolicitud = $postData['idsolicitud_producto'];
        
        // Obtener detalle de la solicitud
        $sql = "
            SELECT 
                spd.idsolicitud_producto_detalle,
                spd.tipo_producto,
                spd.idporcion_codigo_unico,
                spd.idporcion,
                spd.idproducto,
                spd.descripcion,
                spd.cantidad_solicitada,
                spd.cantidad_despachada
            FROM solicitud_producto_detalle spd
            WHERE spd.idsolicitud_producto = $idsolicitud
            ORDER BY spd.idsolicitud_producto_detalle
        ";
        $result_detalle = json_decode($bd->xConsulta3($sql), true);
        if (!$result_detalle) $result_detalle = array();
        
        $items_despacho = array();
        
        foreach ($result_detalle as $item) {
            $item_data = array(
                'idsolicitud_producto_detalle' => $item['idsolicitud_producto_detalle'],
                'tipo_producto' => $item['tipo_producto'],
                'descripcion_solicitada' => $item['descripcion'],
                'cantidad_solicitada' => $item['cantidad_solicitada'],
                'cantidad_despachada' => $item['cantidad_despachada'],
                'match' => null,
                'opciones_almacen' => array()
            );
            
            if ($item['tipo_producto'] == 'porcion') {
                // Buscar porción en sede actual por código único
                $match_result = array();
                $match_sql = "";
                if (!empty($item['idporcion_codigo_unico']) && $item['idporcion_codigo_unico'] != '0') {
                    $match_sql = "
                        SELECT p.idporcion, p.descripcion, p.stock, p.idporcion_codigo_unico, 'codigo_unico' AS match_tipo
                        FROM porcion p
                        WHERE p.idsede = $g_idsede AND p.estado = 0
                        AND p.idporcion_codigo_unico = " . intval($item['idporcion_codigo_unico']) . "
                        LIMIT 1
                    ";
                    $match_result = json_decode($bd->xConsulta3($match_sql), true);
                    if (!$match_result) $match_result = array();
                }
                
                // Si no encontró por código único, buscar por nombre
                if (empty($match_result)) {
                    $desc = $bd->antiInyeccion($item['descripcion']);
                    $match_sql = "
                        SELECT p.idporcion, p.descripcion, p.stock, p.idporcion_codigo_unico, 'nombre' AS match_tipo
                        FROM porcion p
                        WHERE p.idsede = $g_idsede AND p.estado = 0
                        AND p.descripcion LIKE '%$desc%'
                        LIMIT 5
                    ";
                    $match_result = json_decode($bd->xConsulta3($match_sql), true);
                    if (!$match_result) $match_result = array();
                }
                
                if (!empty($match_result)) {
                    $item_data['match'] = $match_result;
                }
                
            } else {
                // Producto de almacén: buscar en almacenes de la sede actual por nombre
                $desc = $bd->antiInyeccion($item['descripcion']);
                // Extraer solo el nombre del producto (sin el prefijo del almacén)
                $desc_parts = explode(' | ', $item['descripcion']);
                $desc_producto = count($desc_parts) > 1 ? $bd->antiInyeccion($desc_parts[1]) : $desc;
                
                $match_sql = "
                    SELECT 
                        ps.idproducto_stock, ps.idproducto, ps.idalmacen, ps.stock,
                        a.descripcion AS almacen_nombre,
                        p.descripcion AS producto_nombre,
                        'nombre' AS match_tipo
                    FROM producto_stock ps
                    INNER JOIN producto p ON ps.idproducto = p.idproducto
                    INNER JOIN almacen a ON ps.idalmacen = a.idalmacen
                    WHERE a.idsede = $g_idsede 
                    AND ps.estado = 0
                    AND p.descripcion LIKE '%$desc_producto%'
                    ORDER BY ps.stock DESC
                ";
                $match_result = json_decode($bd->xConsulta3($match_sql), true);
                if (!$match_result) $match_result = array();
                
                if (!empty($match_result)) {
                    $item_data['opciones_almacen'] = $match_result;
                }
            }
            
            $items_despacho[] = $item_data;
        }
        
        echo json_encode(array(
            'success' => true,
            'datos' => $items_despacho
        ));
        break;
        
    case '14': // Ejecutar despacho de solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        
        $idsolicitud = $postData['idsolicitud_producto'];
        $detalles = json_encode($postData['detalles']);
        $detalles = str_replace("'", "''", $detalles);
        
        $sql = "CALL sp_despachar_solicitud($idsolicitud, $g_us, $g_idsede, '$detalles')";
        
        try {
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array(
                'success' => true,
                'message' => 'Solicitud despachada correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al despachar: ' . $e->getMessage()
            ));
        }
        break;
        
    case '15': // Obtener info para recepción (stock local de sede solicitante)
        $postData = json_decode(file_get_contents('php://input'), true);
        $idsolicitud = $postData['idsolicitud_producto'];
        
        $sql = "
            SELECT 
                spd.idsolicitud_producto_detalle,
                spd.tipo_producto,
                spd.idporcion_codigo_unico,
                spd.idporcion,
                spd.idproducto,
                spd.descripcion,
                spd.cantidad_solicitada,
                spd.cantidad_despachada
            FROM solicitud_producto_detalle spd
            WHERE spd.idsolicitud_producto = $idsolicitud
            AND spd.cantidad_despachada > 0
            ORDER BY spd.idsolicitud_producto_detalle
        ";
        $result_detalle = json_decode($bd->xConsulta3($sql), true);
        if (!$result_detalle) $result_detalle = array();
        
        $items_recepcion = array();
        
        foreach ($result_detalle as $item) {
            $item_data = array(
                'idsolicitud_producto_detalle' => $item['idsolicitud_producto_detalle'],
                'tipo_producto' => $item['tipo_producto'],
                'descripcion' => $item['descripcion'],
                'cantidad_solicitada' => $item['cantidad_solicitada'],
                'cantidad_despachada' => $item['cantidad_despachada'],
                'match' => null,
                'opciones_almacen' => array()
            );
            
            if ($item['tipo_producto'] == 'porcion') {
                $match_result = array();
                if (!empty($item['idporcion_codigo_unico']) && $item['idporcion_codigo_unico'] != '0') {
                    $match_sql = "
                        SELECT p.idporcion, p.descripcion, p.stock, p.idporcion_codigo_unico, 'codigo_unico' AS match_tipo
                        FROM porcion p
                        WHERE p.idsede = $g_idsede AND p.estado = 0
                        AND p.idporcion_codigo_unico = " . intval($item['idporcion_codigo_unico']) . "
                        LIMIT 1
                    ";
                    $match_result = json_decode($bd->xConsulta3($match_sql), true);
                    if (!$match_result) $match_result = array();
                }
                
                if (empty($match_result)) {
                    $desc = $bd->antiInyeccion($item['descripcion']);
                    $match_sql = "
                        SELECT p.idporcion, p.descripcion, p.stock, p.idporcion_codigo_unico, 'nombre' AS match_tipo
                        FROM porcion p
                        WHERE p.idsede = $g_idsede AND p.estado = 0
                        AND p.descripcion LIKE '%$desc%'
                        LIMIT 5
                    ";
                    $match_result = json_decode($bd->xConsulta3($match_sql), true);
                    if (!$match_result) $match_result = array();
                }
                
                if (!empty($match_result)) {
                    $item_data['match'] = $match_result;
                }
            } else {
                $desc_parts = explode(' | ', $item['descripcion']);
                $desc_producto = count($desc_parts) > 1 ? $bd->antiInyeccion($desc_parts[1]) : $bd->antiInyeccion($item['descripcion']);
                
                $match_sql = "
                    SELECT 
                        ps.idproducto_stock, ps.idproducto, ps.idalmacen, ps.stock,
                        a.descripcion AS almacen_nombre,
                        p.descripcion AS producto_nombre,
                        'nombre' AS match_tipo
                    FROM producto_stock ps
                    INNER JOIN producto p ON ps.idproducto = p.idproducto
                    INNER JOIN almacen a ON ps.idalmacen = a.idalmacen
                    WHERE a.idsede = $g_idsede 
                    AND ps.estado = 0
                    AND p.descripcion LIKE '%$desc_producto%'
                    ORDER BY ps.stock DESC
                ";
                $match_result = json_decode($bd->xConsulta3($match_sql), true);
                if (!$match_result) $match_result = array();
                
                if (!empty($match_result)) {
                    $item_data['opciones_almacen'] = $match_result;
                }
            }
            
            $items_recepcion[] = $item_data;
        }
        
        echo json_encode(array(
            'success' => true,
            'datos' => $items_recepcion
        ));
        break;
        
    case '16': // Ejecutar recepción de solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        
        $idsolicitud = $postData['idsolicitud_producto'];
        $detalles = json_encode($postData['detalles']);
        $detalles = str_replace("'", "''", $detalles);
        
        $sql = "CALL sp_recibir_solicitud($idsolicitud, $g_us, $g_idsede, '$detalles')";
        
        try {
            $bd->xConsulta_NoReturn($sql);
            echo json_encode(array(
                'success' => true,
                'message' => 'Solicitud recibida correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al recibir: ' . $e->getMessage()
            ));
        }
        break;
        
    default:
        echo json_encode(array(
            'success' => false,
            'message' => 'Operación no válida'
        ));
        break;
}
?>
