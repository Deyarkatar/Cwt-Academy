{{-- Shared home hero robot visual component --}}
{{-- Always renders the same real robot stage in both English and Kurdish. --}}
{{-- A static image is always visible; the React Spline app can enhance it when it loads. --}}
@php($isKurdish = app()->getLocale() === 'ku')
<div class="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] {{ $isKurdish ? 'lg:order-1' : 'lg:order-2' }}" data-testid="homepage-hero-robot">
    <div class="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4 flex items-center justify-center">
        <img
            src="{{ asset('images/hero-robot.png') }}"
            alt=""
            data-testid="homepage-hero-robot-image"
            class="w-full h-full object-contain"
            loading="eager"
        />
    </div>
</div>
