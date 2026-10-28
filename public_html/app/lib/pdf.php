<?php
/**
 * Generador de PDF sencillo para reportes en tabla (sin librerías externas).
 * Usa la fuente Helvetica estándar con codificación WinAnsi (admite tildes y ñ).
 */
class PdfTabla
{
    private float $ancho;
    private float $alto;
    private float $margen = 36;
    private array $paginas = [];
    private string $actual = '';
    private float $y = 0;
    private array $cabecera = [];
    private array $anchosCol = [];

    // Anchos de Helvetica (unidades /1000) para los caracteres 32..126
    private const W = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,
        278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,
        667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,
        500,722,500,500,500,334,260,334,584];

    public function __construct(private string $titulo, private string $subtitulo = '', bool $horizontal = true)
    {
        [$this->ancho, $this->alto] = $horizontal ? [841.89, 595.28] : [595.28, 841.89];
    }

    private function cp(string $s): string
    {
        $r = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($r === false) $r = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        return $r;
    }

    public function medir(string $texto, float $tam, bool $negrita = false): float
    {
        $w = 0;
        foreach (str_split($this->cp($texto)) as $ch) {
            $o = ord($ch);
            $w += ($o >= 32 && $o <= 126) ? self::W[$o - 32] : 556;
        }
        return $w * $tam / 1000 * ($negrita ? 1.06 : 1);
    }

    private function esc(string $s): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $this->cp($s));
    }

    private function texto(float $x, float $y, string $t, float $tam = 9, bool $negrita = false, string $color = '0 0 0'): void
    {
        $f = $negrita ? 'F2' : 'F1';
        $this->actual .= "BT $color rg /$f $tam Tf " . round($x, 2) . ' ' . round($this->alto - $y, 2) . ' Td (' . $this->esc($t) . ") Tj ET\n";
    }

    private function rect(float $x, float $y, float $w, float $h, string $color): void
    {
        $this->actual .= "$color rg " . round($x, 2) . ' ' . round($this->alto - $y - $h, 2) . ' ' . round($w, 2) . ' ' . round($h, 2) . " re f\n";
    }

    private function linea(float $x1, float $y1, float $x2, float $y2): void
    {
        $this->actual .= '0.8 0.8 0.8 RG 0.5 w ' . round($x1, 2) . ' ' . round($this->alto - $y1, 2) . ' m ' . round($x2, 2) . ' ' . round($this->alto - $y2, 2) . " l S\n";
    }

    /** Divide un texto en líneas que caben en el ancho indicado. */
    private function partir(string $t, float $w, float $tam, bool $negrita = false): array
    {
        $lineas = [];
        foreach (explode("\n", $t) as $parrafo) {
            $actual = '';
            foreach (preg_split('/\s+/u', $parrafo) as $pal) {
                $prueba = $actual === '' ? $pal : "$actual $pal";
                if ($this->medir($prueba, $tam, $negrita) <= $w) { $actual = $prueba; continue; }
                if ($actual !== '') $lineas[] = $actual;
                while ($this->medir($pal, $tam, $negrita) > $w && mb_strlen($pal) > 1) { // palabra más larga que la celda
                    $n = mb_strlen($pal);
                    while ($n > 1 && $this->medir(mb_substr($pal, 0, $n), $tam, $negrita) > $w) $n--;
                    $lineas[] = mb_substr($pal, 0, $n);
                    $pal = mb_substr($pal, $n);
                }
                $actual = $pal;
            }
            $lineas[] = $actual;
        }
        return $lineas;
    }

    private function nuevaPagina(): void
    {
        if ($this->actual !== '') $this->paginas[] = $this->actual;
        $this->actual = '';
        $m = $this->margen;
        $this->rect(0, 0, $this->ancho, 6, '0.122 0.306 0.373');
        $this->texto($m, $m + 4, 'Biblioteca I.E.P. San Juan · San Juan Bautista, Ayacucho', 8, false, '0.4 0.4 0.4');
        $this->texto($m, $m + 22, $this->titulo, 15, true, '0.122 0.306 0.373');
        $y = $m + 38;
        if ($this->subtitulo) { $this->texto($m, $y, $this->subtitulo, 9, false, '0.3 0.3 0.3'); $y += 14; }
        $this->y = $y + 6;
        if ($this->cabecera) $this->filaCabecera();
    }

    private function filaCabecera(): void
    {
        $m = $this->margen;
        $alto = 0;
        $celdas = [];
        foreach ($this->cabecera as $i => $c) {
            $celdas[$i] = $this->partir($c, $this->anchosCol[$i] - 6, 8, true);
            $alto = max($alto, count($celdas[$i]) * 10 + 6);
        }
        $this->rect($m, $this->y, $this->ancho - 2 * $m, $alto, '0.122 0.306 0.373');
        $x = $m;
        foreach ($celdas as $i => $ls) {
            foreach ($ls as $k => $l) $this->texto($x + 3, $this->y + 11 + $k * 10, $l, 8, true, '1 1 1');
            $x += $this->anchosCol[$i];
        }
        $this->y += $alto;
    }

    /** Agrega una tabla. Los anchos se calculan según el contenido. */
    public function tabla(array $columnas, array $filas): void
    {
        $util = $this->ancho - 2 * $this->margen;
        $pesos = [];
        foreach ($columnas as $i => $c) $pesos[$i] = max(40, min(220, $this->medir($c, 8, true) + 8));
        foreach (array_slice($filas, 0, 200) as $f) {
            foreach (array_values($f) as $i => $v) $pesos[$i] = max($pesos[$i] ?? 40, min(220, $this->medir((string)$v, 8) + 8));
        }
        $suma = array_sum($pesos);
        $this->anchosCol = array_map(fn($p) => $p / $suma * $util, $pesos);
        $this->cabecera = $columnas;
        $this->nuevaPagina();

        if (!$filas) {
            $this->texto($this->margen + 3, $this->y + 14, 'Sin registros para el periodo seleccionado.', 9, false, '0.4 0.4 0.4');
            return;
        }
        foreach ($filas as $n => $f) {
            $celdas = [];
            $alto = 0;
            foreach (array_values($f) as $i => $v) {
                $celdas[$i] = $this->partir((string)$v, $this->anchosCol[$i] - 6, 8);
                $alto = max($alto, count($celdas[$i]) * 10 + 6);
            }
            if ($this->y + $alto > $this->alto - $this->margen - 10) $this->nuevaPagina();
            if ($n % 2 === 1) $this->rect($this->margen, $this->y, $util, $alto, '0.95 0.96 0.96');
            $x = $this->margen;
            foreach ($celdas as $i => $ls) {
                foreach ($ls as $k => $l) $this->texto($x + 3, $this->y + 11 + $k * 10, $l, 8);
                $x += $this->anchosCol[$i];
            }
            $this->y += $alto;
            $this->linea($this->margen, $this->y, $this->ancho - $this->margen, $this->y);
        }
    }

    public function salida(): string
    {
        if ($this->actual !== '') $this->paginas[] = $this->actual;
        if (!$this->paginas) { $this->nuevaPagina(); $this->paginas[] = $this->actual; }
        $total = count($this->paginas);
        $objs = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $kids = [];
        $n = 5;
        foreach ($this->paginas as $i => $contenido) {
            $pie = 'Página ' . ($i + 1) . ' de ' . $total . '  ·  Generado el ' . date('d/m/Y H:i');
            $contenido .= 'BT 0.5 0.5 0.5 rg /F1 7 Tf ' . $this->margen . ' 20 Td (' . $this->esc($pie) . ") Tj ET\n";
            $objs[$n] = '<< /Length ' . strlen($contenido) . " >>\nstream\n" . $contenido . "endstream";
            $objs[$n + 1] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $this->ancho . ' ' . $this->alto . '] '
                          . '/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $n . ' 0 R >>';
            $kids[] = ($n + 1) . ' 0 R';
            $n += 2;
        }
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
        $objs[$n] = '<< /Title (' . $this->esc($this->titulo) . ') /Producer (Sistema Biblioteca San Juan) /CreationDate (D:' . date('YmdHis') . ') >>';
        ksort($objs);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $id => $o) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "$id 0 obj\n$o\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref' . "\n0 " . ($n + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $n; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size " . ($n + 1) . " /Root 1 0 R /Info $n 0 R >>\nstartxref\n$xref\n%%EOF";
        return $pdf;
    }
}
