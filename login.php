<?php
require_once __DIR__ . '/funciones.php';

if (isset($_SESSION['usuario_id'])) {
    header('Location: panel.php');
    exit;
}

$error = '';
$email = '';

if (($_GET['aviso'] ?? '') === 'login' && empty($_SESSION['flash'])) {
    flash('aviso', 'Tenés que iniciar sesión para ver esa página.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $error = 'La solicitud no es válida. Recargá la página e intentá de nuevo.';
    } else {
        $email    = mb_strtolower(post('email'));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

        if ($email === '' || $password === '') {
            $error = 'Completá el correo y la contraseña.';
        } else {
            
            $stmt = $conexion->prepare(
                'SELECT id, nombre, apellido, email, password_hash, rol
                FROM usuarios WHERE email = :email AND archivado_en IS NULL LIMIT 1'
            );
            $stmt->execute(['email' => $email]);
            $u = $stmt->fetch();

            if ($u !== false && password_verify($password, $u['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['usuario_id']  = (int)$u['id'];
                $_SESSION['usuario_rol'] = $u['rol'];
                $_SESSION['csrf']        = bin2hex(random_bytes(32));
                header('Location: panel.php');
                exit;
            }
            
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

$titulo = 'Iniciar sesión';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta angosta">
    <h1>Iniciar sesión</h1>
    <?php if ($error !== ''): ?><div class="alerta alerta-error"><?= escapar($error) ?></div><?php endif; ?>

    <form method="POST" action="login.php">
        <?= csrf_campo() ?>
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required value="<?= escapar($email) ?>" autofocus>

        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">

        <button type="submit" class="boton">Entrar</button>
    </form>
    <p class="nota">¿No tenés cuenta? <a href="registro.php">Registrate</a></p>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>