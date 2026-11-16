@extends('themes.'.$site['theme']['key'].'.layout')

@section('content')
    <div class="container">
        <div class="reader-layout">
            <div>
                <span class="eyebrow">404</span>
                <h1>This page is not in the edition</h1>
                <p class="page-lede">The address may have changed, or the article has not been published yet.</p>
                <p><a class="btn" href="{{ $site['home_url'] }}">Return to the front page</a></p>
            </div>
        </div>
    </div>
@endsection
