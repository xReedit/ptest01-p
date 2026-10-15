<?php
/**
 * Logistica — Catalogo > Porciones (central). Las porciones de la central son el modelo para todas las sedes:
 * "HELADO DE COCO sale del BOTE HELADO COCO: 1 bote = 20 porciones". Al enviarlas, cada sede enlaza la suya (o se crea)
 * con el mismo codigo unico (porcion_codigo_unico, el de la pantalla Porciones) y la misma conversion.
 * ?op=lista | guardar | sedes&idporcion= | enviar
 * La recepcion convierte con log_producto_porcion (migracion 028) y encuentra la porcion de la sede por porcion.idproducto_de.
 * Consultas por indice: porcion(idx_porcion_idsede: idsede, estado), log_sede(ix_log_sede_org), log_producto_central(uq_lpc_sede_central).
 */
require_once __DIR__ . '/_logistica.php';

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

/** Porcion de la central (activa) o error. */
function porcion_central($bd, $org, $idporcion) {
    $r = log_filas($bd, "SELECT po.idporcion, po.descripcion, po.peso, po.idproducto_de, po.idporcion_codigo_unico AS idcod,
            (SELECT c.factor_conversion + 0 FROM porcion_unidad_conversion c WHERE c.idporcion = po.idporcion AND c.activo = 1 LIMIT 1) AS factor,
            (SELECT c.nombre_unidad_alternativa FROM porcion_unidad_conversion c WHERE c.idporcion = po.idporcion AND c.activo = 1 LIMIT 1) AS unidad_conv
            FROM porcion po WHERE po.idporcion = ? AND po.idsede = ? AND po.estado = 0", array((int)$idporcion, (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Esa porción ya no está en la central. Actualiza la página.'); }
    return $r[0];
}

/** Sedes que participan (sin la central). */
function sedes_porcion($bd, $org) {
    return log_filas($bd, "SELECT ls.idsede, s.nombre FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre", array((int)$org['idorg'], (int)$org['idsede_central']));
}

/** Codigo unico de la porcion de la central; si no tiene, se crea como en la pantalla Porciones (INICIALES-001). */
function codigo_de($bd, $ido, $p) {
    if ((int)$p['idcod'] > 0) { return (int)$p['idcod']; }
    $ult = log_filas($bd, "SELECT MAX(CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED)) AS n FROM porcion_codigo_unico WHERE idorg = ? AND estado = 0 FOR UPDATE",
        array((int)$ido))[0]['n'];
    $ini = '';
    foreach (preg_split('/\s+/', trim($p['descripcion'])) as $w) { $ini .= mb_substr($w, 0, 1, 'UTF-8'); }
    $codigo = mb_substr(preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($ini, 'UTF-8')) ?: 'P', 0, 6, 'UTF-8') . '-' . str_pad((string)((int)$ult + 1), 3, '0', STR_PAD_LEFT);
    log_exec($bd, "INSERT INTO porcion_codigo_unico (codigo, idorg, estado, fecha_creacion) VALUES (?, ?, 0, NOW())", array($codigo, (int)$ido));
    $id = (int)$bd->lastInsertId();
    log_exec($bd, "UPDATE porcion SET idporcion_codigo_unico = ? WHERE idporcion = ?", array($id, (int)$p['idporcion']));
    return $id;
}

/** Conversion activa de una porcion: crea o corrige el factor (una sola activa por porcion, como la pantalla Porciones). */
function conversion_igual($bd, $idporcion, $nombre, $factor) {
    $c = log_filas($bd, "SELECT idporcion_unidad_conversion AS id, factor_conversion + 0 AS f FROM porcion_unidad_conversion WHERE idporcion = ? AND activo = 1 LIMIT 1",
        array((int)$idporcion));
    if (!$c) {
        log_exec($bd, "INSERT INTO porcion_unidad_conversion (idporcion, nombre_unidad_alternativa, factor_conversion, activo) VALUES (?, ?, ?, 1)",
            array((int)$idporcion, mb_substr($nombre, 0, 100, 'UTF-8'), $factor));
    } elseif (abs((float)$c[0]['f'] - $factor) > 0.0001) {
        log_exec($bd, "UPDATE porcion_unidad_conversion SET factor_conversion = ? WHERE idporcion_unidad_conversion = ?", array($factor, (int)$c[0]['id']));
    }
}

