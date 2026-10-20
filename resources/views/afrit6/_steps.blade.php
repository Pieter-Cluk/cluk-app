<div class="steps" aria-label="{{ __('afrit6.register.step', ['n' => $step]) }}">
    @for ($i = 1; $i <= 3; $i++)<span @class(['bar', 'bar-on' => $i <= $step])></span>@endfor
</div>
<div class="login-kicker">{{ mb_strtoupper(__('afrit6.register.step', ['n' => $step])) }}</div>
