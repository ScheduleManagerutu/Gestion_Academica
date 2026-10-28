<?php
// conexion_horarios.php
// Conexión a la base de datos "BD Web".
// Completá estos datos con los de tu servidor MySQL.

$DB_HOST    = "localhost";
$DB_NOMBRE  = "BD Web";
$DB_USUARIO = "root";
$DB_CLAVE   = "";

// Poné true SOLO mientras desarrollás: muestra el mensaje técnico del error.
// En producción tiene que estar en false.
$MODO_DEBUG = false;

try {
    $pdo = new PDO(
        "mysql:host=" . $DB_HOST . ";dbname=" . $DB_NOMBRE . ";charset=utf8mb4",
        $DB_USUARIO,
        $DB_CLAVE,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false
        ]
    );
} catch (PDOException $e) {
    error_log("Conexion BD: " . $e->getMessage());
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);

    $mensaje = "No se pudo conectar a la base de datos.";
    if ($MODO_DEBUG) {
        $mensaje .= " " . $e->getMessage();
    }
    echo json_encode(["ok" => false, "error" => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}
