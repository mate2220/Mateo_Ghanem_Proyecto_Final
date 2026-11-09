<?php

require_once __DIR__ . '/funciones.php';
requerir_admin();

$errores = [];
$v = ['nombre' => '', 'apellido' => '', 'dni' => '', 'email' => '', 'rol' => 'usuario'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $errores[] = 'La solicitud no es válida. Recargá la página e intentá de nuevo.';
    } else {
        foreach ($v as $campo => $_) {
            $v[$campo] = post($campo);
        }
        $v['email'] = mb_strtolower($v['email']);
        $password  = is_string($_POST['password']  ?? null) ? $_POST['password']  : '';
        $confirmar = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

        $errores = array_merge(validar_datos_usuario($v), validar_password($password, $confirmar));
        if (!in_array($v['rol'], ['usuario', 'admin'], true)) {
            $errores[] = 'El rol elegido no es válido.';
        }
        if (!$errores) {
            $errores = buscar_duplicados($conexion, $v['dni'], $v['email']);
        }
        if (!$errores) {
            try {
                $stmt = $conexion->prepare(
                    'INSERT INTO usuarios (nombre, apellido, dni, email, password_hash, rol)
                    VALUES (:nombre, :apellido, :dni, :email, :hash, :rol)'
                );
                $stmt->execute([
                    'nombre' => $v['nombre'], 'apellido' => $v['apellido'], 'dni' => $v['dni'],
                    'email'  => $v['email'],  'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'rol'    => $v['rol'],
                ]);
                flash('ok', 'Usuario creado correctamente.');
                header('Location: admin_usuarios.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errores[] = 'El DNI o el correo ya están registrados.';
                } else {
                    error_log($e->getMessage());
                    $errores[] = 'Ocurrió un error al guardar los datos.';
                }
            }
        }
    }
}

$titulo = 'Nuevo usuario';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta angosta">
    <h1>Nuevo usuario</h1>
    <?php if ($errores): ?>
        <div class="alerta alerta-error"><ul>
            <?php foreach ($errores as $e): ?><li><?= escapar($e) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="POST" action="crear_usuario.php" novalidate>
        <?= csrf_campo() ?>
        <label for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" maxlength="80" required value="<?= escapar($v['nombre']) ?>">
        <label for="apellido">Apellido</label>
        <input type="text" id="apellido" name="apellido" maxlength="80" required value="<?= escapar($v['apellido']) ?>">
        <label for="dni">DNI (sin puntos)</label>
        <input type="text" id="dni" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" required value="<?= escapar($v['dni']) ?>">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" maxlength="254" required value="<?= escapar($v['email']) ?>">
        <label for="rol">Rol</label>
        <select id="rol" name="rol">
            <option value="usuario" <?= $v['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
            <option value="admin"   <?= $v['rol'] === 'admin'   ? 'selected' : '' ?>>Administrador</option>
        </select>
        <label for="password">Contraseña (mínimo 8 caracteres)</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">
        <label for="confirmar">Repetir contraseña</label>
        <input type="password" id="confirmar" name="confirmar" required autocomplete="new-password">
        <button type="submit" class="boton">Crear usuario</button>
        <a class="boton secundario" href="admin_usuarios.php">Cancelar</a>
    </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>