<?php

require_once __DIR__ . '/SecurityGuard.php';
SecurityGuard::verificarAcceso();
header("Access-Control-Allow-Origin: *");
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
        $bd->xConsulta($sql);
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
        $bd->xConsulta($sql);
        break;
        
    case '3': // Crear nueva solicitud
        $postData = json_decode(file_get_contents('php://input'), true);
        
        $idsede_destino = $postData['idsede_destino'];
        $nota = isset($postData['nota']) ? $bd->antiInyeccion($postData['nota']) : '';
        $detalle = json_encode($postData['detalle']);
        $detalle = str_replace("'", "''", $detalle);
        
        $sql = "CALL sp_crear_solicitud_producto(
            $g_ido,
            $g_idsede,
            $idsede_destino,
            '$nota',
            $g_us,
            '$detalle',
            @idsolicitud
        )";
        
        try {
            $bd->xConsultaSinDatos($sql);
            $result = $bd->xDevolverUnDato("SELECT @idsolicitud AS D1");
            echo json_encode(array(
                'success' => true,
                'idsolicitud_producto' => $result,
                'message' => 'Solicitud creada correctamente'
            ));
        } catch (Exception $e) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Error al crear solicitud: ' . $e->getMessage()
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
        $bd->xConsulta($sql);
        break;
        
    case '5': // Listar solicitudes recibidas (para esta sede)
        $sql = "
            SELECT 
                sp.idsolicitud_producto,
                sp.idsede_solicita,
                s.nombre AS sede_solicita,
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
        $bd->xConsulta($sql);
        break;
        
    case '6': // Obtener detalle de una solicitud
        $idsolicitud = $_POST['idsolicitud_producto'];
        
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
        $bd->xConsulta($sql);
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
        $idsolicitud = $_POST['idsolicitud_producto'];
        
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
        $bd->xConsulta($sql);
        break;
        
    case '11': // Cancelar solicitud
        $idsolicitud = $_POST['idsolicitud_producto'];
        $observacion = isset($_POST['observacion']) ? $bd->antiInyeccion($_POST['observacion']) : 'Solicitud cancelada';
        
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
        
    default:
        echo json_encode(array(
            'success' => false,
            'message' => 'Operación no válida'
        ));
        break;
}
?>
