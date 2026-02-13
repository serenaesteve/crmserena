<?php
require __DIR__ . "/006-funciones.php";
require __DIR__ . "/i18n.php";

set_lang_from_request();
require_login();

$id = (int)($_GET["id"] ?? 0);
$lead = $id ? lead_get($id) : null;
if (!$lead) { http_response_code(404); die("Lead no encontrado"); }

$cfg = cfg();
$colors = estados_crm();
$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  csrf_check();

  if (($_POST["action"] ?? "") === "update_crm") {
    $estado = $_POST["estado"] ?? $lead["estado"];
    $proximo = ($_POST["proximo_seguimiento"] ?? "") ?: null;
    $notas = ($_POST["notas_internas"] ?? "") ?: null;

    if (!isset($colors[$estado])) $estado = "Nuevo";
    lead_update_crm($id, $estado, $proximo, $notas);

    header("Location: lead.php?id=".$id."&lang=".urlencode(lang()));
    exit;
  }

  if (($_POST["action"] ?? "") === "send_email") {
    $to = $lead["email"];
    $subject = trim($_POST["subject"] ?? "");
    $body = trim($_POST["body"] ?? "");

    if ($subject === "" || $body === "") {
      $mensaje = h(t("sent_error")) . ": campos vacíos.";
    } else {
      $err = "";
      $ok = crm_send_email_smtp($to, $subject, $body, $err);
      mail_log_add($id, $to, $subject, $body, $ok, $ok ? null : $err);
      $mensaje = $ok ? t("sent_ok") : (t("sent_error") . ": " . $err);
    }

    header("Location: lead.php?id=".$id."&lang=".urlencode(lang())."&m=1");
    exit;
  }
}

$lead = lead_get($id); // refrescar
$comm = mail_enabled() ? crm_fetch_emails_for_contact($lead["email"], 20) : ["sent"=>[],"received"=>[],"unread"=>false,"error"=>null];
$mailLog = mail_log_list($id, 20);
?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($cfg["app"]["name"]) ?> - <?= h(t("detail")) ?></title>
  <link rel="stylesheet" href="public/assets/style.css">
</head>
<body>

