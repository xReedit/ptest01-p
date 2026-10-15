<?php
/**
 * Logistica (app/logistica/): datos de la sesion actual.
 * GET sesion.php -> {success, data:{idorg, idsede, idusuario, usuario, sede, rol_logistica}}
 * rol_logistica: 'central' | 'sede' (participa) | 'sin_activar' (org sin logistica) | 'no_participa'
 * Sin sesion, SecurityGuard (en _bootstrap) responde 401 y la app muestra "inicia sesion en el POS".
 */
require_once __DIR__ . '/_logistica.php';

log_responder(function () use ($bd, $g_ido, $g_idsede, $g_us) {
    $fila = log_filas($bd, "SELECT u.nombres AS usuario, s.nombre AS sede
            FROM usuario u LEFT JOIN sede s ON s.idsede = ? WHERE u.idusuario = ? LIMIT 1", array($g_idsede, $g_us));
    $org = log_org($bd, $g_ido);
    if (!$org) { $rol = 'sin_activar'; }
    elseif ((int)$org['idsede_central'] === $g_idsede) { $rol = 'central'; }
    else {
        $s = log_filas($bd, "SELECT 1 FROM log_sede WHERE idsede = ? AND idorg = ? AND activo = 1", array($g_idsede, $g_ido));
        $rol = $s ? 'sede' : 'no_participa';
    }
    $prod = $org && in_array($rol, array('central', 'sede'), true)
        ? log_filas($bd, "SELECT produccion_activa, idsede_planta FROM org_logistica WHERE idorg = ?", array($g_ido))[0] : null;
    // bienvenida (empresa sin configurar): una sede o cadena cambia la explicacion. sede es chica (~600 filas)
    $sedes = $rol === 'sin_activar'
        ? array_column(log_filas($bd, "SELECT nombre FROM sede WHERE idorg = ? AND estado = 0 ORDER BY nombre LIMIT 60", array($g_ido)), 'nombre') : array();
    return array(
        'sedes_empresa' => $sedes,
        'idorg' => $g_ido, 'idsede' => $g_idsede, 'idusuario' => $g_us,
        'usuario' => $fila ? $fila[0]['usuario'] : '', 'sede' => $fila ? $fila[0]['sede'] : '',
        'rol_logistica' => $rol,
        'conteo_rapido' => $org && in_array($rol, array('central', 'sede'), true)
            && log_filas($bd, "SELECT 1 FROM org_logistica WHERE idorg = ? AND conteo_rapido = 1", array($g_ido)) ? true : false,
        'produccion' => $prod && (int)$prod['produccion_activa'] === 1,
        'es_planta' => $prod && (int)$prod['produccion_activa'] === 1 && (int)($prod['idsede_planta'] ?: $org['idsede_central']) === (int)$g_idsede,
    );
});
