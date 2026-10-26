<?php
/**
 * Logistica F1 — catalogo de la sede central replicado a las sedes.
 * ?op=config | activar | listar | crear | editar | replicar | sedes_producto
 * Regla: el catalogo se edita solo desde la sede central; cada sede conserva su propia fila `producto`
 * enlazada en log_producto_central. Nunca se vincula por parecido de nombre.
 */
require_once __DIR__ . '/_logistica.php';
require_once __DIR__ . '/_catalogo.php';


$ido = $g_ido; $idsede = $g_idsede; $us = $g_us;

switch ($op) {
case 'config':
    log_responder(function () use ($bd, $ido) {
        $sedes = log_filas($bd, "SELECT s.idsede, s.nombre, ls.idalmacen_principal, IFNULL(ls.activo, 0) AS activo, ls.dias_cobertura, ls.telefono_whatsapp
                FROM sede s LEFT JOIN log_sede ls ON ls.idsede = s.idsede
                WHERE s.idorg = ? AND s.estado = 0 ORDER BY s.nombre", array($ido));
        $alm = log_filas($bd, "SELECT a.idalmacen, a.idsede, a.descripcion FROM almacen a
                JOIN sede s ON s.idsede = a.idsede WHERE s.idorg = ? AND a.estado = 0 ORDER BY a.idalmacen", array($ido));
        return array('org' => log_org($bd, $ido), 'sedes' => $sedes, 'almacenes' => $alm);
    });

case 'activar':
    log_responder(function () use ($bd, $ido, $us) {
        if (!log_tiene_permiso($bd, $us)) {
            // dice a quien pedirselo: "administrador" de cargo no siempre es rol 1
            $adm = log_filas($bd, "SELECT nombres FROM usuario WHERE idorg = ? AND rol = 1 AND estado = 0 ORDER BY idusuario LIMIT 3", array($ido));
            throw new ErrorUsuario('Solo un administrador o quien tenga el permiso Centro Logístico puede configurarlo.'
                . ($adm ? ' Pídeselo a: ' . implode(', ', array_column($adm, 'nombres')) . '.' : ''));
        }
        $b = log_body();
        $central = isset($b['idsede_central']) ? (int)$b['idsede_central'] : 0;
        $sedes = isset($b['sedes']) && is_array($b['sedes']) ? $b['sedes'] : array();
        $validas = array();
        foreach (log_filas($bd, "SELECT idsede FROM sede WHERE idorg = ?", array($ido)) as $s) { $validas[(int)$s['idsede']] = true; }
        if (!isset($validas[$central])) { throw new ErrorUsuario('Elige la sede central.'); }
        $actual = log_org($bd, $ido);
        if ($actual && (int)$actual['idsede_central'] !== $central
            && log_filas($bd, "SELECT 1 FROM log_producto_central WHERE idorg = ? LIMIT 1", array($ido))) {
            throw new ErrorUsuario('No se puede cambiar la sede central: ya hay productos enviados a las sedes. Pide ayuda a soporte.');
        }
        $bd->bd->begin_transaction();
        try {
            $diasOrg = isset($b['dias_cobertura']) ? max(1, min(60, (int)$b['dias_cobertura'])) : 4;
            // ordenes de compra hasta este monto se aprueban solas (0 = todas necesitan aprobacion)
            $umbral = isset($b['umbral_aprobacion_oc']) ? max(0, min(9999999, round((float)$b['umbral_aprobacion_oc'], 2))) : 0;
            $diasVence = isset($b['dias_alerta_vencimiento']) ? max(1, min(365, (int)$b['dias_alerta_vencimiento'])) : 30;
            log_exec($bd, "INSERT INTO org_logistica (idorg, idsede_central, dias_cobertura, umbral_aprobacion_oc, dias_alerta_vencimiento) VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE idsede_central = VALUES(idsede_central), dias_cobertura = VALUES(dias_cobertura),
                    umbral_aprobacion_oc = VALUES(umbral_aprobacion_oc), dias_alerta_vencimiento = VALUES(dias_alerta_vencimiento)",
                array($ido, $central, $diasOrg, $umbral, $diasVence));
            foreach ($sedes as $s) {
                $id = isset($s['idsede']) ? (int)$s['idsede'] : 0;
                if (!isset($validas[$id])) { continue; }
                $alm = isset($s['idalmacen_principal']) ? (int)$s['idalmacen_principal'] : 0;
                if ($alm > 0 && !log_filas($bd, "SELECT 1 FROM almacen WHERE idalmacen = ? AND idsede = ?", array($alm, $id))) {
                    throw new ErrorUsuario('El almacén elegido no pertenece a la sede.');
                }
                $activo = !empty($s['activo']) || $id === $central ? 1 : 0;
                // dias que cubre un pedido de esta sede; vacio = el de la empresa
                $dias = isset($s['dias_cobertura']) && $s['dias_cobertura'] !== '' && $s['dias_cobertura'] !== null
                    ? max(1, min(60, (int)$s['dias_cobertura'])) : null;
                // WhatsApp del encargado: solo digitos (el aviso "va en camino" usa wa.me)
                $tel = substr(preg_replace('/\D/', '', isset($s['telefono_whatsapp']) ? (string)$s['telefono_whatsapp'] : ''), 0, 15);
                log_exec($bd, "INSERT INTO log_sede (idsede, idorg, idalmacen_principal, activo, dias_cobertura, telefono_whatsapp) VALUES (?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE idalmacen_principal = VALUES(idalmacen_principal), activo = VALUES(activo),
                        dias_cobertura = VALUES(dias_cobertura), telefono_whatsapp = VALUES(telefono_whatsapp)",
                    array($id, $ido, $alm > 0 ? $alm : null, $activo, $dias, $tel !== '' ? $tel : null));
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return log_org($bd, $ido);
    });

case 'listar':
    log_responder(function () use ($bd, $ido) {
        $org = log_org($bd, $ido);
        if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
        $q = log_mayus(isset($_GET['q']) ? $_GET['q'] : '', 60);
        $porPagina = 50;
        $pagina = max(1, isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1);
        $params = array($ido, (int)$org['idsede_central']);
        $filtro = '';
        if ($q !== '') { $filtro = " AND (p.descripcion LIKE ? OR pf.descripcion LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; }
        $total = count(sedes_destino($bd, $org));
        // total de la busqueda (mismo filtro, indice idorg+idsede) para el paginador
        $n = (int)log_filas($bd, "SELECT COUNT(*) AS n FROM producto p
                LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 $filtro", $params)[0]['n'];
        $paginas = max(1, (int)ceil($n / $porPagina));
        $pagina = min($pagina, $paginas);
        $filas = log_filas($bd, "SELECT " . CAMPOS_PRODUCTO . ", IFNULL(u.abreviatura, '') AS unidad,
                (SELECT COUNT(*) FROM log_producto_central v JOIN log_sede ls ON ls.idsede = v.idsede AND ls.activo = 1
                  WHERE v.idproducto_central = p.idproducto) AS n_sedes,
                (SELECT GROUP_CONCAT(CONCAT(lp.nombre, '|', lp.factor) ORDER BY lp.factor SEPARATOR ';')
                   FROM log_presentacion lp WHERE lp.idproducto_central = p.idproducto AND lp.activo = 1) AS presentaciones,
                (SELECT pv.descripcion FROM log_proveedor_producto lpp JOIN proveedor pv ON pv.idproveedor = lpp.idproveedor
                  WHERE lpp.idproducto_central = p.idproducto AND lpp.preferido = 1 LIMIT 1) AS proveedor,
                (SELECT po.porciones + 0 FROM log_producto_porcion po WHERE po.idproducto_central = p.idproducto) AS porciones,
                -- sedes que ya enlazaron su porcion (se enlaza al recibir el primer envio); solo para los que se venden por porcion
                IF(EXISTS (SELECT 1 FROM log_producto_porcion po WHERE po.idproducto_central = p.idproducto),
                   (SELECT COUNT(DISTINCT v.idsede) FROM log_producto_central v JOIN log_sede ls ON ls.idsede = v.idsede AND ls.activo = 1
                     JOIN porcion pr ON pr.idproducto_de = v.idproducto AND pr.idsede = v.idsede AND pr.estado = 0
                     WHERE v.idproducto_central = p.idproducto), NULL) AS n_porcion
                FROM producto p
                LEFT JOIN producto_familia pf ON pf.idproducto_familia = p.idproducto_familia
                LEFT JOIN unidad_medida u ON u.idunidad_medida = p.idunidad_kardex
                WHERE p.idorg = ? AND p.idsede = ? AND p.estado = 0 $filtro
                ORDER BY pf.descripcion, p.descripcion, p.idproducto LIMIT $porPagina OFFSET " . (($pagina - 1) * $porPagina), $params);
        $unidades = log_filas($bd, "SELECT idunidad_medida, unidad, abreviatura FROM unidad_medida WHERE estado = 0
            AND (idorg IS NULL OR idorg = ?) ORDER BY unidad", array($ido));
        $familias = log_filas($bd, "SELECT DISTINCT descripcion FROM producto_familia WHERE idsede = ? AND estado = 0 ORDER BY descripcion",
            array((int)$org['idsede_central']));
        $proveedores = log_filas($bd, "SELECT descripcion FROM proveedor WHERE idorg = ? AND idsede = ? AND estado = 0 ORDER BY descripcion",
            array($ido, (int)$org['idsede_central']));
        return array('org' => $org, 'sedes_total' => $total, 'productos' => $filas, 'unidades' => $unidades,
                     'meta' => array('total' => $n, 'pagina' => $pagina, 'paginas' => $paginas, 'por_pagina' => $porPagina),
                     'familias' => array_column($familias, 'descripcion'),
                     'proveedores' => array_column($proveedores, 'descripcion'));
    });

case 'crear':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $c = leer_campos(log_body());
        $bd->bd->begin_transaction();
        try {
            // dentro de la transaccion y con la org bloqueada: un doble clic no crea el producto dos veces
            bloquear_catalogo_org($bd, $ido);
            if (existe_en_central($bd, $ido, $idsede, $c['descripcion'])) {
                throw new ErrorUsuario('Ya existe un producto con ese nombre en el catálogo.');
            }
            $id = crear_en_central($bd, $ido, $idsede, $c);
            guardar_extras($bd, $ido, $org, $id, log_body());
            $rep = replicar_lista($bd, $ido, $org, array($id), false);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('idproducto' => $id, 'replicado' => $rep);
    });

case 'importar':
    // filas: [{fila, descripcion, familia, unidad, codigo_barra}], max 100 por llamada. Una transaccion por llamada.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $filas = isset($b['filas']) && is_array($b['filas']) ? array_slice($b['filas'], 0, 100) : array();
        if (!$filas) { throw new ErrorUsuario('El archivo no tiene filas para importar.'); }
        $res = array('creados' => 0, 'existentes' => 0, 'errores' => array(),
                     'replicado' => array('creado' => 0, 'ya' => 0, 'conflicto' => array()));
        $bd->bd->begin_transaction();
        try {
            bloquear_catalogo_org($bd, $ido);   // dos importaciones a la vez no duplican productos
            foreach ($filas as $i => $f) {
                $n = is_array($f) && isset($f['fila']) ? (int)$f['fila'] : $i + 1;   // n° de fila del Excel
                try {
                    $c = leer_campos(array_merge(is_array($f) ? $f : array(), array('unidad' => isset($f['unidad']) ? (string)$f['unidad'] : '')));
                } catch (ErrorUsuario $e) {
                    $res['errores'][] = array('fila' => $n, 'motivo' => $e->getMessage());
                    continue;
                }
                if (existe_en_central($bd, $ido, $idsede, $c['descripcion'])) { $res['existentes']++; continue; }
                $id = crear_en_central($bd, $ido, $idsede, $c);
                $rep = replicar_lista($bd, $ido, $org, array($id), false);
                $res['creados']++;
                $res['replicado']['creado'] += $rep['creado'];
                $res['replicado']['ya'] += $rep['ya'];
                $res['replicado']['conflicto'] = array_merge($res['replicado']['conflicto'], $rep['conflicto']);
            }
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return $res;
    });

case 'editar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_central($bd, $org, isset($b['idproducto']) ? $b['idproducto'] : 0);
        $c = leer_campos($b);
        if (log_filas($bd, "SELECT 1 FROM producto WHERE idorg = ? AND idsede = ? AND estado = 0 AND UPPER(descripcion) = ? AND idproducto <> ?",
                array($ido, $idsede, $c['descripcion'], (int)$prod['idproducto']))) {
            throw new ErrorUsuario('Ya existe otro producto con ese nombre en el catálogo.');
        }
        $choque = log_filas($bd, "SELECT DISTINCT s.nombre FROM log_producto_central v
                JOIN producto p ON p.idorg = v.idorg AND p.idsede = v.idsede AND p.estado = 0 AND UPPER(p.descripcion) = ? AND p.idproducto <> v.idproducto
                JOIN sede s ON s.idsede = v.idsede
                WHERE v.idproducto_central = ?", array($c['descripcion'], (int)$prod['idproducto']));
        if ($choque) {
            throw new ErrorUsuario('Estas sedes ya tienen otro producto con ese nombre: '
                . implode(', ', array_column($choque, 'nombre')) . '. Cambia el nombre o renombra el de la sede.');
        }
        $bd->bd->begin_transaction();
        log_sync_on($bd);   // el trigger 003 deja pasar los campos heredados solo a Logistica
        try {
            // la unidad se resuelve solo si el usuario cambio el texto: si no, se conserva el id actual tal cual
            $actual = log_filas($bd, "SELECT unidad FROM unidad_medida WHERE idunidad_medida = ?", array((int)$prod['idunidad_kardex']));
            $textoActual = $actual ? log_mayus($actual[0]['unidad'], 50) : '';
            $c['idunidad_kardex'] = ($c['unidad'] === null || log_mayus($c['unidad'], 50) === $textoActual)
                ? (int)$prod['idunidad_kardex'] : unidad_por_nombre($bd, $ido, $c['unidad']);
            $sedes = array_merge(array(array('idsede' => $idsede, 'idproducto' => $prod['idproducto'])),
                log_filas($bd, "SELECT idsede, idproducto FROM log_producto_central WHERE idproducto_central = ?",
                    array((int)$prod['idproducto'])));
            foreach ($sedes as $s) {
                // heredados: nombre, familia, codigo de barras, unidad. Precio, stock y minimo son de cada sede.
                $fam = familia_en_sede($bd, $ido, (int)$s['idsede'], $c['familia']);
                log_exec($bd, "UPDATE producto SET descripcion = ?, idproducto_familia = ?, codigo_barra = ?, idunidad_kardex = ?
                        WHERE idproducto = ?",
                    array($c['descripcion'], $fam, $c['codigo_barra'], $c['idunidad_kardex'], (int)$s['idproducto']));
            }
            // precio: solo el del catalogo (lo que reciben las sedes NUEVAS); el de cada sede no se toca
            if ($c['precio_venta'] !== null) {
                log_exec($bd, "UPDATE producto SET precio_venta = ? WHERE idproducto = ?", array(precio_txt($c['precio_venta']), (int)$prod['idproducto']));
            }
            guardar_extras($bd, $ido, $org, (int)$prod['idproducto'], $b);
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        } finally {
            log_sync_off($bd);
        }
        return array('sedes_actualizadas' => count($sedes));
    });

case 'pendientes_envio':
    // cuantos productos del catalogo faltan en alguna sede (para explicarlo antes de enviar)
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        return array('pendientes' => count(ids_pendientes_envio($bd, $org, 100000)), 'sedes' => count(sedes_destino($bd, $org)));
    });

case 'enviar_pendientes':
    // envia la siguiente tanda de 100 pendientes; el navegador llama de nuevo mientras siga creando
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $despuesDe = isset($b['despues_de']) ? (int)$b['despues_de'] : 0;   // avanza aunque haya productos con conflicto
        $ids = array_values(array_filter(ids_pendientes_envio($bd, $org, 100000), function ($id) use ($despuesDe) { return $id > $despuesDe; }));
        $tanda = array_slice($ids, 0, 100);
        if (!$tanda) { return array('creado' => 0, 'ya' => 0, 'conflicto' => array(), 'ultimo' => $despuesDe, 'quedan' => 0); }
        $r = replicar_lista($bd, $ido, $org, $tanda);
        $r['ultimo'] = end($tanda);
        $r['quedan'] = count($ids) - count($tanda);
        return $r;
    });

case 'replicar':
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $ids = isset($b['idproductos']) && is_array($b['idproductos']) ? array_slice(array_map('intval', $b['idproductos']), 0, 200) : array();
        if (!$ids) { throw new ErrorUsuario('Elige al menos un producto.'); }
        return replicar_lista($bd, $ido, $org, $ids);
    });

