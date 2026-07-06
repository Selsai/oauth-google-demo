<?php
// Démarre la session PHP (nécessaire pour stocker state/nonce et l'utilisateur connecté)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/secret.php';

// Autoloader de Composer (nécessaire pour firebase/php-jwt)
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

define('REDIRECT_URI', 'http://localhost:8000/connect.php');

// Endpoint des clés publiques Google (JWKS), utilisé pour vérifier la signature des id_token
define('GOOGLE_JWKS_ENDPOINT', 'https://www.googleapis.com/oauth2/v3/certs');

// Émetteurs valides pour un id_token Google
define('GOOGLE_VALID_ISSUERS', ['accounts.google.com', 'https://accounts.google.com']);

/**
 * Récupère les clés publiques JWKS de Google (avec un cache fichier de 1h
 * pour éviter de refaire l'appel réseau à chaque requête).
 */
function fetch_google_jwks(): array
{
    $cacheFile = sys_get_temp_dir() . '/google_jwks_cache.json';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            return $cached;
        }
    }

    $ch = curl_init(GOOGLE_JWKS_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $jwks = json_decode($response, true);
    if ($jwks) {
        file_put_contents($cacheFile, $response);
    }

    return $jwks ?: [];
}

/**
 * Décode ET vérifie cryptographiquement un id_token Google à l'aide de
 * firebase/php-jwt et des clés publiques JWKS de Google.
 *
 * Vérifie :
 * - la signature (via les clés publiques Google)
 * - l'expiration (exp) — gérée automatiquement par la librairie
 * - l'émetteur (iss)
 * - le destinataire (aud) = notre client_id
 *
 * @throws Exception si la signature ou les claims sont invalides
 * @return array Les claims du token, sous forme de tableau associatif
 */
function verify_google_id_token(string $jwt): array
{
    if (!class_exists(JWT::class)) {
        throw new RuntimeException(
            "La librairie firebase/php-jwt n'est pas installée. Lance : composer require firebase/php-jwt"
        );
    }

    $jwks = fetch_google_jwks();
    if (empty($jwks['keys'])) {
        throw new RuntimeException('Impossible de récupérer les clés publiques Google (JWKS).');
    }

    // JWK::parseKeySet() choisit automatiquement la bonne clé selon le "kid" du header du JWT
    $keys = JWK::parseKeySet($jwks);

    // Décode + vérifie la signature (lève une exception si invalide, expiré, mal formé...)
    $decoded = JWT::decode($jwt, $keys);

    // Conversion récursive en tableau associatif (JWT::decode renvoie un stdClass)
    $claims = json_decode(json_encode($decoded), true);

    if (!isset($claims['iss']) || !in_array($claims['iss'], GOOGLE_VALID_ISSUERS, true)) {
        throw new RuntimeException('Émetteur (iss) invalide dans l\'id_token.');
    }

    if (!isset($claims['aud']) || $claims['aud'] !== GOOGLE_CLIENT_ID) {
        throw new RuntimeException('Audience (aud) invalide dans l\'id_token.');
    }

    return $claims;
}