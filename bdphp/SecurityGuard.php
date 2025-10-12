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
     * @return void Si falla, termina la ejecución con error JSON
     */
    public static function verificarAcceso($verificarSesion = true) {
        // 1. Verificar que venga desde tu aplicación (no acceso directo)
        self::verificarReferer();
        
        // 2. Verificar que el usuario esté autenticado (si se requiere)
        if ($verificarSesion) {
            self::verificarSesion();
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
            self::bloquear(403, 'Acceso no autorizado - Debe acceder desde la aplicación');
        }
    }
    
    /**
     * Verifica que el usuario esté autenticado
     */
    private static function verificarSesion() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['idsede'])) {
            self::bloquear(401, 'No autenticado - Debe iniciar sesión');
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
