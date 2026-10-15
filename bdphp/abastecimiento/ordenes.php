<?php
/**
 * Logistica F4 — Ordenes de compra de la central.
 * ?op=por_comprar | asignar_proveedor | generar | nueva | listar | detalle&id= | precio | aprobar | enviar | anular
 * Toma las decisiones 'compra' de la Bandeja (log_bandeja_linea), las agrupa por proveedor habitual, redondea a la
 * presentacion de compra y crea orden_compra + orden_compra_item (migraciones 000 y 009).
 * Indices: log_bandeja_linea(idorg, decision, estado) y (idorden); orden_compra(idorg, idsede, estado); PK en el resto.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_catalogo.php';
require_once __DIR__ . '/_stock.php';
require_once __DIR__ . '/_precios.php';

const ESTADOS_OC = array('por_aprobar' => 'Por aprobar', 'aprobada' => 'Aprobada', 'enviada' => 'Enviada al proveedor',
    'parcial' => 'Llegó una parte', 'recibida' => 'Recibida', 'anulada' => 'Anulada', 'borrador' => 'Borrador', 'cerrada' => 'Cerrada');

/** Rol 1 o permiso B16 (checkbox "Aprobar ordenes de compra" en Usuarios). */
function puede_aprobar($bd, $us) {
    $u = log_filas($bd, "SELECT rol, acc FROM usuario WHERE idusuario = ?", array((int)$us));
    return $u && ((int)$u[0]['rol'] === 1 || strpos(',' . (string)$u[0]['acc'] . ',', ',B16,') !== false);
}

/** "Para cuando debe llegar" (YYYY-MM-DD, opcional): de hoy a 90 dias. Con ella se mide si el proveedor llega a tiempo. */
function fecha_entrega($b) {
    $f = isset($b['fecha_entrega']) && is_string($b['fecha_entrega']) ? trim($b['fecha_entrega']) : '';
    if ($f === '') { return null; }
    $d = DateTime::createFromFormat('!Y-m-d', $f);
    if (!$d || $d->format('Y-m-d') !== $f) { throw new ErrorUsuario('La fecha de entrega no es válida.'); }
    if ($f < date('Y-m-d')) { throw new ErrorUsuario('La fecha de entrega ya pasó. Elige hoy o un día después.'); }
    if ($f > date('Y-m-d', strtotime('+90 days'))) { throw new ErrorUsuario('La fecha de entrega es muy lejana (máximo 90 días).'); }
    return $f;
}

