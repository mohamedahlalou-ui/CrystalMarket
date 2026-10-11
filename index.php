<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
cm_send_security_headers();
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
if ($requestPath !== '/') {
    $router = new \CrystalMarket\Http\Router();
    $router->add('GET', '/', static function (): void {});
    $router->add('GET', '/admin', static function (): void {
        $target = !empty($_SESSION['admin_user_id']) ? '/admin/dashboard' : '/admin/login';
        header('Location: ' . $target, true, 302);
    });
    $router->add('GET', '/admin/', static function (): void {
        $target = !empty($_SESSION['admin_user_id']) ? '/admin/dashboard' : '/admin/login';
        header('Location: ' . $target, true, 302);
    });
    $router->add('GET', '/admin/login', [\CrystalMarket\Http\AdminController::class, 'login']);
    $router->add('POST', '/admin/login', [\CrystalMarket\Http\AdminController::class, 'login']);
    $router->add('GET', '/admin/dashboard', [\CrystalMarket\Http\AdminController::class, 'dashboard']);
    $router->add('POST', '/admin/logout', [\CrystalMarket\Http\AdminController::class, 'logout']);

    // Public routes are explicit so unsupported paths remain a real 404.
    $router->add('GET', '/listings', [\CrystalMarket\Http\PublicController::class, 'listings']);
    $router->add('GET', '/categories', [\CrystalMarket\Http\PublicController::class, 'listings']);
    $router->add('GET', '/listing', [\CrystalMarket\Http\PublicController::class, 'listing']);
    $router->add('GET', '/register', [\CrystalMarket\Http\PublicController::class, 'register']);
    $router->add('POST', '/register', [\CrystalMarket\Http\PublicController::class, 'register']);
    $router->add('GET', '/login', [\CrystalMarket\Http\PublicController::class, 'login']);
    $router->add('POST', '/login', [\CrystalMarket\Http\PublicController::class, 'login']);
    $router->add('POST', '/logout', [\CrystalMarket\Http\PublicController::class, 'logout']);
    $router->add('GET', '/favorites', [\CrystalMarket\Http\PublicController::class, 'favorites']);
    $router->add('POST', '/favorites/toggle', [\CrystalMarket\Http\PublicController::class, 'toggleFavorite']);
    $router->add('GET', '/messages', [\CrystalMarket\Http\PublicController::class, 'messages']);
    $router->add('POST', '/messages', [\CrystalMarket\Http\PublicController::class, 'messages']);
    $router->add('GET', '/account', [\CrystalMarket\Http\PublicController::class, 'account']);
    $router->add('POST', '/account', [\CrystalMarket\Http\PublicController::class, 'account']);
    $router->add('GET', '/legal', [\CrystalMarket\Http\PublicController::class, 'staticPage']);
    $router->add('GET', '/privacy', [\CrystalMarket\Http\PublicController::class, 'staticPage']);
    $router->add('GET', '/terms', [\CrystalMarket\Http\PublicController::class, 'staticPage']);
    $router->dispatch((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), rtrim($requestPath, '/') ?: '/');
    exit;
}

/** CrystalMarket public UI — Phase 2 interface shell. Listings are not database-backed yet. */
$allowedLanguages = ['fr', 'nl', 'en', 'es'];
$requestedLanguage = strtolower((string)($_GET['lang'] ?? 'fr'));
$lang = in_array($requestedLanguage, $allowedLanguages, true) ? $requestedLanguage : 'fr';
$translationFile = __DIR__ . '/resources/lang/' . $lang . '/common.json';
if (!is_file($translationFile)) {
    $translationFile = __DIR__ . '/resources/lang/fr/common.json';
}
$translationData = file_get_contents($translationFile);
$translations = is_string($translationData) ? json_decode($translationData, true) : null;
if (!is_array($translations) || !isset($translations['hero_title'])) {
    http_response_code(500);
    exit('Application translations are unavailable.');
}

