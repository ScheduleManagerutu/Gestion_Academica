<?php
// respaldo_horarios.php
// Genera un respaldo (backup) de la base de datos en un archivo .sql y lo descarga.
//
// El archivo incluye la estructura de las tablas (CREATE TABLE) y los datos (INSERT INTO).
// Se genera de dos maneras:
//   A) Con el programa "mysqldump" (el más completo y rápido). Se usa si exec() está
//      habilitado y el programa se encuentra en el equipo.
//   B) En PHP puro (SHOW TABLES, SHOW CREATE TABLE y SELECT *). Se usa si la opción A
//      no se puede usar o falla.
//
// Se pide por POST (desde el botón "Descargar respaldo" de la página de horarios creados).
// Pensado para MySQL 8 (Workbench) en Windows con XAMPP: busca primero el mysqldump de
// MySQL Server (el mismo programa que tu servidor) y, si no sirve, usa la opción B.

require __DIR__ . '/conexion_horarios.php';   // trae $pdo, $DB_HOST, $DB_NOMBRE, $DB_USUARIO y $DB_CLAVE

date_default_timezone_set('America/Montevideo');
set_time_limit(0);

// ---------- Configuración ----------

// El respaldo contiene TODOS los datos (también la tabla Usuario). Si tu sistema tiene
// login, ponelo en true para que solo pueda descargarlo un usuario con sesión iniciada.
// (Ajustá 'IDusuario' al nombre que use tu login en $_SESSION.)
$REQUIERE_SESION = false;

// Ruta de mysqldump si el programa no se encuentra solo. Ejemplos:
//   "/opt/lampp/bin/mysqldump"            (XAMPP en Linux)
//   "C:\\xampp\\mysql\\bin\\mysqldump.exe"   (XAMPP en Windows)
//   "C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe"   (MySQL 8 en Windows)
$RUTA_MYSQLDUMP = "";


// ---------- Funciones de ayuda ----------