/** Lo pendiente de comprar, por producto central, con su proveedor habitual, presentacion de compra y ultimo precio. */
function por_comprar($bd, $org) {
    $filas = log_filas($bd, "SELECT b.idproducto_central, SUM(b.cantidad) AS cantidad,
            GROUP_CONCAT(DISTINCT s.nombre ORDER BY s.nombre SEPARATOR ', ') AS sedes
            FROM log_bandeja_linea b JOIN sede s ON s.idsede = b.idsede
            WHERE b.idorg = ? AND b.decision = 'compra' AND b.estado = 'pendiente' AND b.idorden IS NULL
            GROUP BY b.idproducto_central", array((int)$org['idorg']));
    if (!$filas) { return array(); }
    $ids = array_map('intval', array_column($filas, 'idproducto_central'));
    $info = array();
    foreach (log_filas($bd, "SELECT p.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad, p.idunidad_kardex,
            lpp.idproveedor, pv.descripcion AS proveedor, pv.telefono, IFNULL(lpp.ultimo_precio, 0) AS ultimo_precio,
            lp.idpresentacion, lp.nombre AS presentacion, lp.factor
            FROM producto p
            LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
            LEFT JOIN log_proveedor_producto lpp ON lpp.idproducto_central = p.idproducto AND lpp.preferido = 1
            LEFT JOIN proveedor pv ON pv.idproveedor = lpp.idproveedor
            LEFT JOIN log_presentacion lp ON lp.idpresentacion = (SELECT lp2.idpresentacion FROM log_presentacion lp2
                    WHERE lp2.idproducto_central = p.idproducto AND lp2.activo = 1 AND lp2.es_compra = 1 ORDER BY lp2.factor DESC LIMIT 1)
            WHERE p.idproducto IN (" . marcas(count($ids)) . ")", $ids) as $f) {
        $info[(int)$f['idproducto']] = $f;
    }
    $r = array();
    foreach ($filas as $f) {
        $i = $info[(int)$f['idproducto_central']];
        $cant = (float)$f['cantidad'];
        $factor = $i['factor'] ? (float)$i['factor'] : 1;
        $bultos = $i['factor'] ? ceil($cant / $factor - 1e-9) : null;      // 10 und con CAJA x6 -> 2 CAJA
        $comprar = $bultos !== null ? $bultos * $factor : ((int)$i['idunidad_kardex'] === 0 ? ceil($cant - 1e-9) : $cant);
        $r[] = array('idproducto_central' => (int)$f['idproducto_central'], 'descripcion' => $i['descripcion'], 'unidad' => $i['unidad'],
            'pedido' => $cant, 'sedes' => $f['sedes'], 'comprar' => $comprar, 'bultos' => $bultos,
            'idpresentacion' => $i['idpresentacion'] ? (int)$i['idpresentacion'] : null, 'presentacion' => $i['presentacion'], 'factor' => $factor,
            'idproveedor' => $i['idproveedor'] ? (int)$i['idproveedor'] : null, 'proveedor' => $i['proveedor'], 'telefono' => $i['telefono'],
            'precio' => (float)$i['ultimo_precio'], 'subtotal' => round($comprar * (float)$i['ultimo_precio'], 2));
    }
    return $r;
}

/**
 * Antes de comprar, mover: por producto, sedes con stock parado (se puede traer) y lo que hay en otros almacenes
 * de la central (ej. PRODUCCION). [idcentral => {parado: [...], otros_almacenes: "PRODUCCION: 50"}]
 */
function alertas_compra($bd, $org, $ids) {
    if (!$ids) { return array(); }
    $parado = stock_parado($bd, $org, $ids);
    $alm = alm_principal($bd, $org['idsede_central']);
    $r = array();
    foreach (log_filas($bd, "SELECT p.idproducto, " . SQL_OTROS_ALMACENES . " AS otros FROM producto p WHERE p.idproducto IN (" . marcas(count($ids)) . ")",
            array_merge(array($alm), $ids)) as $f) {
        $id = (int)$f['idproducto'];
        $r[$id] = array('parado' => isset($parado[$id]) ? $parado[$id] : array(), 'otros_almacenes' => $f['otros'],
            'otros' => $f['otros'] ? otros_almacenes_lista($bd, $id, $org['idsede_central'], $alm) : array());
    }
    return $r;
}

/** Orden de la org (y de la central), con FOR UPDATE si se va a cambiar. */
function orden_de_org($bd, $org, $id, $bloquear = false) {
    $r = log_filas($bd, "SELECT * FROM orden_compra WHERE idorden = ? AND idorg = ? AND idsede = ?" . ($bloquear ? " FOR UPDATE" : ""),
        array((int)$id, (int)$org['idorg'], (int)$org['idsede_central']));
    if (!$r) { throw new ErrorUsuario('Orden no encontrada.'); }
    return $r[0];
}

function recalcular_total($bd, $idorden) {
    log_exec($bd, "UPDATE orden_compra SET total = (SELECT IFNULL(SUM(cantidad_pedida * punitario), 0)
            FROM orden_compra_item WHERE idorden = ?) WHERE idorden = ?", array((int)$idorden, (int)$idorden));
}

/** Hasta el monto configurado la orden se aprueba sola (umbral 0 = siempre requiere aprobacion). */
function se_aprueba_sola($org, $total) {
    return (float)$org['umbral_aprobacion_oc'] > 0 && (float)$total <= (float)$org['umbral_aprobacion_oc'];
}

function en_transaccion($bd, $fn) {
    $bd->bd->begin_transaction();
    try { $r = $fn(); $bd->bd->commit(); return $r; }
    catch (Throwable $e) { $bd->bd->rollback(); throw $e; }
}

$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'almacen':
    // lo que se esta terminando en el almacen central + el catalogo para agregar cualquier otro (stockearse)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $alm = alm_principal($bd, $org['idsede_central']);
        return array('bajo' => almacen_bajo($bd, $org), 'dias_alerta' => ALM_DIAS_ALERTA, 'dias_compra' => ALM_DIAS_COMPRA,
            'catalogo' => log_filas($bd, "SELECT p.idproducto AS c, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
                    IFNULL((SELECT SUM(ps.stock) FROM producto_stock ps WHERE ps.idproducto = p.idproducto AND ps.idalmacen = ?), 0) + 0 AS stock,
                    IFNULL(lpp.ultimo_precio, 0) + 0 AS precio, IFNULL(pv.descripcion, '') AS proveedor
                    FROM producto p LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                    LEFT JOIN log_proveedor_producto lpp ON lpp.idproducto_central = p.idproducto AND lpp.preferido = 1
                    LEFT JOIN proveedor pv ON pv.idproveedor = lpp.idproveedor
                    WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 ORDER BY p.descripcion LIMIT 5000", array($alm, (int)$ido, (int)$org['idsede_central'])));
    });

case 'para_almacen':
    // {items:[{idproducto_central, cantidad}], token}: comprar para el almacen central (sin pedido de sede) -> Por comprar
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $items = array();
        foreach ((isset($b['items']) && is_array($b['items']) ? array_slice($b['items'], 0, 300) : array()) as $it) {
            $c = is_array($it) && isset($it['idproducto_central']) ? (int)$it['idproducto_central'] : 0;
            $t = is_array($it) && isset($it['cantidad']) && is_scalar($it['cantidad']) ? str_replace(',', '.', trim((string)$it['cantidad'])) : '';
            if ($t === '' || (is_numeric($t) && (float)$t == 0)) { continue; }
            $p = producto_central($bd, $org, $c);
            if (!is_numeric($t) || (float)$t < 0 || (float)$t > 999999) { throw new ErrorUsuario('Revisa la cantidad de ' . $p['descripcion'] . '.'); }
            $items[$c] = (isset($items[$c]) ? $items[$c] : 0) + round((float)$t, 3);
        }
        if (!$items) { throw new ErrorUsuario('Pon cuánto comprar de al menos un producto.'); }
        $token = log_token($b);
        return en_transaccion($bd, function () use ($bd, $ido, $org, $items, $us, $token) {
            if ($previo = log_token_reservar($bd, 'para_almacen', $token, $ido)) { return $previo; }
            foreach ($items as $c => $q) {
                log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision, cantidad, idusuario)
                        VALUES (?, 0, 0, ?, ?, 'compra', ?, ?)", array((int)$ido, (int)$org['idsede_central'], $c, $q, (int)$us));
            }
            $res = array('productos' => count($items));
            log_token_cerrar($bd, 'para_almacen', $token, $res);
            return $res;
        });
    });

