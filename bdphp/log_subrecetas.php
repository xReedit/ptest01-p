<?php

require_once __DIR__ . '/SecurityGuard.php';
SecurityGuard::verificarAcceso();

header('Content-Type: application/json; charset=utf-8');
// include_once("conex.php");
// include_once("fn.php");

session_start();
include "ManejoBD.php";
$bd=new xManejoBD("restobar");

date_default_timezone_set('America/Lima');

$g_ido = $_SESSION['idorg'];
$g_idsede = $_SESSION['idsede'];

$op = isset($_GET['op']) ? $_GET['op'] : '';

switch ($op) {
    case 'listar':
        // Listar todas las subrecetas de la sede
        $sql = "SELECT idsubreceta, descripcion, FORMAT(costo, 2) as costo 
                FROM subreceta 
                WHERE idsede = $g_idsede AND estado = 0 
                ORDER BY descripcion";
        $bd->xConsulta($sql);
        break;

    case 'listar_autocomplete':
        // Listar subrecetas para autocomplete (usado en recetas)
        $sql = "SELECT idsubreceta as value, descripcion as label, costo as precio_unitario 
                FROM subreceta 
                WHERE idsede = $g_idsede AND estado = 0 
                ORDER BY descripcion";
        $bd->xConsulta($sql);
        break;

    case 'get_ingredientes':
        // Obtener ingredientes de una subreceta
        $idsubreceta = $_POST['id'];
        $sql = "SELECT si.idsubreceta_ingrediente, si.idsubreceta, si.descripcion, 
                       si.cantidad, IFNULL(si.cantidad_show, si.cantidad) as cantidad_show, 
                       si.costo, si.idporcion, si.idproducto_stock, si.viene_de, 
                       si.necesario, IFNULL(si.und_medida, '') as und_medida
                FROM subreceta_ingrediente si
                WHERE si.idsubreceta = $idsubreceta AND si.estado = 0
                ORDER BY si.idsubreceta_ingrediente";
        $bd->xConsulta($sql);
        break;

    case 'guardar':
        // Guardar subreceta con sus ingredientes
        $data = json_decode($_POST['data'], true);
        
        $idsubreceta = $data['idsubreceta'];
        $descripcion = addslashes($data['descripcion']);
        $costo = floatval($data['costo']);
        $idorg = $data['idorg'];
        $idsede = $data['idsede'];
        $ingredientes = $data['ingredientes'];

        if (empty($idsubreceta)) {
            // Insertar nueva subreceta
            $sql = "INSERT INTO subreceta (descripcion, costo, idorg, idsede) 
                    VALUES ('$descripcion', $costo, $idorg, $idsede)";
            $idsubreceta = $bd->xConsulta_UltimoId($sql);
        } else {
            // Actualizar subreceta existente
            $sql = "UPDATE subreceta 
                    SET descripcion = '$descripcion', costo = $costo 
                    WHERE idsubreceta = $idsubreceta";
            $bd->xConsulta_NoReturn($sql);
        }

        // Procesar ingredientes
        foreach ($ingredientes as $item) {
            if ($item['new'] == 1 && $item['borrado'] == 0) {
                // Insertar nuevo ingrediente
                $desc_ing = addslashes($item['descripcion']);
                $cantidad = floatval($item['cantidad']);
                $cantidad_show = $item['cantidad_show'];
                $costo_ing = floatval($item['costo']);
                $idporcion = intval($item['idporcion']);
                $idproducto_stock = intval($item['idproducto_stock']);
                $viene_de = $item['viene_de'];
                $necesario = intval($item['necesario']);
                $und_medida = isset($item['und_medida']) ? $item['und_medida'] : '';

                $sql = "INSERT INTO subreceta_ingrediente 
                        (idsubreceta, descripcion, cantidad, cantidad_show, costo, idporcion, idproducto_stock, viene_de, necesario, und_medida) 
                        VALUES ($idsubreceta, '$desc_ing', $cantidad, '$cantidad_show', $costo_ing, $idporcion, $idproducto_stock, '$viene_de', $necesario, '$und_medida')";
                $bd->xConsulta_NoReturn($sql);
            }

            if ($item['modificado'] == 1 && isset($item['idsubreceta_ingrediente']) && $item['idsubreceta_ingrediente'] > 0) {
                // Actualizar ingrediente existente
                $idsubreceta_ingrediente = intval($item['idsubreceta_ingrediente']);
                $necesario = intval($item['necesario']);
                
                $sql = "UPDATE subreceta_ingrediente 
                        SET necesario = $necesario 
                        WHERE idsubreceta_ingrediente = $idsubreceta_ingrediente";
                $bd->xConsulta_NoReturn($sql);
            }

            if ($item['borrado'] == 1 && $item['new'] == 0 && isset($item['idsubreceta_ingrediente']) && $item['idsubreceta_ingrediente'] > 0) {
                // Eliminar ingrediente (borrado lógico)
                $idsubreceta_ingrediente = intval($item['idsubreceta_ingrediente']);
                
                $sql = "UPDATE subreceta_ingrediente 
                        SET estado = 1 
                        WHERE idsubreceta_ingrediente = $idsubreceta_ingrediente";
                $bd->xConsulta_NoReturn($sql);
            }
        }

        echo json_encode(['success' => true, 'idsubreceta' => $idsubreceta]);
        break;

    case 'eliminar':
        // Eliminar subreceta (borrado lógico)
        $idsubreceta = intval($_POST['id']);
        
        // Eliminar subreceta
        $sql = "UPDATE subreceta SET estado = 1 WHERE idsubreceta = $idsubreceta";
        $bd->xConsulta_NoReturn($sql);
        
        // Eliminar ingredientes asociados
        $sql = "UPDATE subreceta_ingrediente SET estado = 1 WHERE idsubreceta = $idsubreceta";
        $bd->xConsulta_NoReturn($sql);

        echo json_encode(['success' => true]);
        break;

    case 'get_one':
        // Obtener una subreceta específica
        $idsubreceta = intval($_POST['id']);
        $sql = "SELECT idsubreceta, descripcion, costo 
                FROM subreceta 
                WHERE idsubreceta = $idsubreceta AND estado = 0";
        $bd->xConsulta($sql);
        break;

    default:
        echo json_encode(['error' => 'Operación no válida']);
        break;
}
?>
