<?php
require __DIR__ . "/../006-funciones.php";
require __DIR__ . "/../i18n.php";

set_lang_from_request();


ensure_admin_bootstrap();

if (is_logged()) {
  header("Location: panel.php?lang=" . urlencode(lang()));
  exit;
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_check();
  $u = trim($_POST["usuario"] ?? "");
  $p = $_POST["password"] ?? "";
  if (login_attempt($u, $p, $error)) {
    header("Location: panel.php?lang=" . urlencode(lang()));
    exit;
  }
}

$cfg = cfg();
?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($cfg["app"]["name"]) ?> - <?= h(t("admin_access")) ?></title>
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
  <div class="card" style="max-width:480px;margin:auto;">
    <h1><?= h(t("admin_access")) ?></h1>

    <?php if ($error !== ""): ?>
      <div class="notice"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="form" style="grid-template-columns:1fr;">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div>
        <label><?= h(t("username")) ?></label>
        <input type="text" name="usuario" autocomplete="username" required>
      </div>

      <div>
        <label><?= h(t("password")) ?></label>
        <input type="password" name="password" autocomplete="current-password" required>
      </div>

      <div class="actions">
        <button class="btn" type="submit"><?= h(t("login")) ?></button>
        <a class="btn secondary" href="public/index.php?lang=<?= h(lang()) ?>"><?= h(t("public_title")) ?></a>
      </div>
    </form>

    <p style="margin-top:14px;color:#777;font-size:.9em;">
      Dominio: <?= h($cfg["app"]["domain"]) ?>
    </p>
  </div>
</div>

</body>
</html>