// Responde con un error en JSON (lo muestra el botón de la página)
function fallarRespaldo($mensaje, $codigo = 500) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($codigo);
    echo json_encode(["ok" => false, "error" => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// Dice si se puede usar exec() en este servidor
function execDisponible() {
    if (!function_exists('exec')) {
        return false;
    }
    $deshabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
    return !in_array('exec', $deshabilitadas, true);
}

// Busca el programa mysqldump (o su equivalente mariadb-dump)
function buscarMysqldump($rutaManual) {
    if ($rutaManual != "" && is_file($rutaManual)) {
        return $rutaManual;
    }

    // MySQL Server instalado en Windows (el de Workbench). Se prefiere porque es de la misma
    // versión que el servidor. Si hay varias versiones instaladas, se usa la más nueva.
    $instalados = array_merge(
        glob("C:/Program Files/MySQL/MySQL Server */bin/mysqldump.exe") ?: [],
        glob("C:/Program Files (x86)/MySQL/MySQL Server */bin/mysqldump.exe") ?: []
    );
    if (count($instalados) > 0) {
        sort($instalados, SORT_NATURAL);
        return end($instalados);
    }

    $candidatas = [
        "/opt/lampp/bin/mysqldump",              // XAMPP en Linux
        "/usr/bin/mysqldump",
        "/usr/local/bin/mysqldump",
        "/usr/bin/mariadb-dump",
        "C:\\xampp\\mysql\\bin\\mysqldump.exe",  // XAMPP en Windows
        "C:/xampp/mysql/bin/mysqldump.exe"
    ];
    foreach ($candidatas as $ruta) {
        if (is_file($ruta)) {
            return $ruta;
        }
    }
    return null;
}

// Comprueba que mysqldump haya terminado el archivo (la última línea dice "Dump completed")
function respaldoCompleto($archivo) {
    if (!is_file($archivo) || filesize($archivo) == 0) {
        return false;
    }
    $f = fopen($archivo, 'rb');
    fseek($f, max(0, filesize($archivo) - 300));
    $final = fread($f, 300);
    fclose($f);
    return strpos($final, "Dump completed") !== false;
}


// ---------- Opción A: mysqldump ----------

// Devuelve true si logró crear el archivo con mysqldump; false si no se pudo (entonces se usa la opción B)
function respaldoConMysqldump($archivo, $host, $usuario, $clave, $baseDatos, $rutaManual) {
    if (!execDisponible()) {
        return false;
    }
    $programa = buscarMysqldump($rutaManual);
    if ($programa === null) {
        return false;
    }

    // Los datos de conexión van en un archivo temporal (y no en el comando),
    // así la clave no se ve en la lista de procesos del equipo.
    if ($host == "localhost") {
        $host = "127.0.0.1";   // por red, para no depender de la ruta del socket
    }
    $cambiar = ['\\' => '\\\\', '"' => '\\"'];
    $config  = "[client]\n";
    $config .= 'user="' . strtr($usuario, $cambiar) . "\"\n";
    $config .= 'host="' . strtr($host, $cambiar) . "\"\n";
    if ($clave !== "") {
        $config .= 'password="' . strtr($clave, $cambiar) . "\"\n";
    }

    $archivoConfig = tempnam(sys_get_temp_dir(), 'cnf_');
    chmod($archivoConfig, 0600);
    file_put_contents($archivoConfig, $config);

    $comando = escapeshellarg($programa)
        . ' --defaults-extra-file=' . escapeshellarg($archivoConfig)   // tiene que ir primero
        . ' --single-transaction --routines --triggers --default-character-set=utf8mb4'
        . ' --result-file=' . escapeshellarg($archivo)
        . ' --databases ' . escapeshellarg($baseDatos)
        . ' 2>&1';
    if (DIRECTORY_SEPARATOR == '\\') {
        $comando = '"' . $comando . '"';   // en Windows hay que envolver todo el comando
    }

    $salida = [];
    $codigo = 1;
    exec($comando, $salida, $codigo);
    unlink($archivoConfig);

    if ($codigo !== 0 || !respaldoCompleto($archivo)) {
        error_log("Respaldo con mysqldump falló (código " . $codigo . "): " . implode(" | ", $salida));
        return false;
    }
    return true;
}


// ---------- Opción B: PHP puro ----------

// Convierte un valor de la base de datos en texto SQL: NULL, número o texto entre comillas
function valorSql($pdo, $valor) {
    if ($valor === null) {
        return "NULL";
    }
    if (is_int($valor) || is_float($valor)) {
        return (string)$valor;
    }
    if (!mb_check_encoding($valor, 'UTF-8')) {
        return "0x" . bin2hex($valor);   // datos binarios
    }
    return $pdo->quote($valor);
}

// Escribe en $archivo el respaldo completo: estructura y datos de cada tabla
function respaldoConPhp($pdo, $baseDatos, $archivo) {
    $f = fopen($archivo, 'wb');
    if ($f === false) {
        throw new RuntimeException("No se pudo crear el archivo temporal.");
    }

    $nombreBd = str_replace('`', '``', $baseDatos);

    fwrite($f, "-- Respaldo de la base de datos `" . $baseDatos . "`\n");
    fwrite($f, "-- Generado el " . date('Y-m-d H:i:s') . " (PHP puro)\n\n");
    fwrite($f, "SET NAMES utf8mb4;\n");
    fwrite($f, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
    fwrite($f, "CREATE DATABASE IF NOT EXISTS `" . $nombreBd . "` DEFAULT CHARACTER SET utf8mb4;\n");
    fwrite($f, "USE `" . $nombreBd . "`;\n\n");

    // Solo tablas (no vistas)
    $tablas = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tablas as $tabla) {
        $nombre = "`" . str_replace('`', '``', $tabla) . "`";

        // Estructura
        $crear = $pdo->query("SHOW CREATE TABLE " . $nombre)->fetch(PDO::FETCH_NUM);
        fwrite($f, "-- ----------------------------\n-- Tabla " . $nombre . "\n-- ----------------------------\n");
        fwrite($f, "DROP TABLE IF EXISTS " . $nombre . ";\n");
        fwrite($f, $crear[1] . ";\n\n");

        // Datos: de a 100 filas por INSERT, leyendo sin cargar toda la tabla en memoria
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $consulta = $pdo->query("SELECT * FROM " . $nombre);

        $columnas = "";
        $grupo = [];
        while (($fila = $consulta->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($columnas == "") {
                $columnas = "`" . implode("`, `", array_map(function ($c) {
                    return str_replace('`', '``', $c);
                }, array_keys($fila))) . "`";
            }

            $valores = [];
            foreach ($fila as $valor) {
                $valores[] = valorSql($pdo, $valor);
            }
            $grupo[] = "(" . implode(", ", $valores) . ")";

            if (count($grupo) == 100) {
                fwrite($f, "INSERT INTO " . $nombre . " (" . $columnas . ") VALUES\n" . implode(",\n", $grupo) . ";\n");
                $grupo = [];
            }
        }
        $consulta->closeCursor();
        $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        if (count($grupo) > 0) {
            fwrite($f, "INSERT INTO " . $nombre . " (" . $columnas . ") VALUES\n" . implode(",\n", $grupo) . ";\n");
        }
        fwrite($f, "\n");
    }

    fwrite($f, "SET FOREIGN_KEY_CHECKS = 1;\n\n");
    fwrite($f, "-- Fin del respaldo (" . count($tablas) . " tablas)\n");
    fclose($f);
}


// ---------- Programa principal ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fallarRespaldo("Método no permitido.", 405);
}

if ($REQUIERE_SESION) {
    session_start();
    if (!isset($_SESSION['IDusuario'])) {
        fallarRespaldo("Tienes que iniciar sesión para descargar el respaldo.", 401);
    }
}

// El archivo se arma completo en una carpeta temporal y recién después se descarga:
// si algo falla, no se descarga un archivo a medias.
$archivo = tempnam(sys_get_temp_dir(), 'respaldo_');
$metodo = "mysqldump";

try {
    $hecho = respaldoConMysqldump($archivo, $DB_HOST, $DB_USUARIO, $DB_CLAVE, $DB_NOMBRE, $RUTA_MYSQLDUMP);

    if (!$hecho) {
        $metodo = "php";
        respaldoConPhp($pdo, $DB_NOMBRE, $archivo);
    }
} catch (Throwable $e) {
    @unlink($archivo);
    error_log("Respaldo: " . $e->getMessage());

    $mensaje = "No se pudo generar el respaldo.";
    if (!empty($MODO_DEBUG)) {
        $mensaje .= " " . $e->getMessage();
    }
    fallarRespaldo($mensaje);
}

// Descargar el archivo con el nombre del día: backup_grillas_utu_2026-10-28.sql
$nombreArchivo = "backup_grillas_utu_" . date('Y-m-d') . ".sql";

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Content-Length: ' . filesize($archivo));
header('Cache-Control: no-store');
header('X-Metodo-Respaldo: ' . $metodo);   // "mysqldump" o "php" (para saber cuál se usó)

readfile($archivo);
unlink($archivo);
exit;