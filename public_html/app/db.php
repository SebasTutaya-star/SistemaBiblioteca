<?php
/**
 * Acceso a datos con PDO (consultas preparadas para evitar inyección SQL).
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = $GLOBALS['CONFIG']['db'];
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec("SET time_zone = '-05:00'");
        } catch (PDOException $e) {
            http_response_code(500);
            $detalle = !empty($GLOBALS['CONFIG']['debug']) ? ' Detalle: ' . $e->getMessage() : '';
            exit('No se pudo conectar con la base de datos. Revise config/config.php.' . $detalle);
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function fila(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function filas(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function valor(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

/** Lee un valor de la tabla configuracion (con caché por petición). */
function conf(string $clave, $defecto = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (filas('SELECT clave, valor FROM configuracion') as $r) {
            $cache[$r['clave']] = $r['valor'];
        }
    }
    return $cache[$clave] ?? $defecto;
}

/**
 * Consulta base de materiales con cantidades calculadas.
 * disponibles = total - deteriorados - perdidos - prestados activos (RF-07, RF-08, RF-16)
 */
function sql_materiales(): string
{
    return "SELECT m.*, t.nombre AS tipo_nombre,
                   COALESCE(p.prestados,0) AS prestados,
                   GREATEST(m.cantidad_total - m.cantidad_deteriorada - m.cantidad_perdida - COALESCE(p.prestados,0), 0) AS disponibles
            FROM materiales m
            LEFT JOIN tipos_material t ON t.id = m.tipo_material_id
            LEFT JOIN (SELECT material_id, COUNT(*) AS prestados FROM prestamos
                       WHERE estado = 'activo' GROUP BY material_id) p ON p.material_id = m.id";
}

function material_por_id(int $id): ?array
{
    return fila(sql_materiales() . ' WHERE m.id = ?', [$id]);
}

function registrar_movimiento(int $materialId, string $tipo, int $cantidad, string $obs = ''): void
{
    q('INSERT INTO movimientos_inventario (material_id, tipo, cantidad, observacion, usuario_id) VALUES (?,?,?,?,?)',
      [$materialId, $tipo, $cantidad, mb_substr($obs, 0, 255), usuario()['id']]);
}
