@extends('layouts.app')
@section('title', 'Knowledge library')
@section('page-title', 'Knowledge library')
@section('content')
<nav class="flex flex-wrap gap-3 mb-4" aria-label="Knowledge libraries">
@foreach(['crop'=>'Crop knowledge','product'=>'Product knowledge','prices'=>'Price list'] as $key=>$label)
<a class="btn-primary" @if($kind===$key) aria-current="page" @endif href="{{ route('web.knowledge',['kind'=>$key]) }}">{{ $label }}</a>
@endforeach
</nav>
<form method="get" class="card-full p-4 flex flex-wrap items-end gap-4 mb-4">
<input type="hidden" name="kind" value="{{ $kind }}">
@if($kind==='crop')
<label>Crop<select name="crop"><option value="">All crops</option>@foreach($crops as $crop)<option @selected(($filters['crop']??'')===$crop)>{{ $crop }}</option>@endforeach</select></label>
@endif
<label>Product category<select name="category"><option value="">All categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category']??null)==$category->id)>{{ $category->name }}</option>@endforeach</select></label>
<button class="btn-primary">{{ $kind === 'prices' ? 'Filter prices' : 'Find guidance' }}</button>
</form>
@if($kind === 'prices')
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 mb-4">
    @forelse($products as $product)
        <article class="card-full p-4">
            <h2 class="font-bold">{{ $product->name }}</h2>
            <p class="text-sm mb-3">{{ $product->sku }}@if($product->packaging_details) · {{ $product->packaging_details }}@endif</p>
            <dl class="grid grid-cols-2 gap-2 text-sm">
                @foreach(['Farmer' => $product->farmer_unit_price, 'Retailer' => $product->retail_price, 'Franchise' => $product->franchise_price] as $customer => $price)
                    <dt>{{ $customer }}</dt><dd class="text-right">UGX {{ number_format($price, 2) }}</dd>
                @endforeach
            </dl>
            @if($product->price_changed_at)
                <p class="mt-3 text-xs">Price changed <time datetime="{{ substr($product->price_changed_at, 0, 10) }}">{{ substr($product->price_changed_at, 0, 10) }}</time></p>
            @endif
        </article>
    @empty
        <p>No active products match this selection.</p>
    @endforelse
</div>
{{ $products->links() }}
@else
<div class="grid gap-4 md:grid-cols-2">
@forelse($articles as $article)
<details class="card-full p-4"><summary class="font-bold">{{ $article->title }} · {{ $article->category?->name }}</summary>
@if($article->image_url)<img loading="lazy" class="max-h-64 max-w-full my-3" src="{{ $article->image_url }}" alt="{{ $article->title }}">@endif
@if($article->product)<h3>{{ $article->product->name }} · {{ $article->product->sku }}</h3>@if($article->product->image_url)<img loading="lazy" class="max-h-48 max-w-full" src="{{ $article->product->image_url }}" alt="{{ $article->product->name }}">@endif @endif
<dl>@foreach(['active_ingredient'=>'Active ingredient','dosage'=>'Dosage','stage'=>'When to apply','method'=>'Application method','dos'=>'Do','donts'=>'Avoid','phi'=>'Pre-harvest interval','notes'=>'Notes'] as $key=>$label)
@if(filled($article->content[$key]??null))<dt class="font-bold mt-2">{{ $label }}</dt><dd>{{ $article->content[$key] }}</dd>@endif
@endforeach</dl></details>
@empty<p>No published guidance matches this selection.</p>@endforelse</div>
{{ $articles->links() }}
@endif
@endsection
