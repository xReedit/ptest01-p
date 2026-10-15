<?php
/**
 * Logistica — Conteo rapido diario (opcional, se activa en Configurar). Cada sede cuenta pocos productos al dia
 * sin ver el stock; las diferencias quedan como un conteo del modulo Inventario (origen 'rapido', migracion 021)
 * y la central las aprueba (ajusta el stock) o las descarta.
 * ?op=cfg | guardar_cfg {activo, cantidad} | hoy | empezar | terminar {id, filas:[{iddet, contado}]}
 *   | resumen | aprobar {id} | descartar {id}
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_stock.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

const CONTEO_RECONTAR_DIAS = 30;   // un producto contado hace menos de esto va al final de la cola

function conteo_cfg($bd, $ido) {
    $r = log_filas($bd, "SELECT conteo_rapido, conteo_cantidad FROM org_logistica WHERE idorg = ?", array((int)$ido));
    return array('activo' => $r && (int)$r[0]['conteo_rapido'] === 1, 'cantidad' => $r ? (int)$r[0]['conteo_cantidad'] : 5);
}

/** Sede (o central) que participa, con el conteo rapido encendido. Devuelve [cfg, idalmacen]. */
function conteo_requiere($bd, $ido, $idsede, $us) {
    $org = log_org($bd, $ido);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    if ((int)$org['idsede_central'] === (int)$idsede) { log_requiere_permiso($bd, $us); } else { log_requiere_sede($bd, $ido, $idsede, $us); }
    $cfg = conteo_cfg($bd, $ido);
    if (!$cfg['activo']) { throw new ErrorUsuario('El conteo rápido no está activado. La central lo activa en Configurar.'); }
    $alm = alm_principal($bd, $idsede);
    if (!$alm) { throw new ErrorUsuario('Esta sede no tiene almacén principal. La central lo elige en Configurar.'); }
    return array($cfg, $alm);
}

/** Conteo rapido de hoy de la sede, con sus productos. El stock solo se manda cuando ya se termino (conteo ciego). */
function conteo_hoy($bd, $idsede) {
    $c = log_filas($bd, "SELECT idinventario_conteo AS id, estado, DATE_FORMAT(fecha_cierre, '%h:%i %p') AS hora_fin
            FROM inventario_conteo WHERE idsede = ? AND origen = 'rapido' AND estado <> 'anulado' AND fecha_crea >= CURDATE()
            ORDER BY idinventario_conteo DESC LIMIT 1", array((int)$idsede));
    if (!$c) { return null; }
    $c = $c[0];
    $c['items'] = conteo_items($bd, (int)$c['id'], $c['estado'] !== 'borrador');
    return $c;
}

function conteo_items($bd, $id, $conStock) {
    return array_map(function ($f) use ($conStock) {
        $i = array('iddet' => (int)$f['iddet'], 'descripcion' => $f['descripcion'], 'unidad' => $f['unidad'], 'familia' => $f['familia']);
        if ($conStock) {
            $dif = $f['contado'] === null ? 0 : round($f['contado'] - $f['stock_cierre'], 2);
            $i += array('sistema' => (float)$f['stock_cierre'], 'contado' => $f['contado'] === null ? null : (float)$f['contado'],
                'diferencia' => $dif, 'valor' => round($dif * $f['costo_unit'], 2));
        }
        return $i;
    }, log_filas($bd, "SELECT idinventario_conteo_detalle AS iddet, descripcion, IFNULL(unidad, '') AS unidad, IFNULL(familia, '') AS familia,
            stock_cierre, contado, costo_unit FROM inventario_conteo_detalle WHERE idinventario_conteo = ? ORDER BY descripcion", array($id)));
}

/** SIGNAL de los SP de Inventario -> mensaje para el usuario. */
function conteo_sp($bd, $sql) {
    if (!$bd->bd->query($sql)) {
        $m = $bd->bd->error;
        $txt = array('ESTADO_INVALIDO' => 'Ese conteo ya fue terminado o aprobado. Recarga la página.', 'NO_ENCONTRADO' => 'Conteo no encontrado.',
            'SIN_MOTIVO' => 'Falta el motivo AJUSTE INVENTARIO (migración 028).', 'SIN_ALMACEN' => 'La sede no tiene almacén activo.');
        foreach ($txt as $k => $v) { if (strpos($m, $k) !== false) { throw new ErrorUsuario($v); } }
        throw new Exception($m);
    }
    while ($bd->bd->more_results() && $bd->bd->next_result()) { }   // CALL deja un resultado extra
}

switch ($op) {
case 'cfg':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        return conteo_cfg($bd, $ido);
    });

case 'guardar_cfg':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $n = isset($b['cantidad']) ? (int)$b['cantidad'] : 5;
        if ($n < 3 || $n > 20) { throw new ErrorUsuario('Elige entre 3 y 20 productos por día.'); }
        log_exec($bd, "UPDATE org_logistica SET conteo_rapido = ?, conteo_cantidad = ? WHERE idorg = ?", array(empty($b['activo']) ? 0 : 1, $n, (int)$ido));
        return conteo_cfg($bd, $ido);
    });

