<?php
/** admin_usuarios.php - Listado de usuarios ACTIVOS con buscador. Solo administradores. */
require_once __DIR__ . '/funciones.php';
requerir_admin();

$buscar = trim(is_string($_GET['buscar'] ?? null) ? $_GET['buscar'] : '');
$buscar = mb_substr($buscar, 0, 100);

$sql = 'SELECT id, nombre, apellido, dni, email, rol, creado_en
        FROM usuarios WHERE archivado_en IS NULL';
$params = [];

if ($buscar !== '') {
    $sql .= ' AND (nombre LIKE :nombre OR apellido LIKE :apellido OR dni LIKE :dni OR email LIKE :email)';
    $termino = '%' . $buscar . '%';
    $params = ['nombre' => $termino, 'apellido' => $termino, 'dni' => $termino, 'email' => $termino];
}
$sql .= ' ORDER BY apellido, nombre';

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$usuarios = $stmt->fetchAll();

$titulo = 'Administración de usuarios';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta">
    <div class="fila-titulo">
        <h1>Usuarios activos</h1>
        <a class="boton" href="crear_usuario.php">Nuevo usuario</a>
    </div>

    <form method="GET" action="admin_usuarios.php" class="buscador">
        <input type="search" name="buscar" maxlength="100" placeholder="Nombre, apellido, DNI o correo" value="<?= escapar($buscar) ?>">
        <button type="submit" class="boton">Buscar</button>
        <?php if ($buscar !== ''): ?><a class="boton secundario" href="admin_usuarios.php">Limpiar</a><?php endif; ?>
    </form>

    <p class="nota"><?= count($usuarios) ?> usuario(s) encontrado(s).</p>

    <?php if (!$usuarios): ?>
        <p>No se encontraron usuarios.</p>
    <?php else: ?>
    <div class="tabla-scroll">
    <table>
        <thead><tr><th>ID</th><th>Nombre</th><th>Apellido</th><th>DNI</th><th>Correo</th><th>Rol</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= escapar($u['nombre']) ?></td>
                <td><?= escapar($u['apellido']) ?></td>
                <td><?= escapar($u['dni']) ?></td>
                <td><?= escapar($u['email']) ?></td>
                <td><span class="etiqueta etiqueta-<?= escapar($u['rol']) ?>"><?= escapar($u['rol']) ?></span></td>
                <td class="acciones">
                    <a class="boton chico secundario" href="editar_usuario.php?id=<?= (int)$u['id'] ?>">Editar</a>
                    <?php if ((int)$u['id'] !== (int)$_SESSION['usuario_id']): ?>
                    <form method="POST" action="archivar_usuario.php"
                          onsubmit="return confirm('¿Archivar a este usuario? Podrás reactivarlo después.');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                        <button type="submit" class="boton chico peligro">Archivar</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
