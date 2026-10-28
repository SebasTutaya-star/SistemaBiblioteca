<?php
/**
 * Lectura y escritura de archivos Excel (.xlsx) sin librerías externas.
 * Requiere la extensión ZipArchive de PHP (habilitada por defecto en cPanel).
 * También acepta CSV para la importación.
 */

/**
 * Lee la primera hoja de un .xlsx o un .csv y devuelve una matriz de filas (arrays de texto).
 * @throws RuntimeException con un mensaje comprensible para el usuario.
 */
function leer_hoja(string $ruta, string $nombreOriginal): array
{
    $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($ext === 'csv') return leer_csv($ruta);
    if ($ext !== 'xlsx') throw new RuntimeException('Formato no admitido. Use un archivo Excel .xlsx o .csv (el formato antiguo .xls no se admite: ábralo en Excel y use «Guardar como» .xlsx).');
    if (!class_exists('ZipArchive')) throw new RuntimeException('El servidor no tiene habilitada la extensión ZIP de PHP. Guarde el archivo como CSV o pida activarla en cPanel > Seleccionar versión de PHP.');

    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) throw new RuntimeException('No se pudo abrir el archivo. Verifique que sea un Excel válido.');

    // Textos compartidos
    $compartidos = [];
    if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
        $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
        foreach ($sx->si as $si) {
            if (isset($si->t)) { $compartidos[] = (string)$si->t; continue; }
            $t = '';
            foreach ($si->r as $r) $t .= (string)$r->t;
            $compartidos[] = $t;
        }
    }

    // Ubicar la primera hoja según workbook.xml
    $hoja = 'xl/worksheets/sheet1.xml';
    $wb = $zip->getFromName('xl/workbook.xml');
    $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($wb && $rels) {
        $wbx = simplexml_load_string($wb, 'SimpleXMLElement', LIBXML_NONET);
        $wbx->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $primera = $wbx->xpath('//m:sheets/m:sheet')[0] ?? null;
        if ($primera) {
            $rid = (string)$primera->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $rx = simplexml_load_string($rels, 'SimpleXMLElement', LIBXML_NONET);
            foreach ($rx->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $t = ltrim((string)$rel['Target'], '/');
                    $hoja = str_starts_with($t, 'xl/') ? $t : 'xl/' . $t;
                }
            }
        }
    }
    $xml = $zip->getFromName($hoja);
    $zip->close();
    if ($xml === false) throw new RuntimeException('El archivo no contiene hojas de cálculo legibles.');

    $sx = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NONET);
    $filas = [];
    foreach ($sx->sheetData->row as $row) {
        $fila = [];
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = columna_indice(preg_replace('/\d+/', '', $ref));
            $tipo = (string)$c['t'];
            if ($tipo === 's') $v = $compartidos[(int)$c->v] ?? '';
            elseif ($tipo === 'inlineStr') $v = (string)($c->is->t ?? '');
            elseif ($tipo === 'b') $v = ((string)$c->v) === '1' ? 'VERDADERO' : 'FALSO';
            else {
                $v = (string)$c->v;
                if (is_numeric($v) && str_contains(strtolower($v), 'e')) $v = number_format((float)$v, 0, '', '');
                elseif (is_numeric($v) && (float)$v == (int)$v) $v = (string)(int)$v;
            }
            $fila[$col] = trim($v);
        }
        if ($fila) {
            $max = max(array_keys($fila));
            $completa = [];
            for ($i = 0; $i <= $max; $i++) $completa[] = $fila[$i] ?? '';
            $filas[(int)$row['r']] = $completa;
        }
    }
    return $filas;
}

function columna_indice(string $letras): int
{
    $n = 0;
    foreach (str_split(strtoupper($letras)) as $l) $n = $n * 26 + (ord($l) - 64);
    return $n - 1;
}

function leer_csv(string $ruta): array
{
    $contenido = file_get_contents($ruta);
    if (str_starts_with($contenido, "\xEF\xBB\xBF")) $contenido = substr($contenido, 3);
    if (!mb_check_encoding($contenido, 'UTF-8')) $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
    $primera = strtok($contenido, "\n");
    $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    $filas = [];
    $n = 0;
    $h = fopen('php://memory', 'r+');
    fwrite($h, $contenido);
    rewind($h);
    while (($r = fgetcsv($h, 0, $sep, '"', '')) !== false) {
        $n++;
        if ($r === [null]) continue;
        $filas[$n] = array_map(fn($v) => trim((string)$v), $r);
    }
    fclose($h);
    return $filas;
}

