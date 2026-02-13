<?php
require __DIR__ . "/../006-funciones.php";
require __DIR__ . "/../i18n.php";

set_lang_from_request();
require_login();

$cfg = cfg();
$estados = array_keys(estados_crm());

$page = max(1, (int)($_GET["page"] ?? 1));
$perPage = 20;

$filters = [
  "q" => trim($_GET["q"] ?? ""),
  "estado" => $_GET["estado"] ?? "ALL",
];

$total = 0;
$rows = leads_list($filters, $page, $perPage, $total);
$pages = max(1, (int)ceil($total / $perPage));
?>
<!doctype html>
<html lang="<?= h(lang()) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= h($cfg["app"]["name"]) ?> - <?= h(t("leads")) ?></title>
 <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<div class="layout">
  <nav class="sidebar">
    <div class="brand">
      <?= h($cfg["app"]["name"]) ?><br>
      <small><?= h($cfg["app"]["owner"]) ?></small>
    </div>

    <a href="panel.php?lang=<?= h(lang()) ?>"><?= h(t("leads")) ?></a>
    <a href="public/index.php?lang=<?= h(lang()) ?>"><?= h(t("new_lead")) ?></a>
    <a href="logout.php"><?= h(t("logout")) ?></a>

    <div class="langswitch" style="margin-top:auto;">
      <a href="?lang=es">ES</a>
      <a href="?lang=en">EN</a>
    </div>
  </nav>

  <main class="main">
    <div class="tablecard">
      <h1><?= h(t("leads")) ?></h1>

      <form class="filters" method="get">
        <input type="hidden" name="lang" value="<?= h(lang()) ?>">
        <input type="text" name="q" value="<?= h($filters["q"]) ?>" placeholder="<?= h(t("search")) ?>...">
        <select name="estado">
          <option value="ALL"><?= h(t("all")) ?></option>
          <?php foreach ($estados as $e): ?>
            <option value="<?= h($e) ?>" <?= ($filters["estado"]===$e ? "selected" : "") ?>><?= h($e) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit"><?= h(t("search")) ?></button>
        <a class="btn secondary" href="panel.php?lang=<?= h(lang()) ?>"><?= h(t("reset")) ?></a>
      </form>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th><?= h(t("name")) ?></th>
            <th><?= h(t("email")) ?></th>
            <th><?= h(t("service")) ?></th>
            <th><?= h(t("status")) ?></th>
            <th><?= h(t("next_followup")) ?></th>
            <th><?= h(t("unread")) ?></th>
            <th><?= h(t("actions")) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <?php
              $colors = estados_crm();
              $color = $colors[$r["estado"]] ?? "#999";
              $unread = false;
              if (mail_enabled()) {
                $rep = crm_fetch_emails_for_contact($r["email"], 1);
                $unread = !empty($rep["unread"]);
              }
            ?>
            <tr>
              <td><?= (int)$r["id"] ?></td>
              <td><?= h($r["nombre"]) ?></td>
              <td><?= h($r["email"]) ?></td>
              <td><?= h($r["servicio"]) ?></td>
              <td>
                <span class="pill" style="border-color:<?= h($color) ?>; color:<?= h($color) ?>; background:<?= h($color) ?>20;">
                  <?= h($r["estado"]) ?>
                </span>
              </td>
              <td><?= h($r["proximo_seguimiento"] ?: "—") ?></td>
              <td>
                <?php if ($unread): ?>
                  <span class="badge" title="Emails UNSEEN desde este contacto">!</span>
                <?php else: ?>
                  <span style="color:#aaa;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn secondary" href="lead.php?id=<?= (int)$r["id"] ?>&lang=<?= h(lang()) ?>"><?= h(t("view")) ?></a>
              </td>
            </tr>
          <?php endforeach; ?>

          <?php if (empty($rows)): ?>
            <tr><td colspan="8" style="color:#777;">No hay resultados.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div class="notice" style="margin-top:12px; display:flex; justify-content:space-between; align-items:center;">
        <div>Total: <?= (int)$total ?></div>
        <div>
          <?php if ($page > 1): ?>
            <a href="?lang=<?= h(lang()) ?>&q=<?= urlencode($filters["q"]) ?>&estado=<?= urlencode($filters["estado"]) ?>&page=<?= $page-1 ?>">&larr; Prev</a>
          <?php endif; ?>
          <span style="margin:0 10px;">Page <?= $page ?> / <?= $pages ?></span>
          <?php if ($page < $pages): ?>
            <a href="?lang=<?= h(lang()) ?>&q=<?= urlencode($filters["q"]) ?>&estado=<?= urlencode($filters["estado"]) ?>&page=<?= $page+1 ?>">Next &rarr;</a>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>

