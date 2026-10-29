<?php
// includes/CalculadoraFinanciera.php
require_once __DIR__ . '/seguridad.php';

if (!defined('ACCESO_PERMITIDO') || ACCESO_PERMITIDO !== true) {
    header('HTTP/1.1 403 Forbidden');
    die("Acceso directo no permitido.");
}

class CalculadoraFinanciera {
    private float $montoBase;
    private float $tasaAnual;
    private int $plazoMeses;

    public function __construct(float $monto, float $tasa, int $plazo) {
        if ($monto <= 0 || $tasa <= 0 || $plazo <= 0) {
            throw new InvalidArgumentException("Los valores deben ser mayores a cero.");
        }
        $this->montoBase = $monto;
        $this->tasaAnual = $tasa;
        $this->plazoMeses = $plazo;
    }

    public function getMontoBase(): float { return $this->montoBase; }
    public function getTasaAnual(): float { return $this->tasaAnual; }
    public function getPlazoMeses(): int { return $this->plazoMeses; }

    public function calcularCuotaMensual(): float {
        $r = ($this->tasaAnual / 100) / 12;
        $n = $this->plazoMeses;
        if ($r == 0) {
            return $this->montoBase / $n;
        }
        $cuota = $this->montoBase * ($r * pow(1 + $r, $n)) / (pow(1 + $r, $n) - 1);
        return $cuota;
    }

    public function __call($name, $arguments) {
        if ($name === 'calcularInteresTotal') {
            $mesesExtra = isset($arguments[0]) ? (int)$arguments[0] : 0;
            $plazoTotal = $this->plazoMeses + $mesesExtra;
            $cuota = $this->calcularCuotaMensual();
            return ($cuota * $plazoTotal) - $this->montoBase;
        }
        throw new BadMethodCallException("El método '$name' no existe.");
    }
}