case 'por_comprar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $prov = log_filas($bd, "SELECT descripcion FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion",
            array((int)$ido, (int)$org['idsede_central']));
        $items = por_comprar($bd, $org);
        $al = alertas_compra($bd, $org, array_column($items, 'idproducto_central'));
        $hist = historial_precios($bd, array_column($items, 'idproducto_central'));
        foreach ($items as &$i) {
            $i += isset($al[$i['idproducto_central']]) ? $al[$i['idproducto_central']] : array('parado' => array(), 'otros_almacenes' => null);
            // otro proveedor que lo vendio mas barato que el habitual
            $l = isset($hist[$i['idproducto_central']]) ? lectura_precio($hist[$i['idproducto_central']], $i['idproveedor']) : null;
            $i['mas_barato'] = $l && $l['mejor_otro'] && $i['idproveedor'] && $l['ultimo']['idproveedor'] === $i['idproveedor'] ? $l['mejor_otro'] : null;
        }
        unset($i);
        $cal = array();
        foreach (calificacion_proveedores($bd, $org) as $k => $c) { $cal[$k] = array('estrellas' => $c['estrellas'], 'etiqueta' => $c['etiqueta'], 'tarde' => $c['tarde'], 'faltas' => $c['items'] - $c['completos']); }
        return array('items' => $items, 'proveedores' => array_column($prov, 'descripcion'), 'calificacion' => (object)$cal,
            'umbral' => (float)$org['umbral_aprobacion_oc'], 'puede_aprobar' => puede_aprobar($bd, $us), 'idsede_central' => (int)$org['idsede_central']);
    });

