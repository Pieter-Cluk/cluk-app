{{-- The camera and the code field; a hand scanner types into the field and presses Enter. --}}
<form method="post" action="{{ $action }}" class="ph-scan" autocomplete="off">
    @csrf
    <div class="ph-cam" data-scanner data-jsqr="{{ asset('js/vendor/jsQR.js') }}" @if (! empty($scanned)) data-scanned="{{ $scanned }}" @endif>
        <video playsinline muted></video>
        <div class="ph-cam-frame" aria-hidden="true"></div>
        <button type="button" class="primary ph-cam-start" data-scan-start>▣ {{ __('warehouse.scan.camera') }}</button>
        <div class="ph-cam-tools" data-scan-tools></div>
        <button type="button" class="btn ph-cam-stop" data-scan-stop>{{ __('warehouse.scan.camera_off') }}</button>
        <p class="ph-note" data-scan-note hidden>{{ __('warehouse.scan.no_camera') }}</p>
    </div>
    <div class="ph-code">
        <input name="code" inputmode="text" autocapitalize="characters" placeholder="{{ $placeholder ?? __('warehouse.scan.type_code') }}" aria-label="{{ __('warehouse.scan.type_code') }}" @if (! empty($autofocus)) autofocus @endif>
        <button class="btn" type="submit">OK</button>
    </div>
</form>
