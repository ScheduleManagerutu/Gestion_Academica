<?php
// api_horarios.php
// Recibe los pedidos de vista_creación-horarios.html y vista_horarios-creados.html
// (siempre por POST) y responde en formato JSON.
//
// Usa SOLO tablas que ya existen: Docente, Materia, Grupo, Clases_Asignadas,
// Gestion_Usuario_Docente y Gestion_Usuario_Grupo. No crea ni modifica tablas.
//
// Cómo se guarda cada cosa:
//   - Docente  -> tabla Docente (la materia se guarda por nombre en Docente.Materia
//                 y también existe en la tabla Materia).
//   - Curso    -> tabla Grupo. Como Grupo no tiene columna de turno, el turno va
//                 al final del nombre:  "3MF - Matutino".
//   - Grilla   -> tabla Clases_Asignadas (una fila por cada celda con docente).

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conexion_horarios.php';

// (Opcional) Si tu sistema tiene login con sesiones, descomentá y ajustá esto
// para que solo usuarios logueados puedan usar la API:
// session_start();
// if (!isset($_SESSION['IDusuario'])) { fallar("Tenés que iniciar sesión.", 401); }

$TURNOS = ["Matutino", "Vespertino", "Nocturno"];
$DIAS   = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];

// Horas de cada turno. Es el ÚNICO lugar donde están escritas: las dos páginas
// las reciben desde acá. Cada fila es [inicio, fin, tipo].
const HORARIOS = [
    "Matutino" => [
        ["07:30", "08:15", "clase"], ["08:15", "09:00", "clase"], ["09:00", "09:05", "receso"],
        ["09:05", "09:50", "clase"], ["09:50", "10:35", "clase"], ["10:35", "10:40", "receso"],
        ["10:40", "11:25", "clase"], ["11:25", "12:10", "clase"], ["12:10", "12:15", "receso"],
        ["12:15", "13:00", "clase"], ["13:00", "13:05", "receso"], ["13:05", "13:50", "clase"]
    ],
    "Vespertino" => [
        ["13:05", "13:50", "clase"], ["13:50", "14:35", "clase"], ["14:35", "14:40", "receso"],
        ["14:40", "15:25", "clase"], ["15:25", "16:10", "clase"], ["16:10", "16:15", "receso"],
        ["16:15", "17:00", "clase"], ["17:00", "17:45", "clase"], ["17:45", "17:50", "receso"],
        ["17:50", "18:35", "clase"], ["18:35", "19:20", "clase"]
    ],
    "Nocturno" => [
        ["17:50", "18:35", "clase"], ["18:35", "19:20", "clase"], ["19:20", "20:00", "clase"],
        ["20:00", "20:05", "receso"], ["20:05", "20:45", "clase"], ["20:45", "21:25", "clase"],
        ["21:25", "21:30", "receso"], ["21:30", "22:10", "clase"], ["22:10", "22:15", "receso"],
        ["22:15", "22:55", "clase"], ["22:55", "23:35", "clase"]
    ]
];


// ---------- Funciones de ayuda ----------

