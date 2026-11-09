<?php
/** archivar_usuario.php - Borrado LÓGICO: marca archivado_en. Solo POST + CSRF + admin. */
require_once __DIR__ . '/funciones.php';
requerir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
if (!csrf_validar()) {
    http_response_code(400);
    exit('Solicitud no válida (token CSRF incorrecto).');
}

$id = id_valido($_POST['id'] ?? null);
if ($id === null) {
    http_response_code(400);
    exit('Identificador inválido.');
}
if ($id === (int)$_SESSION['usuario_id']) {
    flash('error', 'No podés archivar tu propia cuenta.');
    header('Location: admin_usuarios.php');
    exit;
}

// Proteger al último administrador activo
$stmt = $conexion->prepare('SELECT rol FROM usuarios WHERE id = :id AND archivado_en IS NULL');
$stmt->execute(['id' => $id]);
$objetivo = $stmt->fetch();
if ($objetivo !== false && $objetivo['rol'] === 'admin') {
    $cant = (int)$conexion->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND archivado_en IS NULL")->fetchColumn();
    if ($cant <= 1) {
        flash('error', 'No se puede archivar al único administrador activo.');
        header('Location: admin_usuarios.php');
        exit;
    }
}

$upd = $conexion->prepare('UPDATE usuarios SET archivado_en = CURRENT_TIMESTAMP WHERE id = :id AND archivado_en IS NULL');
$upd->execute(['id' => $id]);

if ($upd->rowCount() === 1) {
    flash('ok', 'Usuario archivado. Podés reactivarlo desde la sección de archivados.');
} else {
    flash('error', 'El usuario no existe o ya estaba archivado.');
}
header('Location: admin_usuarios.php');
exit;
