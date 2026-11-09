<?php
/** usuarios_archivados.php - Lista de usuarios archivados, con botón para reactivar. */
require_once __DIR__ . '/funciones.php';
requerir_admin();

$usuarios = $conexion->query(
    'SELECT id, nombre, apellido, dni, email, rol, archivado_en
     FROM usuarios WHERE archivado_en IS NOT NULL ORDER BY archivado_en DESC'
)->fetchAll();

$titulo = 'Usuarios archivados';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta">
    <h1>Usuarios archivados</h1>
    <?php if (!$usuarios): ?>
        <p>No hay usuarios archivados.</p>
    <?php else: ?>
    <div class="tabla-scroll">
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>DNI</th><th>Correo</th><th>Archivado el</th><th>Acción</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= escapar($u['nombre']) ?></td>
                <td><?= escapar($u['apellido']) ?></td>
                <td><?= escapar($u['dni']) ?></td>
                <td><?= escapar($u['email']) ?></td>
                <td><?= escapar($u['archivado_en']) ?></td>
                <td>
                    <form method="POST" action="reactivar_usuario.php"
                          onsubmit="return confirm('¿Reactivar a este usuario?');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="boton chico">Reactivar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