function responder($datos, $codigo = 200) {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

function fallar($mensaje, $codigo = 400) {
    responder(["ok" => false, "error" => $mensaje], $codigo);
}

// Lee un texto enviado por POST (sin espacios de más)
function textoPost($clave) {
    if (isset($_POST[$clave])) {
        return trim($_POST[$clave]);
    }
    return "";
}

// Lee un número entero enviado por POST
function enteroPost($clave) {
    if (isset($_POST[$clave])) {
        return (int)$_POST[$clave];
    }
    return 0;
}

// Lee una lista de ids enviada como JSON, por ejemplo "[3,5]"
function listaIdsPost($clave) {
    $texto = isset($_POST[$clave]) ? $_POST[$clave] : "[]";
    $lista = json_decode($texto, true);
    $ids = [];
    if (is_array($lista)) {
        foreach ($lista as $valor) {
            $numero = (int)$valor;
            if ($numero > 0) {
                $ids[] = $numero;
            }
        }
    }
    return $ids;
}

// Separa "3MF - Matutino" en ["3MF", "Matutino"]
function partirNombre($nombre) {
    if (preg_match('/^(.*) - (Matutino|Vespertino|Nocturno)$/u', $nombre, $partes)) {
        return [$partes[1], $partes[2]];
    }
    return [$nombre, ""];
}

// Agrega "curso" y "turno" a cada fila que trae el nombre del grupo
function conCursoYTurno($filas) {
    foreach ($filas as $i => $fila) {
        $partes = partirNombre($fila["nombre"]);
        $filas[$i]["curso"] = $partes[0];
        $filas[$i]["turno"] = $partes[1];
    }
    return $filas;
}

// Las horas de los turnos en el formato que usan las páginas: {hora: "07:30 - 08:15", tipo: "clase"}
function horasParaPagina() {
    $resultado = [];
    foreach (HORARIOS as $turno => $filas) {
        foreach ($filas as $fila) {
            $resultado[$turno][] = ["hora" => $fila[0] . " - " . $fila[1], "tipo" => $fila[2]];
        }
    }
    return $resultado;
}

// Dice si una hora de inicio y fin es una CLASE (no un receso) del turno indicado
function horaEnTurno($turno, $inicio, $fin) {
    if (!isset(HORARIOS[$turno])) {
        return false;
    }
    foreach (HORARIOS[$turno] as $fila) {
        if ($fila[2] == "clase" && $fila[0] == $inicio && $fila[1] == $fin) {
            return true;
        }
    }
    return false;
}

// Devuelve "?,?,?" con un signo de pregunta por cada elemento (para usar con IN (...))
function marcadores($cantidad) {
    return implode(",", array_fill(0, $cantidad, "?"));
}

// Valida una cédula de identidad uruguaya. Acepta con o sin puntos y guion
// ("1.234.567-2" o "12345672"). Devuelve solo los dígitos si es válida, o "" si no lo es.
//
// Fórmula del dígito verificador:
//   1) Se completa con ceros a la izquierda hasta tener 8 dígitos (7 del número + 1 verificador).
//   2) Cada uno de los primeros 7 dígitos se multiplica por 2, 9, 8, 7, 6, 3 y 4, y se suman los resultados.
//   3) El verificador es lo que falta para llegar a la decena siguiente: (10 - suma % 10) % 10.
//   4) Tiene que ser igual al último dígito de la cédula.
function cedulaValida($texto) {
    $digitos = preg_replace('/[.\-\s]/', '', $texto);

    if (preg_match('/^[0-9]{6,8}$/', $digitos) !== 1 || (int)$digitos == 0) {
        return "";   // no son 6 a 8 números, o es todo ceros
    }

    $relleno = str_pad($digitos, 8, "0", STR_PAD_LEFT);
    $pesos = [2, 9, 8, 7, 6, 3, 4];
    $suma = 0;
    for ($i = 0; $i < 7; $i++) {
        $suma += (int)$relleno[$i] * $pesos[$i];
    }
    $verificador = (10 - $suma % 10) % 10;

    if ($verificador != (int)$relleno[7]) {
        return "";
    }
    return $digitos;
}

// Comprueba que una hora tenga el formato HH:MM
function horaValida($hora) {
    return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hora) === 1;
}

// Busca si un docente ya tiene una clase en OTRO curso que se pise con el día y
// horario indicados. Devuelve los datos de esa clase, o null si está libre.
// Dos horarios se pisan si el que ya existe empieza antes de que termine el nuevo
// y termina después de que empiece el nuevo (sin importar el turno).
function buscarConflicto($pdo, $idgrupo, $iddocente, $dia, $inicio, $fin) {
    $s = $pdo->prepare(
        "SELECT g.Nombre AS grupo,
                TIME_FORMAT(c.Hora_inicio, '%H:%i') AS inicio,
                TIME_FORMAT(c.Hora_fin, '%H:%i') AS fin
         FROM Clases_Asignadas c
         INNER JOIN Grupo g ON g.IDgrupo = c.IDgrupo
         WHERE c.IDdocente = ? AND c.IDgrupo <> ? AND c.Dia = ?
           AND c.Hora_inicio < ? AND c.Hora_fin > ?
         LIMIT 1"
    );
    $s->execute([$iddocente, $idgrupo, $dia, $fin, $inicio]);
    $fila = $s->fetch();

    if ($fila === false) {
        return null;
    }
    return $fila;
}