<div class="layout">
  <nav class="sidebar">
    <div class="brand">
      <?= h($cfg["app"]["name"]) ?><br>
      <small><?= h($cfg["app"]["owner"]) ?></small>
    </div>

    <a href="panel.php?lang=<?= h(lang()) ?>">&larr; <?= h(t("leads")) ?></a>
    <a href="public/index.php?lang=<?= h(lang()) ?>"><?= h(t("new_lead")) ?></a>
    <a href="logout.php"><?= h(t("logout")) ?></a>

    <div class="langswitch" style="margin-top:auto;">
      <a href="?id=<?= (int)$id ?>&lang=es">ES</a>
      <a href="?id=<?= (int)$id ?>&lang=en">EN</a>
    </div>
  </nav>

  <main class="main">
    <div class="tablecard">
      <h1><?= h(t("detail")) ?> #<?= (int)$lead["id"] ?></h1>

      <div class="notice" style="margin:10px 0;">
        <strong><?= h($lead["nombre"]) ?></strong> — <?= h($lead["email"]) ?> <?= $lead["telefono"] ? " · ".h($lead["telefono"]) : "" ?><br>
        <?= h($lead["servicio"]) ?> <?= $lead["presupuesto"] ? " · €".h($lead["presupuesto"]) : "" ?> <?= $lead["deadline"] ? " · ".h($lead["deadline"]) : "" ?><br>
        <small>Created: <?= h($lead["created_at"]) ?> · Origen: <?= h($lead["origen"]) ?></small>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
        <div class="card" style="box-shadow:none;border-top:0;padding:14px;border:1px solid var(--border);">
          <h3 style="margin:0 0 10px;color:var(--primary);"><?= h(t("status")) ?></h3>

          <form method="post">
            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
            <input type="hidden" name="action" value="update_crm">

            <label><?= h(t("status")) ?></label>
            <select name="estado">
              <?php foreach ($colors as $estado => $color): ?>
                <option value="<?= h($estado) ?>" <?= ($lead["estado"]===$estado ? "selected" : "") ?>>
                  <?= h($estado) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <div style="height:10px;"></div>

            <label><?= h(t("next_followup")) ?></label>
            <input type="date" name="proximo_seguimiento" value="<?= h($lead["proximo_seguimiento"] ?? "") ?>">

            <div style="height:10px;"></div>

            <label><?= h(t("notes")) ?></label>
            <textarea name="notas_internas"><?= h($lead["notas_internas"] ?? "") ?></textarea>

            <div class="actions">
              <button class="btn" type="submit"><?= h(t("save")) ?></button>
              <span class="pill" style="border-color:<?= h($colors[$lead["estado"]] ?? "#999") ?>; color:<?= h($colors[$lead["estado"]] ?? "#999") ?>;">
                <?= h($lead["estado"]) ?>
              </span>
              <?php if (!empty($comm["unread"])): ?>
                <span class="badge" title="UNSEEN desde este contacto">!</span>
              <?php endif; ?>
            </div>
          </form>
        </div>

        <div class="card" style="box-shadow:none;border-top:0;padding:14px;border:1px solid var(--border);">
          <h3 style="margin:0 0 10px;color:var(--primary);"><?= h(t("message")) ?></h3>
          <div class="notice"><?= nl2br(h($lead["mensaje"] ?? "")) ?></div>
          <div style="height:10px;"></div>
          <div class="notice">
            Consentimiento: <?= (int)$lead["consentimiento"] === 1 ? "✅" : "❌" ?>
          </div>
        </div>
      </div>

      <div style="height:14px;"></div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
        <div class="card" style="box-shadow:none;border-top:0;padding:14px;border:1px solid var(--border);">
          <h3 style="margin:0 0 10px;color:var(--primary);"><?= h(t("send_email")) ?></h3>

          <?php if (!mail_enabled()): ?>
            <div class="notice">Email deshabilitado en config.php</div>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
              <input type="hidden" name="action" value="send_email">

              <label><?= h(t("subject")) ?></label>
              <input type="text" name="subject">

              <div style="height:10px;"></div>

              <label><?= h(t("body")) ?></label>
              <textarea name="body"></textarea>

              <div class="actions">
                <button class="btn" type="submit"><?= h(t("send")) ?></button>
              </div>
            </form>

            <div style="height:10px;"></div>
            <h4 style="margin:0 0 8px;">Log interno (mail_log)</h4>
            <div class="notice" style="max-height:260px; overflow:auto;">
              <?php if (empty($mailLog)): ?>
                <div style="color:#777;">Sin envíos registrados.</div>
              <?php else: ?>
                <?php foreach ($mailLog as $ml): ?>
                  <div style="padding:8px 0;border-bottom:1px solid #eee;">
                    <strong><?= h($ml["subject"]) ?></strong>
                    <div style="font-size:.85em;color:#666;">
                      <?= h($ml["created_at"]) ?> · <?= $ml["sent_ok"] ? "✅ OK" : "❌ ERROR" ?>
                    </div>
                    <?php if (!$ml["sent_ok"] && $ml["error_msg"]): ?>
                      <div style="font-size:.85em;color:#b30000;"><?= h($ml["error_msg"]) ?></div>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="box-shadow:none;border-top:0;padding:14px;border:1px solid var(--border);">
          <h3 style="margin:0 0 10px;color:var(--primary);"><?= h(t("email_center")) ?></h3>

          <?php if (!mail_enabled()): ?>
            <div class="notice">Email deshabilitado en config.php</div>
          <?php else: ?>
            <?php if (!empty($comm["error"])): ?>
              <div class="notice" style="border-color:#ffb3b3;background:#ffe6e6;color:#b30000;">
                IMAP error: <?= h($comm["error"]) ?>
              </div>
            <?php else: ?>
              <div class="notice" style="max-height:320px; overflow:auto;">
                <strong>Sent (<?= count($comm["sent"]) ?>)</strong>
                <?php foreach ($comm["sent"] as $m): ?>
                  <div style="padding:8px 0;border-bottom:1px solid #eee;">
                    <div style="color:#333;"><strong><?= h($m["subject"]) ?></strong></div>
                    <div style="font-size:.85em;color:#666;"><?= h($m["date"]) ?></div>
                    <div style="font-size:.85em;color:#555;"><?= nl2br(h($m["snippet"])) ?></div>
                  </div>
                <?php endforeach; ?>

                <div style="height:10px;"></div>

                <strong>Received (<?= count($comm["received"]) ?>)</strong>
                <?php foreach ($comm["received"] as $m): ?>
                  <div style="padding:8px 0;border-bottom:1px solid #eee;">
                    <div style="color:#333;"><strong><?= h($m["subject"]) ?></strong></div>
                    <div style="font-size:.85em;color:#666;"><?= h($m["date"]) ?></div>
                    <div style="font-size:.85em;color:#555;"><?= nl2br(h($m["snippet"])) ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>

