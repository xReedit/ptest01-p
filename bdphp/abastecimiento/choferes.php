<?php
/**
 * Logistica — choferes / repartidores (central).
 * ?op=listar | guardar {idchofer?, nombre, telefono} | enviar_acceso {idchofer} | nuevo_enlace {idchofer} | activo {idchofer, activo}
 * El acceso del chofer es un enlace personal (ver _chofer.php) que se manda a SU celular por el WhatsApp del POS.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_chofer.php';
require_once __DIR__ . '/_whatsapp.php';

function chofer_de_org($bd, $ido, $id) {
    $c = log_filas($bd, "SELECT idchofer, nombre, telefono, activo, acceso_version FROM log_chofer WHERE idchofer = ? AND idorg = ?", array((int)$id, (int)$ido));
    if (!$c) { throw new ErrorUsuario('Chofer no encontrado.'); }
    return $c[0];
}

/** Manda el enlace al celular del chofer. Devuelve {enviado, error, enlace} (el enlace sirve para copiarlo a mano). */
function mandar_acceso($bd, $org, $c) {
    $enlace = chofer_enlace(chofer_token($org['idorg'], $c['idchofer'], $c['acceso_version']));
    if (!$c['telefono']) { return array('enviado' => false, 'error' => 'El chofer no tiene celular.', 'enlace' => $enlace); }
    $texto = 'Hola ' . $c['nombre'] . ', este es tu acceso para ver las entregas que te asignen y marcarlas como entregadas:' . "\n" . $enlace
        . "\n\n" . 'Guárdalo: es solo tuyo, no lo compartas.';
    $err = log_whatsapp($org['idorg'], $org['idsede_central'], $c['telefono'], $texto);
    return array('enviado' => $err === null, 'error' => $err, 'enlace' => $err === null ? null : $enlace);
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'listar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        return log_filas($bd, "SELECT c.idchofer, c.nombre, IFNULL(c.telefono, '') AS telefono, c.activo, c.ultimo_acceso,
                (SELECT COUNT(*) FROM log_ruta r WHERE r.idchofer = c.idchofer AND r.estado = 'en_ruta') AS rutas_abiertas
                FROM log_chofer c WHERE c.idorg = ? ORDER BY c.activo DESC, c.nombre", array((int)$ido));
    });

case 'guardar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $id = isset($b['idchofer']) ? (int)$b['idchofer'] : 0;
        $nombre = log_mayus(isset($b['nombre']) && is_string($b['nombre']) ? $b['nombre'] : '', 80);
        $tel = substr(preg_replace('/\D/', '', isset($b['telefono']) && is_string($b['telefono']) ? $b['telefono'] : ''), 0, 15);
        if ($nombre === '') { throw new ErrorUsuario('Escribe el nombre del chofer.'); }
        if (strlen($tel) < 9) { throw new ErrorUsuario('Escribe el celular del chofer (9 dígitos): ahí le llega su acceso por WhatsApp.'); }
        if ($id) { chofer_de_org($bd, $ido, $id); }
        $otro = log_filas($bd, "SELECT idchofer FROM log_chofer WHERE idorg = ? AND UPPER(nombre) = ? AND idchofer <> ?", array((int)$ido, $nombre, $id));
        if ($otro) { throw new ErrorUsuario('Ya hay un chofer con ese nombre.'); }
        if ($id) {
            log_exec($bd, "UPDATE log_chofer SET nombre = ?, telefono = ? WHERE idchofer = ? AND idorg = ?", array($nombre, $tel, $id, (int)$ido));
        } else {
            log_exec($bd, "INSERT INTO log_chofer (idorg, nombre, telefono, activo) VALUES (?, ?, ?, 1)", array((int)$ido, $nombre, $tel));
            $id = (int)$bd->lastInsertId();
        }
        return array('idchofer' => $id, 'nombre' => $nombre, 'telefono' => $tel);
    });

case 'enviar_acceso':
case 'nuevo_enlace':
    // nuevo_enlace: el anterior deja de servir (celular perdido, chofer cambio de numero)
    log_responder(function () use ($bd, $ido, $idsede, $us, $op) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $c = chofer_de_org($bd, $ido, isset($b['idchofer']) ? $b['idchofer'] : 0);
        if (!(int)$c['activo']) { throw new ErrorUsuario('Ese chofer está desactivado. Actívalo primero.'); }
        if ($op === 'nuevo_enlace') {
            log_exec($bd, "UPDATE log_chofer SET acceso_version = acceso_version + 1 WHERE idchofer = ?", array((int)$c['idchofer']));
            $c['acceso_version'] = (int)$c['acceso_version'] + 1;
        }
        return mandar_acceso($bd, $org, $c);
    });

case 'activo':
    // desactivar = el enlace deja de servir y ya no aparece para asignar rutas
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $c = chofer_de_org($bd, $ido, isset($b['idchofer']) ? $b['idchofer'] : 0);
        $act = !empty($b['activo']) ? 1 : 0;
        log_exec($bd, "UPDATE log_chofer SET activo = ?, acceso_version = acceso_version + IF(? = 0, 1, 0) WHERE idchofer = ?", array($act, $act, (int)$c['idchofer']));
        return array('activo' => $act === 1);
    });

default:
    log_out(false, null, 'Operación no válida.');
}
