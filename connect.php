<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ui.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

if (isset($_GET['error'])) {
    render_error('Google a renvoyé une erreur : ' . $_GET['error'], 1);
}

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    render_error('Requête invalide : le code ou le state est manquant dans l\'URL.', 1);
}

if (!isset($_SESSION['oauth_state']) || $_GET['state'] !== $_SESSION['oauth_state']) {
    render_error('State invalide (protection CSRF). Recommence la connexion depuis le début.', 1);
}

if (empty($_SESSION['pkce_verifier'])) {
    render_error('Le code_verifier PKCE est introuvable en session. Recommence la connexion.', 1);
}

$code = $_GET['code'];

$client = new Client([
    'timeout' => 5.0,
]);

try {
    // --- Découverte automatique des endpoints Google via OpenID Connect Discovery ---
    // (au lieu de coder les URLs en dur, on les récupère dynamiquement, comme dans les slides)
    $discoveryResponse = $client->request('GET', 'https://accounts.google.com/.well-known/openid-configuration');
    $discoveryJSON = json_decode((string) $discoveryResponse->getBody());
    $tokenEndpoint = $discoveryJSON->token_endpoint;
    $userInfoEndpoint = $discoveryJSON->userinfo_endpoint;

    // --- Échange du code contre un access_token (+ id_token), avec le code_verifier PKCE ---
    $accessTokenResponse = $client->request('POST', $tokenEndpoint, [
        'form_params' => [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => REDIRECT_URI,
            'grant_type'    => 'authorization_code',
            'code_verifier' => $_SESSION['pkce_verifier'],
        ],
    ]);

    $tokenData = json_decode((string) $accessTokenResponse->getBody());
    $accessToken = $tokenData->access_token;
    $idToken = $tokenData->id_token ?? null;

    // --- Vérification cryptographique complète de l'id_token (signature JWKS, iss, aud, exp) ---
    if ($idToken) {
        $jwtPayload = verify_google_id_token($idToken);

        if (isset($jwtPayload['nonce']) && $jwtPayload['nonce'] !== ($_SESSION['oauth_nonce'] ?? null)) {
            render_error('Nonce invalide : ce token ne correspond pas à cette session.', 3);
        }
    }

    // --- Récupération du profil utilisateur via l'endpoint userinfo découvert dynamiquement ---
    $authorizationBearer = 'Bearer ' . $accessToken;
    $userResponse = $client->request('GET', $userInfoEndpoint, [
        'headers' => [
            'Authorization' => $authorizationBearer,
        ],
    ]);
    $userInfos = json_decode((string) $userResponse->getBody());

    // --- On n'ouvre l'accès à la page protégée que si l'email est vérifié par Google ---
    if ($userInfos->email_verified === true) {
        $_SESSION['email'] = $userInfos->email;
        $_SESSION['user'] = (array) $userInfos;
        $_SESSION['id_token_claims'] = $jwtPayload ?? null;
        $_SESSION['expires_in'] = $tokenData->expires_in ?? null;

        unset($_SESSION['oauth_state'], $_SESSION['oauth_nonce'], $_SESSION['pkce_verifier']);

        header('Location: /profile.php');
        exit();
    }

    render_error("L'adresse e-mail de ce compte Google n'est pas vérifiée.", 3);

} catch (ClientException $exception) {
    render_error('Erreur lors de la communication avec Google : ' . $exception->getMessage(), 2);
} catch (Throwable $exception) {
    render_error('id_token invalide : ' . $exception->getMessage(), 3);
}