case 'a_central':
    // {idproducto_central}: lo pendiente de comprar pasa a "sale del almacen central" en lo que alcance el almacen
    // principal (despues de pasar la mercaderia desde PRODUCCION u otro almacen). Lo que no alcance se sigue comprando.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_central($bd, $org, isset($b['idproducto_central']) ? $b['idproducto_central'] : 0);
        $c = (int)$prod['idproducto'];
        // lo que se acaba de pasar desde PRODUCCION u otro almacen (mover_almacen)
        $movido = isset($b['movido']) && is_numeric($b['movido']) ? max(0, (float)$b['movido']) : 0;
        $movio = $movido > 0;
        return en_transaccion($bd, function () use ($bd, $ido, $org, $c, $us, $movido, $movio) {
            $lineas = log_filas($bd, "SELECT id, cantidad, idsede FROM log_bandeja_linea WHERE idorg = ? AND idproducto_central = ?
                    AND decision = 'compra' AND estado = 'pendiente' AND idorden IS NULL ORDER BY id FOR UPDATE", array((int)$ido, $c));
            if (!$lineas) { throw new ErrorUsuario('Ese producto ya no está pendiente de compra.'); }
            $alm = alm_principal($bd, $org['idsede_central']);
            $libre = (float)log_filas($bd, "SELECT IFNULL((SELECT SUM(stock) FROM producto_stock WHERE idproducto = ? AND idalmacen = ?), 0)
                    - IFNULL((SELECT SUM(cantidad) FROM log_bandeja_linea WHERE idproducto_central = ? AND decision = 'central' AND estado <> 'hecho'), 0) AS l",
                array($c, $alm, $c))[0]['l'];
            // comprar para la propia central (stockearse, insumos de produccion): lo que se acaba de pasar al principal ya lo cubre,
            // aunque ese stock este apartado para envios. Las lineas de sedes necesitan stock libre para salir.
            usort($lineas, function ($x, $y) use ($org) { return ((int)$y['idsede'] === (int)$org['idsede_central']) - ((int)$x['idsede'] === (int)$org['idsede_central']); });
            $total = 0;
            foreach ($lineas as $l) {
                $paraCentral = (int)$l['idsede'] === (int)$org['idsede_central'];
                $hay = $paraCentral ? max($movido, $libre) : $libre;
                if ($hay <= 0.0001) { continue; }
                $q = (float)$l['cantidad'];
                $t = min($q, $hay);
                if ($paraCentral) { $movido = max(0, $movido - $t); }
                if ($t >= $q) {
                    log_exec($bd, "UPDATE log_bandeja_linea SET decision = 'central'" . ($paraCentral ? ", estado = 'hecho'" : "") . " WHERE id = ?", array((int)$l['id']));
                } else {
                    log_exec($bd, "UPDATE log_bandeja_linea SET cantidad = cantidad - ? WHERE id = ?", array($t, (int)$l['id']));
                    log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                            cantidad, estado, idusuario) SELECT idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, 'central', ?, ?, ?
                            FROM log_bandeja_linea WHERE id = ?", array($t, $paraCentral ? 'hecho' : 'pendiente', (int)$us, (int)$l['id']));
                }
                $libre -= $t; $total += $t;
            }
            // si ya se movio mercaderia al principal no es error: cubre los envios que estaban sin stock
            if ($total < 0.0001 && !$movio) { throw new ErrorUsuario('En el almacén principal no hay stock libre de este producto.'); }
            return array('ya_no_se_compra' => round($total, 4), 'apartado' => $total < 0.0001);
        });
    });

case 'trasladar':
    // {idproducto_central, idsede_origen}: en vez de comprar, traer lo parado de esa sede. Las lineas de compra aun sin
    // orden pasan a 'traslado' (partiendo la linea si alcanza para una parte); nunca de una sede a si misma.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_central($bd, $org, isset($b['idproducto_central']) ? $b['idproducto_central'] : 0);
        $c = (int)$prod['idproducto'];
        $origen = isset($b['idsede_origen']) ? (int)$b['idsede_origen'] : 0;
        return en_transaccion($bd, function () use ($bd, $ido, $org, $c, $origen, $us) {
            $lineas = log_filas($bd, "SELECT id, idsede, cantidad FROM log_bandeja_linea WHERE idorg = ? AND idproducto_central = ?
                    AND decision = 'compra' AND estado = 'pendiente' AND idorden IS NULL ORDER BY id FOR UPDATE", array((int)$ido, $c));
            if (!$lineas) { throw new ErrorUsuario('Ese producto ya no está pendiente de compra.'); }
            $libre = 0; $nombre = '';
            foreach (stock_parado($bd, $org, array($c)) as $lista) {
                foreach ($lista as $o) { if ($o['idsede'] === $origen) { $libre = $o['libre']; $nombre = $o['sede']; } }
            }
            if ($libre < 1) { throw new ErrorUsuario('Esa sede ya no tiene stock libre de este producto.'); }
            $pieza = (int)log_filas($bd, "SELECT idunidad_kardex FROM producto WHERE idproducto = ?", array($c))[0]['idunidad_kardex'] === 0;
            $total = 0;
            foreach ($lineas as $l) {
                if ($libre <= 0) { break; }
                if ((int)$l['idsede'] === $origen) { continue; }   // no se traslada a la misma sede que lo pidio
                $q = (float)$l['cantidad'];
                $t = min($q, $libre);
                if ($pieza) { $t = floor($t); }
                if ($t <= 0) { continue; }
                if ($t >= $q) {
                    log_exec($bd, "UPDATE log_bandeja_linea SET decision = 'traslado', idsede_origen = ? WHERE id = ?", array($origen, (int)$l['id']));
                } else {
                    log_exec($bd, "UPDATE log_bandeja_linea SET cantidad = cantidad - ? WHERE id = ?", array($t, (int)$l['id']));
                    log_exec($bd, "INSERT INTO log_bandeja_linea (idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, decision,
                            idsede_origen, cantidad, estado, idusuario)
                            SELECT idorg, idsolicitud_producto, idsolicitud_detalle, idsede, idproducto_central, 'traslado', ?, ?, 'pendiente', ?
                            FROM log_bandeja_linea WHERE id = ?", array($origen, $t, (int)$us, (int)$l['id']));
                }
                $libre -= $t; $total += $t;
            }
            if ($total <= 0) { throw new ErrorUsuario('Lo pidió la misma sede que lo tiene: no hay nada que trasladar.'); }
            return array('trasladado' => $total, 'sede' => $nombre);
        });
    });

