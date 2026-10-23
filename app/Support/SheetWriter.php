<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Writes a plain .xlsx workbook (one or more sheets) without any package:
 * bold header row, frozen and filterable, sensible column widths. Numbers
 * stay numbers; everything else is text (never a formula, so a cell that
 * starts with "=" can't run anything when the file is opened).
 */
class SheetWriter
{
    /** @var array<string, list<list<mixed>>> */
    private array $sheets = [];

    /** @param list<list<mixed>> $rows first row = column names */
    public function add(string $name, array $rows): self
    {
        $name = mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/', ' ', $name), 0, 31);
        $this->sheets[$name] = $rows;

        return $this;
    }

    public function save(string $path): string
    {
        if (! $this->sheets) {
            throw new RuntimeException('No sheets to write.');
        }
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot write '.$path);
        }
        $n = count($this->sheets);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .implode('', array_map(fn ($i) => '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>', range(1, $n)))
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $sheetsXml = '';
        $rels = '';
        $i = 0;
        foreach (array_keys($this->sheets) as $name) {
            $i++;
            $sheetsXml .= '<sheet name="'.self::esc($name).'" sheetId="'.$i.'" r:id="rId'.$i.'"/>';
            $rels .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $rels .= '<Relationship Id="rId'.($n + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetsXml.'</sheets>'
            .'<definedNames>'.implode('', array_map(fn ($k, $name) => $this->filterName($k, $name), array_keys(array_keys($this->sheets)), array_keys($this->sheets))).'</definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE8F3E6"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'</styleSheet>');
        $i = 0;
        foreach ($this->sheets as $rows) {
            $zip->addFromString('xl/worksheets/sheet'.(++$i).'.xml', $this->sheetXml($rows));
        }
        $zip->close();

        return $path;
    }

    private function filterName(int $index, string $name): string
    {
        $rows = $this->sheets[$name];
        $cols = max(1, count($rows[0] ?? []));

        return '<definedName name="_xlnm._FilterDatabase" localSheetId="'.$index.'" hidden="1">\''.self::esc(str_replace("'", "''", $name)).'\'!$A$1:$'.self::col($cols).'$'.max(1, count($rows)).'</definedName>';
    }

    private function sheetXml(array $rows): string
    {
        $cols = max(1, count($rows[0] ?? []));
        $widths = array_fill(0, $cols, 8);
        foreach (array_slice($rows, 0, 500) as $row) {
            foreach (array_values($row) as $c => $v) {
                if ($c < $cols) {
                    $widths[$c] = max($widths[$c], min(60, mb_strlen((string) $v) + 2));
                }
            }
        }
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.implode('', array_map(fn ($c) => '<col min="'.($c + 1).'" max="'.($c + 1).'" width="'.$widths[$c].'" customWidth="1"/>', array_keys($widths))).'</cols>'
            .'<sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($row) as $c => $v) {
                if ($v === null || $v === '') {
                    continue;
                }
                $ref = self::col($c + 1).($r + 1);
                if ($r > 0 && (is_int($v) || is_float($v))) {
                    $xml .= '<c r="'.$ref.'"><v>'.$v.'</v></c>';
                } else {
                    $v = is_bool($v) ? ($v ? 'Yes' : 'No') : (string) $v;
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.($r === 0 ? ' s="1"' : ' s="2"').'><is><t xml:space="preserve">'.self::esc($v).'</t></is></c>';
                }
            }
            $xml .= '</row>';
        }

        return $xml.'</sheetData><autoFilter ref="A1:'.self::col($cols).max(1, count($rows)).'"/></worksheet>';
    }

    private static function col(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m).$s;
            $n = intdiv($n - 1, 26);
        }

        return $s;
    }

    private static function esc(string $v): string
    {
        // Characters XML 1.0 does not allow are dropped.
        $v = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $v) ?? '';

        return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
