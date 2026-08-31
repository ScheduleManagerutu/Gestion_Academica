<?php
function cargarVariablesEnv($rutaArchivo) {
    if (!file_exists($rutaArchivo)) {
        return false;
    }

    $lineas = file($rutaArchivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lineas as $linea) {
        $lineaLimpia = trim($linea);
        // Ignorar comentarios
        if (strpos($lineaLimpia, '#') === 0) {
            continue;
        }

        if (strpos($lineaLimpia, '=') !== false) {
            list($nombre, $valor) = explode('=', $lineaLimpia, 2);
            
            $nombre = trim($nombre);
            $valor  = trim($valor);
            $valor  = trim($valor, '"\''); // Quitar comillas si las hay

            if (!array_key_exists($nombre, $_SERVER) && !array_key_exists($nombre, $_ENV)) {
                putenv("{$nombre}={$valor}");
                $_ENV[$nombre]     = $valor;
                $_SERVER[$nombre]  = $valor;
            }
        }
    }
    return true;
}
?>