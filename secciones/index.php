<?php include('../templates/cabecera.php'); ?>



<!DOCTYPE html>
<html lang="es" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Horarios</title>

    <style>

        .contenedor-botones {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin: 220px 0;
        }

        .boton-cuadrado {
            width: 400px;
            height: 270px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            background-color: #d6d6d6;
            color: black;
            text-decoration: none;
            font-size: 30px;
            font-weight: bold;
            border-radius: 10px;
            transition: 0.3s;
            padding: 10px;
            box-sizing: border-box;
            border: 2px solid #8f8f8f;
        }

        .boton-cuadrado:hover {
            background-color: #a1a1a1;
            transform: scale(1.05);
        }

        img {
            display: block;
            margin: 0 auto;
            width: 200px;
            height: 200px;
            margin-top: 50px;
        }
    </style>
</head>

<body class="bg-light">
 
    <img src="Logo.png" alt="Logo Empresa" >

<!-- Botones -->
    <div class="contenedor-botones">

        <a href="vista_creacion-horarios.html" class="boton-cuadrado">
            Creación de Horarios
        </a>
        <a href="vista_horarios-creados.html" class="boton-cuadrado">
            Horarios Creados
        </a>
    </div>

</body>
</html>

<?php include('../templates/pie.php'); ?> 