/** Copia del producto "sale de" en la sede (0 si la sede aun no lo tiene). */
function producto_sede($bd, $idcentral, $idsede) {
    if (!$idcentral) { return 0; }
    $r = log_filas($bd, "SELECT idproducto FROM log_producto_central WHERE idsede = ? AND idproducto_central = ?", array((int)$idsede, (int)$idcentral));
    return $r ? (int)$r[0]['idproducto'] : 0;
}

switch ($op) {
case 'lista':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $cen = (int)$org['idsede_central'];
        $porc = log_filas($bd, "SELECT po.idporcion, po.descripcion, po.peso, po.stock + 0 AS stock, po.idproducto_de AS idproducto,
                IFNULL(pr.descripcion, '') AS producto, IFNULL(pcu.codigo, '') AS codigo, po.idporcion_codigo_unico AS idcod,
                c.factor_conversion + 0 AS factor, IFNULL(c.nombre_unidad_alternativa, '') AS unidad_conv
                FROM porcion po
                LEFT JOIN producto pr ON pr.idproducto = po.idproducto_de AND pr.idsede = po.idsede AND pr.estado = 0
                LEFT JOIN porcion_codigo_unico pcu ON pcu.idporcion_codigo_unico = po.idporcion_codigo_unico AND pcu.estado = 0
                LEFT JOIN porcion_unidad_conversion c ON c.idporcion = po.idporcion AND c.activo = 1
                WHERE po.idsede = ? AND po.estado = 0 GROUP BY po.idporcion ORDER BY po.descripcion", array($cen));
        // sedes enlazadas por codigo unico, en una sola pasada por las porciones de las sedes de la org
        $en = array();
        foreach (log_filas($bd, "SELECT po.idporcion_codigo_unico AS idcod, COUNT(DISTINCT po.idsede) AS n FROM log_sede ls
                JOIN porcion po ON po.idsede = ls.idsede AND po.estado = 0 AND po.idporcion_codigo_unico > 0
                WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? GROUP BY po.idporcion_codigo_unico", array((int)$ido, $cen)) as $f) {
            $en[(int)$f['idcod']] = (int)$f['n'];
        }
        foreach ($porc as &$p) {
            $p['en_sedes'] = (int)$p['idcod'] > 0 && isset($en[(int)$p['idcod']]) ? $en[(int)$p['idcod']] : 0;
            $p['factor'] = $p['factor'] !== null ? (float)$p['factor'] : null;
            $p['idproducto'] = $p['producto'] !== '' ? (int)$p['idproducto'] : 0;
            unset($p['idcod']);
        }
        unset($p);
        $prods = log_filas($bd, "SELECT idproducto, descripcion FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion",
            array((int)$ido, $cen));
        return array('porciones' => $porc, 'productos' => $prods, 'sedes_total' => count(sedes_porcion($bd, $org)));
    });

case 'guardar':
    // {idporcion (0 = nueva), descripcion (solo nueva), idproducto (sale de; 0 = de ninguno), porciones (1 producto = N)}
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $cen = (int)$org['idsede_central'];
        $b = log_body();
        $id = isset($b['idporcion']) ? (int)$b['idporcion'] : 0;
        $idp = isset($b['idproducto']) ? (int)$b['idproducto'] : 0;
        $txt = str_replace(',', '.', trim(isset($b['porciones']) && is_scalar($b['porciones']) ? (string)$b['porciones'] : ''));
        $prod = null;
        if ($idp) {
            $prod = log_filas($bd, "SELECT idproducto, descripcion FROM producto WHERE idproducto = ? AND idorg = ? AND idsede = ? AND estado = 0", array($idp, (int)$ido, $cen));
            if (!$prod) { throw new ErrorUsuario('Ese producto no está en el catálogo. Elige otro.'); }
            $prod = $prod[0];
            if (!is_numeric($txt) || (float)$txt < 1 || (float)$txt > 10000) {
                throw new ErrorUsuario('Escribe cuántas porciones salen de 1 ' . $prod['descripcion'] . ' (un número entre 1 y 10000).');
            }
        }
        $factor = $idp ? round((float)$txt, 3) : 0;
        $bd->bd->begin_transaction();
        try {
            if (!$id) {
                $desc = log_mayus(isset($b['descripcion']) ? $b['descripcion'] : '', 180);
                if ($desc === '') { throw new ErrorUsuario('Escribe el nombre de la porción.'); }
                if (log_filas($bd, "SELECT 1 FROM porcion WHERE idsede = ? AND estado = 0 AND UPPER(TRIM(descripcion)) = ? FOR UPDATE", array($cen, $desc))) {
                    throw new ErrorUsuario('Ya existe la porción ' . $desc . ' en la central.');
                }
                log_exec($bd, "INSERT INTO porcion (idorg, idsede, idproducto_de, descripcion, peso, stock, estado) VALUES (?, ?, 0, ?, '0', 0, 0)",
                    array((int)$ido, $cen, $desc));
                $id = (int)$bd->lastInsertId();
            }
            $p = porcion_central($bd, $org, $id);
            if ($idp) {
                $otra = log_filas($bd, "SELECT descripcion FROM porcion WHERE idsede = ? AND estado = 0 AND idproducto_de = ? AND idporcion <> ?", array($cen, $idp, $id));
                if ($otra) { throw new ErrorUsuario($prod['descripcion'] . ' ya se convierte en la porción ' . $otra[0]['descripcion'] . '. Un producto se convierte en una sola porción.'); }
            }
            $antes = (int)$p['idproducto_de'];
            if ($antes && $antes !== $idp) { log_exec($bd, "DELETE FROM log_producto_porcion WHERE idproducto_central = ? AND idorg = ?", array($antes, (int)$ido)); }
            log_exec($bd, "UPDATE porcion SET idproducto_de = ? WHERE idporcion = ?", array($idp, $id));
            if ($idp) {
                conversion_igual($bd, $id, $prod['descripcion'], $factor);
                log_exec($bd, "INSERT INTO log_producto_porcion (idproducto_central, idorg, porciones) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE porciones = VALUES(porciones)",
                    array($idp, (int)$ido, $factor));
                // las sedes ya enlazadas (mismo codigo) toman el mismo numero
                if ((int)$p['idcod'] > 0) {
                    foreach (log_filas($bd, "SELECT po.idporcion FROM log_sede ls JOIN porcion po ON po.idsede = ls.idsede AND po.estado = 0
                            WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? AND po.idporcion_codigo_unico = ?", array((int)$ido, $cen, (int)$p['idcod'])) as $s) {
                        conversion_igual($bd, $s['idporcion'], $prod['descripcion'], $factor);
                    }
                }
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('idporcion' => $id);
    });

case 'sedes':
    // para el dialogo "Enviar a las sedes": por sede, la porcion ya enlazada o la lista de las suyas para elegir
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $p = porcion_central($bd, $org, isset($_GET['idporcion']) ? (int)$_GET['idporcion'] : 0);
        $r = array();
        foreach (sedes_porcion($bd, $org) as $s) {
            $todas = log_filas($bd, "SELECT idporcion, descripcion, idproducto_de, idporcion_codigo_unico AS idcod FROM porcion
                    WHERE idsede = ? AND estado = 0 ORDER BY descripcion", array((int)$s['idsede']));
            $ps = producto_sede($bd, (int)$p['idproducto_de'], (int)$s['idsede']);
            $ya = null;
            foreach ($todas as $t) {
                if (((int)$p['idcod'] > 0 && (int)$t['idcod'] === (int)$p['idcod']) || ($ps && (int)$t['idproducto_de'] === $ps)) { $ya = $t; break; }
            }
            // libres: las que no son "la misma" de otra porcion de la central (otro codigo)
            $libres = array_values(array_filter($todas, function ($t) { return (int)$t['idcod'] === 0; }));
            $r[] = array('idsede' => (int)$s['idsede'], 'sede' => $s['nombre'],
                'enlazada' => $ya ? array('idporcion' => (int)$ya['idporcion'], 'descripcion' => $ya['descripcion']) : null,
                'porciones' => array_map(function ($t) { return array('idporcion' => (int)$t['idporcion'], 'descripcion' => $t['descripcion']); }, $libres));
        }
        return array('porcion' => $p['descripcion'], 'sedes' => $r);
    });

case 'enviar':
    // {idporcion, sedes:[{idsede, idporcion}]}: idporcion de la sede = enlazar esa; 0 = crear la porcion en la sede
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $validas = array();
        foreach (sedes_porcion($bd, $org) as $s) { $validas[(int)$s['idsede']] = $s['nombre']; }
        $res = array('creadas' => 0, 'enlazadas' => 0);
        $bd->bd->begin_transaction();
        try {
            $p = porcion_central($bd, $org, isset($b['idporcion']) ? (int)$b['idporcion'] : 0);
            $cod = codigo_de($bd, $ido, $p);
            foreach (isset($b['sedes']) && is_array($b['sedes']) ? array_slice($b['sedes'], 0, 300) : array() as $x) {
                $s = is_array($x) && isset($x['idsede']) ? (int)$x['idsede'] : 0;
                if (!isset($validas[$s])) { throw new ErrorUsuario('Hay una sede que no participa en Logística. Actualiza la página.'); }
                $ps = producto_sede($bd, (int)$p['idproducto_de'], $s);
                $elegida = isset($x['idporcion']) ? (int)$x['idporcion'] : 0;
                if ($elegida) {
                    $t = log_filas($bd, "SELECT idporcion, idporcion_codigo_unico AS idcod FROM porcion WHERE idporcion = ? AND idsede = ? AND estado = 0 FOR UPDATE", array($elegida, $s));
                    if (!$t) { throw new ErrorUsuario('En ' . $validas[$s] . ' esa porción ya no existe. Actualiza la página.'); }
                    if ((int)$t[0]['idcod'] > 0 && (int)$t[0]['idcod'] !== $cod) {
                        throw new ErrorUsuario('En ' . $validas[$s] . ' esa porción ya está enlazada a otra porción de la central.');
                    }
                    // el bote que llega a la sede se convierte en esta porcion
                    if ($ps) { log_exec($bd, "UPDATE porcion SET idproducto_de = 0 WHERE idsede = ? AND idproducto_de = ? AND idporcion <> ?", array($s, $ps, $elegida)); }
                    log_exec($bd, "UPDATE porcion SET idporcion_codigo_unico = ?, idproducto_de = IF(? > 0, ?, idproducto_de) WHERE idporcion = ?", array($cod, $ps, $ps, $elegida));
                    $res['enlazadas']++;
                } else {
                    if (log_filas($bd, "SELECT 1 FROM porcion WHERE idsede = ? AND estado = 0 AND idporcion_codigo_unico = ?", array($s, $cod))) { continue; }   // ya la tiene
                    if ($ps) { log_exec($bd, "UPDATE porcion SET idproducto_de = 0 WHERE idsede = ? AND idproducto_de = ?", array($s, $ps)); }
                    log_exec($bd, "INSERT INTO porcion (idorg, idsede, idproducto_de, descripcion, peso, stock, estado, idporcion_codigo_unico) VALUES (?, ?, ?, ?, ?, 0, 0, ?)",
                        array((int)$ido, $s, $ps, $p['descripcion'], (string)$p['peso'], $cod));
                    $elegida = (int)$bd->lastInsertId();
                    $res['creadas']++;
                }
                if ($p['factor'] !== null) { conversion_igual($bd, $elegida, $p['unidad_conv'], (float)$p['factor']); }
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

default:
    log_out(false, null, 'Operación no válida.');
}
