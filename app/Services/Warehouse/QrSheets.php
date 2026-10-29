<?php

namespace App\Services\Warehouse;

use App\Http\Controllers\Warehouse\LabelController;
use App\Models\Warehouse\Position;
use App\Models\Warehouse\Zone;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;

/**
 * A4 sheets with big QR codes to hang on the pallet trucks and reach trucks, and at the packing zone:
 * the tasks (open a phone screen, or switch what the scanner does) and the main zones, all on one A4.
 * The codes are short upper-case links (fewer, bigger squares) and readable from about 1.5 m.
 */
class QrSheets
{
    public const SHEETS = ['truck', 'packing'];

    /** Tasks a QR code can start: open a screen, or set what the next scans do. */
    public const TASKS = ['home', 'scan', 'delivery', 'afrit6', 'add', 'remove', 'done'];

    /** Per sheet: the tasks and zone types first, then (truck) the most used pallet places. */
    private const CONTENT = [
        'truck' => ['tasks' => ['scan', 'delivery', 'done'], 'zones' => ['INBOUND', 'DISPATCH', 'A6-24H', 'UNKNOWN'], 'places' => 0, 'paper' => 'A3'],
        'packing' => ['tasks' => ['scan', 'add', 'remove', 'done', 'afrit6'], 'zones' => ['DISPATCH'], 'places' => 0, 'paper' => 'A4'],
    ];

    /** @return list<array{code: string, title: string, hint: string, url: string}> */
    public static function cells(string $sheet): array
    {
        $c = self::CONTENT[$sheet];
        $base = strtoupper(rtrim((string) config('app.url'), '/'));
        $cells = [];
        foreach ($c['tasks'] as $t) {
            $cells[] = ['code' => strtoupper($t), 'title' => __('warehouse.sheets.tasks.'.$t), 'hint' => __('warehouse.sheets.task_hints.'.$t), 'url' => $base.'/W/DO/'.strtoupper($t)];
        }
        foreach (Zone::whereIn('code', $c['zones'])->where('active', true)->get()->sortBy(fn ($z) => array_search($z->code, $c['zones'], true)) as $z) {
            $cells[] = ['code' => $z->code, 'title' => $z->label(), 'hint' => __('warehouse.sheets.zone_hint'), 'url' => $base.'/L/'.$z->code];
        }
        foreach (self::mostUsed($c['places']) as $p) {
            $cells[] = ['code' => $p->code, 'title' => $p->code, 'hint' => LabelController::positionName($p), 'url' => $base.'/L/'.$p->code];
        }

        return $cells;
    }

    /** The pallet places used most in the last 6 months (moves to or from them); filled up with the first pallet places. */
    public static function mostUsed(int $n)
    {
        if ($n === 0) {
            return collect();
        }
        $since = now()->subMonths(6);
        $pallets = Position::where('active', true)->where('kind', 'pallet')->get()->keyBy('code');
        $counts = DB::table('warehouse_moves')->where('created_at', '>=', $since)->whereIn('to_code', $pallets->keys())->selectRaw('to_code as code, count(*) as n')->groupBy('to_code')->pluck('n', 'code')->all();
        foreach (DB::table('warehouse_moves')->where('created_at', '>=', $since)->whereIn('from_code', $pallets->keys())->selectRaw('from_code as code, count(*) as n')->groupBy('from_code')->pluck('n', 'code') as $code => $k) {
            $counts[$code] = ($counts[$code] ?? 0) + $k;
        }
        arsort($counts);
        $top = collect(array_keys($counts))->take($n)->map(fn ($c) => $pallets[$c]);

        return $top->concat($pallets->sortKeys()->reject(fn ($p) => $top->contains('code', $p->code))->take($n - $top->count()))->values();
    }

    public static function paper(string $sheet): string
    {
        return self::CONTENT[$sheet]['paper'];
    }

    public static function png(string $data): string
    {
        return (new QRCode(new QROptions([
            'outputInterface' => QRGdImagePNG::class, 'eccLevel' => EccLevel::M, 'scale' => 20, 'quietzoneSize' => 1,
            'outputBase64' => true, 'imageTransparent' => false,
        ])))->render($data);
    }

    public static function pdf(string $sheet): string
    {
        $cells = array_map(fn ($c) => $c + ['png' => self::png($c['url'])], self::cells($sheet));
        // Everything on one page (A3 for the trucks: bigger codes, readable from about 1.5 m; A4 for the packing zone).
        // 2 columns (3 when there are more than 6 codes); the rows share the height.
        $paper = self::paper($sheet);
        [$w, $h] = $paper === 'A3' ? [277, 372] : [188, 250];
        $cols = count($cells) > 6 ? 3 : 2;
        $rows = max(1, (int) ceil(count($cells) / $cols));
        $rowMm = (int) floor($h / $rows);
        $html = view('warehouse.sheet-pdf', ['sheet' => $sheet, 'pages' => [$cells], 'cols' => $cols, 'rowMm' => $rowMm, 'paper' => $paper,
            'qrMm' => min((int) floor($w / $cols) - 6, $rowMm - 27, 100), 'compact' => $paper === 'A4' && ($cols > 2 || $rows > 2),
            'logo' => is_file($f = public_path('images/cluk-logo.png')) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($f)) : null])->render();
        $o = new Options;
        $o->set('isRemoteEnabled', false);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->setChroot(public_path());
        $o->set('tempDir', storage_path('framework/cache'));
        $o->set('fontCache', storage_path('framework/cache'));
        $pdf = new Dompdf($o);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper($paper);
        $pdf->render();

        return (string) $pdf->output();
    }
}
