<?php
/**
 * Motor de Evaluacion Automatizada para Laboratorio #10 (PHP)
 * Genera resultado_autograde.json local y resumen para GitHub Actions Summary
 */

// Desactivar salida de advertencias secundarias para mantener JSON limpio
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$notaFinal = 100;
$desglose = [
    'ejercicio1' => 30,
    'ejercicio2' => 30,
    'ejercicio3' => 25,
    'git'        => 15
];

$banderasPlagio = [];
$nivelPlagio = "NINGUNA";

// 1. EVALUACION DE GIT Y COMMITS
$gitBranch = getenv('GITHUB_HEAD_REF') ?: getenv('GITHUB_REF_NAME');
if (!$gitBranch) {
    $gitBranch = trim(shell_exec("git rev-parse --abbrev-ref HEAD") ?: "local-branch");
}

$commitLog = shell_exec("git log --pretty=format:'%h|%an|%at|%s' -n 20");
$commits = array_filter(explode("\n", trim($commitLog)));

$autores = [];
$timestamps = [];
$hasEJ1 = false; $hasEJ2 = false; $hasEJ3 = false;

foreach ($commits as $c) {
    $parts = explode("|", $c);
    if (count($parts) >= 4) {
        $autores[$parts[1]] = true;
        $timestamps[] = (int)$parts[2];
        if (str_contains($parts[3], '[EJ1]')) $hasEJ1 = true;
        if (str_contains($parts[3], '[EJ2]')) $hasEJ2 = true;
        if (str_contains($parts[3], '[EJ3]')) $hasEJ3 = true;
    }
}

// Penalizacion por historial Git
if (!$hasEJ1 || !$hasEJ2 || !$hasEJ3) {
    $desglose['git'] -= 5;
    $banderasPlagio[] = "Faltan prefijos estandard [EJ1], [EJ2] o [EJ3] en mensajes de commit.";
}

if (count($timestamps) >= 2) {
    $tiempoTotalMinutos = (max($timestamps) - min($timestamps)) / 60;
    if ($tiempoTotalMinutos < 10) {
        $banderasPlagio[] = "Commits realizados en un lapso excesivamente corto (" . round($tiempoTotalMinutos, 1) . " min).";
        $nivelPlagio = "MEDIA";
    }
}

if (count($autores) > 2) {
    $banderasPlagio[] = "Se detectaron mas de 2 autores distintos en el historial de commits.";
    $nivelPlagio = "ALTA";
}

// 2. VERIFICACION DE MODIFICACION DEL AUTOGRADE O WORKFLOW
if (file_exists('.github/workflows/autograde.yml')) {
    $wfContent = file_get_contents('.github/workflows/autograde.yml');
    if (!str_contains($wfContent, 'SarmientoEdu/laboratorio10-php')) {
        $banderasPlagio[] = "El archivo de workflow .github/workflows/autograde.yml fue modificado.";
        $nivelPlagio = "ALTA";
    }
}

// 3. PRUEBA DE SEMILLAS (15 Iteraciones Dinamicas)
$semillas = ['001', '123', '250', '333', '412', '555', '600', '711', '800', '892', '905', '999', '104', '302', '777'];
$ej1Errores = 0;
$ej2Errores = 0;
$ej3Errores = 0;

require_once 'includes/seguridad.php';
require_once 'includes/CalculadoraFinanciera.php';

// Probar Guard de Seguridad
$cmdGuard = shell_exec("php -r \"require_once 'includes/CalculadoraFinanciera.php';\"");
if (!str_contains($cmdGuard, "Acceso directo no permitido")) {
    $ej1Errores += 10;
    $banderasPlagio[] = "El guard de seguridad en CalculadoraFinanciera.php no bloqueo el acceso directo.";
}

