<?php
// Incluimos la conexión a la Base de Datos subiendo un nivel, 
// según la estructura de tu proyecto (estamos en secciones/ y BD.php está en configuraciones/)
require_once __DIR__ . '/../configuraciones/BD.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contenido = $_POST['contenido'] ?? '';
    $dia = $_POST['dia'] ?? '';
    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';
    $curso = $_POST['curso'] ?? '';

    // Como en una misma celda el usuario escribirá la Materia y el Docente (Ej: "Historia - Pedro")
    // vamos a separarlos asumiendo que usan un guión "-".
    $partes = explode('-', $contenido);
    $nombreMateria = trim($partes[0] ?? $contenido);
    $nombreDocente = trim($partes[1] ?? '');

    try {
        // 1. Buscar el ID de la Materia basándonos en el nombre escrito
        $stmtMateria = $conexion->prepare("SELECT IDmateria FROM Materia WHERE Nombre LIKE :nombre LIMIT 1");
        $stmtMateria->execute([':nombre' => '%' . $nombreMateria . '%']);
        $idMateria = $stmtMateria->fetchColumn();

        // 2. Buscar el ID del Docente basándonos en el nombre escrito
        $idDocente = null;
        if (!empty($nombreDocente)) {
            $stmtDocente = $conexion->prepare("SELECT IDdocente FROM Docente WHERE Nombre LIKE :nombre LIMIT 1");
            $stmtDocente->execute([':nombre' => '%' . $nombreDocente . '%']);
            $idDocente = $stmtDocente->fetchColumn();
        }

        // 3. Guardar en la tabla Clases_Asignadas
        $sql = "INSERT INTO Clases_Asignadas (Curso, Dia, Hora_inicio, Hora_fin, IDmateria, IDdocente) 
                VALUES (:curso, :dia, :hora_inicio, :hora_fin, :id_materia, :id_docente)";
        
        $insert = $conexion->prepare($sql);
        $insert->execute([
            ':curso' => $curso,
            ':dia' => $dia,
            ':hora_inicio' => $hora_inicio,
            ':hora_fin' => $hora_fin,
            ':id_materia' => $idMateria ?: null,
            ':id_docente' => $idDocente ?: null
        ]);
        */

        echo "Datos recibidos: Materia encontrada ($idMateria), Docente encontrado ($idDocente). Listo para guardar.";
        
    } catch (PDOException $e) {
        echo "Error al guardar en BD: " . $e->getMessage();
    }
} else {
    echo "Método no permitido";
}
?>