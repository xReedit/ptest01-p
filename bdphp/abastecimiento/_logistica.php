<?php
/**
 * Helpers del modulo Logistica (app/logistica/). Incluir en lugar de _bootstrap.php.
 * Respuesta siempre {success, data, error}. Consultas preparadas (patron de log_compras.php).
 */
require_once __DIR__ . '/_bootstrap.php';

class ErrorUsuario extends Exception {}

function log_out($ok, $data = null, $error = null) {
    echo json_encode(array('success' => $ok, 'data' => $data, 'error' => $error), JSON_UNESCAPED_UNICODE);
    exit;
}

function log_body() {
    $b = json_decode(file_get_contents('php://input'), true);
    return is_array($b) ? $b : array();
}

function log_filas($bd, $sql, $params = array()) {
    $bd->prepare($sql);
    $bd->execute($params ? $params : null);
    if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
    return $bd->fetchAll();
}

function log_exec($bd, $sql, $params = array()) {
    $bd->prepare($sql);
    $bd->execute($params ? $params : null);
    if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
    return $bd->stmt->affected_rows;
}

function log_mayus($s, $max) {
    return mb_substr(mb_strtoupper(trim((string)$s), 'UTF-8'), 0, $max, 'UTF-8');
}

/** Config de logistica de la org de la sesion, o null si la org no tiene el modulo. */
/** Porciones por unidad de un producto del catalogo (1 bote = 20), o 0 si se recibe normal al almacen. */
function porciones_de($bd, $idcentral) {
    $r = log_filas($bd, "SELECT porciones FROM log_producto_porcion WHERE idproducto_central = ?", array((int)$idcentral));
    return $r ? (float)$r[0]['porciones'] : 0;
}

/** Productos del catalogo que en las sedes se venden por porcion: [idproducto_central => porciones por unidad]. */
function porciones_org($bd, $ido) {
    $r = array();
    foreach (log_filas($bd, "SELECT idproducto_central, porciones FROM log_producto_porcion WHERE idorg = ?", array((int)$ido)) as $f) {
        $r[(int)$f['idproducto_central']] = (float)$f['porciones'];
    }
    return $r;
}

/**
 * Stock y venta de UNA SEDE (no la central: alli el bote sigue en el almacen) de un producto que se vende por porcion,
 * pasados a la unidad del producto (botes). null si la sede aun no enlazo su porcion (se enlaza al recibir el primer envio).
 * Venta de los ultimos $dias dias = ventas (3) - devoluciones (6); 'ultima' = ultima venta en 90 dias; 'dias' = dias desde la
 * primera venta dentro de la ventana (una heladeria no tiene historia de productos). Indice (idporcion, fecha_date, tipo).
 */
function porcion_sede($bd, $idsede, $idproducto, $factor, $dias) {
    $po = porcion_enlazada($bd, $idsede, $idproducto);
    if (!$po || $factor <= 0) { return null; }
    $id = (int)$po['idporcion'];
    $r = log_filas($bd, "SELECT (SELECT stock FROM porcion WHERE idporcion = ?) AS stock,
            SUM(IF(h.fecha_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY), IF(h.idtipo_movimiento_stock = 6, -1, 1) * ABS(h.cantidad + 0), 0)) AS vendido,
            DATEDIFF(CURDATE(), MIN(IF(h.fecha_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY), h.fecha_date, NULL))) + 1 AS dias,
            MAX(IF(h.idtipo_movimiento_stock = 3, h.fecha_date, NULL)) AS ultima
            FROM porcion_historial h FORCE INDEX (idx_porcion_historial_consulta)
            WHERE h.idporcion = ? AND h.fecha_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) AND h.idtipo_movimiento_stock IN (3, 6)",
        array($id, (int)$dias - 1, (int)$dias - 1, $id))[0];
    return array('idporcion' => $id, 'porcion' => $po['descripcion'], 'factor' => $factor,
                 'stock' => round((float)$r['stock'] / $factor, 3), 'vendido' => max(0, (float)$r['vendido']) / $factor,
                 'dias' => $r['dias'] !== null ? (int)$r['dias'] : 0, 'ultima' => $r['ultima']);
}

