<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Reads the values of an .xlsx workbook (every sheet) or a .csv file into
 * plain rows, without extra packages. Formulas give their last calculated
 * value, as saved by Excel.
 */
class SheetReader
{
    /**
     * @return array<string, list<list<string|null>>> sheet name => rows
     */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx' => self::xlsx($path),
            'csv', 'txt' => ['CSV' => self::csv($path)],
            default => throw new RuntimeException('Unsupported file type'),
        };
    }

    /** @return list<list<string|null>> */
    public static function csv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : (substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',');

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($v) => $v === null || trim($v) === '' ? null : trim($v), $row);
        }
        fclose($handle);

        return $rows;
    }

    /** @return array<string, list<list<string|null>>> */
    public static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Not a readable .xlsx file');
        }

        try {
            $shared = [];
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                foreach (self::xml($xml)->si as $si) {
                    $shared[] = self::text($si);
                }
            }

            $rels = [];
            $relsXml = self::xml((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
            foreach ($relsXml->Relationship as $rel) {
                $target = ltrim((string) $rel['Target'], '/');
                $rels[(string) $rel['Id']] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }

            $workbook = self::xml((string) $zip->getFromName('xl/workbook.xml'));
            $sheets = [];
            foreach ($workbook->sheets->sheet as $sheet) {
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $file = $rels[$rid] ?? null;
                if ($file === null || ($sheetXml = $zip->getFromName($file)) === false) {
                    continue;
                }
                $sheets[(string) $sheet['name']] = self::sheetRows(self::xml($sheetXml), $shared);
            }

            return $sheets;
        } finally {
            $zip->close();
        }
    }

    /** @param list<string> $shared */
    private static function sheetRows(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $col = self::column((string) $cell['r']);
                $type = (string) $cell['t'];
                $value = match ($type) {
                    's' => $shared[(int) $cell->v] ?? null,
                    'inlineStr' => self::text($cell->is),
                    'b' => ((string) $cell->v) === '1' ? 'TRUE' : 'FALSE',
                    default => isset($cell->v) ? (string) $cell->v : null,
                };
                $value = $value === null || trim($value) === '' ? null : trim($value);
                // Whole numbers saved as 30.0 or 1.0E+3 read back as "30" / "1000".
                if ($value !== null && $type === '' && is_numeric($value) && (float) $value == (int) (float) $value && abs((float) $value) < 1e15) {
                    $value = (string) (int) (float) $value;
                }
                $values[$col] = $value;
            }
            if ($values === []) {
                $rows[] = [];

                continue;
            }
            $line = array_fill(0, max(array_keys($values)) + 1, null);
            foreach ($values as $i => $v) {
                $line[$i] = $v;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /** "C7" → 2 */
    private static function column(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }

    private static function text(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $out = '';
        foreach ($node->r as $run) {
            $out .= (string) $run->t;
        }

        return $out;
    }

    private static function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        if ($xml === false) {
            throw new RuntimeException('Broken spreadsheet file');
        }

        return $xml;
    }
}
