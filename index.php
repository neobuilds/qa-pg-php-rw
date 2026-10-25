<?php
// xCloud QA fixture: read and write one row in the PostgreSQL database named by the site's .env.
header('Content-Type: application/json');
function env_file() {
    foreach ([__DIR__ . '/.env', dirname(__DIR__) . '/.env'] as $f) if (is_file($f)) return $f;
    return null;
}
$env = [];
if ($f = env_file()) {
    foreach (file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if ($line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) > 1 && ($v[0] === '"' || $v[0] === "'") && substr($v, -1) === $v[0]) $v = stripcslashes(substr($v, 1, -1));
        $env[trim($k)] = $v;
    }
}
$out = ['ok' => true, 'app' => 'qa-pg-php-rw', 'php' => PHP_VERSION, 'pdo_pgsql' => extension_loaded('pdo_pgsql'),
        'env_connection' => $env['DB_CONNECTION'] ?? null];
if (($_GET['op'] ?? '') === 'rw') {
    try {
        $pdo = new PDO(sprintf('pgsql:host=%s;port=%s;dbname=%s', $env['DB_HOST'], $env['DB_PORT'], $env['DB_DATABASE']),
            $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE TABLE IF NOT EXISTS qa_rows (id serial PRIMARY KEY, tag text NOT NULL, at timestamptz DEFAULT now())');
        if (!empty($_GET['tag'])) $pdo->prepare('INSERT INTO qa_rows (tag) VALUES (?)')->execute([$_GET['tag']]);
        $out += $pdo->query('SELECT current_database() AS db, current_user AS usr, inet_server_port() AS port')->fetch(PDO::FETCH_ASSOC);
        $out['rows'] = $pdo->query('SELECT id, tag FROM qa_rows ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        http_response_code(500);
        $out = ['ok' => false, 'error' => get_class($e), 'message' => substr($e->getMessage(), 0, 140), 'pdo_pgsql' => extension_loaded('pdo_pgsql')];
    }
}
echo json_encode($out);
