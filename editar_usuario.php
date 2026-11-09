<?php
/**
 * editar_usuario.php - Edita nombre, apellido, DNI y correo de un usuario ACTIVO.
 * No permite cambiar rol ni contraseña (evita escalada de privilegios).
 */
require_once __DIR__ . '/funciones.php';
requerir_admin();

$errores = [];
$esPost  = ($_SERVER['REQUEST_METHOD'] === 'POST');

// El ID viene por GET al abrir el formulario y por POST (campo oculto) al guardar
$id = id_valido($esPost ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null));
if ($id === null) {
    http_response_code(400);
    exit('Identificador inválido.');
}

$stmt = $conexion->prepare(
    'SELECT id, nombre, apellido, dni, email FROM usuarios WHERE id = :id AND archivado_en IS NULL LIMIT 1'
);
$stmt->execute(['id' => $id]);
$u = $stmt->fetch();
if ($u === false) {
    flash('error', 'El usuario no existe o está archivado.');
    header('Location: admin_usuarios.php');
    exit;
}

$v = ['nombre' => $u['nombre'], 'apellido' => $u['apellido'], 'dni' => $u['dni'], 'email' => $u['email']];

if ($esPost) {
    if (!csrf_validar()) {
        $errores[] = 'La solicitud no es válida. Recargá la página e intentá de nuevo.';
    } else {
        foreach ($v as $campo => $_) {
            $v[$campo] = post($campo);
        }
        $v['email'] = mb_strtolower($v['email']);
        $errores = validar_datos_usuario($v);
        if (!$errores) {
            $errores = buscar_duplicados($conexion, $v['dni'], $v['email'], $id);
        }
        if (!$errores) {
            try {
                $upd = $conexion->prepare(
                    'UPDATE usuarios SET nombre = :nombre, apellido = :apellido, dni = :dni, email = :email
                     WHERE id = :id AND archivado_en IS NULL'
                );
                $upd->execute([
                    'nombre' => $v['nombre'], 'apellido' => $v['apellido'],
                    'dni' => $v['dni'], 'email' => $v['email'], 'id' => $id,
                ]);
                flash('ok', 'Datos actualizados correctamente.');
                header('Location: admin_usuarios.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errores[] = 'El DNI o el correo ya pertenecen a otro usuario.';
                } else {
                    error_log($e->getMessage());
                    $errores[] = 'Ocurrió un error al guardar los cambios.';
                }
            }
        }
    }
}

$titulo = 'Editar usuario';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta angosta">
    <h1>Editar usuario #<?= (int)$id ?></h1>
    <?php if ($errores): ?>
        <div class="alerta alerta-error"><ul>
            <?php foreach ($errores as $e): ?><li><?= escapar($e) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="POST" action="editar_usuario.php" novalidate>
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="80" required value="<?= escapar($v['nombre']) ?>">
        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" maxlength="80" required value="<?= escapar($v['apellido']) ?>">
        <label for="dni">DNI</label>
        <input type="text" id="dni" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" required value="<?= escapar($v['dni']) ?>">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" maxlength="254" required value="<?= escapar($v['email']) ?>">
        <button type="submit" class="boton">Guardar cambios</button>
        <a class="boton secundario" href="admin_usuarios.php">Cancelar</a>
    </form>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
