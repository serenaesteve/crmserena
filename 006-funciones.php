<?php

function cfg(): array {
  static $cfg = null;
  if ($cfg === null) $cfg = require __DIR__ . "/config.php";
  return $cfg;
}

function db(): mysqli {
  static $c = null;
  if ($c) return $c;

  $db = cfg()["db"];
  $c = new mysqli($db["host"], $db["user"], $db["pass"], $db["name"]);
  if ($c->connect_error) die("Error BD: " . $c->connect_error);
  $c->set_charset("utf8mb4");
  return $c;
}

function start_session(): void {
  $name = cfg()["session_name"] ?? "crm_session";
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($name);
    session_start();
  }
}

function h($v): string {
  return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/* ---------------- CSRF ---------------- */
function csrf_token(): string {
  start_session();
  if (empty($_SESSION["csrf"])) $_SESSION["csrf"] = bin2hex(random_bytes(32));
  return $_SESSION["csrf"];
}
function csrf_check(): void {
  start_session();
  $ok = isset($_POST["csrf"]) && hash_equals($_SESSION["csrf"] ?? "", $_POST["csrf"]);
  if (!$ok) { http_response_code(403); die("CSRF inválido"); }
}

/* ---------------- Auth ---------------- */
function is_logged(): bool {
  start_session();
  return !empty($_SESSION["admin_logged"]);
}
function require_login(): void {
  if (!is_logged()) { header("Location: admin.php"); exit; }
}
function login_attempt(string $username, string $password, string &$error): bool {
  $error = "";
  $c = db();
  $stmt = $c->prepare("SELECT password_hash FROM admin_users WHERE username=? LIMIT 1");
  if (!$stmt) { $error = "Error interno."; return false; }
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row || !password_verify($password, $row["password_hash"])) {
    $error = "Usuario o contraseña incorrectos.";
    return false;
  }

  start_session();
  session_regenerate_id(true);
  $_SESSION["admin_logged"] = true;
  $_SESSION["admin_user"] = $username;
  return true;
}
function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}

/* --------- Bootstrap admin (crea serena/1463 si no existe) --------- */
function ensure_admin_bootstrap(): void {
  $cfg = cfg();
  $b = $cfg["admin_bootstrap"] ?? ["enabled"=>false];

  if (empty($b["enabled"])) return;

  $user = $b["username"] ?? "";
  $pass = $b["password"] ?? "";
  if ($user === "" || $pass === "") return;

  $c = db();

  // ¿Existe?
  $stmt = $c->prepare("SELECT id FROM admin_users WHERE username=? LIMIT 1");
  if (!$stmt) return;
  $stmt->bind_param("s", $user);
  $stmt->execute();
  $exists = (bool)$stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($exists) return;

  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $stmt = $c->prepare("INSERT INTO admin_users(username,password_hash) VALUES (?,?)");
  if (!$stmt) return;
  $stmt->bind_param("ss", $user, $hash);
  $stmt->execute();
  $stmt->close();
}

/* ---------------- Dominio CRM ---------------- */
function estados_crm(): array {
  return [
    'Nuevo'               => '#3498db',
    'Contactado'          => '#9b59b6',
    'Reunión agendada'    => '#f1c40f',
    'Presupuesto enviado' => '#e67e22',
    'Ganado'              => '#2ecc71',
    'Perdido'             => '#e74c3c',
  ];
}
function servicios(): array {
  return ['Desarrollo web'];
}
function origenes(): array {
  return ['Web','Instagram','LinkedIn','Referido','Email','Otro'];
}

