<?php

function page_start(string $title, int $activeStep = 1): void {
    $steps = [
        1 => ['label' => 'Identification', 'icon' => '🔑'],
        2 => ['label' => 'Consentement Google', 'icon' => '🤝'],
        3 => ['label' => 'Échange du jeton', 'icon' => '🔄'],
        4 => ['label' => 'Profil vérifié', 'icon' => '✨'],
    ];
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Nunito Sans', sans-serif; }
  .font-display { font-family: 'Quicksand', sans-serif; }
  .blob { position: absolute; border-radius: 50%; filter: blur(60px); opacity: .55; z-index: 0; }
  .copy-btn { transition: transform .15s ease; }
  .copy-btn:active { transform: scale(.92); }
  @keyframes floaty { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
  .float { animation: floaty 5s ease-in-out infinite; }
</style>
</head>
<body class="min-h-screen bg-gradient-to-br from-[#FFF1F7] via-[#F6F1FF] to-[#EAF7FF] text-[#4A3F55] antialiased relative overflow-x-hidden">

<div class="blob w-72 h-72 bg-[#FFC1D9] -top-10 -left-10"></div>
<div class="blob w-80 h-80 bg-[#C7B8FF] top-1/3 -right-16"></div>
<div class="blob w-64 h-64 bg-[#B5F1D8] bottom-0 left-1/4"></div>

<div class="min-h-screen flex flex-col items-center justify-center px-6 py-12 relative z-10">

  <div class="w-full max-w-xl mb-8">
    <div class="flex items-center">
      <?php $i = 1; foreach ($steps as $num => $s): ?>
        <?php $isActive = $num === $activeStep; $isDone = $num < $activeStep; ?>
        <?php if ($i > 1): ?>
          <div class="h-1 flex-1 mx-1.5 rounded-full <?= $isDone || $isActive ? 'bg-gradient-to-r from-[#FF9FC0] to-[#B8A6FF]' : 'bg-[#EADFF5]' ?>"></div>
        <?php endif; ?>
        <div class="flex flex-col items-center gap-1 shrink-0">
          <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm shadow-sm
            <?= $isDone ? 'bg-gradient-to-br from-[#7FDBB6] to-[#9FE6C6]' : ($isActive ? 'bg-white ring-2 ring-[#FF9FC0]' : 'bg-white/70 text-[#C6BBD6]') ?>">
            <?= $isDone ? '✓' : $s['icon'] ?>
          </div>
          <span class="hidden sm:block font-display text-[10px] font-semibold text-center <?= $isActive ? 'text-[#FF6FA0]' : 'text-[#B7A9C9]' ?>"><?= $s['label'] ?></span>
        </div>
      <?php $i++; endforeach; ?>
    </div>
  </div>

  <div class="w-full max-w-xl bg-white/80 backdrop-blur-md border border-white rounded-[2rem] p-8 sm:p-10 shadow-xl shadow-[#E4D9FF]/40">
    <?php
}

function page_end(): void {
    ?>
  </div>
  <p class="mt-8 font-display text-[12px] font-semibold text-[#C6BBD6] tracking-wide">🌸 Démo OAuth 2.0 · OpenID Connect · Google</p>
</div>

<script>
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const original = btn.innerHTML;
    btn.innerHTML = '✓ Copié !';
    setTimeout(() => btn.innerHTML = original, 1400);
  });
}

function launchConfetti() {
  const colors = ['#FF9FC0', '#B8A6FF', '#9FE6C6', '#FFD98E', '#9FD3FF'];
  for (let i = 0; i < 60; i++) {
    const el = document.createElement('div');
    const size = 6 + Math.random() * 6;
    el.style.cssText = `
      position:fixed; top:-20px; left:${Math.random()*100}vw;
      width:${size}px; height:${size}px; background:${colors[Math.floor(Math.random()*colors.length)]};
      border-radius:${Math.random() > .5 ? '50%' : '3px'};
      z-index:9999; pointer-events:none; opacity:.9;
    `;
    document.body.appendChild(el);
    const fall = el.animate([
      { transform: `translate(0,0) rotate(0deg)`, opacity: .9 },
      { transform: `translate(${(Math.random()-.5)*200}px, 100vh) rotate(${Math.random()*720}deg)`, opacity: 0 }
    ], { duration: 2200 + Math.random()*1200, easing: 'ease-in' });
    fall.onfinish = () => el.remove();
  }
}
</script>
</body>
</html>
    <?php
}

function render_error(string $message, int $activeStep = 2): void {
    page_start('Erreur', $activeStep);
    ?>
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-2xl bg-[#FFE4B8] flex items-center justify-center shrink-0 text-lg">⚠️</div>
      <div>
        <h1 class="font-display text-lg font-semibold text-[#E08A3C] mb-2">Oups, la vérification a échoué</h1>
        <p class="text-sm text-[#8B7F99] leading-relaxed"><?= htmlspecialchars($message) ?></p>
      </div>
    </div>
    <a href="login.php" class="mt-8 inline-flex items-center gap-2 text-sm font-semibold text-[#FF6FA0] hover:text-[#FF4D8D] transition font-display">
      &larr; Recommencer la connexion
    </a>
    <?php
    page_end();
    exit;
}

// Décode un segment base64url d'un JWT en tableau associatif
function jwt_decode_segment(string $segment): ?array {
    $decoded = base64_decode(strtr($segment, '-_', '+/') . str_repeat('=', (4 - strlen($segment) % 4) % 4));
    $data = json_decode($decoded, true);
    return is_array($data) ? $data : null;
}