case 'hoy':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        list($cfg) = conteo_requiere($bd, $ido, $idsede, $us);
        return array('cantidad' => $cfg['cantidad'], 'conteo' => conteo_hoy($bd, $idsede));
    });

case 'empezar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        list($cfg, $alm) = conteo_requiere($bd, $ido, $idsede, $us);
        $hoy = conteo_hoy($bd, $idsede);
        if ($hoy) { return $hoy; }   // doble clic / otra PC ya lo empezo
        // el de un dia anterior que nadie termino se anula: si no, bloquea el inventario completo de ese almacen.
        // Fuera de la transaccion: sus candados de rango + dos clics a la vez = deadlock.
        log_exec($bd, "UPDATE inventario_conteo SET estado = 'anulado', fecha_anula = NOW(), idusuario_anula = ?
                WHERE idsede = ? AND origen = 'rapido' AND estado = 'borrador' AND fecha_crea < CURDATE()", array((int)$us, (int)$idsede));
        $bd->bd->begin_transaction();
        try {
            // abierto_key (migracion 028) = un conteo 'borrador' o 'cerrado' (sin aprobar/descartar) por sede+almacen
            $ab = log_filas($bd, "SELECT origen, (fecha_crea >= CURDATE()) + 0 AS hoy, DATE_FORMAT(fecha_crea, '%d/%m/%Y') AS dia
                    FROM inventario_conteo WHERE abierto_key = ?", array($idsede . '-' . $alm));
            if ($ab) {
                // doble clic: el 2.o request ve el rapido de hoy recien creado por el 1.o -> se devuelve ese mismo
                if ($ab[0]['origen'] === 'rapido' && (int)$ab[0]['hoy'] === 1) { $bd->bd->rollback(); return conteo_hoy($bd, $idsede); }
                if ($ab[0]['origen'] === 'rapido') {
                    throw new ErrorUsuario('La central aún no revisa el conteo rápido del ' . $ab[0]['dia'] . '. Cuando lo apruebe o descarte, podrás hacer el de hoy.');
                }
                throw new ErrorUsuario('Hay un inventario completo abierto en este almacén. Termínalo en Inventario; el conteo rápido vuelve cuando se cierre.');
            }
            log_exec($bd, "INSERT INTO inventario_conteo (idorg, idsede, idalmacen, tipo, ciego, obs, idusuario_crea, fecha_crea, origen)
                    VALUES (?, ?, ?, 'producto', 1, 'CONTEO RÁPIDO', ?, NOW(), 'rapido')", array((int)$ido, (int)$idsede, $alm, (int)$us));
            $id = (int)$bd->bd->insert_id;
            // producto que llego por traslado: su copia de sede tiene costo 0 -> se usa el de la central (como mm_costo_central)
            $costo = "COALESCE(NULLIF(ps.costo_promedio, 0), NULLIF(p.precio_unitario + 0, 0),
                (SELECT IFNULL(NULLIF(psc.costo_promedio, 0), pc.precio_unitario + 0) FROM log_producto_central v
                  JOIN producto pc ON pc.idproducto = v.idproducto_central
                  LEFT JOIN log_sede ls ON ls.idsede = pc.idsede
                  LEFT JOIN producto_stock psc ON psc.idalmacen = ls.idalmacen_principal AND psc.idproducto = pc.idproducto
                  WHERE v.idproducto = p.idproducto LIMIT 1), 0)";
            // primero lo que no se conto en 30 dias; entre esos, lo de mas valor en stock (donde mas duele una diferencia)
            $n = log_exec($bd, "INSERT INTO inventario_conteo_detalle
                    (idinventario_conteo, idp, procede, descripcion, familia, unidad, stock_sistema, costo_unit, costo_origen)
                SELECT ?, p.idproducto, 0, p.descripcion, pf.descripcion, um.abreviatura, ps.stock, $costo, IF($costo > 0, 'promedio', 'ninguno')
                FROM producto_stock ps
                JOIN producto p ON p.idproducto = ps.idproducto AND p.estado = 0
                LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex
                LEFT JOIN (SELECT d.idp, MAX(d.fecha_hora) AS f FROM inventario_conteo_detalle d
                           JOIN inventario_conteo c ON c.idinventario_conteo = d.idinventario_conteo
                           WHERE c.idsede = ? AND c.estado IN ('cerrado', 'aprobado') AND d.procede = 0 AND d.contado IS NOT NULL GROUP BY d.idp) u ON u.idp = p.idproducto
                WHERE ps.idalmacen = ? AND ps.estado = 0 AND ps.stock > 0
                ORDER BY (u.f IS NOT NULL AND u.f > NOW() - INTERVAL " . CONTEO_RECONTAR_DIAS . " DAY), ps.stock * $costo DESC, p.descripcion
                LIMIT " . (int)$cfg['cantidad'], array($id, (int)$idsede, $alm));
            if (!$n) { throw new ErrorUsuario('Tu almacén principal no tiene productos con stock para contar.'); }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            if (strpos($e->getMessage(), 'ux_conteo_abierto') !== false) { $h = conteo_hoy($bd, $idsede); if ($h) { return $h; } }
            throw $e;
        }
        return conteo_hoy($bd, $idsede);
    });

case 'terminar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        conteo_requiere($bd, $ido, $idsede, $us);
        $b = log_body();
        $id = isset($b['id']) ? (int)$b['id'] : 0;
        $c = log_filas($bd, "SELECT estado FROM inventario_conteo WHERE idinventario_conteo = ? AND idsede = ? AND origen = 'rapido'", array($id, (int)$idsede));
        if (!$c) { throw new ErrorUsuario('Conteo no encontrado.'); }
        if ($c[0]['estado'] !== 'borrador') { return conteo_hoy($bd, $idsede); }   // ya terminado (doble clic)
        $dets = array_column(log_filas($bd, "SELECT idinventario_conteo_detalle AS d FROM inventario_conteo_detalle WHERE idinventario_conteo = ?", array($id)), 'd');
        $filas = array();
        foreach (isset($b['filas']) && is_array($b['filas']) ? $b['filas'] : array() as $f) {
            $v = isset($f['contado']) && is_scalar($f['contado']) ? trim((string)$f['contado']) : '';
            if ($v === '' || !is_numeric($v) || $v < 0 || $v > 99999) { throw new ErrorUsuario('Escribe cuánto contaste de cada producto (0 si no hay).'); }
            $filas[(int)$f['iddet']] = round((float)$v, 4);
        }
        if (array_diff($dets, array_keys($filas))) { throw new ErrorUsuario('Escribe cuánto contaste de cada producto (0 si no hay).'); }
        $bd->bd->begin_transaction();
        try {
            // doble clic: el segundo espera aqui al primero y luego ve que ya esta cerrado
            $e = log_filas($bd, "SELECT estado FROM inventario_conteo WHERE idinventario_conteo = ? FOR UPDATE", array($id));
            if ($e[0]['estado'] !== 'borrador') { $bd->bd->rollback(); return conteo_hoy($bd, $idsede); }
            foreach ($dets as $d) {
                log_exec($bd, "UPDATE inventario_conteo_detalle SET contado = ?, idusuario_cuenta = ?, fecha_hora = NOW()
                        WHERE idinventario_conteo_detalle = ? AND idinventario_conteo = ?", array($filas[$d], (int)$us, (int)$d, $id));
            }
            conteo_sp($bd, "CALL sp_inventario_cerrar($id, " . (int)$idsede . ", " . (int)$us . ")");
            $bd->bd->commit();
        } catch (Throwable $e) { $bd->bd->rollback(); throw $e; }
        return conteo_hoy($bd, $idsede);
    });

