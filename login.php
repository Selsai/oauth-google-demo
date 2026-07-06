<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ui.php';

// --- State et nonce (protection CSRF + anti-rejeu) ---
$state = bin2hex(random_bytes(16));
$nonce = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$_SESSION['oauth_nonce'] = $nonce;

// --- PKCE (Proof Key for Code Exchange) : couche de sécurité supplémentaire ---
// code_verifier : chaîne aléatoire secrète gardée côté serveur
// code_challenge : SHA-256(code_verifier), envoyé à Google en clair
// Même si le code d'autorisation était intercepté, il serait inutilisable
// sans connaître le code_verifier original.
$codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
$codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
$_SESSION['pkce_verifier'] = $codeVerifier;

$params = [
    'client_id'             => GOOGLE_CLIENT_ID,
    'redirect_uri'          => REDIRECT_URI,
    'response_type'         => 'code',
    'scope'                 => 'openid email profile',
    'access_type'           => 'online',
    'state'                 => $state,
    'nonce'                 => $nonce,
    'code_challenge'        => $codeChallenge,
    'code_challenge_method' => 'S256',
    'prompt'                => 'consent',
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

page_start('Authentification', 1);
?>
<div class="flex items-center gap-2 mb-2">
  <span class="text-lg">🌷</span>
  <span class="font-display text-[12px] font-semibold tracking-wide text-[#FF6FA0]">SESSION PRÊTE À S'OUVRIR</span>
</div>

<h1 class="font-display text-2xl sm:text-3xl font-bold mb-3 text-[#4A3F55]">Bienvenue dans la démo</h1>
<p class="text-sm text-[#8B7F99] leading-relaxed mb-8">
  Connecte-toi avec Google pour voir tout le flux OAuth 2.0 / OpenID Connect en action :
  redirection, échange de jeton sécurisé par <strong class="text-[#B8A6FF]">PKCE</strong>,
  validation de l'ID token, puis récupération du profil.
</p>

<a href="<?= htmlspecialchars($authUrl) ?>"
   class="group flex items-center justify-center gap-3 w-full bg-white hover:shadow-lg hover:-translate-y-0.5 text-[#4A3F55] font-semibold text-sm rounded-2xl px-6 py-4 transition-all border-2 border-[#FFD8E8] font-display">
  <svg width="20" height="20" viewBox="0 0 24 24"><path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v3h3.86c2.26-2.09 3.56-5.17 3.56-8.82z"/><path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.86-3c-1.08.72-2.45 1.15-4.07 1.15-3.13 0-5.78-2.11-6.73-4.96H1.29v3.09C3.26 21.3 7.31 24 12 24z"/><path fill="#FBBC05" d="M5.27 14.28c-.25-.72-.38-1.49-.38-2.28s.14-1.56.38-2.28V6.63H1.29A11.96 11.96 0 000 12c0 1.93.46 3.76 1.29 5.37l3.98-3.09z"/><path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.94 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.63l3.98 3.09C6.22 6.86 8.87 4.75 12 4.75z"/></svg>
  Se connecter via Google
  <span class="text-[#D9B8DC] group-hover:translate-x-1 transition">&rarr;</span>
</a>

<div class="mt-5 flex flex-wrap gap-2 text-[11px] font-display font-semibold">
  <span class="px-3 py-1.5 rounded-full bg-[#FFE4EF] text-[#FF6FA0]">🛡️ state</span>
  <span class="px-3 py-1.5 rounded-full bg-[#EDE6FF] text-[#8A6FE0]">🔐 nonce</span>
  <span class="px-3 py-1.5 rounded-full bg-[#E1F7EC] text-[#3FAE7E]">🔑 PKCE S256</span>
</div>

<div class="mt-10 pt-8 border-t border-[#F3E9F7]">
  <p class="font-display text-[12px] font-semibold text-[#B7A9C9] mb-4">CE QUI VA SE PASSER</p>
  <div class="space-y-3">
    <div class="flex items-start gap-3 float" style="animation-delay:0s">
      <span class="text-lg">1️⃣</span>
      <p class="text-[13px] text-[#8B7F99]">Ton navigateur est redirigé vers l'écran de connexion Google avec un <code class="text-[#B8A6FF]">code_challenge</code>.</p>
    </div>
    <div class="flex items-start gap-3 float" style="animation-delay:.3s">
      <span class="text-lg">2️⃣</span>
      <p class="text-[13px] text-[#8B7F99]">Après ton accord, Google renvoie un <code class="text-[#B8A6FF]">code</code> temporaire vers <code class="text-[#B8A6FF]">connect.php</code>.</p>
    </div>
    <div class="flex items-start gap-3 float" style="animation-delay:.6s">
      <span class="text-lg">3️⃣</span>
      <p class="text-[13px] text-[#8B7F99]">Le serveur échange ce code + le <code class="text-[#B8A6FF]">code_verifier</code> secret contre un jeton d'accès et un ID token (JWT).</p>
    </div>
    <div class="flex items-start gap-3 float" style="animation-delay:.9s">
      <span class="text-lg">4️⃣</span>
      <p class="text-[13px] text-[#8B7F99]">Le jeton d'accès sert à appeler <code class="text-[#B8A6FF]">/userinfo</code> et afficher ton profil.</p>
    </div>
  </div>
</div>
<?php
page_end();