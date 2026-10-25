@extends('site.layout', ['active' => 'downloads'])
@section('title', __('site.downloads.title'))
@section('description', __('site.downloads.meta'))
@section('content')
<main class="newsp dlp">
  <header class="newsp-head gut"><h1>{{ __('site.downloads.title') }}</h1><p class="lab">{{ __('site.downloads.intro') }}</p></header>
  <div class="dl-wrap gut">
    <section class="dl-group" id="terms">
      <h2>{{ __('site.downloads.cats.terms') }}</h2>
      <ul class="dl-list">
        @foreach ($terms as $t)
          <li><span class="dl-type">PDF</span><span class="dl-main"><b>{{ __('site.downloads.terms_doc') }}</b><small>{{ __('site.downloads.langs.'.$t['locale']) }} · {{ max(1, (int) round($t['size'] / 1024)) }} KB</small></span>
            <a class="dl-btn" href="{{ $t['url'] }}" target="_blank" rel="noopener">{{ __('site.downloads.open') }}</a></li>
        @endforeach
        @foreach ($groups['terms'] as $d)@include('site._download', ['d' => $d])@endforeach
      </ul>
    </section>
    @foreach ($groups as $cat => $docs)
      @continue($cat === 'terms' || $docs->isEmpty())
      <section class="dl-group" id="{{ $cat }}">
        <h2>{{ __('site.downloads.cats.'.$cat) }}</h2>
        <ul class="dl-list">@foreach ($docs as $d)@include('site._download', ['d' => $d])@endforeach</ul>
      </section>
    @endforeach
  </div>
</main>
@endsection
