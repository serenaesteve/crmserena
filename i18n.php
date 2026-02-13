<?php
require_once __DIR__ . "/006-funciones.php";

function set_lang_from_request(): void {
  start_session();
  $cfg = cfg();
  $enabled = $cfg["app"]["enabled_langs"] ?? ["es","en"];
  $default = $cfg["app"]["default_lang"] ?? "es";

  if (isset($_GET["lang"]) && in_array($_GET["lang"], $enabled, true)) {
    $_SESSION["lang"] = $_GET["lang"];
  }
  if (empty($_SESSION["lang"])) $_SESSION["lang"] = $default;
}

function lang(): string {
  start_session();
  return $_SESSION["lang"] ?? "es";
}

function t(string $key): string {
  $L = lang();
  $dict = i18n_dict();
  return $dict[$L][$key] ?? $dict["es"][$key] ?? $key;
}

function i18n_dict(): array {
  return [
    "es" => [
      "public_title" => "Solicitar presupuesto",
      "public_subtitle" => "Cuéntame tu proyecto y te responderé lo antes posible.",
      "name" => "Nombre",
      "email" => "Email",
      "phone" => "Teléfono",
      "service" => "Servicio",
      "budget" => "Presupuesto",
      "deadline" => "Fecha límite",
      "source" => "¿Cómo me conociste?",
      "message" => "Mensaje",
      "privacy" => "Acepto la política de privacidad",
      "send" => "Enviar",
      "thanks_title" => "¡Gracias!",
      "thanks_text" => "He recibido tu mensaje. Te responderé lo antes posible.",
      "admin_access" => "Acceso al panel",
      "username" => "Usuario",
      "password" => "Contraseña",
      "login" => "Entrar",
      "logout" => "Cerrar sesión",
      "leads" => "Leads",
      "new_lead" => "Nuevo lead",
      "status" => "Estado",
      "next_followup" => "Próximo seguimiento",
      "actions" => "Acciones",
      "view" => "Ver",
      "save" => "Guardar",
      "notes" => "Notas internas",
      "detail" => "Detalle",
      "email_center" => "Correo",
      "send_email" => "Enviar email",
      "subject" => "Asunto",
      "body" => "Mensaje",
      "sent_ok" => "Mensaje enviado correctamente.",
      "sent_error" => "Error al enviar el mensaje",
      "unread" => "No leídos",
      "search" => "Buscar",
      "reset" => "Limpiar",
      "all" => "Todos",
    ],
    "en" => [
      "public_title" => "Request a quote",
      "public_subtitle" => "Tell me about your project and I’ll reply as soon as possible.",
      "name" => "Name",
      "email" => "Email",
      "phone" => "Phone",
      "service" => "Service",
      "budget" => "Budget",
      "deadline" => "Deadline",
      "source" => "How did you find me?",
      "message" => "Message",
      "privacy" => "I accept the privacy policy",
      "send" => "Send",
      "thanks_title" => "Thanks!",
      "thanks_text" => "I’ve received your message. I’ll get back to you soon.",
      "admin_access" => "Admin access",
      "username" => "Username",
      "password" => "Password",
      "login" => "Login",
      "logout" => "Logout",
      "leads" => "Leads",
      "new_lead" => "New lead",
      "status" => "Status",
      "next_followup" => "Next follow-up",
      "actions" => "Actions",
      "view" => "View",
      "save" => "Save",
      "notes" => "Internal notes",
      "detail" => "Detail",
      "email_center" => "Email",
      "send_email" => "Send email",
      "subject" => "Subject",
      "body" => "Message",
      "sent_ok" => "Message sent successfully.",
      "sent_error" => "Error sending message",
      "unread" => "Unread",
      "search" => "Search",
      "reset" => "Reset",
      "all" => "All",
    ],
  ];
}

