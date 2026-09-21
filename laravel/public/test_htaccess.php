<?php
/**
 * OVANIE — Test complet mod_rewrite & .htaccess LiteSpeed
 * 
 * Accès : https://test.ovanie.com/test_htaccess.php?token=OVANIE_HT_2026
 */

if (($_GET['token'] ?? '') !== 'OVANIE_HT_2026' && !isset($_GET['rewritten'])) {
    http_response_code(403);
    die('<h1>Accès refusé</h1><p>Ajoutez <code>?token=OVANIE_HT_2026</code> à l\'URL.</p>');
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>OVANIE — Diagnostic .htaccess / LiteSpeed</title>
<style>
  body { font-family: monospace; background:#0f1117; color:#e2e8f0; padding:20px; font-size:13px; }
  h1 { color:#f59e0b; font-size:18px; margin-bottom:12px; }
  .section { background:#1a1f2e; border-radius:6px; padding:14px; margin:12px 0; border:1px solid #1f2937; }
  h2 { color:#60a5fa; font-size:14px; margin-bottom:10px; }
  .row { display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #1f2937; }
  .row:last-child { border-bottom:none; }
  .key { color:#94a3b8; }
  .val { color:#e2e8f0; font-weight:bold; }
  .ok { color:#22c55e; }
  .err { color:#ef4444; }
  .info { color:#60a5fa; }
  pre { background:#0d1117; padding:10px; border-radius:4px; font-size:11px; color:#d1fae5; overflow-x:auto; }
</style>
</head>
<body>
<h1>🔍 Diagnostic Réécriture d'URL LiteSpeed / cPanel</h1>

<div class="section">
<h2>1. Informations Serveur &amp; Requête</h2>
<div class="row"><span class="key">SERVER_SOFTWARE</span><span class="val"><?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'N/A') ?></span></div>
<div class="row"><span class="key">REQUEST_URI</span><span class="val info"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') ?></span></div>
<div class="row"><span class="key">SCRIPT_NAME</span><span class="val"><?= htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? 'N/A') ?></span></div>
<div class="row"><span class="key">PHP_SELF</span><span class="val"><?= htmlspecialchars($_SERVER['PHP_SELF'] ?? 'N/A') ?></span></div>
<div class="row"><span class="key">PATH_INFO</span><span class="val"><?= htmlspecialchars($_SERVER['PATH_INFO'] ?? '(vide)') ?></span></div>
<div class="row"><span class="key">DOCUMENT_ROOT</span><span class="val"><?= htmlspecialchars($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') ?></span></div>
<div class="row"><span class="key">HTTP_AUTHORIZATION</span><span class="val"><?= isset($_SERVER['HTTP_AUTHORIZATION']) ? '✅ Transmis' : '❌ Non détecté' ?></span></div>
</div>

<div class="section">
<h2>2. Fichier .htaccess localisé</h2>
<?php
$htPath = __DIR__ . '/.htaccess';
if (file_exists($htPath)) {
    echo "<div class='row'><span class='key'>public/.htaccess</span><span class='val ok'>✅ Présent (" . filesize($htPath) . " octets)</span></div>";
    echo "<pre>" . htmlspecialchars(file_get_contents($htPath)) . "</pre>";
} else {
    echo "<div class='row'><span class='key'>public/.htaccess</span><span class='val err'>❌ INTROUVABLE DANS " . htmlspecialchars(__DIR__) . "</span></div>";
}
?>
</div>

<div class="section">
<h2>3. Test de réécriture mod_rewrite</h2>
<?php
if (isset($_GET['rewritten'])) {
    echo "<div class='row'><span class='key'>Statut mod_rewrite</span><span class='val ok'>🎉 SUCCÈS ! La réécriture .htaccess fonctionne !</span></div>";
} else {
    echo "<p style='margin-bottom:10px'>Testez si LiteSpeed applique les règles de réécriture en cliquant ci-dessous :</p>";
    echo "<p><a href='https://test.ovanie.com/check-rewrite-test-status' target='_blank' style='color:#60a5fa;font-weight:bold;text-decoration:underline'>👉 Cliquer ici pour tester la réécriture (ouvre un nouvel onglet)</a></p>";
}
?>
</div>

<div class="section">
<h2>4. Solution d'urgence alternative si mod_rewrite est désactivé par cPanel</h2>
<p style='line-height:1.6;color:#94a3b8'>
Si mod_rewrite ne fonctionne pas sur votre sous-domaine cPanel, vous pouvez configurer l'option <code>index.php</code> transparente dans Laravel (<code>config/app.php</code>) ou créer un fichier <code>.htaccess</code> à la racine du projet <code>/home/rtxqkd67/test.ovanie.com/.htaccess</code>.
</p>
</div>

</body>
</html>
