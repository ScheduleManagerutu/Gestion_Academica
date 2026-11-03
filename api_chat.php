<?php
// api_chat.php
// Asistente de horarios: responde preguntas sobre un docente leyendo la base de datos.
// Las páginas le mandan POST "mensaje" y recibe JSON: {"respuesta": "..."}.
//
// Usa las tablas que ya existen: Docente, Grupo, Materia y Clases_Asignadas.
// Este archivo NO muestra ninguna página: solo responde JSON (no pongas nada antes de "<?php").

require __DIR__ . '/conexion_horarios.php';   // trae $pdo
require __DIR__ . '/turnos_horarios.php';      // trae HORARIOS (horas de cada turno)

header('Content-Type: application/json; charset=utf-8');

// Palabras clave de cada intención (ya sin tildes ni mayúsculas).
// Se prueban en orden: la primera que aparezca en el mensaje decide la intención.
$INTENCIONES = [
    "SUGERIR_HORARIO"   => ["sugerencia", "sugerir", "libre", "disponible", "mover", "correr"],
    "CONSULTAR_GRUPO"   => ["grupo", "comision", "pertenece", "asignad"],
    "HORARIOS_OCUPADOS" => ["horario", "agenda", "tomado", "ocupado", "superponer", "superposicion"],
    "UBICACION_SALON"   => ["salon", "aula", "donde", "ubicacion", "edificio", "liceo"]
];

$DIAS = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];


// ---------- Funciones de ayuda ----------

function responderChat($texto) {
    echo json_encode(["respuesta" => $texto], JSON_UNESCAPED_UNICODE);
    exit;
}

// Texto seguro para mostrar (las respuestas se escriben como HTML en la página)
function h($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

// Minúsculas, sin tildes y sin símbolos: "¿Dónde está María?" -> "donde esta maria"
function limpiarTexto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $texto = preg_replace('/[^\w\s]/u', ' ', $texto);
    return trim(preg_replace('/\s+/', ' ', $texto));
}

// Busca al docente nombrado en el mensaje: por cédula, o por su nombre (todas sus palabras)
function identificarDocente($mensajeLimpio, $docentes) {
    // 1) Por cédula: un número de 6 a 8 cifras (con o sin puntos y guion)
    $sinSignos = preg_replace('/(?<=\d)[\s.\-](?=\d)/', '', $mensajeLimpio);
    if (preg_match_all('/\d{6,8}/', $sinSignos, $numeros)) {
        foreach ($docentes as $d) {
            $cedula = preg_replace('/\D/', '', $d["Documento"]);
            if (in_array($cedula, $numeros[0], true)) {
                return $d;
            }
        }
    }

    // 2) Por nombre: todas las palabras del nombre tienen que estar en el mensaje
    $palabrasMensaje = explode(" ", $mensajeLimpio);
    $mejor = null;
    $mejorPalabras = 0;
    foreach ($docentes as $d) {
        $palabrasNombre = explode(" ", limpiarTexto($d["Nombre"]));
        if (count(array_diff($palabrasNombre, $palabrasMensaje)) == 0 && count($palabrasNombre) > $mejorPalabras) {
            $mejor = $d;
            $mejorPalabras = count($palabrasNombre);
        }
    }
    return $mejor;
}

function clasificarIntencion($mensajeLimpio, $intenciones) {
    foreach ($intenciones as $intencion => $palabras) {
        foreach ($palabras as $palabra) {
            if (strpos($mensajeLimpio, $palabra) !== false) {
                return $intencion;
            }
        }
    }
    return "DESCONOCIDO";
}

// Las clases guardadas de un docente, ordenadas por día y hora
function clasesDelDocente($pdo, $iddocente, $dias) {
    $s = $pdo->prepare(
        "SELECT g.Nombre AS grupo, c.Dia AS dia, c.Hora_inicio AS inicio, c.Hora_fin AS fin, m.Nombre AS materia
         FROM Clases_Asignadas c
         INNER JOIN Grupo g ON g.IDgrupo = c.IDgrupo
         LEFT JOIN Materia m ON m.IDmateria = c.IDmateria
         WHERE c.IDdocente = ?"
    );
    $s->execute([$iddocente]);
    $clases = $s->fetchAll();

    foreach ($clases as $i => $c) {
        $clases[$i]["inicio"] = substr($c["inicio"], 0, 5);
        $clases[$i]["fin"] = substr($c["fin"], 0, 5);
        $clases[$i]["orden"] = array_search($c["dia"], $dias) . $clases[$i]["inicio"];
    }
    usort($clases, function ($a, $b) {
        return strcmp($a["orden"], $b["orden"]);
    });
    return $clases;
}

