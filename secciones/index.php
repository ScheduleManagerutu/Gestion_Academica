<?php include('../templates/cabecera.php'); ?>

//Inicio del contenido de la página actual

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Horarios</title>
//Personalización de estilos para los botones
    <style>
        .contenedor-botones {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 270px 0;
        }

        .boton-cuadrado {
            width: 400px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background-color: #283e53;
            color: white;
            text-decoration: none;
            font-size: 40px;
            font-weight: bold;
            border-radius: 10px;
            transition: 0.3s;
            padding: 10px;
            box-sizing: border-box;
        }

        .boton-cuadrado:hover {
            background-color: #34495e;
            transform: scale(1.05);
        }
    </style>
</head>
//Coloc
<body>

    <!-- Botones -->
    <div class="contenedor-botones">
        <a href="#creacion-horarios" class="boton-cuadrado">
            Creación de Horarios
        </a>
        <a href="#horarios-creados" class="boton-cuadrado">
            Horarios Creados
        </a>
    </div>

</body>
</html>

<?php include('../templates/pie.php'); ?> 