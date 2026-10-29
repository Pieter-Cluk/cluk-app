<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<style>
@page { size: A4 portrait; margin: 10mm 10mm 8mm 10mm; }
body { font-family: 'DejaVu Sans', sans-serif; color: #111; margin: 0; }
.page { page-break-after: always; }
.page:last-child { page-break-after: auto; }
.head { height: 14mm; border-bottom: 0.6mm solid #111; margin-bottom: 3mm; }
.head img { height: 8mm; float: left; margin-top: 2mm; }
.head h1 { font-size: 15pt; margin: 0; padding-top: 2.5mm; text-align: right; }
table.grid { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.grid td { width: {{ round(100 / $cols, 2) }}%; height: {{ $rowMm }}mm; text-align: center; vertical-align: top; padding: 1.5mm 2mm 0 2mm; }
.qr { width: {{ $qrMm }}mm; height: {{ $qrMm }}mm; display: block; margin: 0 auto; }
.title { font-size: {{ $compact ? 14 : 20 }}pt; font-weight: bold; line-height: 1.1; margin-top: 1mm; }
.hint { font-size: {{ $compact ? 7.5 : 10 }}pt; color: #444; margin-top: 0.8mm; }
.code { font-size: {{ $compact ? 6.5 : 8 }}pt; color: #777; letter-spacing: 0.5pt; }
.foot { font-size: 7.5pt; color: #666; text-align: center; margin-top: 1mm; }
</style>
</head>
<body>
@foreach ($pages as $i => $cells)
    <div class="page">
        <div class="head">@if ($logo)<img src="{{ $logo }}" alt="CLUK">@endif<h1>{{ __('warehouse.sheets.titles.'.$sheet) }}@if (count($pages) > 1) · {{ $i + 1 }}/{{ count($pages) }}@endif</h1></div>
        <table class="grid">
            @foreach (array_chunk($cells, $cols) as $row)
                <tr>
                    @foreach ($row as $c)
                        <td><img class="qr" src="{{ $c['png'] }}" alt="{{ $c['code'] }}"><div class="title">{{ $c['title'] }}</div><div class="hint">{{ $c['hint'] }}</div><div class="code">{{ $c['code'] }}</div></td>
                    @endforeach
                    @for ($k = count($row); $k < $cols; $k++)<td></td>@endfor
                </tr>
            @endforeach
        </table>
        <div class="foot">{{ __('warehouse.sheets.foot', ['date' => now()->format('d/m/Y')]) }}</div>
    </div>
@endforeach
</body>
</html>
