<?php
defined('ESY') or exit;
require __DIR__ . '/layout.php';
layout_start('404 | ' . settings()['site_name'], settings()['meta_desc']);
?>
<main class="wrap article-wrap narrow center">
  <div class="empty-ic">🧭</div>
  <h1><?= bi('पेज नहीं मिला', 'Page not found') ?></h1>
  <p><?= bi('जो पेज आप ढूंढ रहे हैं वह मौजूद नहीं है या हटा दिया गया है।', 'The page you are looking for does not exist or was removed.') ?></p>
  <a class="btn" href="/"><?= bi('होम पर जाएं', 'Go to home') ?></a>
</main>
<?php layout_end();
