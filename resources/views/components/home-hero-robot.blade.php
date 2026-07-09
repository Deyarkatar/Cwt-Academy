{{-- Shared home hero robot visual component --}}
{{-- Always renders the same real robot stage in both English and Kurdish. --}}
{{-- The React Spline app replaces this stage with the glossy black 3D robot. --}}
@php($isKurdish = app()->getLocale() === 'ku')
<div data-testid="homepage-hero-robot" class="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] {{ $isKurdish ? 'lg:order-1' : 'lg:order-2' }}">
    <div class="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4"></div>
</div>