/* ---------------- Leads ---------------- */
function lead_create(array $data, string &$msg, ?int &$newId = null): bool {
  $msg = "";
  $newId = null;

  $c = db();
  $nombre = trim($data["nombre"] ?? "");
  $email  = trim($data["email"] ?? "");
  $telefono = trim($data["telefono"] ?? "");

  $servicio = $data["servicio"] ?? "Desarrollo web";
  $presupuesto = ($data["presupuesto"] ?? "") !== "" ? (float)$data["presupuesto"] : null;
  $deadline = ($data["deadline"] ?? "") !== "" ? $data["deadline"] : null;

  $origen = $data["origen"] ?? "Web";
  $mensaje = trim($data["mensaje"] ?? "");
  $consent = isset($data["consentimiento"]) ? 1 : 0;

  // Todo sí + RGPD obligatorio
  if ($nombre === "" || $email === "" || $telefono === "" || $mensaje === "") {
    $msg = "Faltan campos obligatorios.";
    return false;
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = "Email no válido.";
    return false;
  }
  if ($consent !== 1) {
    $msg = "Debes aceptar la política de privacidad.";
    return false;
  }

  $stmt = $c->prepare("
    INSERT INTO leads (nombre,email,telefono,servicio,presupuesto,deadline,origen,mensaje,consentimiento)
    VALUES (?,?,?,?,?,?,?,?,?)
  ");
  if (!$stmt) { $msg="Error interno."; return false; }

  $stmt->bind_param("ssssdsssi",
    $nombre, $email, $telefono, $servicio, $presupuesto, $deadline, $origen, $mensaje, $consent
  );

  $ok = $stmt->execute();
  $err = $stmt->error;
  $stmt->close();

  if (!$ok) { $msg = "No se pudo guardar: ".$err; return false; }

  $newId = (int)$c->insert_id;
  return true;
}

function lead_get(int $id): ?array {
  $c = db();
  $stmt = $c->prepare("SELECT * FROM leads WHERE id=? LIMIT 1");
  if (!$stmt) return null;
  $stmt->bind_param("i", $id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return $row ?: null;
}

function lead_update_crm(int $id, string $estado, ?string $proximo, ?string $notas): bool {
  $c = db();
  $stmt = $c->prepare("UPDATE leads SET estado=?, proximo_seguimiento=?, notas_internas=? WHERE id=?");
  if (!$stmt) return false;
  $stmt->bind_param("sssi", $estado, $proximo, $notas, $id);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

function leads_list(array $filters, int $page, int $perPage, int &$total): array {
  $c = db();
  $where = [];
  $params = [];
  $types = "";

  if (!empty($filters["q"])) {
    $where[] = "(nombre LIKE ? OR email LIKE ? OR telefono LIKE ?)";
    $q = "%".$filters["q"]."%";
    $params[]=$q; $params[]=$q; $params[]=$q;
    $types .= "sss";
  }

  if (!empty($filters["estado"]) && $filters["estado"] !== "ALL") {
    $where[] = "estado=?";
    $params[] = $filters["estado"];
    $types .= "s";
  }

  $sqlWhere = $where ? ("WHERE ".implode(" AND ", $where)) : "";

  // total
  $stmtT = $c->prepare("SELECT COUNT(*) n FROM leads $sqlWhere");
  if ($stmtT) {
    if ($types !== "") $stmtT->bind_param($types, ...$params);
    $stmtT->execute();
    $total = (int)($stmtT->get_result()->fetch_assoc()["n"] ?? 0);
    $stmtT->close();
  } else $total = 0;

  $offset = max(0, ($page-1)*$perPage);
  $sql = "SELECT * FROM leads $sqlWhere ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
  $stmt = $c->prepare($sql);
  if (!$stmt) return [];
  if ($types !== "") $stmt->bind_param($types, ...$params);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows=[];
  while($r=$res->fetch_assoc()) $rows[]=$r;
  $stmt->close();
  return $rows;
}

/* ---------------- Email ---------------- */
function mail_enabled(): bool {
  return !empty(cfg()["mail"]["enabled"]);
}

function crm_append_to_sent(string $rawMessage): void {
  if (!mail_enabled()) return;
  if (!function_exists("imap_open")) return;

  $m = cfg()["mail"];
  $mailbox = $m["imap_sent"];
  $user = $m["smtp_user"];
  $pass = $m["smtp_pass"];

  $imap = @imap_open($mailbox, $user, $pass);
  if (!$imap) return;

  $date = date('d-M-Y H:i:s O');
  @imap_append($imap, $mailbox, $rawMessage, "\\Seen", $date);
  imap_close($imap);
}

function crm_send_email_smtp(string $to, string $subject, string $body, string &$errorMsg): bool {
  $errorMsg = "";
  if (!mail_enabled()) { $errorMsg="Email deshabilitado."; return false; }

  $m = cfg()["mail"];
  $smtpHost = $m["smtp_host"];
  $smtpPort = (int)$m["smtp_port"];
  $username = $m["smtp_user"];
  $password = $m["smtp_pass"];
  $from     = $m["from_email"];
  $fromName = $m["from_name"];

  $socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 30);
  if (!$socket) { $errorMsg="No se pudo conectar SMTP: $errstr ($errno)"; return false; }

  $resp = fgets($socket, 515);
  if (substr($resp,0,3)!='220') { $errorMsg="Banner SMTP inesperado: $resp"; fclose($socket); return false; }

  $readMulti = function($expected) use ($socket, &$errorMsg){
    while(($line=fgets($socket,515))!==false){
      if(substr($line,0,3)!=$expected){ $errorMsg="SMTP inesperado: $line"; return false; }
      if(strlen($line)<4 || $line[3] != '-') break;
    }
    return true;
  };
  $send = function($cmd,$expected) use($socket,&$errorMsg){
    fputs($socket,$cmd);
    $r=fgets($socket,515);
    if(substr($r,0,3)!=$expected){ $errorMsg="SMTP '$cmd' => $r"; return false; }
    return true;
  };

  fputs($socket,"EHLO localhost\r\n"); if(!$readMulti("250")){ fclose($socket); return false; }

  fputs($socket,"STARTTLS\r\n");
  $r=fgets($socket,515);
  if(substr($r,0,3)!="220"){ $errorMsg="STARTTLS falló: $r"; fclose($socket); return false; }

  $cryptoOk = @stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT);
  if(!$cryptoOk){ $errorMsg="No se pudo activar TLS."; fclose($socket); return false; }

  fputs($socket,"EHLO localhost\r\n"); if(!$readMulti("250")){ fclose($socket); return false; }

  if(!$send("AUTH LOGIN\r\n","334")){ fclose($socket); return false; }
  if(!$send(base64_encode($username)."\r\n","334")){ fclose($socket); return false; }
  if(!$send(base64_encode($password)."\r\n","235")){ fclose($socket); return false; }

  if(!$send("MAIL FROM:<$from>\r\n","250")){ fclose($socket); return false; }
  if(!$send("RCPT TO:<$to>\r\n","250")){ fclose($socket); return false; }
  if(!$send("DATA\r\n","354")){ fclose($socket); return false; }

  $headers  = "From: ".$fromName." <".$from.">\r\n";
  $headers .= "To: <".$to.">\r\n";
  $headers .= "Subject: ".$subject."\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $headers .= "Content-Transfer-Encoding: 8bit\r\n\r\n";

  $raw = $headers.$body;
  fputs($socket, $raw . "\r\n.\r\n");

  $r=fgets($socket,515);
  if(substr($r,0,3)!="250"){ $errorMsg="Error al enviar: $r"; fclose($socket); return false; }

  fputs($socket,"QUIT\r\n");
  fclose($socket);

  crm_append_to_sent($raw);
  return true;
}

function crm_fetch_emails_for_contact(string $contactEmail, int $limit = 10): array {
  $out = ["sent"=>[], "received"=>[], "unread"=>false, "error"=>null];
  if (!mail_enabled()) return $out;
  if (!function_exists("imap_open")) { $out["error"]="Extensión IMAP no habilitada."; return $out; }

  $m = cfg()["mail"];
  $user = $m["smtp_user"];
  $pass = $m["smtp_pass"];
  $inboxBox = $m["imap_inbox"];
  $sentBox  = $m["imap_sent"];

  $safeEmail = str_replace('"','\"',$contactEmail);
  $our = strtolower($m["from_email"]);

  // unread en INBOX
  $imap = @imap_open($inboxBox, $user, $pass);
  if ($imap) {
    $unseen = imap_search($imap, 'UNSEEN FROM "'.$safeEmail.'"');
    if ($unseen !== false && count($unseen)>0) $out["unread"]=true;
    imap_close($imap);
  }

  $scan = function(string $box) use (&$out,$user,$pass,$safeEmail,$our){
    $imap = @imap_open($box, $user, $pass);
    if(!$imap){ if($out["error"]===null) $out["error"]=imap_last_error(); return; }

    $ids=[];
    $fromIds = imap_search($imap,'FROM "'.$safeEmail.'"'); if($fromIds!==false) $ids=array_merge($ids,$fromIds);
    $toIds   = imap_search($imap,'TO "'.$safeEmail.'"');   if($toIds!==false)   $ids=array_merge($ids,$toIds);
    if(empty($ids)){ imap_close($imap); return; }

    $ids=array_values(array_unique($ids)); rsort($ids);

    foreach($ids as $num){
      $ov = imap_fetch_overview($imap,$num,0); if(!$ov) continue; $ov=$ov[0];
      $header = imap_headerinfo($imap,$num);
      $from = $header->fromaddress ?? ($ov->from ?? "");
      $to   = $header->toaddress ?? ($ov->to ?? "");
      $subject = isset($ov->subject) ? imap_utf8($ov->subject) : "";
      $date = $ov->date ?? "";

      $body = imap_fetchbody($imap,$num,1) ?: "";
      $body = imap_qprint($body);
      $snippet = trim($body);
      $snippet = function_exists("mb_substr") ? mb_substr($snippet,0,200) : substr($snippet,0,200);

      $entry=["subject"=>$subject,"date"=>$date,"from"=>$from,"to"=>$to,"snippet"=>$snippet];
      if (strpos(strtolower($from), $our) !== false) $out["sent"][]=$entry;
      else $out["received"][]=$entry;
    }

    imap_close($imap);
  };

  $scan($inboxBox);
  $scan($sentBox);

  $sort = function(&$arr){
    usort($arr, fn($a,$b)=> (strtotime($b["date"]??"") <=> strtotime($a["date"]??"")));
  };
  $sort($out["sent"]); $sort($out["received"]);
  $out["sent"]=array_slice($out["sent"],0,$limit);
  $out["received"]=array_slice($out["received"],0,$limit);

  return $out;
}

/* ---------------- Mail log ---------------- */
function mail_log_add(int $leadId, string $to, string $subject, string $body, bool $ok, ?string $err): void {
  $c = db();
  $stmt = $c->prepare("INSERT INTO mail_log(lead_id,to_email,subject,body,sent_ok,error_msg) VALUES (?,?,?,?,?,?)");
  if(!$stmt) return;
  $sent_ok = $ok ? 1 : 0;
  $stmt->bind_param("isssis",$leadId,$to,$subject,$body,$sent_ok,$err);
  $stmt->execute();
  $stmt->close();
}

/* ---------------- Auto emails on new lead (ES) ---------------- */
function send_auto_emails_new_lead(array $leadRow): void {
  if (!mail_enabled()) return;

  $m = cfg()["mail"];
  $domain = cfg()["app"]["domain"] ?? "";

  $client = $leadRow["email"];
  $name = $leadRow["nombre"];
  $servicio = $leadRow["servicio"];

  // 1) Auto-reply al cliente (ES)
  if (!empty($m["auto_reply_client"])) {
    $subject = "Hemos recibido tu solicitud - CRM Serena Sania";
    $body =
"Hola $name,

¡Gracias por contactar conmigo! He recibido tu solicitud sobre: $servicio.

En breve te responderé para concretar detalles.

Un saludo,
Serena Sania Esteve
$domain
";
    $err="";
    $ok = crm_send_email_smtp($client,$subject,$body,$err);
    mail_log_add((int)$leadRow["id"], $client, $subject, $body, $ok, $ok ? null : $err);
  }

  // 2) Aviso a ti (owner) (ES)
  if (!empty($m["auto_notify_owner"])) {
    $owner = $m["from_email"];
    $subject = "Nuevo lead recibido: ".$leadRow["nombre"];
    $body =
"Nuevo lead en $domain

Nombre: ".$leadRow["nombre"]."
Email: ".$leadRow["email"]."
Teléfono: ".$leadRow["telefono"]."
Servicio: ".$leadRow["servicio"]."
Presupuesto: ".$leadRow["presupuesto"]."
Fecha límite: ".$leadRow["deadline"]."
Origen: ".$leadRow["origen"]."

Mensaje:
".$leadRow["mensaje"]."
";
    $err="";
    $ok = crm_send_email_smtp($owner,$subject,$body,$err);
    mail_log_add((int)$leadRow["id"], $owner, $subject, $body, $ok, $ok ? null : $err);
  }
}

