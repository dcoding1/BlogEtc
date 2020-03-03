@php
    $posts = \WebDevEtc\BlogEtc\Models\BlogEtcPost::getMostViewed();
@endphp

<div>
    <h2>@lang('blogetc.most_viewed')</h2>
    @foreach($posts as $post)
        <p><a href="{{ $post->url() }}">{{ $post->title }}</a></p>
    @endforeach
</div>
