<?php
// Incluir el cargador subiendo un nivel desde 'configuraciones' a la raíz
require_once __DIR__ . '/../cargador_env.php';

// Cargar el archivo .env ubicado en la raíz
cargarVariablesEnv(__DIR__ . '/../.env');

// Leer las variables cargadas
$host     = $_ENV['DB_HOST'] ?? '127.0.0.1';
$user     = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$database = $_ENV['DB_NAME'] ?? 'BD Web';
$port     = $_ENV['DB_PORT'] ?? 3306;

// Crear la conexión PDO (o mysqli, según lo que utilicen)
try {
    $conexion = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $user, $password);
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Error de conexión: " . $e->getMessage();
}
?>