// Horarios en que el docente está libre, en los turnos donde da clases
// (o en todos los turnos si todavía no tiene clases). Junta las horas libres seguidas.
function bloquesLibres($clases, $dias) {
    $turnos = [];
    foreach ($clases as $c) {
        $partes = explode(" - ", $c["grupo"]);
        $turno = end($partes);
        if (isset(HORARIOS[$turno])) {
            $turnos[$turno] = true;
        }
    }
    $turnos = count($turnos) > 0 ? array_keys($turnos) : array_keys(HORARIOS);

    $lineas = [];
    foreach ($turnos as $turno) {
        foreach ($dias as $dia) {
            $bloques = [];
            $actual = null;

            foreach (HORARIOS[$turno] as $fila) {
                if ($fila[2] == "recreo") {
                    continue;   // un recreo no corta un bloque libre
                }
                $ocupada = false;
                foreach ($clases as $c) {
                    if ($c["dia"] == $dia && $c["inicio"] < $fila[1] && $c["fin"] > $fila[0]) {
                        $ocupada = true;
                    }
                }

                if ($ocupada) {
                    $actual = null;
                } elseif ($actual === null) {
                    $bloques[] = [$fila[0], $fila[1]];
                    $actual = count($bloques) - 1;
                } else {
                    $bloques[$actual][1] = $fila[1];
                }
            }

            foreach ($bloques as $b) {
                $lineas[] = $dia . " de " . $b[0] . " a " . $b[1] . " (" . $turno . ")";
            }
        }
    }
    return $lineas;
}


// ---------- Programa principal ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['mensaje'])) {
    http_response_code(405);
    responderChat("Método no permitido.");
}

try {
    $mensaje = limpiarTexto(mb_substr((string)$_POST['mensaje'], 0, 300));
    if ($mensaje == "") {
        responderChat("Escribe tu pregunta.");
    }

    $docentes = $pdo->query("SELECT IDdocente, Nombre, Documento, Materia FROM Docente")->fetchAll();
    $docente = identificarDocente($mensaje, $docentes);

    if ($docente === null) {
        responderChat("Indica el nombre completo o la cédula del docente sobre el que quieres consultar.");
    }

    $nombre = h($docente["Nombre"]);
    $clases = clasesDelDocente($pdo, $docente["IDdocente"], $DIAS);
    $intencion = clasificarIntencion($mensaje, $INTENCIONES);

    // En qué grupos da clase
    if ($intencion == "CONSULTAR_GRUPO") {
        if (count($clases) == 0) {
            responderChat("El/La docente " . $nombre . " no tiene grupos asignados actualmente.");
        }
        $grupos = array_unique(array_column($clases, "grupo"));
        responderChat("El/La docente " . $nombre . " (" . h($docente["Materia"]) . ") está asignado/a a: " . h(implode(", ", $grupos)) . ".");
    }

    // Dónde está: la base no guarda salones, así que se informa grupo y horario
    if ($intencion == "UBICACION_SALON") {
        if (count($clases) == 0) {
            responderChat("El/La docente " . $nombre . " no tiene clases asignadas, no hay dónde ubicarlo/a.");
        }
        $resp = "Clases de " . $nombre . " (el sistema todavía no registra el salón, solo el grupo):<br>";
        foreach ($clases as $c) {
            $resp .= "• " . h($c["dia"]) . " " . h($c["inicio"]) . " - " . h($c["fin"]) . ": grupo " . h($c["grupo"]) . "<br>";
        }
        responderChat($resp);
    }

    // Horarios ocupados
    if ($intencion == "HORARIOS_OCUPADOS") {
        if (count($clases) == 0) {
            responderChat("El/La docente " . $nombre . " tiene toda su agenda libre.");
        }
        $resp = "Horarios ocupados de " . $nombre . ":<br>";
        foreach ($clases as $c) {
            $resp .= "• " . h($c["dia"]) . " de " . h($c["inicio"]) . " a " . h($c["fin"]) . " (grupo " . h($c["grupo"]) . ")<br>";
        }
        responderChat($resp);
    }

    // Sugerencias: horas en que realmente está libre
    if ($intencion == "SUGERIR_HORARIO") {
        $libres = bloquesLibres($clases, $DIAS);
        if (count($libres) == 0) {
            responderChat($nombre . " no tiene horarios libres en sus turnos.");
        }
        $resp = "Horarios libres de " . $nombre . " para mover una clase:<br>";
        foreach (array_slice($libres, 0, 12) as $linea) {
            $resp .= "✔ " . h($linea) . "<br>";
        }
        if (count($libres) > 12) {
            $resp .= "… y " . (count($libres) - 12) . " más.";
        }
        responderChat($resp);
    }

    responderChat("No pude interpretar la consulta sobre " . $nombre . ". Prueba preguntando por su grupo, sus horarios ocupados o sus horarios libres.");

} catch (PDOException $e) {
    error_log("api_chat: " . $e->getMessage());
    http_response_code(500);
    responderChat("No pude consultar la base de datos.");
}