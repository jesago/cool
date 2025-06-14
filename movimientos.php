<?php
// Simplified CRUD for Movimientos with image uploads and category colors

$host     = 'localhost';
$user     = 'root';
$password = '';
$dbname   = 'test_prespuestos';

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die('<div class="alert alert-danger">Conexión fallida: ' . $conn->connect_error . '</div>');
}

// Helper queries for selects
$categorias = $conn->query("SELECT id_categoria, nombre, color FROM Categorias ORDER BY nombre");
$presupuestos = $conn->query("SELECT id_presupuesto, nombre FROM Presupuestos ORDER BY nombre");
$usuarios = $conn->query("SELECT id_usuario, nombre FROM Usuarios ORDER BY nombre");

$action = $_REQUEST['action'] ?? 'list';

function subirFotos($movId, $files, $conn) {
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    foreach ($files['error'] as $i => $err) {
        if ($err === UPLOAD_ERR_OK) {
            $tmp = $files['tmp_name'][$i];
            $name = basename($files['name'][$i]);
            $dest = $dir . '/' . uniqid() . '-' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $stmt = $conn->prepare("INSERT INTO Fotos_Sustentos(id_movimiento, ruta_archivo) VALUES (?, ?)");
                $ruta = 'uploads/' . basename($dest);
                $stmt->bind_param('is', $movId, $ruta);
                $stmt->execute();
                $stmt->close();
            }
        }
    }
}

if ($action === 'add' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare(
        "INSERT INTO Movimientos (id_presupuesto, id_categoria, fecha_movimiento, descripcion, monto, fecha_sistema, id_usuario)
         VALUES (?, ?, ?, ?, ?, NOW(), ?)"
    );
    $stmt->bind_param('iisdsi', $_POST['id_presupuesto'], $_POST['id_categoria'], $_POST['fecha_movimiento'], $_POST['descripcion'], $_POST['monto'], $_POST['id_usuario']);
    $stmt->execute();
    $movId = $stmt->insert_id;
    $stmt->close();

    if (!empty($_FILES['fotos']['name'][0])) {
        subirFotos($movId, $_FILES['fotos'], $conn);
    }

    header('Location: ?action=list');
    exit;
}

if ($action === 'edit') {
    if (!empty($_POST['id_movimiento'])) {
        $stmt = $conn->prepare(
            "UPDATE Movimientos SET id_presupuesto=?, id_categoria=?, fecha_movimiento=?, descripcion=?, monto=?, id_usuario=? WHERE id_movimiento=?"
        );
        $stmt->bind_param('iidsiii', $_POST['id_presupuesto'], $_POST['id_categoria'], $_POST['fecha_movimiento'], $_POST['descripcion'], $_POST['monto'], $_POST['id_usuario'], $_POST['id_movimiento']);
        $stmt->execute();
        $stmt->close();
        $movId = (int)$_POST['id_movimiento'];
        if (!empty($_FILES['fotos']['name'][0])) {
            subirFotos($movId, $_FILES['fotos'], $conn);
        }
        header('Location: ?action=list');
        exit;
    } else {
        $id = (int)$_GET['id'];
        $res = $conn->query("SELECT * FROM Movimientos WHERE id_movimiento=$id");
        $mov = $res->fetch_assoc();
        $fotos = $conn->query("SELECT * FROM Fotos_Sustentos WHERE id_movimiento=$id");
    }
}

if ($action === 'delete') {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM Movimientos WHERE id_movimiento=$id");
    header('Location: ?action=list');
    exit;
}

if ($action === 'delete_foto') {
    $fid = (int)$_GET['fid'];
    $row = $conn->query("SELECT ruta_archivo FROM Fotos_Sustentos WHERE id_foto=$fid")->fetch_assoc();
    if ($row) {
        @unlink(__DIR__ . '/' . $row['ruta_archivo']);
        $conn->query("DELETE FROM Fotos_Sustentos WHERE id_foto=$fid");
    }
    header('Location: ?action=edit&id='.(int)$_GET['id']);
    exit;
}

