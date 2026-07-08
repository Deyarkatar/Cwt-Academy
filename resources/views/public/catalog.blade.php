@extends('layouts.app')

@section('title', __('catalog.page_title'))

@section('content')
<section class="pt-32 pb-8 px-6 max-w-(--spacing-container) mx-auto">
    <h1 class="font-(--font-headline) text-3xl md:text-4xl font-bold text-text-primary mb-4">{{ __('catalog.title') }}</h1>
    <p class="text-text-secondary max-w-xl">{{ __('catalog.subtitle') }}</p>
</section>

<section class="py-8 px-6 max-w-(--spacing-container) mx-auto">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($courses ?? [] as $course)
            @include('components.course-card', ['course' => $course])
        @endforeach
        @if(empty($courses ?? []))
            @for($i = 0; $i < 6; $i++)
                @include('components.course-card', ['course' => null])
            @endfor
        @endif
    </div>
</section>
@endsection
