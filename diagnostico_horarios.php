<?php
// diagnostico_horarios.php
// Revisa que los archivos del sistema estén en su lugar, que la base de datos responda
// y que api_horarios.php conteste bien desde el navegador.
//
// Cómo usarlo: copialo a la carpeta Gestion_Academica (la misma donde está api_horarios.php)
// y abrilo en el navegador:  http://localhost/app/Gestion_Academica/diagnostico_horarios.php
// BORRALO cuando termines: muestra información de tu servidor.

header('Content-Type: text/html; charset=utf-8');

// Texto seguro para mostrar en la página
function h($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Escribe una línea de resultado: ✔ si está bien, ✘ si hay un problema
function linea($ok, $texto) {
    echo '<li>' . ($ok ? '✔' : '✘') . ' ' . h($texto) . '</li>';
}

// Deja un nombre en forma simple para encontrar nombres parecidos:
// "api-horarios.php" y "API_horarios.php.txt" pasan a ser "api_horarios"
function normalizar($nombre) {
    $nombre = strtolower($nombre);
    $nombre = strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
    $nombre = str_replace(['-', ' '], '_', $nombre);
    return preg_replace('/\.(php|html|css)(\.txt)?$/', '', $nombre);
}

$archivos = [
    "api_horarios.php",
    "api_chat.php",
    "conexion_horarios.php",
    "turnos_horarios.php",
    "respaldo_horarios.php",
    "style.css",
    "secciones/vista_creacion-horarios.html",
    "secciones/vista_horarios-creados.html"
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Diagnóstico del sistema de horarios</title>
</head>
<body style="font-family: Arial, sans-serif; margin: 20px; line-height: 1.5;">
<h1>Diagnóstico del sistema de horarios</h1>

<h2>1. Archivos</h2>
<p>Carpeta donde está este archivo: <b><?php echo h(__DIR__); ?></b></p>
<ul>
<?php
foreach ($archivos as $ruta) {
    $completa = __DIR__ . '/' . $ruta;

    if (is_file($completa)) {
        if (substr($ruta, -4) == '.php') {
            $inicio = file_get_contents($completa, false, null, 0, 5);
            if (substr($inicio, 0, 3) == "\xEF\xBB\xBF") {
                linea(false, $ruta . ": está guardado con BOM. Guardalo como UTF-8 sin BOM (el BOM rompe las respuestas JSON).");
            } elseif ($inicio != '<?php') {
                linea(false, $ruta . ": no empieza con <?php (se perdió esa primera línea).");
            } else {
                linea(true, $ruta . ": está y empieza bien.");
            }
        } else {
            linea(true, $ruta . ": está.");
        }
    } else {
        // No está con ese nombre exacto: se buscan nombres parecidos en la misma carpeta
        $parecidos = [];
        $carpeta = dirname($completa);
        if (is_dir($carpeta)) {
            foreach (scandir($carpeta) as $otro) {
                if ($otro != basename($ruta) && normalizar($otro) == normalizar(basename($ruta))) {
                    $parecidos[] = $otro;
                }
            }
        }

        $texto = $ruta . ": NO se encuentra con ese nombre exacto.";
        if (count($parecidos) > 0) {
            $texto .= " Hay un archivo parecido: " . implode(", ", $parecidos) . " (hay que renombrarlo a " . basename($ruta) . ").";
        }
        linea(false, $texto);
    }
}
?>
</ul>

<h2>2. PHP</h2>
<ul>
<?php
linea(version_compare(PHP_VERSION, '8.0.0', '>='), "Versión de PHP: " . PHP_VERSION . " (se necesita 8.0 o más)");
linea(extension_loaded('pdo_mysql'), "Extensión pdo_mysql: " . (extension_loaded('pdo_mysql') ? "activada" : "NO está activada (activala en php.ini)"));
?>
</ul>

<h2>3. Base de datos</h2>
<?php
// Si la conexión falla, conexion_horarios.php corta el programa: esta función avisa qué hacer
register_shutdown_function(function () {
    global $pdo;
    if (!isset($pdo)) {
        echo '<p>✘ No se pudo conectar. Revisá usuario, clave y nombre de la base en conexion_horarios.php, '
           . 'y que esté encendido el MySQL correcto (el de Workbench, puerto 3306).</p></body></html>';
    }
});

require __DIR__ . '/conexion_horarios.php';   // trae $pdo
?>
<ul>
<?php
linea(true, "Conexión correcta a la base " . $pdo->query("SELECT DATABASE()")->fetchColumn()
          . " (versión del servidor: " . $pdo->query("SELECT VERSION()")->fetchColumn() . ")");

// Tablas que usa la aplicación
$existentes = array_map('strtolower', $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN));
$tablas = ["Docente", "Materia", "Grupo", "Clases_Asignadas", "Gestion_Usuario_Docente", "Gestion_Usuario_Grupo"];

foreach ($tablas as $tabla) {
    if (in_array(strtolower($tabla), $existentes, true)) {
        $filas = $pdo->query("SELECT COUNT(*) FROM `" . $tabla . "`")->fetchColumn();
        linea(true, "Tabla " . $tabla . ": existe (" . $filas . " filas).");
    } else {
        linea(false, "Tabla " . $tabla . ": NO existe.");
    }
}

// Claves foráneas que bloquean al agregar docentes o cursos
$claves = $pdo->query(
    "SELECT TABLE_NAME AS tabla, CONSTRAINT_NAME AS clave
     FROM information_schema.KEY_COLUMN_USAGE
     WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL"
)->fetchAll();

$problemas = 0;
foreach ($claves as $c) {
    if ($c["clave"] == "Docente_Materia_FK" || $c["clave"] == "Grupo_Docente_FK") {
        linea(false, "Clave foránea " . $c["clave"] . " (tabla " . $c["tabla"] . "): puede dar el error 1452 al guardar.");
        $problemas++;
    }
}
if ($problemas == 0) {
    linea(true, "No están Docente_Materia_FK ni Grupo_Docente_FK.");
}

// Respaldo: ¿se puede usar mysqldump?
$deshabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$execOk = function_exists('exec') && !in_array('exec', $deshabilitadas, true);
// Mismo orden que usa respaldo_horarios.php: primero MySQL Server (la versión más nueva), después XAMPP
$mysqldump = array_merge(
    glob("C:/Program Files/MySQL/MySQL Server */bin/mysqldump.exe") ?: [],
    glob("C:/Program Files (x86)/MySQL/MySQL Server */bin/mysqldump.exe") ?: []
);
if (count($mysqldump) > 0) {
    sort($mysqldump, SORT_NATURAL);
} else {
    $mysqldump = glob("C:/xampp/mysql/bin/mysqldump.exe") ?: [];
}
linea($execOk && count($mysqldump) > 0,
      "Respaldo con mysqldump: " . (($execOk && count($mysqldump) > 0)
          ? "disponible (" . end($mysqldump) . ")."
          : "no disponible; el respaldo se hará en PHP puro (también funciona)."));
?>
</ul>

<h2>4. Prueba de api_horarios.php desde el navegador</h2>
<p id="resultado-api">Probando...</p>

<script>
// Pide la lista de docentes y cursos, igual que hace la página de creación
var datos = new FormData();
datos.append("accion", "listar");

fetch("api_horarios.php", { method: "POST", body: datos })
  .then(function (respuesta) {
    return respuesta.text().then(function (texto) {
      var mensaje = "";
      try {
        var json = JSON.parse(texto);
        if (json.ok) {
          mensaje = "✔ api_horarios.php responde bien (estado " + respuesta.status + ").";
        } else {
          mensaje = "✘ api_horarios.php respondió con un error: " + json.error;
        }
      } catch (e) {
        mensaje = "✘ api_horarios.php no devolvió JSON (estado " + respuesta.status + ").";
        if (texto.charCodeAt(0) == 0xFEFF) {
          mensaje += " Empieza con un carácter invisible (BOM): guardá los archivos .php como UTF-8 sin BOM.";
        }
        mensaje += " Empieza con: " + texto.substring(0, 200);
      }
      document.getElementById("resultado-api").textContent = mensaje;
    });
  })
  .catch(function (error) {
    document.getElementById("resultado-api").textContent = "✘ No se pudo llamar a api_horarios.php: " + error.message;
  });
</script>

</body>
</html>