case 'resumen':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $sedes = log_filas($bd, "SELECT ls.idsede, s.nombre AS sede FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                WHERE ls.idorg = ? AND ls.activo = 1 ORDER BY ls.idsede <> ?, s.nombre", array((int)$ido, (int)$org['idsede_central']));
        $conteos = log_filas($bd, "SELECT c.idinventario_conteo AS id, c.idsede, s.nombre AS sede, c.estado, DATE_FORMAT(c.fecha_crea, '%d/%m/%Y') AS fecha,
                    (c.fecha_crea >= CURDATE()) + 0 AS hoy, u.nombres AS usuario
                FROM inventario_conteo c JOIN sede s ON s.idsede = c.idsede LEFT JOIN usuario u ON u.idusuario = c.idusuario_cierre
                WHERE c.idorg = ? AND c.origen = 'rapido' AND c.estado IN ('borrador', 'cerrado', 'aprobado') AND c.fecha_crea >= CURDATE() - INTERVAL 6 DAY
                ORDER BY c.estado = 'cerrado' DESC, c.fecha_crea DESC", array((int)$ido));
        foreach ($conteos as &$c) {
            $c['items'] = $c['estado'] === 'borrador' ? array() : conteo_items($bd, (int)$c['id'], true);
            $c['con_diferencia'] = count(array_filter($c['items'], function ($i) { return $i['diferencia'] != 0; }));
            $c['valor'] = round(array_sum(array_column($c['items'], 'valor')), 2);
        }
        unset($c);
        $hoy = array();
        foreach ($conteos as $c) { if ($c['hoy']) { $hoy[(int)$c['idsede']] = $c['estado']; } }
        foreach ($sedes as &$s) { $s['hoy'] = isset($hoy[(int)$s['idsede']]) ? $hoy[(int)$s['idsede']] : null; }
        unset($s);
        return conteo_cfg($bd, $ido) + array('sedes' => $sedes, 'conteos' => $conteos);
    });

