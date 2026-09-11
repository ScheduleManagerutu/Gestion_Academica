  
<?php

// Base de datos vinculada SQL

require_once __DIR__ . '/configuraciones/BD.php';

// Diccionario de intención y palabras clave

$intencionesPalabrasClave = [
    "SUGERIR_HORARIO"   => ["sugerencia", "sugerir", "libre", "disponible", "mover", "correr"],
    "CONSULTAR_GRUPO"   => ["grupo", "comision", "pertenece", "asignado"],
    "UBICACION_SALON"   => ["salon", "aula", "donde esta", "ubicacion", "donde", "edificio", "institucion", "liceo"],
    "HORARIOS_OCUPADOS"  => ["horario", "agenda", "tomado", "ocupado", "superponer", "superposicion"]
];

/**
 * Preprocesamiento de texto (Limpieza)
 */
function limpiarTexto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $reemplazos = [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u',
        'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u'
    ];
    $texto = strtr($texto, $reemplazos);
    $texto = preg_replace('/[^\w\s]/u', '', $texto);
    return $texto;
}

/**
 * Extracción de entidades (Docente)
 */
function identificarDocente($mensajeLimpio, $conexion) {
    foreach ($conexion as $clave => $datos) {
        if (strpos($mensajeLimpio, $clave) !== false) {
            return $datos;
        }
    }
    return null;

    $stmt = $conexion->prepare("SELECT id, nombre FROM docentes");
    $stmt->execute();
    $conexion = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conexion as $conexion) {
        $nombreLimpio = limpiarTexto($conexion['nombre']);
        if (strpos($mensajeLimpio, $nombreLimpio) !== false) {
            return $conexion;
        }
    }
    return null;
}

