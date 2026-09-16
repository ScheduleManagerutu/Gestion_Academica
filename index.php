<?php 
session_start();

// 1. Credenciales fijas
$usuario_correcto = "AdminG";
$clave_correcta   = "UTUG26";

$error = "";

// 2. Procesar cuando el formulario se envía mediante POST
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Capturar lo que envió el formulario usando los nombres del input (name="usuario" y name="password")
    $usuario_ingresado = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $clave_ingresada   = isset($_POST['password']) ? trim($_POST['password']) : '';

    // Validar si coinciden las credenciales
    if ($usuario_ingresado === $usuario_correcto && $clave_ingresada === $clave_correcta) {
        
        // Exito: Guardamos la sesión y redirigimos al sistema de horarios
        $_SESSION['autenticado'] = true;
        header("Location: secciones/index.php");
        exit();

    } else {
        
        // Error: Marcamos la sesión como no autenticada y preparamos el mensaje
        $_SESSION['autenticado'] = false;
        $error = "Usuario o contraseña incorrectos.";

    }
}
?>
<!doctype html>
<html lang="es" data-bs-theme="light">
    <head>
        <title>Inicio de sesión</title>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        <!-- Bootstrap CSS -->
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
            rel="stylesheet"
        />
    </head>

    <body class="bg-light">

        <div class="container mt-5">
            <div class="row justify-content-center">
                
                <div class="col-md-6">
                    <h1 class="h3 text-center mb-1">Gestión de Horarios</h1>
                    <p class="text-muted text-center mb-4">Escuela Técnica Carlos Martín De Vecchi</p>
                
                    <!-- Formulario con action apuntando a sí mismo mediante POST -->
                    <form action="" method="POST"> 

                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h4 class="card-title mb-3">Inicio de sesión</h4>

                                <!-- Alerta dinámica si la contraseña es incorrecta -->
                                <?php if (!empty($error)): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php echo htmlspecialchars($error); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="usuario" class="form-label">Nombre de usuario</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        name="usuario"
                                        id="usuario"
                                        placeholder="Ingrese su usuario"
                                        required
                                    />
                                </div>

                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input
                                        type="password"
                                        class="form-control"
                                        name="password"
                                        id="password"
                                        placeholder="Ingrese su contraseña"
                                        required
                                    />
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-2">Iniciar sesión</button>
                            </div>

                            <div class="card-footer text-body-secondary text-center">
                                Ingrese su usuario y contraseña
                            </div>
                        </div>

                    </form>
                </div>

            </div>
        </div>

        <!-- Bootstrap JS Bundle -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>