$res_mov = $conn->query(
    "SELECT m.*, c.nombre AS categoria, c.color, p.nombre AS presupuesto, u.nombre AS usuario
     FROM Movimientos m
     JOIN Categorias c ON m.id_categoria=c.id_categoria
     JOIN Presupuestos p ON m.id_presupuesto=p.id_presupuesto
     JOIN Usuarios u ON m.id_usuario=u.id_usuario"
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h1 class="mb-4">Gestión de Movimientos</h1>

    <?php if ($action === 'edit' && !empty($mov)): ?>
    <div class="card mb-4">
        <div class="card-header">Editar Movimiento #<?= $mov['id_movimiento'] ?></div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" action="?action=edit">
                <input type="hidden" name="id_movimiento" value="<?= $mov['id_movimiento'] ?>">
                <div class="mb-3">
                    <label class="form-label">Presupuesto</label>
                    <select name="id_presupuesto" class="form-select" required>
                        <option value="">Selecciona presupuesto</option>
                        <?php while($p = $presupuestos->fetch_assoc()): ?>
                        <option value="<?= $p['id_presupuesto'] ?>" <?= $p['id_presupuesto']==$mov['id_presupuesto']?'selected':'' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="id_categoria" class="form-select" required>
                        <option value="">Selecciona categoría</option>
                        <?php while($c = $categorias->fetch_assoc()): ?>
                        <option value="<?= $c['id_categoria'] ?>" <?= $c['id_categoria']==$mov['id_categoria']?'selected':'' ?> data-color="<?= $c['color'] ?>">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <select name="id_usuario" class="form-select" required>
                        <option value="">Selecciona usuario</option>
                        <?php while($u = $usuarios->fetch_assoc()): ?>
                        <option value="<?= $u['id_usuario'] ?>" <?= $u['id_usuario']==$mov['id_usuario']?'selected':'' ?>>
                            <?= htmlspecialchars($u['nombre']) ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha_movimiento" class="form-control" value="<?= $mov['fecha_movimiento'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control" value="<?= htmlspecialchars($mov['descripcion']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" name="monto" class="form-control" value="<?= $mov['monto'] ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nuevas fotos</label>
                    <input type="file" name="fotos[]" class="form-control" multiple>
                </div>
                <button class="btn btn-primary">Guardar</button>
                <a href="?action=list" class="btn btn-secondary">Cancelar</a>
            </form>
            <?php if ($fotos): ?>
            <hr>
            <div class="row">
                <?php while($f = $fotos->fetch_assoc()): ?>
                <div class="col-3 text-center">
                    <img src="<?= htmlspecialchars($f['ruta_archivo']) ?>" class="img-fluid mb-2">
                    <div>
                        <a href="?action=delete_foto&fid=<?= $f['id_foto'] ?>&id=<?= $mov['id_movimiento'] ?>" class="btn btn-sm btn-danger">Eliminar</a>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header">Nuevo Movimiento</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" action="?action=add">
                <div class="mb-3">
                    <label class="form-label">Presupuesto</label>
                    <select name="id_presupuesto" class="form-select" required>
                        <option value="">Selecciona presupuesto</option>
                        <?php foreach($conn->query("SELECT id_presupuesto,nombre FROM Presupuestos ORDER BY nombre") as $p): ?>
                        <option value="<?= $p['id_presupuesto'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Categoría</label>
                    <select name="id_categoria" class="form-select" required>
                        <option value="">Selecciona categoría</option>
                        <?php foreach($conn->query("SELECT id_categoria,nombre,color FROM Categorias ORDER BY nombre") as $c): ?>
                        <option value="<?= $c['id_categoria'] ?>" data-color="<?= $c['color'] ?>">
                            <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Usuario</label>
                    <select name="id_usuario" class="form-select" required>
                        <option value="">Selecciona usuario</option>
                        <?php foreach($conn->query("SELECT id_usuario,nombre FROM Usuarios ORDER BY nombre") as $u): ?>
                        <option value="<?= $u['id_usuario'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha_movimiento" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="descripcion" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label">Monto</label>
                    <input type="number" step="0.01" name="monto" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Fotos</label>
                    <input type="file" name="fotos[]" class="form-control" multiple>
                </div>
                <button type="submit" class="btn btn-success">Agregar</button>
            </form>
        </div>
    </div>

    <h2 class="mb-3">Listado de Movimientos</h2>
    <table class="table table-bordered table-hover">
        <thead class="table-light">
            <tr>
                <th>ID</th><th>Presupuesto</th><th>Categoría</th><th>Usuario</th><th>Fecha</th><th>Descripción</th><th>Monto</th><th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($row = $res_mov->fetch_assoc()): ?>
            <tr>
                <td><?= $row['id_movimiento'] ?></td>
                <td><?= htmlspecialchars($row['presupuesto']) ?></td>
                <td><span style="color: <?= htmlspecialchars($row['color']) ?>;"><?= htmlspecialchars($row['categoria']) ?></span></td>
                <td><?= htmlspecialchars($row['usuario']) ?></td>
                <td><?= $row['fecha_movimiento'] ?></td>
                <td><?= htmlspecialchars($row['descripcion']) ?></td>
                <td><?= number_format($row['monto'],2) ?></td>
                <td>
                    <a href="?action=edit&id=<?= $row['id_movimiento'] ?>" class="btn btn-sm btn-primary">Editar</a>
                    <a href="?action=delete&id=<?= $row['id_movimiento'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Borrar este movimiento?')">Borrar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
