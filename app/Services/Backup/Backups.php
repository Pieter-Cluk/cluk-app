<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backups of the database and the uploaded files, gzip-compressed, in a folder
 * outside the website. Kept: the last 2 database backups plus the last one of
 * each of the last 7 days; the last 2 file archives.
 *
 * The database is written as plain SQL, one statement per block, each ending
 * with the marker line below, so a restore never splits inside the data.
 */
class Backups
{
    public const MARK = "\n-- @@cluk-end@@\n";

    public const KEEP_RECENT = 2;

    public const KEEP_DAYS = 7;

    public const KEEP_FILES = 2;

    public static function dir(): string
    {
        $dir = (string) config('cluk.backup_path');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Cannot create the backup folder '.$dir);
        }

        return rtrim($dir, '/');
    }

    /** @return array{db: string, files: ?string, tables: int, rows: int} */
    public static function run(bool $withFiles = true, ?Carbon $at = null): array
    {
        $at ??= now();
        $stamp = $at->format('Ymd-Hi');
        $db = self::dir().'/cluk-db-'.$stamp.'.sql.gz';
        [$tables, $rows] = self::dumpDatabase($db);
        $files = $withFiles ? self::archiveFiles(self::dir().'/cluk-files-'.$stamp.'.tar.gz') : null;
        self::prune();

        return ['db' => $db, 'files' => $files, 'tables' => $tables, 'rows' => $rows];
    }

    /** @return array{0: int, 1: int} tables, rows */
    public static function dumpDatabase(string $path): array
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        $q = fn (string $n) => $driver === 'sqlite' ? '"'.str_replace('"', '""', $n).'"' : '`'.str_replace('`', '``', $n).'`';
        $tmp = $path.'.part';
        $gz = gzopen($tmp, 'wb6');
        if (! $gz) {
            throw new RuntimeException('Cannot write '.$tmp);
        }
        $write = fn (string $sql) => gzwrite($gz, rtrim($sql, ";\n ").';'.self::MARK);
        gzwrite($gz, '-- CLUK app database backup · '.now()->toIso8601String().' · '.$driver."\n");
        if ($driver !== 'sqlite') {
            $write('SET FOREIGN_KEY_CHECKS=0');
            $write('SET NAMES utf8mb4');
        }
        $tables = collect(Schema::getTables())->pluck('name')->reject(fn ($t) => str_starts_with($t, 'sqlite_'))->values();
        $rows = 0;
        foreach ($tables as $table) {
            $create = $driver === 'sqlite'
                ? (string) DB::selectOne("select sql from sqlite_master where type = 'table' and name = ?", [$table])->sql
                : (string) array_values((array) DB::selectOne('SHOW CREATE TABLE '.$q($table)))[1];
            $write('DROP TABLE IF EXISTS '.$q($table));
            $write($create);
            $columns = null;
            $batch = [];
            $stmt = $pdo->query('SELECT * FROM '.$q($table));
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns ??= '('.implode(', ', array_map($q, array_keys($row))).')';
                $batch[] = '('.implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)).')';
                $rows++;
                if (count($batch) >= 200) {
                    $write('INSERT INTO '.$q($table).' '.$columns.' VALUES '.implode(",\n", $batch));
                    $batch = [];
                }
            }
            if ($batch) {
                $write('INSERT INTO '.$q($table).' '.$columns.' VALUES '.implode(",\n", $batch));
            }
        }
        if ($driver !== 'sqlite') {
            $write('SET FOREIGN_KEY_CHECKS=1');
        }
        gzwrite($gz, "-- end of backup\n");
        gzclose($gz);
        rename($tmp, $path);
        @chmod($path, 0600);

        return [$tables->count(), $rows];
    }

    /** Everything uploaded (storage/app), packed and gzipped with the system's tar. */
    public static function archiveFiles(string $path): string
    {
        $tmp = $path.'.part';
        $p = new Process(['tar', '-czf', $tmp, '--exclude=./imports', '--exclude=./private/imports', '--exclude=./backups', '--exclude=.gitignore', '-C', storage_path('app'), '.']);
        $p->setTimeout(900)->run();
        if (! $p->isSuccessful()) {
            @unlink($tmp);
            throw new RuntimeException('Packing the files failed: '.mb_substr($p->getErrorOutput(), 0, 300));
        }
        rename($tmp, $path);
        @chmod($path, 0600);

        return $path;
    }

    /** Keep the last 2 database backups plus the last of each of the last 7 days; the last 2 file archives. */
    public static function prune(): array
    {
        $removed = [];
        $db = self::list('db');
        $keep = array_slice($db, 0, self::KEEP_RECENT);
        $byDay = [];
        foreach ($db as $f) {
            $day = substr(basename($f), 8, 8);
            $byDay[$day] ??= $f;
        }
        $keep = array_merge($keep, array_slice(array_values($byDay), 0, self::KEEP_DAYS));
        foreach ($db as $f) {
            if (! in_array($f, $keep, true)) {
                unlink($f);
                $removed[] = basename($f);
            }
        }
        foreach (array_slice(self::list('files'), self::KEEP_FILES) as $f) {
            unlink($f);
            $removed[] = basename($f);
        }

        return $removed;
    }

    /** Newest first. @return list<string> */
    public static function list(string $kind): array
    {
        $files = glob(self::dir().'/cluk-'.$kind.'-*.'.($kind === 'db' ? 'sql.gz' : 'tar.gz')) ?: [];
        rsort($files);

        return $files;
    }

    /** Read a database backup to the end without changing anything: [tables, insert statements, complete?]. */
    public static function check(string $path): array
    {
        $gz = gzopen($path, 'rb');
        $tables = $inserts = 0;
        $complete = false;
        $buffer = '';
        while (! gzeof($gz)) {
            $buffer .= gzread($gz, 1 << 20);
            while (($pos = strpos($buffer, self::MARK)) !== false) {
                $sql = ltrim(preg_replace('/^--[^\n]*\n/m', '', substr($buffer, 0, $pos)));
                $buffer = substr($buffer, $pos + strlen(self::MARK));
                $tables += (int) str_starts_with($sql, 'CREATE TABLE');
                $inserts += (int) str_starts_with($sql, 'INSERT INTO');
            }
        }
        gzclose($gz);
        $complete = str_contains($buffer, '-- end of backup');

        return [$tables, $inserts, $complete];
    }

    /** Put a database backup back: every table is replaced by the one in the backup. */
    public static function restoreDatabase(string $path): int
    {
        $gz = gzopen($path, 'rb');
        if (! $gz) {
            throw new RuntimeException('Cannot read '.$path);
        }
        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
        }
        $buffer = '';
        $n = 0;
        while (! gzeof($gz)) {
            $buffer .= gzread($gz, 1 << 20);
            while (($pos = strpos($buffer, self::MARK)) !== false) {
                $sql = trim(preg_replace('/^--[^\n]*\n/m', '', substr($buffer, 0, $pos)));
                $buffer = substr($buffer, $pos + strlen(self::MARK));
                if ($sql !== '' && $sql !== ';') {
                    $pdo->exec($sql);
                    $n++;
                }
            }
        }
        gzclose($gz);
        if (DB::connection()->getDriverName() === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $n;
    }

    /** Put the uploaded files back (existing files with the same name are overwritten). */
    public static function restoreFiles(string $path): int
    {
        $p = new Process(['tar', '-xzf', $path, '-C', storage_path('app')]);
        $p->setTimeout(900)->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('Unpacking failed: '.mb_substr($p->getErrorOutput(), 0, 300));
        }
        $list = new Process(['tar', '-tzf', $path]);
        $list->run();

        return count(array_filter(explode("\n", $list->getOutput()), fn ($l) => $l !== '' && ! str_ends_with($l, '/')));
    }
}
