# Démo OAuth 2.0 / OpenID Connect avec Google (PHP)

Implémentation complète du flux d'authentification OIDC "serveur" avec Google,
incluant les protections **state** (CSRF), **nonce** (anti-rejeu), **PKCE**
(S256) et la **vérification cryptographique de signature** de l'ID token via
les clés publiques JWKS de Google (`firebase/php-jwt`).

## Arborescence

```
oauth-google-demo/
├── secret.example.php   # modèle à copier en secret.php
├── secret.php           # tes identifiants Google (ignoré par git)
├── config.php           # session, config, vérification JWT signée
├── ui.php                # style HTML/CSS
├── login.php             # page de connexion (state, nonce, PKCE)
├── connect.php           # callback : échange le code, vérifie, affiche le profil
├── logout.php            # déconnexion
├── index.php             # redirige vers login.php
├── composer.json / .lock # dépendance firebase/php-jwt
└── vendor/                # généré par composer (ignoré par git)
```

## Installation

1. Cloner le repo :
   ```
   git clone <url-du-repo>
   cd oauth-google-demo
   ```
2. Installer les dépendances PHP :
   ```
   composer install
   ```
3. Copier le modèle de configuration :
   ```
   cp secret.example.php secret.php
   ```
4. Remplir `secret.php` avec tes propres identifiants OAuth 2.0 obtenus dans
   [Google Cloud Console](https://console.cloud.google.com/apis/credentials) :
   - Type d'application : **Application Web**
   - URI de redirection autorisé : `http://localhost:8000/connect.php`
5. Lancer le serveur :
   ```
   php -S localhost:8000
   ```
6. Ouvrir http://localhost:8000 dans le navigateur.

## Déroulé du flux

1. **login.php** génère un `state` (CSRF), un `nonce` (anti-rejeu) et une paire
   PKCE (`code_verifier` / `code_challenge` en SHA-256), puis redirige vers
   l'écran de consentement Google.
2. Google redirige vers **connect.php** avec `?code=...&state=...`.
3. `connect.php` vérifie le `state`, échange le `code` (+ `code_verifier`)
   contre un `access_token` et un `id_token` via `POST /token`.
4. L'`id_token` (JWT) est **vérifié cryptographiquement** (`config.php` →
   `verify_google_id_token()`) : signature via les clés publiques JWKS de
   Google, expiration, émetteur (`iss`) et audience (`aud`). Le `nonce` est
   comparé à celui généré à l'étape 1.
5. Le `access_token` est utilisé pour appeler `GET /v1/userinfo` et récupérer
   le profil (nom, email, photo).
6. Le profil et les claims du JWT vérifié sont affichés à l'écran.

## Sécurité

- Le secret Google (`secret.php`) n'est **jamais** poussé sur GitHub (voir `.gitignore`).
- La signature de l'`id_token` est vérifiée avec les clés publiques Google
  (JWKS), pas seulement décodée — protection contre les tokens falsifiés.
- Protections CSRF (`state`), anti-rejeu (`nonce`), et PKCE (`code_challenge`
  S256) sont toutes implémentées.