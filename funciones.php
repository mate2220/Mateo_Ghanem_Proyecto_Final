<?php

require_once __DIR__ . '/conexion.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}


function escapar(?string $valor): string 
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function post(string $clave): string 
{
    $v = $_POST[$clave] ?? '';
    return is_string($v) ? trim($v) : '';
}


function csrf_token(): string 
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string 
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_validar(): bool 
{
    $enviado = $_POST['csrf'] ?? '';
    return is_string($enviado)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

// ---------- Control de acceso ----------
/** Exige sesión iniciada. Vuelve a consultar la BD: si el usuario fue archivado, se cierra su sesión. */
function requerir_login(): void
{
    global $conexion;
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: login.php?aviso=login');
        exit;
    }
    $stmt = $conexion->prepare('SELECT rol FROM usuarios WHERE id = :id AND archivado_en IS NULL LIMIT 1');
    $stmt->execute(['id' => $_SESSION['usuario_id']]);
    $fila = $stmt->fetch();
    if ($fila === false) {
        session_unset();
        session_destroy();
        header('Location: login.php?aviso=login');
        exit;
    }
    $_SESSION['usuario_rol'] = $fila['rol']; // el rol siempre sale de la BD
}

/** Exige sesión iniciada Y rol administrador. */
function requerir_admin(): void
{
    requerir_login();
    if (($_SESSION['usuario_rol'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Acceso denegado: esta sección es solo para administradores.');
    }
}


// ---------- Mensajes flash (se muestran una sola vez) ----------
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_mostrar(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return '<div class="alerta alerta-' . escapar($f['tipo']) . '">' . escapar($f['mensaje']) . '</div>';
}

// ---------- Validaciones ----------
/** Valida nombre, apellido, DNI y email. Devuelve un array de errores (vacío = todo bien). */
function validar_datos_usuario(array $d): array
{
    $errores = [];
    if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 80) {
        $errores[] = 'El nombre es obligatorio (máximo 80 caracteres).';
    }
    if ($d['apellido'] === '' || mb_strlen($d['apellido']) > 80) {
        $errores[] = 'El apellido es obligatorio (máximo 80 caracteres).';
    }
    if (!preg_match('/^[0-9]{7,8}$/', $d['dni'])) {
        $errores[] = 'El DNI debe tener 7 u 8 números, sin puntos.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($d['email']) > 254) {
        $errores[] = 'Ingresá un correo electrónico válido.';
    }
    return $errores;
}

function validar_password(string $password, string $confirmar): array
{
    $errores = [];
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $confirmar) {
        $errores[] = 'Las contraseñas no coinciden.';
    }
    return $errores;
}

/** Busca DNI o email ya usados (por cualquier usuario, activo o archivado). */
function buscar_duplicados(PDO $conexion, string $dni, string $email, ?int $excluirId = null): array
{
    $errores = [];
    $sql = 'SELECT dni, email FROM usuarios WHERE (dni = :dni OR email = :email)';
    $params = ['dni' => $dni, 'email' => $email];
    if ($excluirId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $excluirId;
    }
    $stmt = $conexion->prepare($sql);
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $fila) {
        if ($fila['dni'] === $dni) {
            $errores['dni'] = 'Ya existe un usuario con ese DNI.';
        }
        if (strcasecmp($fila['email'], $email) === 0) {
            $errores['email'] = 'Ya existe un usuario con ese correo electrónico.';
        }
    }
    return array_values($errores);
}

/** Valida que un ID recibido por POST/GET sea un entero positivo. */
function id_valido($valor): ?int
{
    $id = filter_var($valor, FILTER_VALIDATE_INT);
    return ($id !== false && $id >= 1) ? $id : null;
}


// =====================================================
// Control de gastos
// =====================================================

/** Formatea un número como dinero: $ 1.234,50 */
function dinero($n): string
{
    return '$ ' . number_format((float)$n, 2, ',', '.');
}

/** Si el usuario no tiene categorías, le crea un conjunto básico. */
function asegurar_categorias_base(PDO $conexion, int $uid): void
{
    $s = $conexion->prepare('SELECT COUNT(*) FROM categorias WHERE usuario_id = :u');
    $s->execute(['u' => $uid]);
    if ((int)$s->fetchColumn() > 0) {
        return;
    }
    $base = [
        ['Sueldo', 'ingreso'], ['Otros ingresos', 'ingreso'],
        ['Comida', 'gasto'], ['Transporte', 'gasto'], ['Vivienda', 'gasto'],
        ['Servicios', 'gasto'], ['Salud', 'gasto'], ['Ocio', 'gasto'], ['Otros gastos', 'gasto'],
    ];
    $i = $conexion->prepare('INSERT INTO categorias (usuario_id, nombre, tipo) VALUES (:u, :n, :t)');
    foreach ($base as [$nombre, $tipo]) {
        $i->execute(['u' => $uid, 'n' => $nombre, 't' => $tipo]);
    }
}

/** Valida los datos de un movimiento. Devuelve un array de errores. */
function validar_movimiento(PDO $conexion, int $uid, array $d): array
{
    $errores = [];

    $monto = str_replace(',', '.', $d['monto']);
    if (!preg_match('/^\d{1,10}(\.\d{1,2})?$/', $monto) || (float)$monto <= 0) {
        $errores[] = 'El monto debe ser un número mayor a 0, con hasta 2 decimales (ej: 1500,50).';
    }

    $f = DateTime::createFromFormat('Y-m-d', $d['fecha']);
    if (!$f || $f->format('Y-m-d') !== $d['fecha']) {
        $errores[] = 'La fecha no es válida.';
    }

    if (mb_strlen($d['descripcion']) > 255) {
        $errores[] = 'La descripción es demasiado larga (máximo 255 caracteres).';
    }

    // La categoría debe existir Y pertenecer al usuario logueado
    $cid = id_valido($d['categoria_id']);
    if ($cid === null) {
        $errores[] = 'Elegí una categoría.';
    } else {
        $s = $conexion->prepare('SELECT id FROM categorias WHERE id = :id AND usuario_id = :u');
        $s->execute(['id' => $cid, 'u' => $uid]);
        if ($s->fetch() === false) {
            $errores[] = 'La categoría elegida no es válida.';
        }
    }
    return $errores;
}