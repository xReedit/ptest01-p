<?php
/**
 * Logistica — Mermas (la logica vive en bdphp/_mermas.php, la misma del modulo Inventario).
 * Aqui: la sede registra desde su almacen principal, y la central ve las perdidas de todas las sedes.
 * ?op=productos | registrar {idproducto, cantidad, motivo, nota, token} | mias | reporte&periodo= | detalle&idsede=&periodo=
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/../_mermas.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

/** Sede que participa (o la central). Devuelve su almacen principal. */
function merma_requiere($bd, $ido, $idsede, $us) {
    $org = log_org($bd, $ido);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    if ((int)$org['idsede_central'] === (int)$idsede) { log_requiere_permiso($bd, $us); } else { log_requiere_sede($bd, $ido, $idsede, $us); }
    $alm = alm_principal($bd, $idsede);
    if (!$alm) { throw new ErrorUsuario('Esta sede no tiene almacén principal. La central lo elige en Configurar.'); }
    return $alm;
}

function merma_periodo() {
    return isset($_GET['periodo']) && in_array($_GET['periodo'], MM_PERIODOS, true) ? $_GET['periodo'] : 'mes';
}

/** MermaError -> ErrorUsuario (log_responder muestra el mensaje). */
function merma_try($fn) {
    try { return $fn(); } catch (MermaError $e) { throw new ErrorUsuario($e->getMessage()); }
}

$NOMBRES = MM_MOTIVOS + array('conteo' => 'FALTÓ EN CONTEO');

switch ($op) {
case 'productos':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $alm = merma_requiere($bd, $ido, $idsede, $us);
        return array('motivos' => MM_MOTIVOS, 'productos' => mm_productos($bd, $idsede, $alm));
    });

case 'registrar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $alm = merma_requiere($bd, $ido, $idsede, $us);
        return merma_try(function () use ($bd, $ido, $idsede, $us, $alm) { return mm_registrar($bd, $ido, $idsede, $us, $alm, log_body()); });
    });

case 'mias':
    log_responder(function () use ($bd, $ido, $idsede, $us, $NOMBRES) {
        merma_requiere($bd, $ido, $idsede, $us);
        $f = mm_perdidas($bd, $ido, $idsede, mm_rango('mes'));
        return array('filas' => array_slice($f, 0, 500), 'total' => round(array_sum(array_column($f, 'valor')), 2), 'motivos' => $NOMBRES);
    });

case 'reporte':
    log_responder(function () use ($bd, $ido, $idsede, $us, $NOMBRES) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $s = mm_resumen(mm_perdidas($bd, $ido, 0, mm_rango(merma_periodo())));
        return array('sedes' => $s, 'total' => round(array_sum(array_column($s, 'total')), 2), 'motivos' => $NOMBRES);
    });

case 'detalle':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $s = isset($_GET['idsede']) ? (int)$_GET['idsede'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM sede WHERE idsede = ? AND idorg = ?", array($s, (int)$ido))) { throw new ErrorUsuario('Sede no encontrada.'); }
        return array('filas' => array_slice(mm_perdidas($bd, $ido, $s, mm_rango(merma_periodo())), 0, 500));
    });

default:
    log_out(false, null, 'Operación no válida.');
}
