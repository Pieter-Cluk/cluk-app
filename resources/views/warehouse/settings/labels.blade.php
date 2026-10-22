<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ trans_choice('warehouse.settings.labels_title', $labels->count(), ['n' => $labels->count()]) }}</title>
<style>
    /* Dymo LabelWriter 550 · 2112290 labels, 59 × 102 mm, printed landscape: one label per page. */
    @page { size: 102mm 59mm; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e9ece8; }
    .bar { padding: 14px 16px; display: flex; gap: 12px; align-items: center; font-size: 14px; }
    .bar button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 6px; background: #52ae32; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 16px; }
    .label { width: 102mm; height: 59mm; background: #fff; display: flex; align-items: center; gap: 4mm; padding: 3mm 4mm 3mm 3mm; overflow: hidden; break-after: page; page-break-after: always; }
    .label svg { width: 52mm; height: 52mm; flex: none; display: block; }
    .txt { display: flex; flex-direction: column; gap: 2mm; min-width: 0; }
    .txt b { font-size: 11mm; line-height: 1; letter-spacing: .3mm; white-space: nowrap; }
    .txt b.m { font-size: 8mm; } .txt b.s { font-size: 5.5mm; }
    .txt span { font-size: 4mm; line-height: 1.25; }
    .txt small { font-size: 2.8mm; color: #444; letter-spacing: .2mm; }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { display: block; padding: 0; } .label { margin: 0; } }
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ __('equipment.print') }}</button><span>{{ __('warehouse.settings.labels_hint', ['n' => $labels->count()]) }}</span></div>
<div class="sheet">
    @foreach ($labels as $l)
        <div class="label">{!! \App\Support\QrSvg::make(route('w.loc', $l['code']), true) !!}<div class="txt"><b @class(['m' => mb_strlen($l['code']) > 5 && mb_strlen($l['code']) <= 7, 's' => mb_strlen($l['code']) > 7])>{{ $l['code'] }}</b><span>{{ $l['name'] }}</span><small>CLUK · {{ __('warehouse.settings.scan_to_move') }}</small></div></div>
    @endforeach
</div>
</body>
</html>