case 'asignar_proveedor':
    // "a quien se lo compras": deja el proveedor habitual del producto (vincula si existe, crea si no)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_central($bd, $org, isset($b['idproducto_central']) ? $b['idproducto_central'] : 0);
        $nombre = log_mayus(isset($b['proveedor']) ? $b['proveedor'] : '', 150);
        if ($nombre === '') { throw new ErrorUsuario('Escribe el proveedor.'); }
        en_transaccion($bd, function () use ($bd, $ido, $org, $prod, $nombre) {
            guardar_extras($bd, $ido, $org, (int)$prod['idproducto'], array('proveedor' => $nombre));
        });
        return array('ok' => true);
    });

case 'generar':
    // una orden por proveedor con todo lo pendiente de sus productos
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $idprov = isset($b['idproveedor']) ? (int)$b['idproveedor'] : 0;
        if (!log_filas($bd, "SELECT 1 FROM proveedor WHERE idproveedor = ? AND idorg = ?", array($idprov, (int)$ido))) {
            throw new ErrorUsuario('Proveedor no válido.');
        }
        $nota = isset($b['nota']) && is_string($b['nota']) ? trim($b['nota']) : '';
        if (mb_strlen($nota) > 255) { throw new ErrorUsuario('Las observaciones son muy largas (máximo 255 letras).'); }
        $entrega = fecha_entrega($b);
        return en_transaccion($bd, function () use ($bd, $ido, $org, $idprov, $us, $nota, $entrega) {
            // bloquea las lineas pendientes de la org: dos clics no generan dos ordenes
            log_filas($bd, "SELECT id FROM log_bandeja_linea WHERE idorg = ? AND decision = 'compra' AND estado = 'pendiente' FOR UPDATE", array((int)$ido));
            $items = array_values(array_filter(por_comprar($bd, $org), function ($i) use ($idprov) { return $i['idproveedor'] === $idprov; }));
            if (!$items) { throw new ErrorUsuario('No hay nada pendiente de comprar a ese proveedor.'); }
            $alm = log_filas($bd, "SELECT idalmacen_principal FROM log_sede WHERE idsede = ?", array((int)$org['idsede_central']));
            log_exec($bd, "INSERT INTO orden_compra (idorg, idsede, idproveedor, idalmacen_destino, fecha, estado, total, idusuario, nota, fecha_hora, fecha_entrega)
                    VALUES (?, ?, ?, ?, NOW(), 'por_aprobar', 0, ?, ?, NOW(), ?)",
                array((int)$ido, (int)$org['idsede_central'], $idprov, $alm ? (int)$alm[0]['idalmacen_principal'] : null, (int)$us, $nota === '' ? null : $nota, $entrega));
            $idorden = (int)$bd->lastInsertId();
            foreach ($items as $i) {
                log_exec($bd, "INSERT INTO orden_compra_item (idorden, idproducto, cantidad_pedida, punitario, idpresentacion, factor)
                        VALUES (?, ?, ?, ?, ?, ?)", array($idorden, $i['idproducto_central'], $i['comprar'], $i['precio'], $i['idpresentacion'], $i['factor']));
                log_exec($bd, "UPDATE log_bandeja_linea SET idorden = ?, estado = 'en_proceso'
                        WHERE idorg = ? AND decision = 'compra' AND estado = 'pendiente' AND idorden IS NULL AND idproducto_central = ?",
                    array($idorden, (int)$ido, $i['idproducto_central']));
            }
            recalcular_total($bd, $idorden);
            $total = (float)log_filas($bd, "SELECT total FROM orden_compra WHERE idorden = ?", array($idorden))[0]['total'];
            // el origen queda tambien en texto: si la orden se anula, log_bandeja_linea suelta el idorden
            $peds = log_filas($bd, "SELECT DISTINCT b.idsolicitud_producto AS n, sd.nombre FROM log_bandeja_linea b JOIN sede sd ON sd.idsede = b.idsede
                    WHERE b.idorden = ? ORDER BY b.idsolicitud_producto", array($idorden));
            oc_evento($bd, $idorden, 'creada', $us, $peds ? 'Para ' . implode(', ', array_map(function ($p) {
                return 'pedido N° ' . $p['n'] . ' (' . $p['nombre'] . ')'; }, $peds)) : null);
            if (se_aprueba_sola($org, $total)) {
                log_exec($bd, "UPDATE orden_compra SET estado = 'aprobada', fecha_aprueba = NOW() WHERE idorden = ?", array($idorden));
                oc_evento($bd, $idorden, 'aprobada', null, 'Se aprobó sola: el total no pasa el monto que requiere aprobación');
            }
            return array('idorden' => $idorden, 'total' => $total);
        });
    });

