<?php
/** categorias.php - Alta, listado y baja de las categorías propias del usuario. */
require_once __DIR__ . '/funciones.php';
requerir_login();
$uid = (int)$_SESSION['usuario_id'];
asegurar_categorias_base($conexion, $uid);

$errores = [];
$nombre = '';
$tipo = 'gasto';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validar()) {
        $errores[] = 'La solicitud no es válida. Recargá la página e intentá de nuevo.';
    } elseif (post('accion') === 'crear') {
        $nombre = post('nombre');
        $tipo   = post('tipo');
        if ($nombre === '' || mb_strlen($nombre) > 60) {
            $errores[] = 'El nombre es obligatorio (máximo 60 caracteres).';
        }
        if (!in_array($tipo, ['ingreso', 'gasto'], true)) {
            $errores[] = 'El tipo elegido no es válido.';
        }
        if (!$errores) {
            try {
                $s = $conexion->prepare('INSERT INTO categorias (usuario_id, nombre, tipo) VALUES (:u, :n, :t)');
                $s->execute(['u' => $uid, 'n' => $nombre, 't' => $tipo]);
                flash('ok', 'Categoría creada.');
                header('Location: categorias.php');
                exit;
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errores[] = 'Ya tenés una categoría con ese nombre y tipo.';
                } else {
                    error_log($e->getMessage());
                    $errores[] = 'No se pudo crear la categoría.';
                }
            }
        }
    } elseif (post('accion') === 'eliminar') {
        $id = id_valido($_POST['id'] ?? null);
        if ($id === null) {
            http_response_code(400);
            exit('Identificador inválido.');
        }
        try {
            $s = $conexion->prepare('DELETE FROM categorias WHERE id = :id AND usuario_id = :u');
            $s->execute(['id' => $id, 'u' => $uid]);
            flash($s->rowCount() === 1 ? 'ok' : 'error', $s->rowCount() === 1 ? 'Categoría eliminada.' : 'La categoría no existe.');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {   // RESTRICT: tiene movimientos asociados
                flash('error', 'No se puede eliminar: la categoría tiene movimientos. Eliminá o cambiá esos movimientos primero.');
            } else {
                error_log($e->getMessage());
                flash('error', 'No se pudo eliminar la categoría.');
            }
        }
        header('Location: categorias.php');
        exit;
    }
}

$s = $conexion->prepare(
    'SELECT c.id, c.nombre, c.tipo, COUNT(m.id) AS cantidad
    FROM categorias c LEFT JOIN movimientos m ON m.categoria_id = c.id
    WHERE c.usuario_id = :u GROUP BY c.id, c.nombre, c.tipo ORDER BY c.tipo, c.nombre'
);
$s->execute(['u' => $uid]);
$categorias = $s->fetchAll();

$titulo = 'Categorías';
require __DIR__ . '/css/header.php';
?>
<div class="columnas">
    <section class="tarjeta">
        <h1>Mis categorías</h1>
        <div class="tabla-scroll">
        <table>
            <thead><tr><th>Nombre</th><th>Tipo</th><th>Movimientos</th><th>Acción</th></tr></thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td><?= escapar($c['nombre']) ?></td>
                    <td><span class="etiqueta etiqueta-<?= escapar($c['tipo']) ?>"><?= escapar($c['tipo']) ?></span></td>
                    <td><?= (int)$c['cantidad'] ?></td>
                    <td>
                        <form method="POST" action="categorias.php"
                              onsubmit="return confirm('¿Eliminar esta categoría?');">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                            <button type="submit" class="boton chico peligro">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="tarjeta">
        <h2>Nueva categoría</h2>
        <?php if ($errores): ?>
            <div class="alerta alerta-error"><ul>
                <?php foreach ($errores as $e): ?><li><?= escapar($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>
        <form method="POST" action="categorias.php" novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="accion" value="crear">
            <label for="nombre">Nombre</label>
            <input type="text" id="nombre" name="nombre" maxlength="60" required value="<?= escapar($nombre) ?>">
            <label for="tipo">Tipo</label>
            <select id="tipo" name="tipo">
                <option value="gasto"   <?= $tipo === 'gasto'   ? 'selected' : '' ?>>Gasto</option>
                <option value="ingreso" <?= $tipo === 'ingreso' ? 'selected' : '' ?>>Ingreso</option>
            </select>
            <button type="submit" class="boton">Crear categoría</button>
        </form>
    </section>
</div>
<?php require __DIR__ . '/css/footer.php'; ?>
