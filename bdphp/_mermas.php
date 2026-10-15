<?php
/**
 * Mermas y perdidas (migracion 071). Lo usan el modulo Inventario (log_inventario.php, por sede) y Logistica
 * (abastecimiento/mermas.php, todas las sedes). Una sola fuente para "cuanto se pierde y por que":
 *  - mermas registradas aqui (inventario_merma),
 *  - salidas viejas de la pantalla de almacen con motivo DETERIORO / CONSUMO DEL PERSONAL / MERMA,
 *  - faltantes de conteos aprobados (inventario_conteo).
 * Errores para el usuario: MermaError (el llamador lo muestra tal cual).
 * Incluir con require_once.
 */

date_default_timezone_set('America/Lima');   // mm_rango usa date(): mismo reloj que log.php

class MermaError extends Exception {}

const MM_DEC = 3;   // decimales de cantidad: el mismo en round() y en el texto que va a almacen_ie_detalle/producto_historial

const MM_MOTIVOS = array('roto' => 'SE ROMPIÓ', 'vencido' => 'SE VENCIÓ', 'mal_estado' => 'MAL ESTADO', 'consumo' => 'CONSUMO DEL PERSONAL', 'otro' => 'OTRO');
// salidas de la pantalla de almacen (op 1806) que cuentan como perdida, y en que columna caen
const MM_LEGACY = array('DETERIORO' => 'mal_estado', 'CONSUMO DEL PERSONAL' => 'consumo', 'MERMA' => 'otro');
const MM_PERIODOS = array('mes', '7', '30', 'mes_ant');

function mm_filas($bd, $sql, $p = array()) {
    $bd->prepare($sql);
    $bd->execute($p ? $p : null);
    if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
    return $bd->fetchAll();
}

function mm_exec($bd, $sql, $p = array()) {
    $bd->prepare($sql);
    $bd->execute($p ? $p : null);
    if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
    return $bd->stmt->affected_rows;
}

function mm_num($n) {
    $t = rtrim(rtrim(number_format((float)$n, MM_DEC, '.', ''), '0'), '.');
    return $t === '' || $t === '-0' ? '0' : $t;
}

/** [desde, hasta) del periodo: mes actual, ultimos 7/30 dias o mes pasado. */
function mm_rango($p) {
    if ($p === '7' || $p === '30') { return array(date('Y-m-d', strtotime('-' . ((int)$p - 1) . ' days')), date('Y-m-d', strtotime('+1 day'))); }
    if ($p === 'mes_ant') { return array(date('Y-m-01', strtotime('first day of last month')), date('Y-m-01')); }
    return array(date('Y-m-01'), date('Y-m-d', strtotime('+1 day')));
}

function mm_almacenes($bd, $idsede) {
    return mm_filas($bd, "SELECT idalmacen, descripcion FROM almacen WHERE idsede = ? AND estado = 0 ORDER BY descripcion", array((int)$idsede));
}

