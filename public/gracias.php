<?php
require __DIR__ . "/../006-funciones.php";
require __DIR__ . "/../i18n.php";
set_lang_from_request();
$cfg = cfg();
?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($cfg["app"]["name"]) ?> - <?= h(t("thanks_title")) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="topbar">
  <div><strong><?= h($cfg["app"]["name"]) ?></strong></div>
  <div class="langswitch">
    <a href="?lang=es">ES</a>
    <a href="?lang=en">EN</a>
  </div>
</div>

<div class="container">
  <div class="card">
    <h1><?= h(t("thanks_title")) ?></h1>
    <p><?= h(t("thanks_text")) ?></p>

    <div class="actions">
      <a class="btn" href="index.php?lang=<?= h(lang()) ?>"><?= h(t("new_lead")) ?></a>
      <a class="btn secondary" href="../admin.php?lang=<?= h(lang()) ?>"><?= h(t("admin_access")) ?></a>
    </div>
  </div>
</div>

</body>
</html>