foreach ($semillas as $sem) {
    $monto = 1000 + ((int)$sem * 10);
    $tasa = 5 + (((int)$sem % 10));
    $plazo = 12 + (((int)$sem % 24));

    // Evaluar Ejercicio 1 (POO)
    try {
        $calc = new CalculadoraFinanciera($monto, $tasa, $plazo);
        $cuotaEsperada = round($monto * ((($tasa/100)/12) * pow(1+(($tasa/100)/12), $plazo)) / (pow(1+(($tasa/100)/12), $plazo) - 1), 2);
        
        if (abs($calc->calcularCuotaMensual() - $cuotaEsperada) > 0.05) {
            $ej1Errores++;
        }

        // Probar Sobrecarga __call
        $interesSobrecargado = $calc->calcularInteresTotal(12);
        if ($interesSobrecargado <= 0) {
            $ej1Errores++;
        }
    } catch (Throwable $e) {
        $ej1Errores++;
    }

    // Evaluar Ejercicio 3 via CLI Simulado GET
    $urlGET = "ejercicio3.php?cedula_semilla={$sem}&monto={$monto}&tasa={$tasa}&plazo={$plazo}";
    $cmdGET = shell_exec("php -r \"\$_GET=['cedula_semilla'=>'{$sem}','monto'=>'{$monto}','tasa'=>'{$tasa}','plazo'=>'{$plazo}']; include 'ejercicio3.php';\"");

    $claveEsperada = hash('sha256', $sem);
    $ivEsperado = substr(hash('sha256', 'UTP_IV_' . $sem), 0, 16);
    
    if (!str_contains($cmdGET, 'id="cifrado-resultado"')) {
        $ej3Errores++;
    }
}

// Calcular notas parciales
if ($ej1Errores > 0) $desglose['ejercicio1'] = max(0, 30 - ($ej1Errores * 2));
if ($ej3Errores > 0) $desglose['ejercicio3'] = max(0, 25 - ($ej3Errores * 2));

// Inspeccion Sintactica con token_get_all para Deteccion de Estructuras Prohibidas
$archivosCodigo = ['includes/CalculadoraFinanciera.php', 'ejercicio2.php', 'ejercicio3.php'];
foreach ($archivosCodigo as $f) {
    if (file_exists($f)) {
        $tokens = token_get_all(file_get_contents($f));
        foreach ($tokens as $tok) {
            if (is_array($tok)) {
                $nombreTok = token_name($tok[0]);
                if ($nombreTok === 'T_EVAL') {
                    $banderasPlagio[] = "Uso de 'eval()' detectado en $f.";
                    $nivelPlagio = "ALTA";
                    $notaFinal -= 30;
                }
            }
        }
    }
}

// Nota Total
$notaFinal = array_sum($desglose);

// Compilar JSON Final
$reporte = [
    'estudiante_rama'   => $gitBranch,
    'nota_final'        => max(0, $notaFinal),
    'observacion_plagio'=> $nivelPlagio,
    'banderas'          => $banderasPlagio,
    'desglose'          => $desglose,
    'fecha'             => date('Y-m-d H:i:s')
];

file_put_contents('resultado_autograde.json', json_encode($reporte, JSON_PRETTY_PRINT));

// Salida para GitHub Job Summary
if (getenv('GITHUB_STEP_SUMMARY')) {
    $summary = "## 📊 Resultado Autograde - Laboratorio #10\n";
    $summary .= "**Rama:** `$gitBranch` | **Nota:** `{$notaFinal}/100` | **Plagio:** `{$nivelPlagio}`\n\n";
    $summary .= "| Criterio | Puntaje Obtenido |\n| :--- | :---: |\n";
    foreach ($desglose as $k => $v) {
        $summary .= "| " . ucfirst($k) . " | {$v} pts |\n";
    }
    if (!empty($banderasPlagio)) {
        $summary .= "\n### ⚠️ Banderas de Observación:\n";
        foreach ($banderasPlagio as $b) {
            $summary .= "- $b\n";
        }
    }
    file_put_contents(getenv('GITHUB_STEP_SUMMARY'), $summary, FILE_APPEND);
}

echo "Autograde completado. Nota: $notaFinal / 100\n";
if ($notaFinal < 60) {
    exit(1); // Hace fallar la ejecucion en Actions si la nota es reprobatoria
}