/** Porcion de la sede enlazada al producto (porcion.idproducto_de). Si hay varias, la que ya tiene conversion. */
function porcion_enlazada($bd, $idsede, $idproducto) {
    // ponytail: dos porciones del mismo producto (octavo y cuarto de pollo) -> toma una; elegir por envio si algun cliente lo necesita
    $r = log_filas($bd, "SELECT po.idporcion, po.descripcion FROM porcion po
            WHERE po.idproducto_de = ? AND po.idsede = ? AND po.estado = 0
            ORDER BY EXISTS (SELECT 1 FROM porcion_unidad_conversion c WHERE c.idporcion = po.idporcion AND c.activo = 1) DESC, po.idporcion
            LIMIT 1", array((int)$idproducto, (int)$idsede));
    return $r ? $r[0] : null;
}

function log_org($bd, $idorg) {
    $r = log_filas($bd, "SELECT idorg, idsede_central, umbral_aprobacion_oc, dias_alerta_vencimiento, dias_cobertura
                           FROM org_logistica WHERE idorg = ?", array($idorg));
    return $r ? $r[0] : null;
}

/** Administrador (rol 1) o usuario con la opcion Centro Logístico (B15). */
function log_tiene_permiso($bd, $idusuario) {
    $u = log_filas($bd, "SELECT rol, acc FROM usuario WHERE idusuario = ?", array((int)$idusuario));
    // acc a veces viene sin coma final ('...,B15'): se envuelve en comas para comparar exacto
    return $u && ((int)$u[0]['rol'] === 1 || strpos(',' . (string)$u[0]['acc'] . ',', ',B15,') !== false);
}

function log_requiere_permiso($bd, $idusuario) {
    if (!log_tiene_permiso($bd, $idusuario)) { throw new ErrorUsuario('No tienes permiso para modificar el catálogo.'); }
}

/** Corta si la org no tiene logistica, la sesion no esta en la sede central o el usuario no tiene permiso. */
function log_requiere_central($bd, $idorg, $idsede, $idusuario) {
    $org = log_org($bd, $idorg);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    if ((int)$org['idsede_central'] !== (int)$idsede) {
        // nombra las dos sedes y la salida: solo el admin (rol 1) puede cambiar de sede desde la barra de arriba
        $nom = array_column(log_filas($bd, "SELECT idsede, nombre FROM sede WHERE idsede IN (?, ?)",
            array((int)$idsede, (int)$org['idsede_central'])), 'nombre', 'idsede');
        $u = log_filas($bd, "SELECT rol FROM usuario WHERE idusuario = ?", array((int)$idusuario));
        $aqui = isset($nom[(int)$idsede]) ? $nom[(int)$idsede] : 'otra sede';
        $central = isset($nom[(int)$org['idsede_central']]) ? $nom[(int)$org['idsede_central']] : 'la central';
        throw new ErrorUsuario("Estás en $aqui. Esto se hace desde la sede central, $central: "
            . ($u && (int)$u[0]['rol'] === 1 ? 'toca el nombre de la sede, arriba, y elígela.' : 'entra con un usuario de esa sede.'));
    }
    log_requiere_permiso($bd, $idusuario);
    return $org;
}

/** Ejecuta $fn y responde; ErrorUsuario se muestra tal cual, el resto se registra y se responde generico. */
function log_responder($fn) {
    try {
        log_out(true, $fn());
    } catch (ErrorUsuario $e) {
        log_out(false, null, $e->getMessage());
    } catch (Throwable $e) {
        error_log('logistica: ' . $e->getMessage());
        log_out(false, null, 'No se pudo completar la operación. Intenta de nuevo.');
    }
}

/**
 * Permiso para que el trigger trg_log_producto_heredados (migracion 003) deje pasar cambios de campos heredados.
 * La conexion es persistente: se limpia en el finally del caller Y al apagarse PHP (timeout/fatal), para que
 * nunca quede activo en la siguiente peticion que reuse la conexion.
 */
function log_sync_on($bd) {
    static $registrado = false;
    if (!$registrado) {
        register_shutdown_function(function () use ($bd) { @$bd->bd->query("SET @log_sync = NULL"); });
        $registrado = true;
    }
    log_exec($bd, "SET @log_sync = 1");
}

function log_sync_off($bd) {
    if (!$bd->bd->query("SET @log_sync = NULL")) { error_log('logistica: no se pudo limpiar @log_sync'); }
}

/**
 * Doble clic / reintento (migracion 011): el navegador manda un token por formulario y el servidor lo RESERVA en
 * log_token dentro de la transaccion. Un segundo envio igual queda esperando el candado de la PK hasta que el primero
 * termina y recibe "duplicado": se le devuelve el resultado guardado, sin repetir nada. No depende de la sesion PHP.
 */
function log_token($b) {
    return isset($b['token']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$b['token']), 0, 64) : '';
}

/** null = token nuevo (seguir); array = resultado del envio anterior (devolverlo y no hacer nada). Llamar DENTRO de la tx. */
function log_token_reservar($bd, $tipo, $token, $idorg) {
    if ($token === '') { return null; }
    $bd->prepare("INSERT INTO log_token (tipo, token, idorg) VALUES (?, ?, ?)");
    $bd->execute(array($tipo, $token, (int)$idorg));
    if ($bd->stmt->errno === 1062) {
        // solo el resultado de la misma org: un token ajeno nunca devuelve datos de otra empresa
        $r = log_filas($bd, "SELECT resultado FROM log_token WHERE tipo = ? AND token = ? AND idorg = ?", array($tipo, $token, (int)$idorg));
        $prev = $r ? json_decode((string)$r[0]['resultado'], true) : null;
        if (!$prev) { throw new ErrorUsuario('Ese envío ya se está procesando. Espera un momento y recarga.'); }
        return $prev;
    }
    if ($bd->stmt->errno) { throw new Exception($bd->stmt->error); }
    return null;
}

function log_token_cerrar($bd, $tipo, $token, $resultado) {
    if ($token === '') { return; }
    log_exec($bd, "UPDATE log_token SET resultado = ? WHERE tipo = ? AND token = ?", array(json_encode($resultado), $tipo, $token));
}

/** Historial de una orden de compra (log_oc_evento). $us null = el sistema. */
function oc_evento($bd, $idorden, $evento, $us, $detalle = null) {
    log_exec($bd, "INSERT INTO log_oc_evento (idorden, evento, idusuario, fecha_hora, detalle) VALUES (?, ?, ?, NOW(), ?)",
        array((int)$idorden, $evento, $us === null ? null : (int)$us, $detalle === null ? null : mb_substr($detalle, 0, 255)));
}

/** La sesion debe estar en una sede que participa (no la central) y tener permiso. */
function log_requiere_sede($bd, $ido, $idsede, $us) {
    $org = log_org($bd, $ido);
    if (!$org) { throw new ErrorUsuario('Tu empresa aún no tiene Logística activada.'); }
    if ((int)$org['idsede_central'] === (int)$idsede) {
        throw new ErrorUsuario('Estás en la sede central: los pedidos se hacen desde cada sede.');
    }
    $s = log_filas($bd, "SELECT idsede, dias_cobertura FROM log_sede WHERE idsede = ? AND idorg = ? AND activo = 1", array((int)$idsede, (int)$ido));
    if (!$s) { throw new ErrorUsuario('Esta sede no participa en Logística. Pide a la central que la active.'); }
    log_requiere_permiso($bd, $us);
    $org['dias_cobertura_sede'] = $s[0]['dias_cobertura'] !== null ? (int)$s[0]['dias_cobertura'] : (int)$org['dias_cobertura'];
    return $org;
}
