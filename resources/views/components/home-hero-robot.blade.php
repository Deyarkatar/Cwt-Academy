{{-- Shared home hero robot visual component --}}
{{-- Always renders the same robot visual in both English and Kurdish --}}
@php($isKurdish = app()->getLocale() === 'ku')
<div class="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] {{ $isKurdish ? 'lg:order-1' : 'lg:order-2' }}">
    {{-- Spline 3D scene (will be replaced by React if it loads successfully) --}}
    <div class="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4">
        {{-- Fallback: Real robot image - always visible as reliable fallback --}}
        <img 
            src="{{ asset('images/hero-robot.svg') }}" 
            alt="Cwt Academy robot" 
            class="w-full h-full object-contain drop-shadow-[0_0_40px_rgba(255,215,0,0.15)]"
            loading="eager"
            decoding="async"
        />
    </div>
</div>
