<?php
// ejercicio3.php
require_once 'includes/seguridad.php';
require_once 'includes/CalculadoraFinanciera.php';

$cedula_semilla = htmlspecialchars(trim($_GET['cedula_semilla'] ?? '892'), ENT_QUOTES, 'UTF-8');
$monto = filter_var($_GET['monto'] ?? 1000, FILTER_VALIDATE_FLOAT);
$tasa = filter_var($_GET['tasa'] ?? 10, FILTER_VALIDATE_FLOAT);
$plazo = filter_var($_GET['plazo'] ?? 12, FILTER_VALIDATE_INT);

try {
    $calc = new CalculadoraFinanciera($monto, $tasa, $plazo);
    $cuota = $calc->calcularCuotaMensual();
} catch (Exception $e) {
    die("Error en los parámetros: " . $e->getMessage());
}

// Cifrado simétrico OpenSSL requerido
$key = hash('sha256', $cedula_semilla);
$iv = substr(hash('sha256', 'UTP_IV_' . $cedula_semilla), 0, 16);
$textoPlano = "Monto: $monto | Cuota: " . round($cuota, 2);
$cifradoResultado = openssl_encrypt($textoPlano, 'AES-256-CBC', $key, 0, $iv);

// Generar tabla de amortización con bucle
$tablaAmortizacion = [];
$saldoRestante = $monto;
$r = ($tasa / 100) / 12;

for ($mes = 1; $mes <= $plazo; $mes++) {
    $interesMes = $saldoRestante * $r;
    $principalPagado = $cuota - $interesMes;
    $saldoRestante -= $principalPagado;
    if ($saldoRestante < 0) $saldoRestante = 0;

    $tablaAmortizacion[] = [
        'mes' => $mes,
        'cuota' => round($cuota, 2),
        'interes' => round($interesMes, 2),
        'principal' => round($principalPagado, 2),
        'saldo' => round($saldoRestante, 2)
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ejercicio 3 - Tabla y Cifrado</title>
</head>
<body>
    <h1>Tabla de Amortización y Cifrado OpenSSL</h1>
    
    <p><strong>String Cifrado:</strong></p>
    <div id="cifrado-resultado"><?php echo htmlspecialchars($cifradoResultado); ?></div>

    <h2>Desglose de Amortización</h2>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Mes</th>
                <th>Cuota</th>
                <th>Interés</th>
                <th>Principal</th>
                <th>Saldo Restante</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tablaAmortizacion as $fila): ?>
                <tr>
                    <td><?php echo $fila['mes']; ?></td>
                    <td><?php echo $fila['cuota']; ?></td>
                    <td><?php echo $fila['interes']; ?></td>
                    <td><?php echo $fila['principal']; ?></td>
                    <td><?php echo $fila['saldo']; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>