// Igual que buscarConflicto(), pero para todas las clases de una grilla a la vez.
// Hace UNA sola consulta (las clases de esos docentes en otros cursos) y compara en PHP.
// Devuelve ["clase" => la clase nueva, "otra" => la clase que ya existía], o null si no hay choque.
function buscarConflictosVarios($pdo, $idgrupo, $clases) {
    $ids = [];
    foreach ($clases as $c) {
        $ids[(int)$c["iddocente"]] = true;
    }
    $ids = array_keys($ids);

    if (count($ids) == 0) {
        return null;
    }

    $s = $pdo->prepare(
        "SELECT c.IDdocente AS iddocente, c.Dia AS dia,
                TIME_FORMAT(c.Hora_inicio, '%H:%i') AS inicio,
                TIME_FORMAT(c.Hora_fin, '%H:%i') AS fin,
                g.Nombre AS grupo
         FROM Clases_Asignadas c
         INNER JOIN Grupo g ON g.IDgrupo = c.IDgrupo
         WHERE c.IDgrupo <> ? AND c.IDdocente IN (" . marcadores(count($ids)) . ")"
    );
    $s->execute(array_merge([$idgrupo], $ids));
    $ocupadas = $s->fetchAll();

    // Las horas "HH:MM" se pueden comparar como texto
    foreach ($clases as $c) {
        foreach ($ocupadas as $otra) {
            if ($otra["iddocente"] == $c["iddocente"] && $otra["dia"] == $c["dia"]
                && $otra["inicio"] < $c["fin"] && $otra["fin"] > $c["inicio"]) {
                return ["clase" => $c, "otra" => $otra];
            }
        }
    }
    return null;
}

// Convierte un error de la base de datos en un mensaje entendible
function mensajeErrorBD($e) {
    global $MODO_DEBUG;
    error_log("API horarios: " . $e->getMessage());

    $codigo = 0;
    if (isset($e->errorInfo[1])) {
        $codigo = $e->errorInfo[1];
    }

    if ($codigo == 1452) {
        return "La base de datos rechazó el dato porque hace referencia a un registro que no existe (error 1452). "
             . "Si pasó al agregar un docente o un curso, revisá las claves foráneas Docente_Materia_FK y Grupo_Docente_FK.";
    }
    if ($codigo == 1451) {
        return "No se puede borrar porque otros registros dependen de este (error 1451).";
    }
    if ($MODO_DEBUG) {
        return $e->getMessage();
    }
    return "Error en la base de datos.";
}


// ---------- Programa principal ----------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fallar("Método no permitido.", 405);
}

$accion = textoPost("accion");