$t = $translations;
$e = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$searchTerm = trim((string)($_GET['q'] ?? ''));
$cityTerm = trim((string)($_GET['city'] ?? ''));
$dir = 'ltr';
?><!doctype html>
<html lang="<?= $e($lang) ?>" dir="<?= $dir ?>" data-theme="light">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#3a55e8"><meta name="description" content="<?= $e($t['tagline']) ?>">
  <title>CrystalMarket — <?= $e($t['tagline']) ?></title>
  <link rel="manifest" href="/manifest.webmanifest"><link rel="icon" href="/assets/icons/crystal.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/tokens.css"><link rel="stylesheet" href="/assets/css/app.css">
  <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="topbar glass">
  <a class="brand" href="/?lang=<?= $e($lang) ?>" aria-label="CrystalMarket home"><span class="brand-mark" aria-hidden="true"><img src="/assets/icons/crystal.svg" alt=""></span><span><strong>CrystalMarket</strong><small><?= $e($t['tagline']) ?></small></span></a>
  <form class="top-search" action="/" method="get" role="search"><input type="hidden" name="lang" value="<?= $e($lang) ?>"><label class="sr-only" for="global-search"><?= $e($t['search']) ?></label><input id="global-search" name="q" value="<?= $e($searchTerm) ?>" placeholder="<?= $e($t['search']) ?>"><button class="icon-button" type="submit" aria-label="<?= $e($t['search_btn']) ?>">⌕</button></form>
  <div class="top-actions"><a class="quiet-link desktop-only" href="/login?lang=<?= $e($lang) ?>"><?= $e($t['login']) ?></a><a class="button button-primary button-small" href="/register?lang=<?= $e($lang) ?>"><?= $e($t['register']) ?></a><label class="sr-only" for="language-select"><?= $e($t['language']) ?></label><select id="language-select" class="language-select" aria-label="<?= $e($t['language']) ?>"><option value="fr" <?= $lang==='fr'?'selected':'' ?>>FR</option><option value="en" <?= $lang==='en'?'selected':'' ?>>EN</option><option value="nl" <?= $lang==='nl'?'selected':'' ?>>NL</option><option value="es" <?= $lang==='es'?'selected':'' ?>>ES</option></select><button class="icon-button mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Menu">☰</button></div>
</header>
<div class="page-layout">
  <aside class="sidebar glass" id="primary-nav"><nav aria-label="Primary"><a class="nav-link active" href="/?lang=<?= $e($lang) ?>"><span>⌂</span><?= $e($t['home']) ?></a><a class="nav-link" href="/listings?lang=<?= $e($lang) ?>"><span>▦</span><?= $e($t['listings']) ?></a><a class="nav-link" href="/#categories"><span>◇</span><?= $e($t['categories']) ?></a><a class="nav-link" href="/favorites?lang=<?= $e($lang) ?>"><span>♡</span><?= $e($t['favorites']) ?></a><a class="nav-link" href="/messages?lang=<?= $e($lang) ?>"><span>✉</span><?= $e($t['messages']) ?></a><a class="nav-link" href="/account?lang=<?= $e($lang) ?>"><span>◉</span><?= $e($t['account']) ?></a></nav>
    <section class="install-card"><span class="install-icon" aria-hidden="true">✦</span><h2><?= $e($t['install']) ?></h2><p><?= $e($t['install_copy']) ?></p><button class="button button-secondary install-button" type="button" hidden><?= $e($t['install_btn']) ?></button><p class="install-status" aria-live="polite"></p></section>
    <div class="sidebar-theme"><h2><?= $e($t['theme']) ?></h2><div class="theme-options" role="group" aria-label="<?= $e($t['theme']) ?>"><button type="button" class="theme-option" data-theme-choice="light" aria-label="<?= $e($t['light']) ?>"><span class="swatch swatch-light"></span></button><button type="button" class="theme-option" data-theme-choice="dark" aria-label="<?= $e($t['dark']) ?>"><span class="swatch swatch-dark"></span></button><button type="button" class="theme-option" data-theme-choice="green" aria-label="<?= $e($t['green']) ?>"><span class="swatch swatch-green"></span></button><button type="button" class="theme-option" data-theme-choice="rose" aria-label="<?= $e($t['rose']) ?>"><span class="swatch swatch-rose"></span></button></div></div>
  </aside>
  <main id="main" class="main-content">
    <section class="hero glass"><div class="hero-orb orb-one"></div><div class="hero-orb orb-two"></div><div class="hero-copy"><span class="eyebrow"><span class="eyebrow-dot"></span><?= $e($t['hero_kicker']) ?></span><h1><?= $e($t['hero_title']) ?></h1><p><?= $e($t['hero_copy']) ?></p><div class="hero-actions"><a class="button button-primary" href="/listings?lang=<?= $e($lang) ?>"><?= $e($t['browse']) ?> <span aria-hidden="true">↗</span></a><a class="button button-glass" href="/register?lang=<?= $e($lang) ?>"><?= $e($t['publish']) ?></a></div></div><div class="hero-art" aria-hidden="true"><div class="crystal crystal-back"></div><div class="crystal crystal-front"></div><div class="art-ring"></div><div class="art-spark spark-one">✦</div><div class="art-spark spark-two">✧</div></div><div class="hero-dots" aria-hidden="true"><i class="selected"></i><i></i><i></i></div></section>
    <section class="feature-strip" aria-label="Platform features"><div class="feature-chip glass"><span>✓</span><?= $e($t['safe']) ?></div><div class="feature-chip glass"><span>文</span><?= $e($t['languages']) ?></div><div class="feature-chip glass"><span>♧</span><?= $e($t['notifications']) ?></div><div class="feature-chip glass"><span>↥</span><?= $e($t['pwa']) ?></div><div class="feature-chip glass"><span>◐</span><?= $e($t['themes']) ?></div></section>
    <section class="section-block" id="categories"><div class="section-heading"><div><span class="section-kicker">DISCOVER</span><h2><?= $e($t['categories_title']) ?></h2></div><span class="section-note"><?= $e($t['no_categories']) ?></span></div><div class="category-grid"><a class="category-tile glass tint-blue" href="/listings?lang=<?= $e($lang) ?>"><span class="category-icon">◇</span><span><?= $e($t['listings']) ?></span><small>→</small></a><a class="category-tile glass tint-violet" href="/listings?lang=<?= $e($lang) ?>"><span class="category-icon">⌂</span><span><?= $e($t['categories']) ?></span><small>→</small></a><a class="category-tile glass tint-rose" href="/listings?lang=<?= $e($lang) ?>"><span class="category-icon">✧</span><span><?= $e($t['favorites']) ?></span><small>→</small></a><a class="category-tile glass tint-green" href="/register?lang=<?= $e($lang) ?>"><span class="category-icon">＋</span><span><?= $e($t['publish']) ?></span><small>→</small></a></div></section>
    <section class="section-block"><div class="section-heading"><div><span class="section-kicker">CURATED FOR YOU</span><h2><?= $e($t['featured']) ?></h2></div><a class="text-link" href="/listings?lang=<?= $e($lang) ?>"><?= $e($t['view_all']) ?> <span aria-hidden="true">→</span></a></div><div class="empty-state glass"><div class="empty-illustration" aria-hidden="true"><span class="empty-sun"></span><span class="empty-mountain mountain-a"></span><span class="empty-mountain mountain-b"></span><span class="empty-star">✦</span></div><h3><?= $e($t['empty_title']) ?></h3><p><?= $e($t['empty_copy']) ?></p><a class="button button-primary" href="/register?lang=<?= $e($lang) ?>"><?= $e($t['publish_first']) ?></a></div></section>
    <section class="why-panel glass"><div><span class="section-kicker">CRYSTAL EXPERIENCE</span><h2><?= $e($t['why']) ?></h2><p><?= $e($t['why_copy']) ?></p></div><div class="why-symbol" aria-hidden="true">✧</div></section>
  </main>
  <aside class="filters-column"><section class="filter-panel glass"><div class="panel-heading"><span class="filter-icon">☷</span><h2><?= $e($t['filters']) ?></h2></div><form action="/" method="get" class="filter-form"><input type="hidden" name="lang" value="<?= $e($lang) ?>"><label for="filter-q"><?= $e($t['keyword']) ?></label><input id="filter-q" name="q" value="<?= $e($searchTerm) ?>" placeholder="<?= $e($t['search']) ?>"><label for="filter-city"><?= $e($t['city']) ?></label><input id="filter-city" name="city" value="<?= $e($cityTerm) ?>" placeholder="<?= $e($t['city']) ?>"><div class="price-row"><div><label for="min-price"><?= $e($t['min_price']) ?></label><input id="min-price" type="number" min="0" step="1" name="min" inputmode="numeric"></div><div><label for="max-price"><?= $e($t['max_price']) ?></label><input id="max-price" type="number" min="0" step="1" name="max" inputmode="numeric"></div></div><button class="button button-primary button-full" type="submit"><?= $e($t['apply']) ?></button></form></section><section class="premium-panel glass"><div class="panel-heading"><span class="premium-icon">✦</span><h2><?= $e($t['premium']) ?></h2></div><p class="muted-copy"><?= $e($t['empty_copy']) ?></p></section></aside>