/** Normaliza encabezados: minúsculas, sin tildes ni espacios (Año -> anio). */
function normalizar_encabezado(string $h): string
{
    $h = mb_strtolower(sin_tildes(trim($h)), 'UTF-8');
    $h = str_replace(['ñ', ' ', '/', '.'], ['n', '_', '_', ''], $h);
    $alias = ['ano' => 'anio', 'año' => 'anio', 'cantidad_de_ejemplares' => 'cantidad', 'ejemplares' => 'cantidad',
              'nivel_grado' => 'nivel', 'grado_nivel' => 'nivel', 'tipo_de_material' => 'tipo', 'correo_institucional' => 'correo',
              'tipo_lector' => 'tipo', 'tipo_de_lector' => 'tipo', 'apellido' => 'apellidos', 'nombre' => 'nombres'];
    return $alias[$h] ?? $h;
}

/**
 * Genera un .xlsx con un título, encabezados y filas. Devuelve el contenido binario.
 */
function xlsx_generar(string $titulo, array $columnas, array $filas, string $hoja = 'Reporte'): string
{
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $col = function (int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    };
    $celda = function (string $ref, $v, int $estilo = 0) use ($esc): string {
        $s = $estilo ? " s=\"$estilo\"" : '';
        if (is_int($v) || is_float($v)) return "<c r=\"$ref\"$s><v>$v</v></c>";
        return "<c r=\"$ref\" t=\"inlineStr\"$s><is><t xml:space=\"preserve\">" . $esc($v) . '</t></is></c>';
    };

    $anchos = [];
    foreach ($columnas as $i => $c) $anchos[$i] = max(10, mb_strlen($c) + 2);
    foreach ($filas as $f) foreach (array_values($f) as $i => $v) $anchos[$i] = min(60, max($anchos[$i] ?? 10, mb_strlen((string)$v) + 2));

    // Sin título (plantillas): los encabezados quedan en la fila 1
    $rows = '';
    $r = 1;
    if ($titulo !== '') {
        $rows = '<row r="1">' . $celda('A1', $titulo, 1) . '</row>';
        $rows .= '<row r="2">' . $celda('A2', 'Generado: ' . date('d/m/Y H:i'), 3) . '</row>';
        $r = 4;
    }
    $filaEnc = $r;
    $rows .= "<row r=\"$r\">";
    foreach ($columnas as $i => $c) $rows .= $celda($col($i) . $r, $c, 2);
    $rows .= '</row>';
    foreach ($filas as $f) {
        $r++;
        $rows .= "<row r=\"$r\">";
        foreach (array_values($f) as $i => $v) $rows .= $celda($col($i) . $r, $v);
        $rows .= '</row>';
    }
    $cols = '';
    foreach ($anchos as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
    $ultima = $col(max(0, count($columnas) - 1));

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $filaEnc . '" topLeftCell="A' . ($filaEnc + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . "<cols>$cols</cols><sheetData>$rows</sheetData>"
        . ($filas ? "<autoFilter ref=\"A$filaEnc:$ultima$r\"/>" : '')
        . '</worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><i/><sz val="10"/><color rgb="FF666666"/><name val="Calibri"/></font></fonts>'
        . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF1F4E5F"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
        . '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
    $hoja = $esc(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $hoja), 0, 31));
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . "<sheets><sheet name=\"$hoja\" sheetId=\"1\" r:id=\"rId1\"/></sheets>"
        . ($filas ? "<definedNames><definedName name=\"_xlnm._FilterDatabase\" localSheetId=\"0\" hidden=\"1\">'$hoja'!\$A\$$filaEnc:\$$ultima\$$r</definedName></definedNames>" : '')
        . '</workbook>';

    $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
    $zip = new ZipArchive();
    $zip->open($tmp, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->close();
    $bin = file_get_contents($tmp);
    unlink($tmp);
    return $bin;
}