case 'nueva':
    // {proveedor (nombre), items:[{idproducto_central, cantidad, precio}], nota, token}: orden armada a mano, para el almacen central
    // (sin pedido de sede). Proveedor por nombre: vincula si existe, crea si no.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $nombre = log_mayus(isset($b['proveedor']) && is_string($b['proveedor']) ? $b['proveedor'] : '', 150);
        if ($nombre === '') { throw new ErrorUsuario('Escribe a qué proveedor le compras.'); }
        $nota = isset($b['nota']) && is_string($b['nota']) ? trim($b['nota']) : '';
        if (mb_strlen($nota) > 255) { throw new ErrorUsuario('Las observaciones son muy largas (máximo 255 letras).'); }
        $items = array();
        foreach ((isset($b['items']) && is_array($b['items']) ? array_slice($b['items'], 0, 300) : array()) as $it) {
            if (!is_array($it)) { continue; }
            $p = producto_central($bd, $org, isset($it['idproducto_central']) ? $it['idproducto_central'] : 0);
            $q = isset($it['cantidad']) && is_numeric($it['cantidad']) ? round((float)$it['cantidad'], 3) : -1;
            $pr = isset($it['precio']) && is_numeric($it['precio']) ? round((float)$it['precio'], 4) : -1;
            if ($q <= 0 || $q > 999999) { throw new ErrorUsuario('Revisa la cantidad de ' . $p['descripcion'] . '.'); }
            if ($pr < 0 || $pr > 9999999) { throw new ErrorUsuario('Revisa el precio de ' . $p['descripcion'] . '.'); }
            if (isset($items[(int)$p['idproducto']])) { throw new ErrorUsuario($p['descripcion'] . ' está dos veces.'); }
            $items[(int)$p['idproducto']] = array($q, $pr);
        }
        if (!$items) { throw new ErrorUsuario('Agrega al menos un producto.'); }
        $entrega = fecha_entrega($b);
        $token = log_token($b);
        return en_transaccion($bd, function () use ($bd, $ido, $org, $nombre, $nota, $items, $us, $token, $entrega) {
            if ($previo = log_token_reservar($bd, 'oc_nueva', $token, $ido)) { return $previo; }
            $idprov = proveedor_en_central($bd, $ido, (int)$org['idsede_central'], $nombre);
            log_exec($bd, "INSERT INTO orden_compra (idorg, idsede, idproveedor, idalmacen_destino, fecha, estado, total, idusuario, nota, fecha_hora, fecha_entrega)
                    VALUES (?, ?, ?, ?, NOW(), 'por_aprobar', 0, ?, ?, NOW(), ?)",
                array((int)$ido, (int)$org['idsede_central'], $idprov, alm_principal($bd, $org['idsede_central']), (int)$us, $nota === '' ? null : $nota, $entrega));
            $idorden = (int)$bd->lastInsertId();
            foreach ($items as $c => $x) {
                log_exec($bd, "INSERT INTO orden_compra_item (idorden, idproducto, cantidad_pedida, punitario, idpresentacion, factor)
                        VALUES (?, ?, ?, ?, NULL, 1)", array($idorden, $c, $x[0], $x[1]));
            }
            recalcular_total($bd, $idorden);
            $total = (float)log_filas($bd, "SELECT total FROM orden_compra WHERE idorden = ?", array($idorden))[0]['total'];
            oc_evento($bd, $idorden, 'creada', $us, 'Orden nueva armada a mano, para el almacén central');
            if (se_aprueba_sola($org, $total)) {
                log_exec($bd, "UPDATE orden_compra SET estado = 'aprobada', fecha_aprueba = NOW() WHERE idorden = ?", array($idorden));
                oc_evento($bd, $idorden, 'aprobada', null, 'Se aprobó sola: el total no pasa el monto que requiere aprobación');
            }
            $res = array('idorden' => $idorden, 'total' => $total);
            log_token_cerrar($bd, 'oc_nueva', $token, $res);
            return $res;
        });
    });

