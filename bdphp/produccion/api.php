<?php
/**
 * API Producción v2 — módulo aislado (no toca log_009.php ni log.php).
 * URL: ../../bdphp/produccion/api.php
 */

require_once __DIR__ . '/../SecurityGuard.php';
SecurityGuard::verificarAcceso();

header('Content-Type: application/json;charset=utf-8');

include __DIR__ . '/../ManejoBD.php';
$bd = new xManejoBD('restobar');

if (isset($_POST['op'])) {
    $op = $_POST['op'];
} elseif (isset($_GET['op'])) {
    $op = $_GET['op'];
} else {
    $postBody = json_decode(file_get_contents('php://input'));
    $op = isset($postBody->op) ? $postBody->op : null;
}

$g_ido  = $_SESSION['ido'];
$g_idsede = $_SESSION['idsede'];
$g_us   = $_SESSION['idusuario'];

function prod_json($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

function prod_body() {
    $raw = file_get_contents('php://input');
    $obj = json_decode($raw);
    return $obj ? $obj : new stdClass();
}

function prod_esc($bd, $str) {
    return $bd->bd->real_escape_string($str);
}

/** Descuenta o incrementa stock en producto_stock; registra producto_historial. */
function prod_movimiento_stock($bd, $idsede, $idusuario, $idproducto, $idalmacen, $cantidad, $tipo_movimiento) {
    $idproducto = intval($idproducto);
    $idalmacen  = intval($idalmacen);
    $cantidad   = floatval($cantidad);

    if ($cantidad == 0) {
        return array('ok' => true);
    }

    $sql = "SELECT ps.idproducto_stock, ps.stock, COALESCE(p.precio, 0) AS precio
            FROM producto_stock ps
            INNER JOIN producto p ON p.idproducto = ps.idproducto
            WHERE ps.idproducto = $idproducto AND ps.idalmacen = $idalmacen AND ps.estado = 0
            LIMIT 1 FOR UPDATE";
    $res = $bd->bd->query($sql);
    if (!$res || $res->num_rows === 0) {
        if ($cantidad > 0) {
            $sqlIns = "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado)
                       VALUES ($idproducto, $idalmacen, 0, 0)";
            if (!$bd->bd->query($sqlIns)) {
                return array('ok' => false, 'error' => $bd->bd->error);
            }
            $res = $bd->bd->query($sql);
        } else {
            return array('ok' => false, 'error' => 'Producto no encontrado en almacén (idproducto=' . $idproducto . ')');
        }
    }
    $row = $res->fetch_assoc();
    $res->free();

    $stockActual = floatval($row['stock']);
    $nuevoStock  = $stockActual + $cantidad;

    if ($nuevoStock < 0) {
        return array('ok' => false, 'error' => 'Stock insuficiente para producto id=' . $idproducto);
    }

    $idps = intval($row['idproducto_stock']);
    $sqlUp = "UPDATE producto_stock SET stock = $nuevoStock WHERE idproducto_stock = $idps";
    if (!$bd->bd->query($sqlUp)) {
        return array('ok' => false, 'error' => $bd->bd->error);
    }

    $hora = date('h:i a');
    $cantHist = abs($cantidad);
    $tipoEsc = prod_esc($bd, $tipo_movimiento);
    $sqlHist = "INSERT INTO producto_historial(tipo_movimiento, fecha, hora, cantidad, idusuario, idsede, idproducto, idalmacen)
                VALUES ('$tipoEsc', CURDATE(), '$hora', '$cantHist', $idusuario, $idsede, $idproducto, $idalmacen)";
    if (!$bd->bd->query($sqlHist)) {
        return array('ok' => false, 'error' => $bd->bd->error);
    }

    return array('ok' => true, 'costo_unitario' => floatval($row['precio']));
}

function prod_get_almacen_produccion($bd, $idsede) {
    $sql = "SELECT idalmacen AS D1 FROM almacen
            WHERE idsede = $idsede AND descripcion = 'PRODUCCION' AND estado = 0 LIMIT 1";
    return $bd->xDevolverUnDato($sql);
}

/** Crea almacén PRODUCCION si no existe. */
function prod_ensure_almacen_produccion($bd, $idorg, $idsede) {
    $id = prod_get_almacen_produccion($bd, $idsede);
    if ($id && is_numeric($id)) {
        return intval($id);
    }
    $sql = "INSERT INTO almacen (idorg, idsede, descripcion, bodega, imprimir_comanda, estado)
            VALUES ($idorg, $idsede, 'PRODUCCION', 0, 0, 0)";
    if (!$bd->bd->query($sql)) {
        throw new Exception('No se pudo crear almacén PRODUCCION: ' . $bd->bd->error);
    }
    return intval($bd->bd->insert_id);
}

function prod_ensure_familia($bd, $idorg, $idsede, $nombre = 'PRODUCCION') {
    $nom = prod_esc($bd, $nombre);
    $sql = "SELECT idproducto_familia AS d1 FROM producto_familia
            WHERE descripcion='$nom' AND idorg=$idorg AND idsede=$idsede AND estado=0 LIMIT 1";
    $id = $bd->xDevolverUnDato($sql);
    if ($id && $id !== 'null') {
        return $id;
    }
    $sql = "INSERT INTO producto_familia (idproducto_familia, idorg, idsede, descripcion)
            VALUES (0, $idorg, $idsede, '$nom')";
    if (!$bd->bd->query($sql)) {
        throw new Exception('No se pudo crear familia PRODUCCION: ' . $bd->bd->error);
    }
    $sql = "SELECT idproducto_familia AS d1 FROM producto_familia
            WHERE descripcion='$nom' AND idorg=$idorg AND idsede=$idsede AND estado=0 LIMIT 1";
    return $bd->xDevolverUnDato($sql);
}

function prod_ensure_producto_en_almacen($bd, $idproducto, $idalmacen) {
    $idproducto = intval($idproducto);
    $idalmacen = intval($idalmacen);
    $sql = "SELECT idproducto_stock AS d1 FROM producto_stock
            WHERE idproducto=$idproducto AND idalmacen=$idalmacen AND estado=0 LIMIT 1";
    $idps = $bd->xDevolverUnDato($sql);
    if ($idps && is_numeric($idps)) {
        return intval($idps);
    }
    $sql = "INSERT INTO producto_stock (idproducto, idalmacen, stock, estado)
            VALUES ($idproducto, $idalmacen, 0, 0)";
    if (!$bd->bd->query($sql)) {
        throw new Exception('No se pudo vincular producto al almacén: ' . $bd->bd->error);
    }
    return intval($bd->bd->insert_id);
}

function prod_buscar_producto_por_nombre($bd, $idsede, $descripcion) {
    $desc = prod_esc($bd, strtoupper(trim($descripcion)));
    if ($desc === '') {
        return 0;
    }
    $sql = "SELECT idproducto AS d1 FROM producto
            WHERE idsede=$idsede AND estado=0 AND UPPER(TRIM(descripcion))='$desc' LIMIT 1";
    $id = $bd->xDevolverUnDato($sql);
    return ($id && is_numeric($id)) ? intval($id) : 0;
}

function prod_crear_producto_salida($bd, $idorg, $idsede, $idalmacen_prod, $descripcion) {
    $desc = prod_esc($bd, strtoupper(trim($descripcion)));
    if ($desc === '') {
        throw new Exception('Nombre de producto terminado vacío');
    }
    $idfam = prod_ensure_familia($bd, $idorg, $idsede);
    $idfamEsc = prod_esc($bd, $idfam);
    $sql = "INSERT INTO producto (descripcion, idproducto_familia, codigo_barra, stock_minimo,
            precio, precio_unitario, precio_venta, idorg, idsede, img, estado)
            VALUES ('$desc', '$idfamEsc', '', '0', '0', '0', '0', $idorg, $idsede, '', 0)";
    if (!$bd->bd->query($sql)) {
        throw new Exception('No se pudo crear producto: ' . $bd->bd->error);
    }
    $idproducto = intval($bd->bd->insert_id);
    prod_ensure_producto_en_almacen($bd, $idproducto, $idalmacen_prod);
    return $idproducto;
}