</div>
<footer class="site-footer"><a class="footer-brand" href="/?lang=<?= $e($lang) ?>">✧ CrystalMarket</a><p><?= $e($t['footer']) ?></p><nav aria-label="Footer"><a href="/legal?lang=<?= $e($lang) ?>"><?= $e($t['legal']) ?></a><a href="/privacy?lang=<?= $e($lang) ?>"><?= $e($t['privacy']) ?></a><a href="/terms?lang=<?= $e($lang) ?>"><?= $e($t['terms']) ?></a></nav><small>© <?= date('Y') ?> CrystalMarket</small></footer>
<nav class="mobile-tabs glass" aria-label="Mobile navigation"><a class="active" href="/?lang=<?= $e($lang) ?>"><span>⌂</span><small><?= $e($t['home']) ?></small></a><a href="/listings?lang=<?= $e($lang) ?>"><span>▦</span><small><?= $e($t['listings']) ?></small></a><a class="mobile-add" href="/register?lang=<?= $e($lang) ?>"><span>＋</span><small><?= $e($t['publish']) ?></small></a><a href="/messages?lang=<?= $e($lang) ?>"><span>✉</span><small><?= $e($t['messages']) ?></small></a><a href="/account?lang=<?= $e($lang) ?>"><span>◉</span><small><?= $e($t['account']) ?></small></a></nav>
</body></html>
