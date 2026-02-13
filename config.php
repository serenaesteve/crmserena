<?php
return [
  "app" => [
    "name" => "CRM Serena Sania",
    "owner" => "Serena Sania Esteve",
    "domain" => "crmserenasania.com",
    "default_lang" => "es",           
    "enabled_langs" => ["es", "en"],  
  ],

  "db" => [
    "host" => "localhost",
    "user" => "crm_serena_user",
    "pass" => "Serena2026!CRM",  
    "name" => "crm_serena",
  ],

  "session_name" => "crm_serena_session",


  "admin_bootstrap" => [
    "enabled" => true,
    "username" => "serena",
    "password" => "1463"
  ],


  "mail" => [
    "enabled" => true,

    "from_name" => "CRM Serena Sania",
    "from_email" => "serenaestevee@gmail.com",

    "smtp_host" => "smtp.gmail.com",
    "smtp_port" => 587,
    "smtp_user" => "serenaestevee@gmail.com",
    "smtp_pass" => "mbgz lpoo gdvd gegs", 

    "imap_inbox" => "{imap.gmail.com:993/imap/ssl}INBOX",
    "imap_sent"  => "{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail",


    "auto_reply_client" => true,
    "auto_notify_owner" => true,
    "auto_emails_language" => "es" 
  ],
];

