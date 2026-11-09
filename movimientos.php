<?php
/** movimientos.php - Listado (READ) de ingresos y gastos del usuario, con filtros y totales. */
require_once __DIR__ . '/funciones.php';
requerir_login();
$uid = (int)$_SESSION['usuario_id'];
asegurar_categorias_base($conexion, $uid);

// ---- Filtros (GET) ----
// mes: formato AAAA-MM. Si no viene, se usa el mes actual. Si viene vacío, se muestran todos.
$mes = isset($_GET['mes']) && is_string($_GET['mes']) ? trim($_GET['mes']) : date('Y-m');
if ($mes !== '' && !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
    $mes = date('Y-m');
}
$tipo = (is_string($_GET['tipo'] ?? null) && in_array($_GET['tipo'], ['ingreso', 'gasto'], true)) ? $_GET['tipo'] : '';
$catFiltro = id_valido($_GET['categoria'] ?? null);

$where  = ['m.usuario_id = :u'];          // SIEMPRE se filtra por el usuario logueado
$params = ['u' => $uid];
if ($mes !== '') {
    $where[] = 'm.fecha >= :desde AND m.fecha < :hasta';
    $params['desde'] = $mes . '-01';
    $params['hasta'] = date('Y-m-01', strtotime('+1 month', strtotime($mes . '-01')));
}
if ($tipo !== '') {
    $where[] = 'c.tipo = :tipo';
    $params['tipo'] = $tipo;
}
if ($catFiltro !== null) {
    $where[] = 'm.categoria_id = :cat';
    $params['cat'] = $catFiltro;
}
$whereSql = implode(' AND ', $where);

// ---- Totales ----
$stmt = $conexion->prepare(
    "SELECT COALESCE(SUM(CASE WHEN c.tipo = 'ingreso' THEN m.monto END), 0) AS ingresos,
            COALESCE(SUM(CASE WHEN c.tipo = 'gasto'   THEN m.monto END), 0) AS gastos
     FROM movimientos m JOIN categorias c ON c.id = m.categoria_id WHERE $whereSql"
);
$stmt->execute($params);
$tot = $stmt->fetch();
$balance = (float)$tot['ingresos'] - (float)$tot['gastos'];

// ---- Listado ----
$stmt = $conexion->prepare(
    "SELECT m.id, m.fecha, m.monto, m.descripcion, c.nombre AS categoria, c.tipo
     FROM movimientos m JOIN categorias c ON c.id = m.categoria_id
     WHERE $whereSql ORDER BY m.fecha DESC, m.id DESC LIMIT 500"
);
$stmt->execute($params);
$movs = $stmt->fetchAll();

// Categorías para el filtro
$stmt = $conexion->prepare('SELECT id, nombre, tipo FROM categorias WHERE usuario_id = :u ORDER BY tipo, nombre');
$stmt->execute(['u' => $uid]);
$categorias = $stmt->fetchAll();

$titulo = 'Movimientos';
require __DIR__ . '/css/header.php';
?>
<section class="resumen">
    <div class="cifra"><span>Ingresos</span><strong class="monto-ingreso"><?= dinero($tot['ingresos']) ?></strong></div>
    <div class="cifra"><span>Gastos</span><strong class="monto-gasto"><?= dinero($tot['gastos']) ?></strong></div>
    <div class="cifra"><span>Balance</span><strong class="<?= $balance >= 0 ? 'monto-ingreso' : 'monto-gasto' ?>"><?= dinero($balance) ?></strong></div>
</section>

<section class="tarjeta">
    <div class="fila-titulo">
        <h1>Movimientos</h1>
        <a class="boton" href="movimiento_form.php">Nuevo movimiento</a>
    </div>

    <form method="GET" action="movimientos.php" class="filtros">
        <div><label for="mes">Mes (vacío = todos)</label>
             <input type="month" id="mes" name="mes" value="<?= escapar($mes) ?>"></div>
        <div><label for="tipo">Tipo</label>
             <select id="tipo" name="tipo">
                <option value="">Todos</option>
                <option value="ingreso" <?= $tipo === 'ingreso' ? 'selected' : '' ?>>Ingresos</option>
                <option value="gasto"   <?= $tipo === 'gasto'   ? 'selected' : '' ?>>Gastos</option>
             </select></div>
        <div><label for="categoria">Categoría</label>
             <select id="categoria" name="categoria">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?>
                    <option value="<?= (int)$c['id'] ?>" <?= $catFiltro === (int)$c['id'] ? 'selected' : '' ?>>
                        <?= escapar($c['nombre']) ?> (<?= escapar($c['tipo']) ?>)
                    </option>
                <?php endforeach; ?>
             </select></div>
        <div class="filtros-botones">
            <button type="submit" class="boton">Filtrar</button>
            <a class="boton secundario" href="movimientos.php">Restablecer</a>
        </div>
    </form>

    <?php if (!$movs): ?>
        <p>No hay movimientos con esos filtros.</p>
    <?php else: ?>
    <div class="tabla-scroll">
    <table>
        <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th class="der">Monto</th><th>Acciones</th></tr></thead>
        <tbody>
        <?php foreach ($movs as $m): ?>
            <tr>
                <td><?= escapar($m['fecha']) ?></td>
                <td><span class="etiqueta etiqueta-<?= escapar($m['tipo']) ?>"><?= escapar($m['categoria']) ?></span></td>
                <td class="desc"><?= escapar($m['descripcion']) ?></td>
                <td class="der monto-<?= escapar($m['tipo']) ?>"><?= $m['tipo'] === 'gasto' ? '−' : '+' ?> <?= dinero($m['monto']) ?></td>
                <td class="acciones">
                    <a class="boton chico secundario" href="movimiento_form.php?id=<?= (int)$m['id'] ?>">Editar</a>
                    <form method="POST" action="eliminar_movimiento.php"
                          onsubmit="return confirm('¿Eliminar este movimiento? No se puede deshacer.');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="boton chico peligro">Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
