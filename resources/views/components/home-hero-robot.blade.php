{{-- Shared home hero robot visual component --}}
{{-- Always renders the same robot visual in both English and Kurdish --}}
@php($isKurdish = app()->getLocale() === 'ku')
<div class="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] {{ $isKurdish ? 'lg:order-1' : 'lg:order-2' }}">
    {{-- Spline 3D scene (will be replaced by React if it loads successfully) --}}
    <div class="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4">
        {{-- Fallback: Real robot image - always visible as reliable fallback --}}
        <img 
            src="{{ asset('images/cwt-academy-robot.jpg') }}" 
            alt="Cwt Academy Robot" 
            class="w-full h-full object-contain rounded-xl"
            loading="eager"
            decoding="async"
        />
    </div>
</div>
