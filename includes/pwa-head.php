<?php
// <head> tags that make the site installable as an app (manifest, theme colour, iOS "Add to Home Screen"
// metadata) plus service-worker registration. Included from every layout's <head>, so any page can be the
// install point. Paths come from base_url() so it works at the domain root and in a local subfolder alike.
$pwaScope = rtrim(dirname((string) parse_url(base_url('sw.js'), PHP_URL_PATH)), '/') . '/';
?>
<link rel="manifest" href="<?= e(base_url('manifest.json')) ?>">
<meta name="theme-color" content="#0b00ff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Obin Academy">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" href="<?= e(base_url('assets/icons/apple-touch-icon.png')) ?>">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register(<?= json_encode(base_url('sw.js')) ?>, { scope: <?= json_encode($pwaScope) ?>, updateViaCache: 'none' }).catch(function () {});
  });
}
</script>