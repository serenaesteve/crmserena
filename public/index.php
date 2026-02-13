<?php
require __DIR__ . "/../006-funciones.php";
require __DIR__ . "/../i18n.php";

set_lang_from_request();

$cfg = cfg();
$appName = $cfg["app"]["name"];

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_check();

  $newId = null;
  $ok = lead_create($_POST, $mensaje, $newId);

  if ($ok && $newId) {
    $lead = lead_get($newId);
    if ($lead) {
      // emails automáticos (si mail enabled)
      send_auto_emails_new_lead($lead);
    }
    header("Location: gracias.php?lang=" . urlencode(lang()));
    exit;
  }
}
?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($appName) ?> - <?= h(t("public_title")) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="topbar">
  <div><strong><?= h($appName) ?></strong></div>
  <div class="langswitch">
    <a href="?lang=es">ES</a>
    <a href="?lang=en">EN</a>
  </div>
</div>

<div class="container">
  <div class="card">
    <h1><?= h(t("public_title")) ?></h1>
    <p><?= h(t("public_subtitle")) ?></p>

    <?php if ($mensaje !== ""): ?>
      <div class="notice"><?= h($mensaje) ?></div>
    <?php endif; ?>

    <form method="post" class="form">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

      <div>
        <label><?= h(t("name")) ?></label>
        <input type="text" name="nombre" required>
      </div>

      <div>
        <label><?= h(t("email")) ?></label>
        <input type="email" name="email" required>
      </div>

      <div>
        <label><?= h(t("phone")) ?></label>
        <input type="text" name="telefono" required>
      </div>

      <div>
        <label><?= h(t("service")) ?></label>
        <select name="servicio" required>
          <?php foreach (servicios() as $s): ?>
            <option value="<?= h($s) ?>"><?= h($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label><?= h(t("budget")) ?></label>
        <input type="number" name="presupuesto" step="0.01" min="0" placeholder="€" required>
      </div>

      <div>
        <label><?= h(t("deadline")) ?></label>
        <input type="date" name="deadline" required>
      </div>

      <div class="full">
        <label><?= h(t("source")) ?></label>
        <select name="origen" required>
          <?php foreach (origenes() as $o): ?>
            <option value="<?= h($o) ?>"><?= h($o) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="full">
        <label><?= h(t("message")) ?></label>
        <textarea name="mensaje" required></textarea>
      </div>

      <div class="full">
        <label>
          <input type="checkbox" name="consentimiento" value="1" required>
          <?= h(t("privacy")) ?>
        </label>
      </div>

      <div class="full actions">
        <button class="btn" type="submit"><?= h(t("send")) ?></button>
        <a class="btn secondary" href="../admin.php?lang=<?= h(lang()) ?>"><?= h(t("admin_access")) ?></a>
      </div>
    </form>

    <p style="margin-top:14px;color:#777;font-size:.9em;">
      <?= h($cfg["app"]["domain"]) ?>
    </p>
  </div>
</div>

</body>
</html>

