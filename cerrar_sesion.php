<?php
/** cerrar_sesion.php - Destruye la sesión y vuelve al login. */
require_once __DIR__ . '/funciones.php';

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

session_start();                       // sesión nueva solo para mostrar el mensaje
flash('ok', 'Cerraste sesión correctamente.');
header('Location: login.php');
exit;
