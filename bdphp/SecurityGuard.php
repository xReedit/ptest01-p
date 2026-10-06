<?php
/**
 * SecurityGuard - Protección simple contra acceso directo a archivos PHP
 * 
 * Uso:
 * require_once __DIR__ . '/SecurityGuard.php';
 * SecurityGuard::verificarAcceso();
 */

class SecurityGuard {
    
    /**
     * Verifica que el acceso sea legítimo (desde la aplicación y autenticado)
     * 
     * @param bool $verificarSesion Si debe verificar que el usuario esté logueado (default: true)
     * @param array $casosExcluidos Array de casos (op) que no requieren verificación
     * @return void Si falla, termina la ejecución con error JSON
     */
    public static function verificarAcceso($verificarSesion = true, $casosExcluidos = []) {
        // Si hay casos excluidos, verificar si el op actual está en la lista
        if (!empty($casosExcluidos)) {
            $op = isset($_GET['op']) ? intval($_GET['op']) : 0;
            if (in_array($op, $casosExcluidos)) {
                // Caso público - solo iniciar sesión pero NO verificar referer ni sesión
                // Esto permite login (-1) y verificación de sesión (102)
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                self::soltarSesionSiSoloLee();
                return; // Permitir sin verificar nada
            }
        }
        
        // 1. Verificar que venga desde tu aplicación (no acceso directo)
        self::verificarReferer();
        
        // 2. Verificar que el usuario esté autenticado (si se requiere)
        if ($verificarSesion) {
            self::verificarSesion();
        }
        self::soltarSesionSiSoloLee();
    }

    /**
     * PHP bloquea la sesion mientras dura la peticion: si una consulta tarda, TODAS las demas
     * peticiones del mismo usuario (otras pestañas, el router, la caja) esperan en fila.
     * Estos archivos solo LEEN $_SESSION (sigue disponible en memoria tras cerrarla), asi que
     * se suelta el candado apenas se valida el acceso. NO agregar aqui un archivo que escriba
     * $_SESSION (log.php, log_004, log_compras, log_cuentas, log_pos_op): lo escrito se perderia.
     */
    private static function soltarSesionSiSoloLee() {
        static $soloLeen = array(
            'log_001.php', 'log_002.php', 'log_003.php', 'log_005.php', 'log_007.php', 'log_008.php',
            'log_009.php', 'log_010.php', 'log_011.php', 'log_100.php', 'log_asistencia.php',
            'log_carta_export.php', 'log_chart.php', 'log_componentes.php', 'log_costeo.php',
            'log_encuesta.php', 'log_inventario.php', 'log_run.php', 'log_soap.php',
            'log_subrecetas.php', 'log_suscripcion.php', 'log_tracker.php'
        );
        if (session_status() === PHP_SESSION_ACTIVE
            && in_array(basename($_SERVER['SCRIPT_FILENAME'] ?? ''), $soloLeen, true)) {
            session_write_close();
        }
    }
    
    /**
     * Verifica que la petición venga desde tu dominio
     */
    private static function verificarReferer() {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Si no hay referer o no viene de tu dominio, bloquear
        if (empty($referer) || strpos($referer, $host) === false) {
            self::bloquear(403, 'ERR_FORBIDDEN: Invalid request origin');
        }

        // 2026-09 (paginas falsas / CSRF): antes bastaba que el texto del host apareciera en cualquier
        // parte del referer ("https://sitio-malo.com/?mi-host" pasaba). Ahora el HOST del referer debe
        // ser el mismo servidor (o un subdominio suyo), sin importar el puerto. Solo puede rechazar
        // casos que la regla anterior aceptaba; esos se anotan en el log de PHP para poder revisarlos.
        $hostSolo = strtolower(preg_replace('/:\d+$/', '', $host));
        $refHost = strtolower((string)parse_url($referer, PHP_URL_HOST));
        $mismo = $hostSolo !== '' && $refHost !== ''
            && ($refHost === $hostSolo || substr($refHost, -strlen('.' . $hostSolo)) === '.' . $hostSolo);
        if (!$mismo) {
            error_log('SecurityGuard: referer de otro sitio bloqueado: ' . substr($referer, 0, 200) . ' (host ' . $host . ')');
            self::bloquear(403, 'ERR_FORBIDDEN: Invalid request origin');
        }
    }
    
    /**
     * Verifica que el usuario esté autenticado
     */
    private static function verificarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['idusuario']) || !isset($_SESSION['idsede'])) {
            self::bloquear(401, 'ERR_UNAUTHORIZED: Authentication required');
        }

        // Sede bloqueada o dada de baja: se corta la sesión (se revisa como máximo cada hora, ver SEDE_BLOQUEADA_TTL).
        require_once __DIR__ . '/_sede_estado.php';
        if (!xSedeHabilitada($_SESSION['idsede'])) {
            xSedeBloqueadaSalir();
        }
    }
    
    /**
     * Bloquea el acceso y devuelve error JSON
     */
    private static function bloquear($codigo, $mensaje) {
        http_response_code($codigo);
        header('Content-Type: application/json');
        die(json_encode([
            'success' => false,
            'error' => $mensaje,
            'code' => $codigo
        ]));
    }
    
    /**
     * Verifica solo el referer (sin verificar sesión)
     * Útil para endpoints públicos que solo quieres proteger de acceso directo
     */
    public static function verificarRefererSolamente() {
        self::verificarReferer();
    }
    
    /**
     * Verifica solo la sesión (sin verificar referer)
     * Útil si necesitas permitir acceso desde cualquier origen pero autenticado
     */
    public static function verificarSesionSolamente() {
        self::verificarSesion();
    }
}
