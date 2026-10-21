<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('equipment.labels_title', ['count' => $assets->count()]) }}</title>
<style>
    /* Dymo LabelWriter 550 · 1933083 square labels, 25 × 25 mm: one label per page. */
    @page { size: 25mm 25mm; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e9ece8; }
    .bar { padding: 14px 16px; display: flex; gap: 12px; align-items: center; font-size: 14px; }
    .bar button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 6px; background: #52ae32; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 16px; }
    .label { width: 25mm; height: 25mm; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1mm; overflow: hidden; break-after: page; page-break-after: always; }
    .label svg { width: 19.5mm; height: 19.5mm; display: block; }
    .label b { font-size: 2.6mm; line-height: 1; letter-spacing: .2mm; margin-top: .4mm; }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { display: block; padding: 0; } .label { margin: 0; } }
</style>
</head>
<body>
<div class="bar"><button type="button" onclick="window.print()">{{ __('equipment.print') }}</button><span>{{ __('equipment.labels_hint', ['count' => $assets->count()]) }}</span></div>
<div class="sheet">
    @foreach ($assets as $a)
        <div class="label">{!! \App\Support\QrSvg::make(route('equipment.scan', $a->code)) !!}<b>{{ $a->code }}</b></div>
    @endforeach
</div>
</body>
</html>