case 'listar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        // &estado=por_aprobar|aprobada: solo esas (desde el diagrama)
        $est = isset($_GET['estado']) && isset(ESTADOS_OC[$_GET['estado']]) ? $_GET['estado'] : '';
        $filas = log_filas($bd, "SELECT oc.idorden, oc.fecha, oc.estado, oc.total, IFNULL(pv.descripcion, '') AS proveedor,
                (SELECT COUNT(*) FROM orden_compra_item i WHERE i.idorden = oc.idorden) AS productos
                FROM orden_compra oc LEFT JOIN proveedor pv ON pv.idproveedor = oc.idproveedor
                WHERE oc.idorg = ? AND oc.idsede = ? AND " . ($est ? "oc.estado = ?" : "oc.estado <> 'borrador'") . "
                ORDER BY oc.idorden DESC LIMIT 50", $est ? array((int)$ido, (int)$org['idsede_central'], $est) : array((int)$ido, (int)$org['idsede_central']));
        foreach ($filas as &$f) { $f['estado_txt'] = isset(ESTADOS_OC[$f['estado']]) ? ESTADOS_OC[$f['estado']] : $f['estado']; }
        return $filas;
    });

case 'detalle':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $oc = orden_de_org($bd, $org, isset($_GET['id']) ? $_GET['id'] : 0);
        $prov = log_filas($bd, "SELECT descripcion, dni, telefono, direccion FROM proveedor WHERE idproveedor = ?", array((int)$oc['idproveedor']));
        $items = log_filas($bd, "SELECT i.idorden_item, i.idproducto, p.descripcion, IFNULL(u.abreviatura, '') AS unidad,
                i.cantidad_pedida + 0 AS cantidad, i.cantidad_recibida + 0 AS recibida, i.punitario + 0 AS precio, i.factor + 0 AS factor,
                IFNULL(lp.nombre, '') AS presentacion
                FROM orden_compra_item i JOIN producto p ON p.idproducto = i.idproducto
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                LEFT JOIN log_presentacion lp ON lp.idpresentacion = i.idpresentacion
                WHERE i.idorden = ? ORDER BY p.descripcion", array((int)$oc['idorden']));
        $sede = log_filas($bd, "SELECT nombre, direccion FROM sede WHERE idsede = ?", array((int)$org['idsede_central']));
        return array('orden' => $oc, 'estado_txt' => ESTADOS_OC[$oc['estado']], 'proveedor' => $prov ? $prov[0] : null,
            'items' => $items, 'entregar_en' => $sede ? $sede[0] : null, 'puede_aprobar' => puede_aprobar($bd, $us));
    });

case 'precio':
    // precio por unidad base de un item; solo antes de enviar
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        // texto ("abc"), vacio o negativo se rechaza (antes (float) lo dejaba en 0)
        $txt = isset($b['precio']) && !is_array($b['precio']) ? str_replace(',', '.', trim((string)$b['precio'])) : '';
        if (!is_numeric($txt)) { throw new ErrorUsuario('Escribe el precio solo con números (ej. 12.50).'); }
        $precio = round((float)$txt, 4);
        if ($precio < 0 || $precio > 9999999) { throw new ErrorUsuario('El precio no puede ser negativo ni tan alto.'); }
        $aprueba = puede_aprobar($bd, $us);
        return en_transaccion($bd, function () use ($bd, $org, $b, $precio, $aprueba, $us) {
            $oc = orden_de_org($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
            if (!in_array($oc['estado'], array('por_aprobar', 'aprobada'), true)) { throw new ErrorUsuario('La orden ya se envió: no se puede cambiar el precio.'); }
            $item = isset($b['idorden_item']) ? (int)$b['idorden_item'] : 0;
            // affected_rows es 0 si el precio no cambia: se valida que el item sea de ESTA orden con un SELECT
            $it = log_filas($bd, "SELECT i.punitario + 0 AS antes, p.descripcion FROM orden_compra_item i JOIN producto p ON p.idproducto = i.idproducto
                    WHERE i.idorden_item = ? AND i.idorden = ?", array($item, (int)$oc['idorden']));
            if (!$it) { throw new ErrorUsuario('Producto no encontrado en la orden.'); }
            log_exec($bd, "UPDATE orden_compra_item SET punitario = ? WHERE idorden_item = ? AND idorden = ?",
                array($precio, $item, (int)$oc['idorden']));
            recalcular_total($bd, (int)$oc['idorden']);
            $total = (float)log_filas($bd, "SELECT total FROM orden_compra WHERE idorden = ?", array((int)$oc['idorden']))[0]['total'];
            if (abs((float)$it[0]['antes'] - $precio) > 0.00001) {
                oc_evento($bd, (int)$oc['idorden'], 'precio', $us, $it[0]['descripcion'] . ': S/ ' . number_format((float)$it[0]['antes'], 2)
                    . ' → S/ ' . number_format($precio, 2) . ' c/u');
            }
            // una aprobada que ya no entra en el umbral vuelve a aprobacion si quien cambia el precio no puede aprobar
            // (si no, una OC en 0 se aprobaba sola y luego se le subia el precio sin que nadie la apruebe)
            $estado = $oc['estado'];
            if ($estado === 'aprobada' && !$aprueba && !se_aprueba_sola($org, $total)) {
                log_exec($bd, "UPDATE orden_compra SET estado = 'por_aprobar', idusuario_aprueba = NULL, fecha_aprueba = NULL WHERE idorden = ?",
                    array((int)$oc['idorden']));
                oc_evento($bd, (int)$oc['idorden'], 'volvio', $us, 'Volvió a aprobación: cambió el precio y el total pasa el monto permitido');
                $estado = 'por_aprobar';
            }
            return array('total' => $total, 'estado' => $estado);
        });
    });

