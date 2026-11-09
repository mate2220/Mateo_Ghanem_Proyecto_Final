<?php

require_once __DIR__ . '/funciones.php';
header('Location: ' . (isset($_SESSION['usuario_id']) ? 'panel.php' : 'login.php'));
exit;