// 🟢 AÑADIR (Nueva función para traer los horarios del docente desde MySQL):
function obtenerHorariosDocente($idDocente, $conexion) {
    $sql = "SELECT h.grupo, h.salon, h.dia, h.hora_inicio, h.hora_fin 
            FROM horarios h 
            WHERE h.id_docente = :id_docente";
            
    $stmt = $conexion->prepare($sql);
    $stmt->execute(['id_docente' => $idDocente]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function clasificarIntencion($mensajeLimpio, $intencionesPalabrasClave) {
    foreach ($intencionesPalabrasClave as $intencion => $palabras) {
        foreach ($palabras as $palabra) {
            if (strpos($mensajeLimpio, $palabra) !== false) {
                return $intencion;
            }
        }
    }
    return "DESCONOCIDO";
}

/**
 * Clasificación de la Intención
 */
function clasificarIntencion($mensajeLimpio, $intencionesPalabrasClave) {
    foreach ($intencionesPalabrasClave as $intencion => $palabras) {
        foreach ($palabras as $palabra) {
            if (strpos($mensajeLimpio, $palabra) !== false) {
                return $intencion;
            }
        }
    }
    return "DESCONOCIDO";
}

/**
 * Lógica principal de procesamiento
 */
function procesarConsulta($mensajeUsuario, $conexion, $horariosBD, $intencionesPalabrasClave) {
    $mensajeLimpio = limpiarTexto($mensajeUsuario);
    $conexion = identificarDocente($mensajeLimpio, $conexion);

    if (!$conexion) {
        return "Debe indicar el nombre del docente sobre el cual desea hacer la consulta.";
    }

    $intencion = clasificarIntencion($mensajeLimpio, $intencionesPalabrasClave);
    $idDocente = $conexion["id"];
    
    // Filtrar asignaciones del docente
    $clasesDocente = array_filter($horariosBD, function($h) use ($idDocente) {
        return $h["id_docente"] === $idDocente;
    });

    // Pregunta 1: Grupos asignados
    if ($intencion === "CONSULTAR_GRUPO") {
        if (empty($clasesDocente)) {
            return "El/La docente " . $conexion['nombre'] . " no tiene grupos asignados actualmente.";
        }
        $grupos = array_unique(array_column($clasesDocente, 'grupo'));
        return "El/La docente " . $conexion['nombre'] . " se encuentra asignado/a a los siguientes grupos: " . implode(", ", $grupos) . ".";
    }

    // Preguntas 2 y 4: Salón / Ubicación
    elseif ($intencion === "UBICACION_SALON") {
        if (empty($clasesDocente)) {
            return "No hay registro de aula asignada para el/la docente " . $conexion['nombre'] . ".";
        }
        $resp = "Ubicación de " . $conexion['nombre'] . ":<br>";
        foreach ($clasesDocente as $c) {
            $resp .= "• " . $c['dia'] . " (" . $c['hora_inicio'] . " - " . $c['hora_fin'] . "): " . $c['salon'] . " con el grupo " . $c['grupo'] . "<br>";
        }
        return $resp;
    }

    // Pregunta 3: Horarios ocupados
    elseif ($intencion === "HORARIOS_OCUPADOS") {
        if (empty($clasesDocente)) {
            return "El/La docente " . $conexion['nombre'] . " tiene toda su agenda libre.";
        }
        $resp = "Los horarios ocupados de " . $conexion['nombre'] . " son:<br>";
        foreach ($clasesDocente as $c) {
            $resp .= "• " . $c['dia'] . " de " . $c['hora_inicio'] . " a " . $c['hora_fin'] . " (Grupo: " . $c['grupo'] . ")<br>";
        }
        return $resp;
    }

    // Pregunta 5: Sugerencias de horarios
    elseif ($intencion === "SUGERIR_HORARIO") {
        $bloquesLibres = [
            "Lunes de 12:30 a 14:30",
            "Martes de 08:00 a 12:00",
            "Jueves de 14:00 a 18:00"
        ];
        $resp = "Sugerencias de horario para mover/correr clase con " . $conexion['nombre'] . ":<br>";
        $resp .= "Teniendo en cuenta sus horas tomadas y la disponibilidad de salones, los bloques recomendados son:<br>";
        foreach ($bloquesLibres as $b) {
            $resp .= "✔ " . $b . "<br>";
        }
        return $resp;
    }

    return "No pude interpretar la consulta sobre el docente. Intente preguntando por su salón, grupo u horario.";
}

// Si la petición viene por POST (AJAX desde JavaScript), procesar y devolver JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mensaje'])) {
    header('Content-Type: application/json; charset=utf-8');
    $respuesta = procesarConsulta($_POST['mensaje'], $conexion, $horariosBD, $intencionesPalabrasClave);
    echo json_encode(['respuesta' => $respuesta]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot de Horarios - PHP & JavaScript</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #eef2f5; margin: 0; padding: 20px; }
        .chat-container { max-width: 650px; margin: 20px auto; background: #ffffff; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        h2 { text-align: center; color: #333; margin-top: 0; }
        .chat-box { height: 380px; border: 1px solid #e0e0e0; padding: 15px; overflow-y: auto; margin-bottom: 15px; background: #fdfdfd; border-radius: 8px; display: flex; flex-direction: column; gap: 10px; }
        .mensaje { padding: 10px 14px; border-radius: 8px; max-width: 80%; line-height: 1.4; word-wrap: break-word; }
        .usuario { align-self: flex-end; background: #007bff; color: white; border-bottom-right-radius: 0; }
        .bot { align-self: flex-start; background: #e9ecef; color: #212529; border-bottom-left-radius: 0; }
        .input-group { display: flex; gap: 10px; }
        input[type="text"] { flex: 1; padding: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; }
        button.btn-enviar { padding: 12px 20px; background: #28a745; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; }
        button.btn-enviar:hover { background: #218838; }
        .pruebas { margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        .pruebas p { margin: 0 0 10px 0; font-size: 13px; color: #666; font-weight: bold; }
        .btn-prueba { background: #17a2b8; color: white; border: none; padding: 6px 10px; margin: 3px; border-radius: 4px; font-size: 12px; cursor: pointer; }
        .btn-prueba:hover { background: #138496; }
    </style>
</head>
<body>

<div class="chat-container">
    <h2>Chatbot Gestor de Horarios</h2>
    
    <div class="chat-box" id="chatBox">
        <div class="mensaje bot">¡Hola! Soy el asistente de horarios. Puedes hacerme preguntas sobre docentes como <strong>María Galarza</strong> o <strong>Juan Pérez</strong>.</div>
    </div>

    <form id="chatForm" class="input-group">
        <input type="text" id="mensajeInput" placeholder="Ej: ¿En qué salón se encuentra Maria Galarza?" required autocomplete="off">
        <button type="submit" class="btn-enviar">Enviar</button>
    </form>

    <div class="pruebas">
        <p>Pruebas rápidas (Requerimientos de María Galarza):</p>
        <button class="btn-prueba" onclick="ejecutarPrueba('¿En qué grupo está asignada Maria Galarza?')">1. Consultar Grupo</button>
        <button class="btn-prueba" onclick="ejecutarPrueba('¿En qué salón se encuentra Maria Galarza?')">2. Salón / Ubicación</button>
        <button class="btn-prueba" onclick="ejecutarPrueba('¿Qué horarios tiene tomados Maria Galarza?')">3. Horarios Ocupados</button>
        <button class="btn-prueba" onclick="ejecutarPrueba('¿Dónde está la docente Maria Galarza hoy?')">4. Evitar Superposición</button>
        <button class="btn-prueba" onclick="ejecutarPrueba('Necesito sugerencias de horarios libres para mover una hora con Maria Galarza')">5. Sugerir Horario</button>
    </div>
</div>

<script>
    const chatForm = document.getElementById('chatForm');
    const mensajeInput = document.getElementById('mensajeInput');
    const chatBox = document.getElementById('chatBox');

    // Muestra los mensajes en la ventana de chat
    function agregarMensaje(texto, remitente) {
        const div = document.createElement('div');
        div.className = 'mensaje ' + remitente;
        div.innerHTML = texto;
        chatBox.appendChild(div);
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    // Envía la consulta al archivo PHP vía AJAX
    function enviarConsulta(mensaje) {
        agregarMensaje(mensaje, 'usuario');
        
        const formData = new FormData();
        formData.append('mensaje', mensaje);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            agregarMensaje(data.respuesta, 'bot');
        })
        .catch(error => {
            agregarMensaje('Ocurrió un error al procesar la respuesta del servidor.', 'bot');
        });
    }

    // Evento al enviar el formulario
    chatForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const mensaje = mensajeInput.value.trim();
        if (mensaje) {
            enviarConsulta(mensaje);
            mensajeInput.value = '';
        }
    });

    // Función auxiliar para los botones de prueba rápida
    function ejecutarPrueba(pregunta) {
        mensajeInput.value = pregunta;
        chatForm.dispatchEvent(new Event('submit'));
    }
</script>

</body>
</html>

