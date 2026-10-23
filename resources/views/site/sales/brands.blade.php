@extends('site.layout', ['active' => 'sales'])
@section('title', __('site.sales.brands').' · '.__('site.sales.title'))
@section('description', __('site.sales.meta'))
@section('body', 'sl')
@section('content')
@include('site.sales._bar', ['mode' => 'brands'])
<main class="swrap gut">
  <nav class="crumbs" aria-label="breadcrumb"><a href="{{ route('site.sales') }}">{{ __('site.sales.home') }}</a><span>›</span><span>{{ __('site.sales.brands') }}</span></nav>
  <header class="lhead"><h1>{{ __('site.sales.all_brands') }}</h1></header>
  @if ($brands->isEmpty())<p class="muted">{{ __('site.sales.empty') }}</p>@endif
  <div class="brandgrid">
    @foreach ($brands as $b)
      <a class="brandtile big" href="{{ route('site.sales.brand', $b->slug) }}">@if ($b->logo_path)<img src="{{ route('brand.logo', $b) }}" alt="{{ $b->name }}" loading="lazy">@else<span>{{ $b->name }}</span>@endif<small class="num">{{ __('site.sales.products_n', ['n' => $b->n]) }}</small></a>
    @endforeach
  </div>
</main>
@include('site.sales._basket')
@endsection
