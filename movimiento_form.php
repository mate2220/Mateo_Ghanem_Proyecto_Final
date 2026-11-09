<?php
/** movimiento_form.php - CREATE y UPDATE de movimientos (sin ?id = nuevo, con ?id = editar). */
require_once __DIR__ . '/funciones.php';
requerir_login();
$uid = (int)$_SESSION['usuario_id'];
asegurar_categorias_base($conexion, $uid);

$esPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$errores = [];

// ¿Modo edición?
$idCrudo = $esPost ? ($_POST['id'] ?? '') : ($_GET['id'] ?? '');
$id = null;
if ($idCrudo !== '' && $idCrudo !== null) {
    $id = id_valido($idCrudo);
    if ($id === null) {
        http_response_code(400);
        exit('Identificador inválido.');
    }
}

$v = ['categoria_id' => '', 'monto' => '', 'descripcion' => '', 'fecha' => date('Y-m-d')];

if ($id !== null) {
    // Solo se puede editar un movimiento PROPIO
    $stmt = $conexion->prepare('SELECT categoria_id, monto, descripcion, fecha FROM movimientos WHERE id = :id AND usuario_id = :u');
    $stmt->execute(['id' => $id, 'u' => $uid]);
    $m = $stmt->fetch();
    if ($m === false) {
        flash('error', 'El movimiento no existe.');
        header('Location: movimientos.php');
        exit;
    }
    if (!$esPost) {
        $v = ['categoria_id' => (string)$m['categoria_id'], 'monto' => number_format((float)$m['monto'], 2, ',', ''),
              'descripcion' => (string)$m['descripcion'], 'fecha' => $m['fecha']];
    }
}

if ($esPost) {
    if (!csrf_validar()) {
        $errores[] = 'La solicitud no es válida. Recargá la página e intentá de nuevo.';
    } else {
        foreach ($v as $campo => $_) {
            $v[$campo] = post($campo);
        }
        $errores = validar_movimiento($conexion, $uid, $v);
        if (!$errores) {
            $datos = [
                'cat'   => (int)$v['categoria_id'],
                'monto' => str_replace(',', '.', $v['monto']),
                'desc'  => $v['descripcion'] !== '' ? $v['descripcion'] : null,
                'fecha' => $v['fecha'],
                'u'     => $uid,
            ];
            try {
                if ($id === null) {
                    $s = $conexion->prepare('INSERT INTO movimientos (usuario_id, categoria_id, monto, descripcion, fecha)
                                            VALUES (:u, :cat, :monto, :desc, :fecha)');
                    $s->execute($datos);
                    flash('ok', 'Movimiento registrado.');
                } else {
                    $datos['id'] = $id;
                    $s = $conexion->prepare('UPDATE movimientos SET categoria_id = :cat, monto = :monto,
                                            descripcion = :desc, fecha = :fecha
                                            WHERE id = :id AND usuario_id = :u');
                    $s->execute($datos);
                    flash('ok', 'Movimiento actualizado.');
                }
                header('Location: movimientos.php');
                exit;
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $errores[] = 'Ocurrió un error al guardar el movimiento.';
            }
        }
    }
}

$stmt = $conexion->prepare('SELECT id, nombre, tipo FROM categorias WHERE usuario_id = :u ORDER BY tipo, nombre');
$stmt->execute(['u' => $uid]);
$categorias = [];
foreach ($stmt->fetchAll() as $c) {
    $categorias[$c['tipo']][] = $c;
}

$titulo = $id === null ? 'Nuevo movimiento' : 'Editar movimiento';
require __DIR__ . '/css/header.php';
?>
<section class="tarjeta angosta">
    <h1><?= escapar($titulo) ?></h1>
    <?php if ($errores): ?>
        <div class="alerta alerta-error"><ul>
            <?php foreach ($errores as $e): ?><li><?= escapar($e) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>

    <form method="POST" action="movimiento_form.php" novalidate>
        <?= csrf_campo() ?>
        <?php if ($id !== null): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>

        <label for="categoria_id">Categoría</label>
        <select id="categoria_id" name="categoria_id" required>
            <option value="">Elegí una categoría</option>
            <?php foreach (['ingreso' => 'Ingresos', 'gasto' => 'Gastos'] as $tipo => $rotulo): ?>
                <?php if (!empty($categorias[$tipo])): ?>
                <optgroup label="<?= $rotulo ?>">
                    <?php foreach ($categorias[$tipo] as $c): ?>
                        <option value="<?= (int)$c['id'] ?>" <?= $v['categoria_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                            <?= escapar($c['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>

        <label for="monto">Monto</label>
        <input type="text" id="monto" name="monto" inputmode="decimal" placeholder="1500,50" required value="<?= escapar($v['monto']) ?>">

        <label for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" required value="<?= escapar($v['fecha']) ?>">

        <label for="descripcion">Descripción (opcional)</label>
        <input type="text" id="descripcion" name="descripcion" maxlength="255" value="<?= escapar($v['descripcion']) ?>">

        <button type="submit" class="boton">Guardar movimiento</button>
        <a class="boton secundario" href="movimientos.php">Cancelar</a>
    </form>
    <p class="nota">¿Falta una categoría? <a href="categorias.php">Creala acá</a>.</p>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
