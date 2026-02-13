<?php

$pass = $_GET['pass'] ?? '';
if ($pass === '') die("Pasa la clave así: ?pass=TUCLAVE");

echo "<pre>";
echo password_hash($pass, PASSWORD_DEFAULT);
echo "</pre>";

