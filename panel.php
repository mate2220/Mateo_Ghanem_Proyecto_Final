<?php

require_once __DIR__ . '/funciones.php';
requerir_login();
$uid = (int)$_SESSION['usuario_id'];
asegurar_categorias_base($conexion, $uid);

$stmt = $conexion->prepare('SELECT nombre, apellido, dni, email, rol, creado_en FROM usuarios WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $uid]);
$u = $stmt->fetch();

// Rango del mes actual
$desde = date('Y-m-01');
$hasta = date('Y-m-01', strtotime('+1 month', strtotime($desde)));

// Total del mes
$stmt = $conexion->prepare(
    "SELECT COALESCE(SUM(CASE WHEN c.tipo = 'ingreso' THEN m.monto END), 0) AS ingresos,
            COALESCE(SUM(CASE WHEN c.tipo = 'gasto'   THEN m.monto END), 0) AS gastos
     FROM movimientos m JOIN categorias c ON c.id = m.categoria_id
     WHERE m.usuario_id = :u AND m.fecha >= :d AND m.fecha < :h"
);
$stmt->execute(['u' => $uid, 'd' => $desde, 'h' => $hasta]);
$tot = $stmt->fetch();
$balance = (float)$tot['ingresos'] - (float)$tot['gastos'];

// Gastos del mes por categoría
$stmt = $conexion->prepare(
    "SELECT c.nombre, SUM(m.monto) AS total
     FROM movimientos m JOIN categorias c ON c.id = m.categoria_id
     WHERE m.usuario_id = :u AND c.tipo = 'gasto' AND m.fecha >= :d AND m.fecha < :h
     GROUP BY c.id, c.nombre ORDER BY total DESC"
);
$stmt->execute(['u' => $uid, 'd' => $desde, 'h' => $hasta]);
$porCategoria = $stmt->fetchAll();

// Últimos 5 movimientos
$stmt = $conexion->prepare(
    'SELECT m.fecha, m.monto, m.descripcion, c.nombre AS categoria, c.tipo
     FROM movimientos m JOIN categorias c ON c.id = m.categoria_id
     WHERE m.usuario_id = :u ORDER BY m.fecha DESC, m.id DESC LIMIT 5'
);
$stmt->execute(['u' => $uid]);
$ultimos = $stmt->fetchAll();

$titulo = 'Resumen';
require __DIR__ . '/css/header.php';
?>
<h1>Hola, <?= escapar($u['nombre']) ?></h1>

<section class="resumen">
    <div class="cifra"><span>Ingresos del mes</span><strong class="monto-ingreso"><?= dinero($tot['ingresos']) ?></strong></div>
    <div class="cifra"><span>Gastos del mes</span><strong class="monto-gasto"><?= dinero($tot['gastos']) ?></strong></div>
    <div class="cifra"><span>Balance</span><strong class="<?= $balance >= 0 ? 'monto-ingreso' : 'monto-gasto' ?>"><?= dinero($balance) ?></strong></div>
</section>

<div class="columnas">
    <section class="tarjeta">
        <div class="fila-titulo">
            <h2>Últimos movimientos</h2>
            <a class="boton chico" href="movimiento_form.php">Nuevo movimiento</a>
        </div>
        <?php if (!$ultimos): ?>
            <p>Todavía no registraste movimientos. Empezá cargando tu primer ingreso o gasto.</p>
        <?php else: ?>
            <ul class="lista-mov">
            <?php foreach ($ultimos as $m): ?>
                <li>
                    <div>
                        <strong><?= escapar($m['categoria']) ?></strong>
                        <small><?= escapar($m['fecha']) ?><?= $m['descripcion'] ? ' · ' . escapar($m['descripcion']) : '' ?></small>
                    </div>
                    <span class="monto-<?= escapar($m['tipo']) ?>"><?= $m['tipo'] === 'gasto' ? '−' : '+' ?> <?= dinero($m['monto']) ?></span>
                </li>
            <?php endforeach; ?>
            </ul>
            <a href="movimientos.php">Ver todos</a>
        <?php endif; ?>
    </section>

    <section class="tarjeta">
        <h2>Gastos por categoría (este mes)</h2>
        <?php if (!$porCategoria): ?>
            <p>No hay gastos este mes.</p>
        <?php else: $max = (float)$porCategoria[0]['total']; ?>
            <?php foreach ($porCategoria as $c): ?>
                <div class="fila-cat">
                    <div class="fila-cat-texto"><span><?= escapar($c['nombre']) ?></span><span><?= dinero($c['total']) ?></span></div>
                    <div class="progreso"><div style="width: <?= $max > 0 ? round($c['total'] / $max * 100) : 0 ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<section class="tarjeta">
    <h2>Mis datos</h2>
    <dl class="datos">
        <dt>Nombre</dt>   <dd><?= escapar($u['nombre'] . ' ' . $u['apellido']) ?></dd>
        <dt>DNI</dt>      <dd><?= escapar($u['dni']) ?></dd>
        <dt>Correo</dt>   <dd><?= escapar($u['email']) ?></dd>
        <dt>Rol</dt>      <dd><?= escapar($u['rol']) ?></dd>
        <dt>Miembro desde</dt> <dd><?= escapar($u['creado_en']) ?></dd>
    </dl>
</section>
<?php require __DIR__ . '/css/footer.php'; ?>