try {
    switch ($accion) {

        // Devuelve todos los docentes y todos los cursos (grupos)
        case "listar":
            $docentes = $pdo->query(
                "SELECT d.IDdocente AS id, d.Nombre AS nombre, d.Documento AS documento,
                        d.Telefono AS telefono, d.Materia AS materia,
                        (SELECT MIN(m.IDmateria) FROM Materia m WHERE m.Nombre = d.Materia) AS idmateria
                 FROM Docente d
                 ORDER BY d.Materia, d.Nombre"
            )->fetchAll();

            $grupos = conCursoYTurno($pdo->query(
                "SELECT IDgrupo AS id, Nombre AS nombre, Cantidad_alumnos AS cantidad, IDdocente AS iddocente
                 FROM Grupo
                 ORDER BY Nombre"
            )->fetchAll());

            // Nombres de materias que ya existen (para sugerirlas al agregar un docente)
            $materias = $pdo->query("SELECT Nombre FROM Materia ORDER BY Nombre")->fetchAll(PDO::FETCH_COLUMN);

            responder(["ok" => true, "docentes" => $docentes, "grupos" => $grupos, "materias" => $materias, "horas" => horasParaPagina()]);
            break;


        // Agrega un docente (y su materia si todavía no existe)
        case "agregar_docente":
            $nombre    = textoPost("nombre");
            $documento = textoPost("documento");
            $telefono  = textoPost("telefono");
            $materia   = textoPost("materia");

            if ($nombre == "" || $documento == "" || $materia == "") {
                fallar("Materia, nombre del docente y documento son obligatorios.");
            }

            // El documento tiene que ser una cédula uruguaya válida; se guarda solo con los dígitos
            $documento = cedulaValida($documento);
            if ($documento == "") {
                fallar("El documento no es una cédula uruguaya válida. Revisa los números (ejemplo: 1.234.567-2).");
            }
            if (mb_strlen($nombre) > 100 || mb_strlen($materia) > 100 || mb_strlen($documento) > 20 || mb_strlen($telefono) > 20) {
                fallar("Alguno de los datos es demasiado largo (nombre y materia: 100, documento y teléfono: 20).");
            }

            $s = $pdo->prepare("SELECT COUNT(*) FROM Docente WHERE Documento = ?");
            $s->execute([$documento]);
            if ($s->fetchColumn() > 0) {
                fallar("Ya existe un docente con ese documento.");
            }

            $pdo->beginTransaction();

            // Si la materia no existe en la tabla Materia, se crea
            $s = $pdo->prepare("SELECT IDmateria FROM Materia WHERE Nombre = ? LIMIT 1");
            $s->execute([$materia]);
            if ($s->fetchColumn() === false) {
                $pdo->prepare("INSERT INTO Materia (Nombre) VALUES (?)")->execute([$materia]);
            }

            $telefonoGuardar = null;
            if ($telefono != "") {
                $telefonoGuardar = $telefono;
            }
            $pdo->prepare("INSERT INTO Docente (Documento, Materia, Telefono, Nombre) VALUES (?, ?, ?, ?)")
                ->execute([$documento, $materia, $telefonoGuardar, $nombre]);
            $nuevoId = (int)$pdo->lastInsertId();

            $pdo->commit();
            responder(["ok" => true, "id" => $nuevoId]);
            break;


        // Borra un docente y las clases que tenía asignadas
        case "borrar_docente":
            $id = enteroPost("id");
            if ($id <= 0) {
                fallar("Falta el docente.");
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM Clases_Asignadas WHERE IDdocente = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM Gestion_Usuario_Docente WHERE IDdocente = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM Docente WHERE IDdocente = ?")->execute([$id]);
            $pdo->commit();

            responder(["ok" => true]);
            break;


        // Agrega un curso (grupo) en un turno
        case "agregar_grupo":
            $curso     = textoPost("curso");
            $turno     = textoPost("turno");
            $cantidad  = enteroPost("cantidad");
            $iddocente = enteroPost("iddocente");

            if ($curso == "") {
                fallar("Escribí el nombre del curso.");
            }
            if (mb_strlen($curso) > 80) {
                fallar("El nombre del curso es demasiado largo.");
            }
            if (!in_array($turno, $TURNOS, true)) {
                fallar("Turno no válido.");
            }
            if ($cantidad < 0) {
                fallar("La cantidad de alumnos no puede ser negativa.");
            }

            $nombre = $curso . " - " . $turno;

            $s = $pdo->prepare("SELECT COUNT(*) FROM Grupo WHERE Nombre = ?");
            $s->execute([$nombre]);
            if ($s->fetchColumn() > 0) {
                fallar("Ya existe el curso " . $curso . " en el turno " . $turno . ".");
            }

            // Grupo.IDdocente no acepta vacío: si no hay docente elegido se guarda 0
            $pdo->prepare("INSERT INTO Grupo (Nombre, Cantidad_alumnos, IDdocente) VALUES (?, ?, ?)")
                ->execute([$nombre, $cantidad, $iddocente]);

            responder(["ok" => true, "id" => (int)$pdo->lastInsertId(), "nombre" => $nombre]);
            break;


        // Borra cursos (grupos) y todas sus clases
        case "borrar_grupos":
            $ids = listaIdsPost("ids");
            if (count($ids) == 0) {
                fallar("No hay cursos para borrar.");
            }

            $m = marcadores(count($ids));
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM Clases_Asignadas WHERE IDgrupo IN ($m)")->execute($ids);
            $pdo->prepare("DELETE FROM Gestion_Usuario_Grupo WHERE IDgrupo IN ($m)")->execute($ids);
            $pdo->prepare("DELETE FROM Grupo WHERE IDgrupo IN ($m)")->execute($ids);
            $pdo->commit();

            responder(["ok" => true]);
            break;


        // Borra solo las clases (el horario) de varios cursos; los cursos quedan
        case "borrar_horarios":
            $ids = listaIdsPost("ids");
            if (count($ids) == 0) {
                fallar("No hay horarios para borrar.");
            }

            $pdo->prepare("DELETE FROM Clases_Asignadas WHERE IDgrupo IN (" . marcadores(count($ids)) . ")")->execute($ids);

            responder(["ok" => true]);
            break;


        // Devuelve las clases guardadas de un curso
        case "leer_grilla":
            $idgrupo = enteroPost("idgrupo");
            if ($idgrupo <= 0) {
                fallar("Falta el curso.");
            }

            $s = $pdo->prepare(
                "SELECT c.Dia AS dia,
                        TIME_FORMAT(c.Hora_inicio, '%H:%i') AS inicio,
                        TIME_FORMAT(c.Hora_fin, '%H:%i') AS fin,
                        c.IDdocente AS iddocente, c.IDmateria AS idmateria,
                        d.Nombre AS docente, m.Nombre AS materia
                 FROM Clases_Asignadas c
                 LEFT JOIN Docente d ON d.IDdocente = c.IDdocente
                 LEFT JOIN Materia m ON m.IDmateria = c.IDmateria
                 WHERE c.IDgrupo = ?
                 ORDER BY c.Hora_inicio"
            );
            $s->execute([$idgrupo]);

            responder(["ok" => true, "clases" => $s->fetchAll()]);
            break;


        // Guarda la grilla de un curso (reemplaza las clases anteriores)
        case "guardar_grilla":
            $idgrupo = enteroPost("idgrupo");
            $texto   = isset($_POST["clases"]) ? $_POST["clases"] : "[]";
            $clases  = json_decode($texto, true);

            if ($idgrupo <= 0 || !is_array($clases)) {
                fallar("Datos incompletos.");
            }

            // El curso tiene que existir; de su nombre se saca el turno ("3MF - Matutino")
            $s = $pdo->prepare("SELECT Nombre FROM Grupo WHERE IDgrupo = ?");
            $s->execute([$idgrupo]);
            $nombreGrupo = $s->fetchColumn();
            if ($nombreGrupo === false) {
                fallar("El curso ya no existe. Actualizá la página.", 404);
            }
            $turnoGrupo = partirNombre($nombreGrupo)[1];

            // Validar todo antes de tocar la base de datos
            $docentesUsados = [];
            $celdasUsadas = [];
            foreach ($clases as $c) {
                if (!is_array($c)) {
                    fallar("Datos de clase no válidos.");
                }
                $dia    = isset($c["dia"]) ? $c["dia"] : "";
                $inicio = isset($c["inicio"]) ? $c["inicio"] : "";
                $fin    = isset($c["fin"]) ? $c["fin"] : "";
                $iddoc  = isset($c["iddocente"]) ? (int)$c["iddocente"] : 0;

                if (!in_array($dia, $DIAS, true)) {
                    fallar("Día no válido: " . $dia);
                }
                if (!horaValida($inicio) || !horaValida($fin) || $inicio >= $fin) {
                    fallar("Hora no válida en una de las clases.");
                }
                if ($iddoc <= 0) {
                    fallar("Falta el docente en una de las clases.");
                }
                // La hora tiene que ser una clase del turno del curso (si el turno se conoce)
                if ($turnoGrupo != "" && !horaEnTurno($turnoGrupo, $inicio, $fin)) {
                    fallar("La hora " . $inicio . " - " . $fin . " no corresponde al turno " . $turnoGrupo . ".");
                }
                // No puede haber dos clases en la misma celda
                $clave = $dia . "|" . $inicio . "|" . $fin;
                if (isset($celdasUsadas[$clave])) {
                    fallar("Hay dos clases en la misma celda (" . $dia . " " . $inicio . ").");
                }
                $celdasUsadas[$clave] = true;
                $docentesUsados[$iddoc] = true;
            }

            // Los docentes tienen que existir (una sola consulta; se guardan los nombres para los mensajes)
            $nombresDocentes = [];
            if (count($docentesUsados) > 0) {
                $idsDocentes = array_keys($docentesUsados);
                $s = $pdo->prepare("SELECT IDdocente, Nombre FROM Docente WHERE IDdocente IN (" . marcadores(count($idsDocentes)) . ")");
                $s->execute($idsDocentes);
                foreach ($s->fetchAll() as $fila) {
                    $nombresDocentes[(int)$fila["IDdocente"]] = $fila["Nombre"];
                }
                if (count($nombresDocentes) != count($idsDocentes)) {
                    fallar("Uno de los docentes ya no existe. Actualizá la página.");
                }
            }

            // Un docente no puede estar en dos cursos al mismo tiempo (una sola consulta para toda la grilla)
            $choque = buscarConflictosVarios($pdo, $idgrupo, $clases);
            if ($choque !== null) {
                fallar($nombresDocentes[(int)$choque["clase"]["iddocente"]] . " ya está asignado el " . $choque["clase"]["dia"] .
                       " de " . $choque["otra"]["inicio"] . " a " . $choque["otra"]["fin"] .
                       " en el curso " . $choque["otra"]["grupo"] .
                       ". Un docente no puede estar en dos cursos al mismo tiempo.", 409);
            }

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM Clases_Asignadas WHERE IDgrupo = ?")->execute([$idgrupo]);

            $insertar = $pdo->prepare(
                "INSERT INTO Clases_Asignadas (IDgrupo, Dia, Hora_inicio, Hora_fin, IDmateria, IDdocente)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($clases as $c) {
                $idmateria = isset($c["idmateria"]) ? (int)$c["idmateria"] : 0;
                if ($idmateria <= 0) {
                    $idmateria = null;
                }
                $insertar->execute([$idgrupo, $c["dia"], $c["inicio"], $c["fin"], $idmateria, (int)$c["iddocente"]]);
            }
            $pdo->commit();

            responder(["ok" => true, "guardadas" => count($clases)]);
            break;


        // Dice si un docente ya está ocupado en otro curso en ese día y horario
        case "conflicto_docente":
            $idgrupo   = enteroPost("idgrupo");
            $iddocente = enteroPost("iddocente");
            $dia       = textoPost("dia");
            $inicio    = textoPost("inicio");
            $fin       = textoPost("fin");

            if ($iddocente <= 0 || !in_array($dia, $DIAS, true) || !horaValida($inicio) || !horaValida($fin) || $inicio >= $fin) {
                fallar("Datos incompletos.");
            }

            responder(["ok" => true, "conflicto" => buscarConflicto($pdo, $idgrupo, $iddocente, $dia, $inicio, $fin)]);
            break;


        // Devuelve los cursos que tienen un horario guardado (para la vista)
        case "listar_horarios":
            $horarios = conCursoYTurno($pdo->query(
                "SELECT g.IDgrupo AS id, g.Nombre AS nombre, g.Cantidad_alumnos AS cantidad,
                        COUNT(c.IDclase) AS clases
                 FROM Grupo g
                 INNER JOIN Clases_Asignadas c ON c.IDgrupo = g.IDgrupo
                 GROUP BY g.IDgrupo, g.Nombre, g.Cantidad_alumnos
                 ORDER BY g.Nombre"
            )->fetchAll());

            // "grupos" son TODOS los cursos (también los que no tienen horario): sirven para eliminar un grupo completo
            $grupos = conCursoYTurno($pdo->query(
                "SELECT IDgrupo AS id, Nombre AS nombre FROM Grupo"
            )->fetchAll());

            responder(["ok" => true, "horarios" => $horarios, "grupos" => $grupos, "horas" => horasParaPagina()]);
            break;


        default:
            fallar("Acción no válida.");
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fallar(mensajeErrorBD($e), 500);
}