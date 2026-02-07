<?php
/**
 * Configuración para generación de imágenes con IA
 * 
 * IMPORTANTE: Este archivo contiene las API keys. No lo subas a repositorios públicos.
 */

// ============================================
// PROVEEDOR DE IA
// ============================================
// Opciones: 'pollinations', 'replicate', 'stability', 'openai'
// 
// 'pollinations' - GRATIS, sin API key necesaria (recomendado para empezar)
// 'replicate'    - Pago por uso (~$0.002 por imagen)
// 'stability'    - Pago por uso (~$0.01 por imagen)
// 'openai'       - Más caro (~$0.04 por imagen con DALL-E 3)

define('AI_PROVIDER', 'pollinations');

// ============================================
// API KEYS
// ============================================
// Solo necesitas configurar la API key del proveedor que uses

// Pollinations.ai - GRATIS (no requiere API key)
// Sitio: https://pollinations.ai/

// Replicate
// Sitio: https://replicate.com/
// Obtén tu API key en: https://replicate.com/account/api-tokens
define('REPLICATE_API_KEY', '');

// Stability AI
// Sitio: https://stability.ai/
// Obtén tu API key en: https://platform.stability.ai/account/keys
define('STABILITY_API_KEY', '');

// OpenAI (DALL-E)
// Sitio: https://openai.com/
// Obtén tu API key en: https://platform.openai.com/api-keys
define('OPENAI_API_KEY', '');

// ============================================
// CONFIGURACIÓN ADICIONAL
// ============================================

// Directorio donde se guardarán las imágenes generadas (para algunos proveedores)
define('AI_IMAGES_DIR', '../file/ai_images/');

// Tiempo máximo de espera para generación (segundos)
define('AI_TIMEOUT', 60);

// ============================================
// NOTAS IMPORTANTES
// ============================================
/*
 * POLLINATIONS (Recomendado para empezar):
 * - Totalmente GRATIS
 * - No requiere registro ni API key
 * - Buena calidad para imágenes de comida
 * - Sin límites de uso
 * - Perfecto para testing
 * 
 * REPLICATE:
 * - Requiere cuenta y API key
 * - Pago por uso: ~$0.002 por imagen
 * - Modelos: Stable Diffusion XL
 * - Buena calidad
 * 
 * STABILITY AI:
 * - Requiere cuenta y API key
 * - Pago por uso: ~$0.01 por imagen
 * - Modelos: Stable Diffusion XL
 * - Muy buena calidad
 * 
 * OPENAI (DALL-E 3):
 * - Requiere cuenta y API key
 * - Más caro: ~$0.04 por imagen
 * - Mejor calidad y comprensión de prompts
 * - Ideal para resultados profesionales
 * 
 * RECOMENDACIÓN:
 * 1. Empieza con Pollinations (gratis) para probar
 * 2. Si necesitas mejor calidad, usa Replicate o Stability
 * 3. Para máxima calidad, usa OpenAI DALL-E 3
 */