case 'antes_eliminar':
    // lo que se muestra en la alerta: en cuantas sedes esta y cuanto stock queda
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $prod = producto_central($bd, $org, isset($_GET['idproducto']) ? $_GET['idproducto'] : 0);
        $id = (int)$prod['idproducto'];
        // por PK: primero los ids (central + copias), luego producto/producto_stock por indice. Sin OR + subconsulta.
        $ids = ids_con_copias($bd, $id);
        $r = log_filas($bd, "SELECT COUNT(DISTINCT p.idsede) - 1 AS sedes, IFNULL(SUM(ps.stock), 0) AS stock
                FROM producto p LEFT JOIN producto_stock ps ON ps.idproducto = p.idproducto
                WHERE p.idproducto IN (" . marcas(count($ids)) . ") AND p.estado = 0", $ids);
        return array('descripcion' => $prod['descripcion'], 'sedes' => max(0, (int)$r[0]['sedes']),
                     'stock' => (float)$r[0]['stock']);
    });

case 'eliminar':
    // Eliminacion LOGICA (estado = 1, igual que log_009 op 118) en la central y en todas sus sedes.
    // Ventas, compras, kardex e historial que usan el producto no se tocan.
    log_responder(function () use ($bd, $ido, $idsede, $us) {
        $org = log_requiere_central($bd, $ido, $idsede, $us);
        $b = log_body();
        $prod = producto_central($bd, $org, isset($b['idproducto']) ? $b['idproducto'] : 0);
        $id = (int)$prod['idproducto'];
        $bd->bd->begin_transaction();
        try {
            // UPDATE por PK: en MySQL 5.7 un UPDATE con IN (subconsulta) recorre y bloquea toda la org
            $ids = ids_con_copias($bd, $id);
            $n = log_exec($bd, "UPDATE producto SET estado = 1 WHERE idproducto IN (" . marcas(count($ids)) . ") AND idorg = ?",
                array_merge($ids, array($ido)));
            $bd->bd->commit();
        } catch (Throwable $e) {
            $bd->bd->rollback();
            throw $e;
        }
        return array('eliminados' => $n);
    });

case 'sedes_producto':
    log_responder(function () use ($bd, $ido) {
        $org = log_org($bd, $ido);
        if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
        $id = isset($_GET['idproducto']) ? (int)$_GET['idproducto'] : 0;
        return log_filas($bd, "SELECT s.idsede, s.nombre, v.idproducto, p.precio_venta, p.stock_minimo
                FROM log_sede ls JOIN sede s ON s.idsede = ls.idsede
                LEFT JOIN log_producto_central v ON v.idsede = ls.idsede AND v.idproducto_central = ?
                LEFT JOIN producto p ON p.idproducto = v.idproducto
                WHERE ls.idorg = ? AND ls.activo = 1 AND ls.idsede <> ? ORDER BY s.nombre",
            array($id, $ido, (int)$org['idsede_central']));
    });

default:
    log_out(false, null, 'Operación no válida.');
}
