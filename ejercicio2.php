<?php
// ejercicio2.php
require_once 'includes/seguridad.php';
require_once 'includes/CalculadoraFinanciera.php';

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$resultadoData = null;
$errorMsg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Error CSRF: Token inválido.");
    }

    $cedula_semilla = htmlspecialchars(trim($_POST['cedula_semilla'] ?? ''), ENT_QUOTES, 'UTF-8');
    $monto = filter_var($_POST['monto'] ?? 0, FILTER_VALIDATE_FLOAT);
    $tasa = filter_var($_POST['tasa'] ?? 0, FILTER_VALIDATE_FLOAT);
    $plazo = filter_var($_POST['plazo'] ?? 0, FILTER_VALIDATE_INT);

    if ($monto < 100 || $tasa < 1 || $plazo < 1) {
        $errorMsg = "Por favor, introduce valores válidos (monto mínimo 100).";
    } else {
        try {
            $calc = new CalculadoraFinanciera($monto, $tasa, $plazo);
            $cuota = $calc->calcularCuotaMensual();
            $interesTotal = $calc->calcularInteresTotal();

            $resultadoData = [
                "cuota" => round($cuota, 2),
                "interes_total" => round($interesTotal, 2),
                "semilla" => $cedula_semilla
            ];
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ejercicio 2 - Formulario Seguro</title>
</head>
<body>
    <h1>Calculadora Financiera - Formulario Seguro</h1>
    <?php if(!empty($errorMsg)) echo "<p style='color:red;'>$errorMsg</p>"; ?>
    
    <form action="ejercicio2.php" method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <label>Últimos dígitos cédula (cedula_semilla):</label><br>
        <input type="text" name="cedula_semilla" required><br><br>

        <label>Monto (Mínimo 100):</label><br>
        <input type="number" step="0.01" name="monto" min="100" required><br><br>

        <label>Tasa Anual (%):</label><br>
        <input type="number" step="0.01" name="tasa" min="1" required><br><br>

        <label>Plazo (Meses):</label><br>
        <input type="number" name="plazo" min="1" required><br><br>

        <button type="submit">Calcular</button>
    </form>

    <?php if ($resultadoData): ?>
        <h2>Resumen del cálculo</h2>
        <p>Cuota Mensual: $<?php echo $resultadoData['cuota']; ?></p>
        <p>Interés Total: $<?php echo $resultadoData['interes_total']; ?></p>

        <script type="application/json" id="resultado-ej2">
            <?php echo json_encode($resultadoData); ?>
        </script>
    <?php endif; ?>
</body>
</html>