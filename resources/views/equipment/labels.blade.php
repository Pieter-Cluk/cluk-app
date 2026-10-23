<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('equipment.labels_title', ['count' => $assets->count() + ($bulk['qty'] ?? 0)]) }}</title>
<style>
    /* Dymo LabelWriter 550 · 1933083 square labels, 25 × 25 mm (paper size "11353 Multi Purpose" in the printer settings): one label per page.
       The QR code, code and serial number sit as one block in the exact middle of the label. */
    @page { size: 25mm 25mm; margin: 0; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #e9ece8; }
    .bar { padding: 14px 16px; display: flex; gap: 12px; align-items: center; font-size: 14px; }
    .bar a { color: #1f2a1c; font-weight: 700; text-decoration: none; padding: 0 6px; }
    .bar button { min-height: 44px; padding: 0 18px; border: 0; border-radius: 6px; background: #52ae32; color: #fff; font-weight: 700; font-size: 14px; cursor: pointer; }
    .sheet { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 16px 16px; }
    .label { width: 25mm; height: 25mm; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 1.5mm; gap: .3mm; overflow: hidden; break-after: page; page-break-after: always; }
    .label svg { width: 17mm; height: 17mm; display: block; flex: none; }
    .label b { font-size: 2.5mm; line-height: 1; letter-spacing: .15mm; white-space: nowrap; }
    .label b.m { font-size: 2mm; letter-spacing: 0; } .label b.s { font-size: 1.6mm; letter-spacing: 0; }
    .label small { font-size: 1.8mm; line-height: 1.1; max-width: 23mm; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
    @media print { body { background: #fff; } .bar { display: none; } .sheet { display: block; padding: 0; } .label { margin: 0; } }
</style>
</head>
<body>
<div class="bar"><a href="{{ url()->previous() }}" onclick="var a = this; if (history.length <= 1) { window.close(); } setTimeout(function () { location.href = a.href; }, 150); return false;">← {{ __('warehouse.labels.back') }}</a><button type="button" onclick="window.print()">{{ __('equipment.print') }}</button><span>{{ __('equipment.labels_hint', ['count' => $assets->count() + ($bulk['qty'] ?? 0)]) }}</span></div>
<div class="sheet">
    @if (! empty($bulk))
        @for ($i = 0; $i < $bulk['qty']; $i++)
            <div class="label">{!! \App\Support\QrSvg::make(route('equipment.item.scan', $bulk['item']->number), true) !!}<b class="s">{{ $bulk['item']->type ?: $bulk['item']->name }}</b><small>{{ $bulk['item']->number }}</small></div>
        @endfor
    @endif
    @foreach ($assets as $a)
        <div class="label">{!! \App\Support\QrSvg::make(route('equipment.scan', $a->code), true) !!}<b @class(['m' => strlen($a->code) > 9 && strlen($a->code) <= 13, 's' => strlen($a->code) > 13])>{{ $a->code }}</b>@if ($a->serial_number)<small>SN {{ $a->serial_number }}</small>@endif</div>
    @endforeach
</div>
</body>
</html>
