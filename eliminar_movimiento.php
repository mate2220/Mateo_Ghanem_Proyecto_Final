<?php
/** eliminar_movimiento.php - DELETE de un movimiento propio. Solo POST + CSRF. */
require_once __DIR__ . '/funciones.php';
requerir_login();

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

// El "AND usuario_id" impide borrar movimientos de otra persona cambiando el id
$s = $conexion->prepare('DELETE FROM movimientos WHERE id = :id AND usuario_id = :u');
$s->execute(['id' => $id, 'u' => (int)$_SESSION['usuario_id']]);

flash($s->rowCount() === 1 ? 'ok' : 'error', $s->rowCount() === 1 ? 'Movimiento eliminado.' : 'El movimiento no existe.');
header('Location: movimientos.php');
exit;
