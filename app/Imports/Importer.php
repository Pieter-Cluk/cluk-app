<?php

namespace App\Imports;

use Illuminate\Support\Str;

/**
 * Shared parts of the spreadsheet imports: finding the columns by their
 * names (English, Dutch, HubSpot and HireHop exports), and the report.
 */
abstract class Importer
{
    /** @var array{created: int, updated: int, skipped: int, messages: list<array{row: int, level: string, text: string}>} */
    public array $report = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'messages' => []];

    /** @return array<string, list<string>> field => accepted column names */
    abstract protected function columns(): array;

    /** @param array<string, string|null> $row */
    abstract protected function importRow(array $row, int $line): void;

    /** Sheet names this import recognises in a workbook. */
    abstract public static function sheetNames(): array;

    /**
     * @param  list<list<string|null>>  $rows  first row = column names
     */
    public function run(array $rows): array
    {
        $header = array_shift($rows) ?? [];
        $map = $this->mapHeader($header);

        if (! isset($map[$this->requiredField()])) {
            $this->message(1, 'error', __('import.errors.missing_column', ['column' => $this->columns()[$this->requiredField()][0]]));

            return $this->report;
        }

        foreach ($rows as $i => $values) {
            $row = [];
            foreach ($map as $field => $index) {
                $row[$field] = $values[$index] ?? null;
            }
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue; // empty line
            }
            if ($this->isExample($row)) {
                $this->report['skipped']++;
                $this->message($i + 2, 'warning', __('import.warnings.example'));

                continue;
            }
            $this->importRow($row, $i + 2);
        }

        return $this->report;
    }

    abstract protected function requiredField(): string;

    /** @param list<string|null> $header */
    protected function mapHeader(array $header): array
    {
        $map = [];
        foreach ($this->columns() as $field => $names) {
            $wanted = array_map([self::class, 'key'], $names);
            foreach ($header as $i => $name) {
                if ($name !== null && in_array(self::key($name), $wanted, true)) {
                    $map[$field] = $i;
                    break;
                }
            }
        }

        return $map;
    }

    /** The template's example rows use the reserved ".example" domain. */
    protected function isExample(array $row): bool
    {
        foreach (['email', 'website'] as $field) {
            if (isset($row[$field]) && preg_match('/\.example$/i', trim((string) $row[$field]))) {
                return true;
            }
        }

        return false;
    }

    public static function key(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($name)));
    }

    protected function message(int $line, string $level, string $text): void
    {
        $this->report['messages'][] = ['row' => $line, 'level' => $level, 'text' => $text];
    }

    protected static function yes(?string $value): bool
    {
        return in_array(self::key((string) $value), ['yes', 'y', 'ja', 'j', 'oui', 'si', '1', 'true', 'waar', 'x'], true);
    }

    protected static function language(?string $value): ?string
    {
        $v = self::key((string) $value);
        $map = ['nl' => 'nl', 'dutch' => 'nl', 'nederlands' => 'nl', 'vlaams' => 'nl', 'en' => 'en', 'english' => 'en', 'engels' => 'en',
            'fr' => 'fr', 'french' => 'fr', 'frans' => 'fr', 'francais' => 'fr', 'es' => 'es', 'spanish' => 'es', 'spaans' => 'es', 'espanol' => 'es'];

        return $map[$v] ?? null;
    }

    /** "Belgium", "België", "Belgique", "be" → "BE". */
    protected static function country(?string $value): ?string
    {
        $v = self::key((string) $value);
        if ($v === '') {
            return null;
        }
        $names = [
            'BE' => ['be', 'belgium', 'belgie', 'belgique', 'belgien', 'belgica'],
            'NL' => ['nl', 'netherlands', 'thenetherlands', 'nederland', 'paysbas', 'holland', 'niederlande'],
            'FR' => ['fr', 'france', 'frankrijk', 'frankreich', 'francia'],
            'DE' => ['de', 'germany', 'duitsland', 'allemagne', 'deutschland', 'alemania'],
            'LU' => ['lu', 'luxembourg', 'luxemburg', 'luxemburgo'],
            'ES' => ['es', 'spain', 'spanje', 'espagne', 'espana', 'spanien'],
            'GB' => ['gb', 'uk', 'unitedkingdom', 'greatbritain', 'england', 'verenigdkoninkrijk', 'royaumeuni', 'engeland'],
            'IT' => ['it', 'italy', 'italie', 'italia', 'italien'],
            'PT' => ['pt', 'portugal'],
            'AT' => ['at', 'austria', 'oostenrijk', 'autriche', 'osterreich'],
            'CH' => ['ch', 'switzerland', 'zwitserland', 'suisse', 'schweiz', 'suiza'],
            'DK' => ['dk', 'denmark', 'denemarken', 'danemark', 'danmark'],
            'SE' => ['se', 'sweden', 'zweden', 'suede', 'sverige'],
            'IE' => ['ie', 'ireland', 'ierland', 'irlande'],
            'PL' => ['pl', 'poland', 'polen', 'pologne', 'polska'],
            'US' => ['us', 'usa', 'unitedstates', 'unitedstatesofamerica', 'verenigdestaten', 'etatsunis'],
        ];
        foreach ($names as $code => $list) {
            if (in_array($v, $list, true)) {
                return $code;
            }
        }

        return strlen($v) === 2 ? strtoupper($v) : null;
    }
}
