<?php
/** Inserta únicamente libros nuevos en la instancia local de pruebas. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$config = require $root . '/public_html/config/config.php';
$c = $config['db'];
if ($c['name'] !== 'biblioteca_ui_test' || $c['host'] !== '127.0.0.1;port=3307') {
    fwrite(STDERR, "Este comando solo permite la base local biblioteca_ui_test en 127.0.0.1:3307.\n"); exit(1);
}
$catalogo = json_decode(file_get_contents($root . '/public_html/assets/catalogo/catalogo.json'), true, 512, JSON_THROW_ON_ERROR);
$pdo = new PDO("mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone = '-05:00'");
$admin = $pdo->query("SELECT id FROM usuarios WHERE rol = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
if (!$admin) throw new RuntimeException('No existe un administrador para registrar el movimiento.');
$tipo = $pdo->query("SELECT id FROM tipos_material WHERE nombre = 'Libro' LIMIT 1")->fetchColumn() ?: null;
$insert = $pdo->prepare('INSERT INTO materiales (codigo,titulo,autor,editorial,anio,area,nivel,tema,descripcion,tipo_material_id,cantidad_total,ubicacion,creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$exists = $pdo->prepare('SELECT id FROM materiales WHERE codigo = ?');
$mov = $pdo->prepare("INSERT INTO movimientos_inventario (material_id,tipo,cantidad,observacion,usuario_id) VALUES (?,'importacion',?,'Importación catálogo fotográfico del inventario 2026',?)");
$count = 0;
$pdo->beginTransaction();
try {
    foreach ($catalogo as $code => $r) {
        if ($code !== $r['codigo'] || !preg_match('/^INV-2026-\d{3}$/D', $code) || !$r['imagenes'] || $r['cantidad'] < 1) throw new RuntimeException('Registro inválido: ' . $code);
        foreach ($r['imagenes'] as $foto) {
            if (!is_file($root . '/public_html/' . $foto['src'])) throw new RuntimeException('Falta fotografía: ' . $code);
        }
        $exists->execute([$code]);
        if ($exists->fetchColumn()) continue; // Nunca sobrescribe existencias ni préstamos.
        $insert->execute([$code,$r['titulo'],$r['autor'],$r['editorial'],$r['anio'],$r['area'],$r['nivel'],$r['tema'],$r['descripcion'],$tipo,$r['cantidad'],$r['ubicacion'],$admin]);
        $mov->execute([$pdo->lastInsertId(),$r['cantidad'],$admin]);
        $count++;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack(); throw $e;
}
echo json_encode(['insertados' => $count, 'registros_catalogo' => count($catalogo)], JSON_UNESCAPED_UNICODE) . "\n";