/**
 * Resuelve producto terminado: por id o por nombre (crea en almacén PRODUCCION si no existe).
 */
function prod_resolver_producto_salida($bd, $idorg, $idsede, $idproducto, $nombreTexto) {
    $idalmacen_prod = prod_ensure_almacen_produccion($bd, $idorg, $idsede);

    if ($idproducto > 0) {
        prod_ensure_producto_en_almacen($bd, $idproducto, $idalmacen_prod);
        return array('idproducto' => $idproducto, 'idalmacen' => $idalmacen_prod, 'creado' => false);
    }

    $nombreTexto = trim($nombreTexto);
    if ($nombreTexto === '') {
        throw new Exception('Indique producto terminado');
    }

    $existente = prod_buscar_producto_por_nombre($bd, $idsede, $nombreTexto);
    if ($existente > 0) {
        prod_ensure_producto_en_almacen($bd, $existente, $idalmacen_prod);
        return array('idproducto' => $existente, 'idalmacen' => $idalmacen_prod, 'creado' => false);
    }

    $nuevo = prod_crear_producto_salida($bd, $idorg, $idsede, $idalmacen_prod, $nombreTexto);
    return array('idproducto' => $nuevo, 'idalmacen' => $idalmacen_prod, 'creado' => true);
}

/** Resuelve idproducto + idalmacen desde idproducto_stock (vínculo exacto al elegir del autocomplete). */
function prod_resolve_insumo($bd, $ins) {
    $idps = isset($ins->idproducto_stock) ? intval($ins->idproducto_stock) : 0;
    if ($idps > 0) {
        $sql = "SELECT ps.idproducto, ps.idalmacen
                FROM producto_stock ps
                WHERE ps.idproducto_stock = $idps AND ps.estado = 0 LIMIT 1";
        $row = json_decode($bd->xConsulta3($sql), true);
        if ($row && isset($row[0]['idproducto'], $row[0]['idalmacen'])) {
            return array(
                'idproducto' => intval($row[0]['idproducto']),
                'idalmacen' => intval($row[0]['idalmacen'])
            );
        }
        throw new Exception('Insumo no encontrado en stock');
    }
    $idp = intval(isset($ins->idproducto) ? $ins->idproducto : 0);
    $ida = intval(isset($ins->idalmacen_origen) ? $ins->idalmacen_origen
        : (isset($ins->idalmacen) ? $ins->idalmacen : 0));
    if ($idp <= 0 || $ida <= 0) {
        throw new Exception('Seleccione el insumo del autocompletado');
    }
    return array('idproducto' => $idp, 'idalmacen' => $ida);
}