case 'aprobar':
case 'descartar':
    log_responder(function () use ($bd, $ido, $idsede, $us, $op) {
        log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $id = isset($b['id']) ? (int)$b['id'] : 0;
        $c = log_filas($bd, "SELECT idsede, estado FROM inventario_conteo WHERE idinventario_conteo = ? AND idorg = ? AND origen = 'rapido'", array($id, (int)$ido));
        if (!$c) { throw new ErrorUsuario('Conteo no encontrado.'); }
        if ($c[0]['estado'] !== 'cerrado') { throw new ErrorUsuario($c[0]['estado'] === 'borrador' ? 'La sede todavía no termina de contar.' : 'Ese conteo ya fue revisado.'); }
        if ($op === 'descartar') {
            log_exec($bd, "UPDATE inventario_conteo SET estado = 'anulado', fecha_anula = NOW(), idusuario_anula = ? WHERE idinventario_conteo = ? AND estado = 'cerrado'", array((int)$us, $id));
            return array('estado' => 'anulado');
        }
        $u = log_filas($bd, "SELECT rol FROM usuario WHERE idusuario = ?", array((int)$us));
        if (!$u || (int)$u[0]['rol'] !== 1) { throw new ErrorUsuario('Solo un administrador puede ajustar el stock.'); }
        $bd->bd->begin_transaction();
        try {
            conteo_sp($bd, "CALL sp_inventario_aprobar($id, " . (int)$c[0]['idsede'] . ", " . (int)$us . ")");
            $bd->bd->commit();
        } catch (Throwable $e) { $bd->bd->rollback(); throw $e; }
        return array('estado' => 'aprobado');
    });

default:
    log_out(false, null, 'Operación no válida.');
}
