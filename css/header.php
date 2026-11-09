<?php
/** Cabecera común. Antes de incluirla, definir $titulo. */
$esAdmin  = (($_SESSION['usuario_rol'] ?? '') === 'admin');
$logueado = isset($_SESSION['usuario_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($titulo ?? 'Control de gastos') ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="barra">
    <a class="marca" href="index.php">Control de gastos</a>
    <nav>
        <?php if ($logueado): ?>
            <a href="panel.php">Resumen</a>
            <a href="movimientos.php">Movimientos</a>
            <a href="categorias.php">Categorías</a>
            <?php if ($esAdmin): ?>
                <a href="admin_usuarios.php">Usuarios</a>
                <a href="usuarios_archivados.php">Archivados</a>
            <?php endif; ?>
            <a href="cerrar_sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a href="login.php">Iniciar sesión</a>
            <a href="registro.php">Crear cuenta</a>
        <?php endif; ?>
    </nav>
</header>
<main class="contenedor">
<?= flash_mostrar() ?>
