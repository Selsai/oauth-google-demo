<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ui.php';

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

// --- Échange du code contre un token (avec code_verifier PKCE) ---
$tokenUrl = 'https://oauth2.googleapis.com/token';
$postFields = [
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => REDIRECT_URI,
    'grant_type'    => 'authorization_code',
    'code_verifier' => $_SESSION['pkce_verifier'],
];

$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    render_error('Erreur réseau lors de l\'appel à Google : ' . $curlError, 2);
}

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    render_error('Impossible de récupérer le token : ' . $response, 2);
}

$accessToken = $tokenData['access_token'];
$idToken = $tokenData['id_token'] ?? null;
$expiresIn = $tokenData['expires_in'] ?? null;

$jwtPayload = null;

if ($idToken) {
    try {
        // Vérification cryptographique complète : signature (via JWKS Google),
        // expiration, émetteur (iss) et audience (aud).
        $jwtPayload = verify_google_id_token($idToken);
    } catch (Throwable $e) {
        render_error('id_token invalide : ' . $e->getMessage(), 3);
    }

    // Vérification du nonce (protection anti-rejeu, propre à notre session)
    if (isset($jwtPayload['nonce']) && $jwtPayload['nonce'] !== ($_SESSION['oauth_nonce'] ?? null)) {
        render_error('Nonce invalide : ce token ne correspond pas à cette session.', 3);
    }
}

// --- Appel de l'endpoint userinfo ---
$userinfoUrl = 'https://openidconnect.googleapis.com/v1/userinfo';
$ch = curl_init($userinfoUrl);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$userinfoResponse = curl_exec($ch);
curl_close($ch);

$userinfo = json_decode($userinfoResponse, true);
$_SESSION['user'] = $userinfo;

unset($_SESSION['oauth_state'], $_SESSION['oauth_nonce'], $_SESSION['pkce_verifier']);

// Explication des claims JWT les plus courants (pour l'affichage pédagogique)
$claimHelp = [
    'iss' => "Émetteur du token (ici, Google)",
    'aud' => "Destinataire prévu du token (ton client_id)",
    'sub' => "Identifiant unique et stable de l'utilisateur chez Google",
    'iat' => "Date de création du token (issued at)",
    'exp' => "Date d'expiration du token",
    'nonce' => "Valeur anti-rejeu, doit correspondre à celle envoyée au login",
    'azp' => "Partie autorisée à utiliser ce token",
    'at_hash' => "Empreinte du token d'accès associé",
    'email' => "Adresse e-mail de l'utilisateur",
    'email_verified' => "Vrai si Google a vérifié cette adresse e-mail",
];

page_start('Profil vérifié', 4);
?>
<div class="flex items-center gap-4 mb-2">
  <?php if (!empty($userinfo['picture'])): ?>
    <img src="<?= htmlspecialchars($userinfo['picture']) ?>" alt="Photo de profil"
         class="w-16 h-16 rounded-full border-4 border-[#FFD8E8] shadow-sm">
  <?php endif; ?>
  <div>
    <h1 class="font-display text-xl font-bold"><?= htmlspecialchars($userinfo['name'] ?? $userinfo['email'] ?? 'Utilisateur') ?></h1>
    <p class="text-sm text-[#8B7F99]"><?= htmlspecialchars($userinfo['email'] ?? '') ?></p>
  </div>
  <?php if (!empty($userinfo['email_verified'])): ?>
    <span class="ml-auto font-display text-[11px] font-semibold bg-[#E1F7EC] text-[#3FAE7E] px-3 py-1.5 rounded-full">✓ Vérifié</span>
  <?php endif; ?>
</div>

<?php if ($expiresIn): ?>
<div class="mt-4 flex items-center gap-2 bg-[#FFF7E4] text-[#C98A1E] rounded-2xl px-4 py-3 text-sm font-display font-semibold">
  ⏳ Jeton d'accès valide encore <span id="countdown"><?= (int)$expiresIn ?></span>s
</div>
<?php endif; ?>

<div class="mt-6 flex flex-wrap gap-2 text-[11px] font-display font-semibold">
  <span class="px-3 py-1.5 rounded-full bg-[#EDE6FF] text-[#8A6FE0]">🔑 PKCE vérifié</span>
  <span class="px-3 py-1.5 rounded-full bg-[#FFE4EF] text-[#FF6FA0]">🛡️ state validé</span>
  <span class="px-3 py-1.5 rounded-full bg-[#E1F7EC] text-[#3FAE7E]">🔐 nonce validé</span>
  <span class="px-3 py-1.5 rounded-full bg-[#FFE9C6] text-[#C98A1E]">✍️ signature JWT vérifiée</span>
</div>

<?php if ($jwtPayload): ?>
<div class="mt-8">
  <p class="font-display text-[12px] font-semibold text-[#B7A9C9] mb-3">CLAIMS DE L'ID TOKEN (JWT VÉRIFIÉ)</p>
  <div class="grid sm:grid-cols-2 gap-2">
    <?php foreach ($jwtPayload as $key => $value): ?>
      <?php if (is_array($value)) { $value = json_encode($value); } ?>
      <div class="bg-[#FBF7FF] border border-[#F0E6FA] rounded-xl px-3 py-2 group relative">
        <p class="font-display text-[11px] font-bold text-[#8A6FE0]"><?= htmlspecialchars($key) ?></p>
        <p class="text-[12px] text-[#5C5068] break-all"><?= htmlspecialchars((string)$value) ?></p>
        <?php if (isset($claimHelp[$key])): ?>
          <p class="text-[10px] text-[#B7A9C9] mt-1 italic"><?= htmlspecialchars($claimHelp[$key]) ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="mt-8">
  <div class="flex items-center justify-between mb-2">
    <p class="font-display text-[12px] font-semibold text-[#B7A9C9]">RÉPONSE DE GET /v1/userinfo</p>
    <button onclick='copyText(<?= json_encode(json_encode($userinfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?>, this)'
      class="copy-btn font-display text-[11px] font-semibold text-[#8A6FE0] bg-[#EDE6FF] px-3 py-1 rounded-full">📋 Copier</button>
  </div>
  <pre class="bg-[#FBF7FF] border border-[#F0E6FA] rounded-2xl p-4 text-[12px] text-[#5C5068] overflow-x-auto leading-relaxed"><?= htmlspecialchars(json_encode($userinfo, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
</div>

<a href="logout.php" class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-[#FF6FA0] hover:text-[#FF4D8D] transition font-display">
  Se déconnecter &rarr;
</a>

<script>
launchConfetti();
<?php if ($expiresIn): ?>
let remaining = <?= (int)$expiresIn ?>;
const el = document.getElementById('countdown');
setInterval(() => {
  remaining = Math.max(0, remaining - 1);
  if (el) el.textContent = remaining;
}, 1000);
<?php endif; ?>
</script>
<?php
page_end();