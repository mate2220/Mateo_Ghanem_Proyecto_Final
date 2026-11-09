<?php
/** reactivar_usuario.php - Vuelve a activar un usuario archivado. Solo POST + CSRF + admin. */
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

$upd = $conexion->prepare('UPDATE usuarios SET archivado_en = NULL WHERE id = :id AND archivado_en IS NOT NULL');
$upd->execute(['id' => $id]);

if ($upd->rowCount() === 1) {
    flash('ok', 'Usuario reactivado correctamente.');
} else {
    flash('error', 'El usuario no existe o no estaba archivado.');
}
header('Location: usuarios_archivados.php');
exit;