case 'aprobar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        if (!puede_aprobar($bd, $us)) { throw new ErrorUsuario('No tienes permiso para aprobar órdenes de compra. Pídelo al administrador.'); }
        $b = log_body();
        return en_transaccion($bd, function () use ($bd, $org, $b, $us) {
            $oc = orden_de_org($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
            if ($oc['estado'] !== 'por_aprobar') { throw new ErrorUsuario('Esta orden no está por aprobar.'); }
            log_exec($bd, "UPDATE orden_compra SET estado = 'aprobada', idusuario_aprueba = ?, fecha_aprueba = NOW() WHERE idorden = ?",
                array((int)$us, (int)$oc['idorden']));
            oc_evento($bd, (int)$oc['idorden'], 'aprobada', $us, 'Total S/ ' . number_format((float)$oc['total'], 2));
            return array('ok' => true);
        });
    });

case 'enviar':
    // marca enviada (el mensaje de WhatsApp / PDF lo arma el navegador con el detalle)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        return en_transaccion($bd, function () use ($bd, $org, $b, $us) {
            $oc = orden_de_org($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
            if ($oc['estado'] === 'por_aprobar') { throw new ErrorUsuario('Primero hay que aprobar la orden.'); }
            if ($oc['estado'] === 'aprobada') {
                log_exec($bd, "UPDATE orden_compra SET estado = 'enviada', fecha_envio = NOW() WHERE idorden = ?", array((int)$oc['idorden']));
                oc_evento($bd, (int)$oc['idorden'], 'enviada', $us, 'Por WhatsApp');
            } elseif (!in_array($oc['estado'], array('enviada', 'parcial', 'recibida'), true)) {
                // reenviar el WhatsApp de una ya enviada sigue siendo ok; una anulada/cerrada no se envia
                $nombres = ESTADOS_OC;
                $txt = isset($nombres[$oc['estado']]) ? $nombres[$oc['estado']] : $oc['estado'];
                throw new ErrorUsuario('Esta orden está "' . $txt . '": no se puede enviar al proveedor.');
            }
            return array('ok' => true);
        });
    });

case 'anular':
    // solo si no llego nada; lo que estaba en la orden vuelve a "Por comprar"
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        return en_transaccion($bd, function () use ($bd, $org, $b, $us) {
            $oc = orden_de_org($bd, $org, isset($b['idorden']) ? $b['idorden'] : 0, true);
            if (!in_array($oc['estado'], array('por_aprobar', 'aprobada', 'enviada'), true)) { throw new ErrorUsuario('Esta orden ya no se puede anular.'); }
            log_exec($bd, "UPDATE orden_compra SET estado = 'anulada' WHERE idorden = ?", array((int)$oc['idorden']));
            oc_evento($bd, (int)$oc['idorden'], 'anulada', $us, 'Lo que tenía volvió a Por comprar');
            log_exec($bd, "UPDATE log_bandeja_linea SET idorden = NULL, estado = 'pendiente' WHERE idorden = ? AND decision = 'compra'", array((int)$oc['idorden']));
            return array('ok' => true);
        });
    });

default:
    log_out(false, null, 'Operación no válida.');
}