/** Productos con stock en ese almacen de la sede. */
function mm_productos($bd, $idsede, $idalm) {
    return array_map(function ($f) {
        return array('idproducto' => (int)$f['idproducto'], 'descripcion' => $f['descripcion'], 'unidad' => $f['unidad'], 'stock' => (float)$f['stock']);
    }, mm_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(um.abreviatura, '') AS unidad, ps.stock
            FROM producto_stock ps JOIN producto p ON p.idproducto = ps.idproducto AND p.estado = 0 AND p.idsede = ?
            LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex
            WHERE ps.idalmacen = ? AND ps.estado = 0 AND ps.stock > 0 ORDER BY p.descripcion", array((int)$idsede, (int)$idalm)));
}

/**
 * Registra la merma y baja el stock. $b = {idproducto, cantidad, motivo, nota, token}.
 * Devuelve {producto, cantidad, valor}. Abre y cierra su propia transaccion.
 */
function mm_registrar($bd, $ido, $idsede, $us, $idalm, $b) {
    $prod = isset($b['idproducto']) ? (int)$b['idproducto'] : 0;
    $cant = isset($b['cantidad']) && is_numeric($b['cantidad']) ? round((float)$b['cantidad'], MM_DEC) : 0;
    $mot = isset($b['motivo']) && is_string($b['motivo']) && array_key_exists($b['motivo'], MM_MOTIVOS) ? $b['motivo'] : '';
    $nota = isset($b['nota']) && is_scalar($b['nota']) ? mb_strtoupper(mb_substr(trim((string)$b['nota']), 0, 150, 'UTF-8'), 'UTF-8') : '';
    $token = isset($b['token']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$b['token']), 0, 64) : '';
    if (!$prod) { throw new MermaError('Elige el producto de la lista.'); }
    if ($cant <= 0 || $cant > 999999) { throw new MermaError('Escribe cuánto se perdió.'); }
    if ($mot === '') { throw new MermaError('Elige por qué se perdió.'); }
    if ($mot === 'otro' && $nota === '') { throw new MermaError('Si el motivo es "Otro", escribe qué pasó.'); }
    if (!mm_filas($bd, "SELECT 1 FROM almacen WHERE idalmacen = ? AND idsede = ? AND estado = 0", array((int)$idalm, (int)$idsede))) {
        throw new MermaError('Elige un almacén de tu sede.');
    }
    $p = mm_filas($bd, "SELECT descripcion FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ? AND estado = 0", array($prod, (int)$ido, (int)$idsede));
    if (!$p) { throw new MermaError('Ese producto no es de tu sede.'); }
    $bd->bd->begin_transaction();
    try {
        // doble clic: el token es UNIQUE; el segundo envio espera al primero y recibe su resultado
        $bd->prepare("INSERT INTO inventario_merma (idorg, idsede, idalmacen, idproducto, cantidad, motivo, nota, idusuario, fecha_hora, token)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $bd->execute(array((int)$ido, (int)$idsede, (int)$idalm, $prod, $cant, $mot, $nota !== '' ? $nota : null, (int)$us, $token !== '' ? $token : null));
        if ($bd->stmt->errno === 1062) {
            $bd->bd->rollback();
            $r = mm_filas($bd, "SELECT m.cantidad, m.cantidad * m.costo_unit AS valor, p.descripcion FROM inventario_merma m JOIN producto p ON p.idproducto = m.idproducto
                    WHERE m.token = ?", array($token));
            if (!$r) { throw new MermaError('No se pudo registrar la merma, intenta de nuevo.'); }   // el otro envio se deshizo
            return array('producto' => $r[0]['descripcion'], 'cantidad' => (float)$r[0]['cantidad'], 'valor' => round((float)$r[0]['valor'], 2));
        }
        if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
        $idm = (int)$bd->lastInsertId();
        $st = mm_filas($bd, "SELECT stock, IFNULL(NULLIF(costo_promedio, 0), (SELECT IFNULL(precio_unitario + 0, 0) FROM producto WHERE idproducto = ?)) AS costo
                FROM producto_stock WHERE idproducto = ? AND idalmacen = ? ORDER BY idproducto_stock LIMIT 1 FOR UPDATE", array($prod, $prod, (int)$idalm));
        $hay = $st ? (float)$st[0]['stock'] : 0;
        if ($cant > $hay + 0.0001) { throw new MermaError('Solo hay ' . mm_num($hay) . ' de ' . $p[0]['descripcion'] . ' en ese almacén.'); }
        $costo = round((float)$st[0]['costo'], 4);
        if ($costo <= 0) { $costo = mm_costo_central($bd, $prod); }   // llego por traslado: la copia de la sede no tiene costo
        $motId = mm_filas($bd, "SELECT idalmacen_motivo_es AS id FROM almacen_motivo_es WHERE motivo = 'MERMA' AND tipo = 2 LIMIT 1");
        if (!$motId) {
            error_log('[mermas] falta el motivo MERMA (tipo 2) en almacen_motivo_es (migracion 071)');
            throw new MermaError('Falta configurar el motivo MERMA en el almacén. Avisa a soporte.');
        }
        $texto = 'MERMA: ' . MM_MOTIVOS[$mot] . ($nota !== '' ? ' - ' . $nota : '');
        mm_exec($bd, "INSERT INTO almacen_ie (idorg, idsede, idusuario, idalmacen, tipo, fecha, idproducto_motivo_es, motivo, fecha_hora, hora)
                VALUES (?, ?, ?, ?, '2', DATE_FORMAT(NOW(), '%d/%m/%Y'), ?, ?, NOW(), DATE_FORMAT(NOW(), '%H:%i:%s'))",
            array((int)$ido, (int)$idsede, (int)$us, (int)$idalm, (int)$motId[0]['id'], mb_substr($texto, 0, 120, 'UTF-8')));
        $idie = (int)$bd->lastInsertId();
        // el trigger stock_insert_almacen_i descuenta (cantidad negativa = salida, como op 1806)
        mm_exec($bd, "INSERT INTO almacen_ie_detalle (idalmacen_ie, idp, procede, cantidad) VALUES (?, ?, 0, ?)", array($idie, $prod, mm_num(-$cant)));
        mm_exec($bd, "INSERT INTO producto_historial (tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen, stock_total)
                SELECT 'MERMA', CURDATE(), DATE_FORMAT(NOW(), '%h:%i %p'), ?, ?, ?, ?, ?, ps.stock FROM producto_stock ps
                WHERE ps.idproducto = ? AND ps.idalmacen = ? ORDER BY ps.idproducto_stock LIMIT 1",
            array(mm_num($cant), (int)$us, (int)$idsede, $prod, (int)$idalm, $prod, (int)$idalm));
        mm_exec($bd, "UPDATE inventario_merma SET costo_unit = ?, idalmacen_ie = ? WHERE idinventario_merma = ?", array($costo, $idie, $idm));
        mm_lotes($bd, $prod, (int)$idalm, $cant, $idie);
        $bd->bd->commit();
    } catch (Throwable $e) { $bd->bd->rollback(); throw $e; }
    return array('producto' => $p[0]['descripcion'], 'cantidad' => $cant, 'valor' => round($cant * $costo, 2));
}

/**
 * Costo del producto central (Logistica) de una copia de sede: costo promedio en el almacen principal de la central
 * o, si no hay, su precio_unitario (ultimo costo de compra). 0 si no es copia o la empresa no usa Logistica.
 * Por PK: log_producto_central (idproducto) -> producto -> log_sede (idsede) -> producto_stock uq (idproducto, idalmacen).
 */
function mm_costo_central($bd, $prod) {
    static $hay = null;
    if ($hay === null) { $hay = (bool)mm_filas($bd, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'log_producto_central'"); }
    if (!$hay) { return 0; }
    $r = mm_filas($bd, "SELECT IFNULL(NULLIF((SELECT ps.costo_promedio FROM log_sede ls JOIN producto_stock ps ON ps.idalmacen = ls.idalmacen_principal
                WHERE ls.idsede = pc.idsede AND ps.idproducto = pc.idproducto LIMIT 1), 0), IFNULL(pc.precio_unitario + 0, 0)) AS costo
            FROM log_producto_central v JOIN producto pc ON pc.idproducto = v.idproducto_central WHERE v.idproducto = ?", array((int)$prod));
    return $r ? round((float)$r[0]['costo'], 4) : 0;
}

/** Lotes de Logistica (si la empresa los usa): baja primero lo que vence antes, vencidos incluidos. */
function mm_lotes($bd, $prod, $idalm, $cant, $idie) {
    static $hay = null;
    if ($hay === null) { $hay = (bool)mm_filas($bd, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'log_lote_mov'"); }
    if (!$hay) { return; }
    $c = mm_filas($bd, "SELECT idproducto_central FROM log_producto_central WHERE idproducto = ?", array($prod));
    $idc = $c ? (int)$c[0]['idproducto_central'] : $prod;
    $falta = $cant;
    foreach (mm_filas($bd, "SELECT l.idlote, SUM(m.cantidad) AS saldo FROM log_lote l JOIN log_lote_mov m ON m.idlote = l.idlote AND m.idalmacen = ?
            WHERE l.idproducto_central = ? GROUP BY l.idlote HAVING saldo > 0 ORDER BY l.vencimiento IS NULL, l.vencimiento, l.idlote", array($idalm, $idc)) as $l) {
        if ($falta <= 0) { break; }
        $q = min($falta, (float)$l['saldo']);
        mm_exec($bd, "INSERT INTO log_lote_mov (idlote, idalmacen, cantidad, origen, iddoc) VALUES (?, ?, ?, 'ajuste', ?)", array((int)$l['idlote'], $idalm, -$q, $idie));
        $falta -= $q;
    }
}

/**
 * Perdidas de una sede ($idsede) o de toda la org ($idsede = 0) en [desde, hasta).
 * Devuelve filas [{idsede, sede, fecha, producto, unidad, cantidad, motivo, nota, usuario, almacen, origen, valor}]
 * origen: 'merma' (registrada aqui) | 'almacen' (salida vieja de almacen) | 'conteo' (falto al contar).
 */
function mm_perdidas($bd, $ido, $idsede, $rango) {
    $w = $idsede ? 'idsede = ?' : 'idorg = ?';
    $k = $idsede ? (int)$idsede : (int)$ido;
    list($d, $h) = $rango;
    $r = array();
    foreach (mm_filas($bd, "SELECT m.idsede, s.nombre AS sede, DATE_FORMAT(m.fecha_hora, '%d/%m/%Y %h:%i %p') AS fecha, m.fecha_hora AS orden,
                p.descripcion AS producto, IFNULL(um.abreviatura, '') AS unidad, m.cantidad, m.motivo, m.nota, u.nombres AS usuario,
                a.descripcion AS almacen, m.cantidad * m.costo_unit AS valor
            FROM inventario_merma m JOIN sede s ON s.idsede = m.idsede JOIN producto p ON p.idproducto = m.idproducto
            LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex LEFT JOIN usuario u ON u.idusuario = m.idusuario
            LEFT JOIN almacen a ON a.idalmacen = m.idalmacen
            WHERE m.$w AND m.fecha_hora >= ? AND m.fecha_hora < ? AND m.idalmacen_ie IS NOT NULL", array($k, $d, $h)) as $f) {
        $r[] = $f + array('origen' => 'merma');
    }
    // salidas de la pantalla de almacen (las de esta pantalla ya estan arriba)
    foreach (mm_filas($bd, "SELECT a.idsede, s.nombre AS sede, DATE_FORMAT(a.fecha_hora, '%d/%m/%Y %h:%i %p') AS fecha, a.fecha_hora AS orden,
                p.descripcion AS producto, IFNULL(um.abreviatura, '') AS unidad, ABS(d.cantidad + 0) AS cantidad, mo.motivo AS mot_legacy,
                NULLIF(TRIM(a.motivo), '') AS nota, u.nombres AS usuario, al.descripcion AS almacen,
                ABS(d.cantidad + 0) * IFNULL(NULLIF(ps.costo_promedio, 0), IFNULL(p.precio_unitario + 0, 0)) AS valor
            FROM almacen_ie a JOIN almacen_motivo_es mo ON mo.idalmacen_motivo_es = a.idproducto_motivo_es AND mo.motivo IN ('DETERIORO', 'CONSUMO DEL PERSONAL', 'MERMA')
            JOIN almacen_ie_detalle d ON d.idalmacen_ie = a.idalmacen_ie AND d.procede = 0
            JOIN producto p ON p.idproducto = d.idp JOIN sede s ON s.idsede = a.idsede
            LEFT JOIN producto_stock ps ON ps.idproducto = d.idp AND ps.idalmacen = a.idalmacen
            LEFT JOIN unidad_medida um ON um.idunidad_medida = p.idunidad_kardex LEFT JOIN usuario u ON u.idusuario = a.idusuario
            LEFT JOIN almacen al ON al.idalmacen = a.idalmacen
            WHERE a.$w AND a.tipo = '2' AND a.fecha_hora >= ? AND a.fecha_hora < ?
              AND NOT EXISTS (SELECT 1 FROM inventario_merma im WHERE im.idalmacen_ie = a.idalmacen_ie)", array($k, $d, $h)) as $f) {
        $f['motivo'] = MM_LEGACY[$f['mot_legacy']];
        unset($f['mot_legacy']);
        $r[] = $f + array('origen' => 'almacen');
    }
    // lo que falto al contar (conteos completos y rapidos) y se ajusto
    foreach (mm_filas($bd, "SELECT c.idsede, s.nombre AS sede, DATE_FORMAT(c.fecha_aprob, '%d/%m/%Y %h:%i %p') AS fecha, c.fecha_aprob AS orden,
                d.descripcion AS producto, IFNULL(d.unidad, '') AS unidad, d.stock_cierre - d.contado AS cantidad,
                CONCAT('CONTEO #', c.idinventario_conteo) AS nota, u.nombres AS usuario, IFNULL(a.descripcion, 'PORCIONES') AS almacen,
                (d.stock_cierre - d.contado) * d.costo_unit AS valor
            FROM inventario_conteo c JOIN inventario_conteo_detalle d ON d.idinventario_conteo = c.idinventario_conteo JOIN sede s ON s.idsede = c.idsede
            LEFT JOIN usuario u ON u.idusuario = c.idusuario_aprob LEFT JOIN almacen a ON a.idalmacen = c.idalmacen
            WHERE c.$w AND c.estado = 'aprobado' AND c.fecha_aprob >= ? AND c.fecha_aprob < ? AND d.aplicar = 1 AND d.contado < d.stock_cierre",
            array($k, $d, $h)) as $f) {
        $r[] = $f + array('motivo' => 'conteo', 'origen' => 'conteo');
    }
    usort($r, function ($a, $b) { return strcmp($b['orden'], $a['orden']); });
    return array_map(function ($f) {
        unset($f['orden']);
        return array('idsede' => (int)$f['idsede'], 'cantidad' => round((float)$f['cantidad'], MM_DEC), 'valor' => round((float)$f['valor'], 2)) + $f;
    }, $r);
}

/** Totales por sede y motivo (columnas: los 5 motivos + 'conteo'), de mayor a menor perdida. */
function mm_resumen($filas) {
    $s = array();
    foreach ($filas as $f) {
        $k = $f['idsede'];
        if (!isset($s[$k])) { $s[$k] = array('idsede' => $k, 'sede' => $f['sede'], 'motivos' => array(), 'total' => 0); }
        $m = $f['motivo'];
        $s[$k]['motivos'][$m] = round((isset($s[$k]['motivos'][$m]) ? $s[$k]['motivos'][$m] : 0) + $f['valor'], 2);
        $s[$k]['total'] = round($s[$k]['total'] + $f['valor'], 2);
    }
    $s = array_values($s);
    usort($s, function ($a, $b) { return $b['total'] <=> $a['total']; });
    return $s;
}