function prod_tablas_existen($bd) {
    $sql = "SELECT COUNT(*) AS c FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'prod_formula'";
    $r = json_decode($bd->xConsulta3($sql), true);
    return ($r && intval($r[0]['c']) > 0);
}

switch ($op) {

    case '1': // id almacén PRODUCCION (crea si no existe)
        try {
            $created = !prod_get_almacen_produccion($bd, $g_idsede);
            $id = prod_ensure_almacen_produccion($bd, $g_ido, $g_idsede);
            prod_json(array('success' => true, 'idalmacen' => $id, 'created' => $created));
        } catch (Exception $e) {
            prod_json(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    case '2': // listar almacenes de la sede
        $sql = "SELECT idalmacen, descripcion FROM almacen
                WHERE idsede = $g_idsede AND estado = 0 ORDER BY descripcion";
        $bd->xConsulta($sql);
        break;

    case '10': // listar fórmulas
        if (!prod_tablas_existen($bd)) {
            prod_json(array('success' => false, 'error' => 'Ejecute migración 2026-06-30_017_prod_modulo.sql'));
            break;
        }
        $sql = "SELECT pf.idprod_formula, pf.descripcion, pf.cantidad_salida_estandar, pf.idalmacen_insumos,
                       p.descripcion AS producto_salida, pf.fecha,
                       (SELECT COUNT(*) FROM prod_formula_insumo fi WHERE fi.idprod_formula = pf.idprod_formula) AS num_insumos
                FROM prod_formula pf
                INNER JOIN producto p ON p.idproducto = pf.idproducto_salida
                WHERE pf.idsede = $g_idsede AND pf.activo = 1
                ORDER BY pf.idprod_formula DESC";
        $bd->xConsulta($sql);
        break;

    case '11': // detalle fórmula
        $data = prod_body();
        $id = intval($data->idprod_formula);
        $sql = "SELECT pf.*, p.descripcion AS producto_salida
                FROM prod_formula pf
                INNER JOIN producto p ON p.idproducto = pf.idproducto_salida
                WHERE pf.idprod_formula = $id AND pf.idsede = $g_idsede LIMIT 1";
        $cab = json_decode($bd->xConsulta3($sql), true);
        $sql2 = "SELECT fi.*, CONCAT(a.descripcion, ' | ', p.descripcion) AS nom_producto, a.descripcion AS nom_almacen
                 FROM prod_formula_insumo fi
                 INNER JOIN producto p ON p.idproducto = fi.idproducto
                 INNER JOIN almacen a ON a.idalmacen = fi.idalmacen_origen
                 WHERE fi.idprod_formula = $id";
        $ins = json_decode($bd->xConsulta3($sql2), true);
        prod_json(array('success' => true, 'formula' => $cab ? $cab[0] : null, 'insumos' => $ins ? $ins : array()));
        break;

    case '12': // guardar fórmula (insert o update)
        if (!prod_tablas_existen($bd)) {
            prod_json(array('success' => false, 'error' => 'Ejecute migración 2026-06-30_017_prod_modulo.sql'));
            break;
        }
        $data = prod_body();
        $idFormula = isset($data->idprod_formula) ? intval($data->idprod_formula) : 0;
        $descripcion = prod_esc($bd, isset($data->descripcion) ? $data->descripcion : '');
        $idproducto_salida = isset($data->idproducto_salida) ? intval($data->idproducto_salida) : 0;
        $producto_salida_text = isset($data->producto_salida_text) ? trim($data->producto_salida_text) : '';
        $cantidad_salida = floatval($data->cantidad_salida_estandar);
        $insumos = isset($data->insumos) ? $data->insumos : array();

        if ($descripcion === '' || $cantidad_salida <= 0) {
            prod_json(array('success' => false, 'error' => 'Complete descripción de fórmula y cantidad estándar de salida'));
            break;
        }
        if (empty($insumos)) {
            prod_json(array('success' => false, 'error' => 'Agregue al menos un insumo'));
            break;
        }

        $bd->bd->begin_transaction();
        try {
            $resSalida = prod_resolver_producto_salida($bd, $g_ido, $g_idsede, $idproducto_salida, $producto_salida_text);
            $idproducto_salida = $resSalida['idproducto'];
            $idalmacen_insumos = 0;
            $insumosResueltos = array();

            foreach ($insumos as $ins) {
                $resolved = prod_resolve_insumo($bd, $ins);
                $cant = floatval($ins->cantidad_estandar);
                $principal = !empty($ins->es_principal) ? 1 : 0;
                if ($resolved['idproducto'] <= 0 || $resolved['idalmacen'] <= 0 || $cant <= 0) {
                    continue;
                }
                if ($idalmacen_insumos === 0) {
                    $idalmacen_insumos = $resolved['idalmacen'];
                }
                $insumosResueltos[] = array(
                    'idproducto' => $resolved['idproducto'],
                    'idalmacen' => $resolved['idalmacen'],
                    'cantidad_estandar' => $cant,
                    'es_principal' => $principal
                );
            }
            if (empty($insumosResueltos)) {
                throw new Exception('No se pudieron resolver los insumos');
            }

            if ($idFormula > 0) {
                $sql = "UPDATE prod_formula SET descripcion='$descripcion', idproducto_salida=$idproducto_salida,
                        cantidad_salida_estandar=$cantidad_salida, idalmacen_insumos=$idalmacen_insumos,
                        idusuario=$g_us, fecha=NOW()
                        WHERE idprod_formula=$idFormula AND idsede=$g_idsede";
                if (!$bd->bd->query($sql)) {
                    throw new Exception($bd->bd->error);
                }
                $bd->bd->query("DELETE FROM prod_formula_insumo WHERE idprod_formula=$idFormula");
            } else {
                $sql = "INSERT INTO prod_formula(idsede, idorg, descripcion, idproducto_salida,
                        cantidad_salida_estandar, idalmacen_insumos, activo, idusuario, fecha)
                        VALUES ($g_idsede, $g_ido, '$descripcion', $idproducto_salida, $cantidad_salida,
                        $idalmacen_insumos, 1, $g_us, NOW())";
                if (!$bd->bd->query($sql)) {
                    throw new Exception($bd->bd->error);
                }
                $idFormula = $bd->bd->insert_id;
            }

            foreach ($insumosResueltos as $ins) {
                $idp = $ins['idproducto'];
                $ida = $ins['idalmacen'];
                $cant = $ins['cantidad_estandar'];
                $principal = $ins['es_principal'];
                $sqlIns = "INSERT INTO prod_formula_insumo(idprod_formula, idproducto, idalmacen_origen,
                           cantidad_estandar, es_principal)
                           VALUES ($idFormula, $idp, $ida, $cant, $principal)";
                if (!$bd->bd->query($sqlIns)) {
                    throw new Exception($bd->bd->error);
                }
            }

            $bd->bd->commit();
            prod_json(array(
                'success' => true,
                'idprod_formula' => $idFormula,
                'idproducto_salida' => $idproducto_salida,
                'producto_creado' => !empty($resSalida['creado'])
            ));
        } catch (Exception $e) {
            $bd->bd->rollback();
            prod_json(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    case '13': // desactivar fórmula
        $data = prod_body();
        $id = intval($data->idprod_formula);
        $sql = "UPDATE prod_formula SET activo = 0 WHERE idprod_formula = $id AND idsede = $g_idsede";
        $bd->xConsulta($sql);
        break;

    case '30': // ejecutar orden de producción
        if (!prod_tablas_existen($bd)) {
            prod_json(array('success' => false, 'error' => 'Ejecute migración 2026-06-30_017_prod_modulo.sql'));
            break;
        }
        $data = prod_body();
        $idFormula = isset($data->idprod_formula) ? intval($data->idprod_formula) : 0;
        $idproducto_salida = isset($data->idproducto_salida) ? intval($data->idproducto_salida) : 0;
        $producto_salida_text = isset($data->producto_salida_text) ? trim($data->producto_salida_text) : '';
        $cantidad_salida = floatval($data->cantidad_salida);
        $insumos = isset($data->insumos) ? $data->insumos : array();
        $detalle = prod_esc($bd, isset($data->detalle) ? $data->detalle : '');

        if ($cantidad_salida <= 0) {
            prod_json(array('success' => false, 'error' => 'Indique cantidad de salida'));
            break;
        }
        if (empty($insumos)) {
            prod_json(array('success' => false, 'error' => 'Agregue insumos a consumir'));
            break;
        }

        $bd->bd->begin_transaction();
        try {
            $resSalida = prod_resolver_producto_salida($bd, $g_ido, $g_idsede, $idproducto_salida, $producto_salida_text);
            $idproducto_salida = $resSalida['idproducto'];
            $idalmacen_salida = $resSalida['idalmacen'];
            $idalmacen_insumos = 0;

            $costoTotal = 0;

            foreach ($insumos as $ins) {
                $resolved = prod_resolve_insumo($bd, $ins);
                $cant = floatval($ins->cantidad);
                if ($resolved['idproducto'] <= 0 || $cant <= 0) {
                    continue;
                }
                if ($idalmacen_insumos === 0) {
                    $idalmacen_insumos = $resolved['idalmacen'];
                }
                $mov = prod_movimiento_stock($bd, $g_idsede, $g_us, $resolved['idproducto'], $resolved['idalmacen'], -$cant, 'PROD_SALIDA');
                if (!$mov['ok']) {
                    throw new Exception($mov['error']);
                }
                $costoTotal += $cant * $mov['costo_unitario'];
            }

            $movIn = prod_movimiento_stock($bd, $g_idsede, $g_us, $idproducto_salida, $idalmacen_salida, $cantidad_salida, 'PROD_ENTRADA');
            if (!$movIn['ok']) {
                throw new Exception($movIn['error']);
            }

            $idFormulaSql = $idFormula > 0 ? $idFormula : 'NULL';
            $sqlOrd = "INSERT INTO prod_orden(idsede, idorg, idprod_formula, idproducto_salida, cantidad_salida,
                       idalmacen_insumos, idalmacen_salida, costo_total, detalle, idusuario, fecha, estado)
                       VALUES ($g_idsede, $g_ido, $idFormulaSql, $idproducto_salida, $cantidad_salida,
                       $idalmacen_insumos, $idalmacen_salida, $costoTotal, '$detalle', $g_us, NOW(), 0)";
            if (!$bd->bd->query($sqlOrd)) {
                throw new Exception($bd->bd->error);
            }
            $idOrden = $bd->bd->insert_id;

            foreach ($insumos as $ins) {
                $resolved = prod_resolve_insumo($bd, $ins);
                $cant = floatval($ins->cantidad);
                if ($resolved['idproducto'] <= 0 || $cant <= 0) {
                    continue;
                }
                $cu = isset($ins->costo_unitario) ? floatval($ins->costo_unitario) : 0;
                $sqlDet = "INSERT INTO prod_orden_insumo(idprod_orden, idproducto, idalmacen, cantidad, costo_unitario)
                           VALUES ($idOrden, {$resolved['idproducto']}, {$resolved['idalmacen']}, $cant, $cu)";
                if (!$bd->bd->query($sqlDet)) {
                    throw new Exception($bd->bd->error);
                }
            }

            $bd->bd->commit();
            prod_json(array(
                'success' => true,
                'idprod_orden' => $idOrden,
                'costo_total' => $costoTotal,
                'idproducto_salida' => $idproducto_salida,
                'producto_creado' => !empty($resSalida['creado'])
            ));
        } catch (Exception $e) {
            $bd->bd->rollback();
            prod_json(array('success' => false, 'error' => $e->getMessage()));
        }
        break;

    case '31': // historial órdenes
        $sql = "SELECT po.idprod_orden, po.fecha, po.cantidad_salida, po.costo_total, po.detalle,
                       p.descripcion AS producto_salida, u.usuario,
                       pf.descripcion AS formula
                FROM prod_orden po
                INNER JOIN producto p ON p.idproducto = po.idproducto_salida
                INNER JOIN usuario u ON u.idusuario = po.idusuario
                LEFT JOIN prod_formula pf ON pf.idprod_formula = po.idprod_formula
                WHERE po.idsede = $g_idsede
                ORDER BY po.idprod_orden DESC LIMIT 100";
        $bd->xConsulta($sql);
        break;

    case '32': // detalle orden
        $data = prod_body();
        $id = intval($data->idprod_orden);
        $sql = "SELECT po.*, p.descripcion AS producto_salida, u.usuario
                FROM prod_orden po
                INNER JOIN producto p ON p.idproducto = po.idproducto_salida
                INNER JOIN usuario u ON u.idusuario = po.idusuario
                WHERE po.idprod_orden = $id AND po.idsede = $g_idsede LIMIT 1";
        $cab = json_decode($bd->xConsulta3($sql), true);
        $sql2 = "SELECT oi.*, CONCAT(a.descripcion, ' | ', p.descripcion) AS nom_producto
                 FROM prod_orden_insumo oi
                 INNER JOIN producto p ON p.idproducto = oi.idproducto
                 INNER JOIN almacen a ON a.idalmacen = oi.idalmacen
                 WHERE oi.idprod_orden = $id";
        $ins = json_decode($bd->xConsulta3($sql2), true);
        prod_json(array('success' => true, 'orden' => $cab ? $cab[0] : null, 'insumos' => $ins ? $ins : array()));
        break;

    default:
        prod_json(array('success' => false, 'error' => 'Operación no válida'));
        break;
}
