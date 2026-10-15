<?php
/**
 * Logistica — Precios de proveedores (central): que subio, cuanto, y quien lo vende mas barato.
 * ?op=lista&filtro=todos|subieron|mas_barato | historial&id= | proveedores (calificacion de los ultimos 90 dias)
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_catalogo.php';
require_once __DIR__ . '/_precios.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'lista':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $filtro = isset($_GET['filtro']) && in_array($_GET['filtro'], array('todos', 'subieron', 'mas_barato'), true) ? $_GET['filtro'] : 'todos';
        // catalogo central por indice (idorg, idsede)
        $prods = array();
        foreach (log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto p
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0", array((int)$ido, (int)$org['idsede_central'])) as $p) { $prods[(int)$p['idproducto']] = $p; }
        $hist = historial_precios($bd, array_keys($prods));
        $r = array();
        foreach ($hist as $id => $h) {
            $l = lectura_precio($h);
            if ($filtro === 'subieron' && !($l['cambio_pct'] > 0)) { continue; }
            if ($filtro === 'mas_barato' && !$l['mejor_otro']) { continue; }
            $r[] = array('idproducto' => $id, 'descripcion' => $prods[$id]['descripcion'], 'unidad' => $prods[$id]['unidad']) + $l;
        }
        // primero lo que mas subio, luego lo que se puede comprar mas barato
        usort($r, function ($a, $b) {
            return ($b['cambio_pct'] ?: -999) <=> ($a['cambio_pct'] ?: -999) ?: strcmp($a['descripcion'], $b['descripcion']);
        });
        $sub = count(array_filter($r, function ($x) { return $x['cambio_pct'] >= PRECIO_ALERTA_PCT; }));
        return array('productos' => array_slice($r, 0, 500), 'total' => count($r), 'subieron' => $sub, 'alerta_pct' => PRECIO_ALERTA_PCT);
    });

case 'historial':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        $p = log_filas($bd, "SELECT p.descripcion, IFNULL(u.abreviatura, '') AS unidad FROM producto p LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE p.idproducto = ? AND p.idorg = ? AND p.idsede = ?", array($id, (int)$ido, (int)$org['idsede_central']));
        if (!$p) { throw new ErrorUsuario('Producto no encontrado.'); }
        $h = historial_precios($bd, array($id));
        return array('producto' => $p[0], 'compras' => isset($h[$id]) ? $h[$id] : array(), 'lectura' => isset($h[$id]) ? lectura_precio($h[$id]) : null);
    });

case 'proveedores':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $r = array_values(calificacion_proveedores($bd, $org));
        // primero los que fallan: es lo que hay que mirar
        usort($r, function ($a, $b) { return ($a['estrellas'] === null) - ($b['estrellas'] === null) ?: $a['estrellas'] <=> $b['estrellas'] ?: $b['comprado'] <=> $a['comprado']; });
        return array('proveedores' => $r, 'dias' => CALIF_DIAS, 'alerta_pct' => PRECIO_ALERTA_PCT);
    });

default:
    log_out(false, null, 'Operación no válida.');
}
