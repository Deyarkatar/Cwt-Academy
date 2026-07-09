<div class="stitch-card flex flex-col group" data-testid="course-card">
    <div class="h-48 relative overflow-hidden">
        @if($course && $course->image)
            <img src="{{ $course->image }}" alt="{{ $course->title }}" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
        @else
            <div class="w-full h-full bg-bg-elevated flex items-center justify-center text-text-muted text-5xl font-bold opacity-20">
                {{ $course ? strtoupper(substr($course->title, 0, 2)) : 'CW' }}
            </div>
        @endif
        <div class="absolute inset-0 bg-black/30 mix-blend-multiply"></div>
        <div class="absolute top-3 left-3">
            <span class="bg-bg-elevated/80 backdrop-blur text-text-primary text-xs font-semibold px-2 py-1 rounded">
                {{ $course?->category?->name ?? '-' }}
            </span>
        </div>
    </div>
    <div class="p-6 flex flex-col flex-grow">
        <div class="flex justify-between items-start mb-3">
            <span class="bg-gold-400/10 text-gold-400 text-xs font-semibold px-2 py-1 rounded border border-gold-400/20">
                {{ __('catalog.telegram_delivery') }}
            </span>
            <span class="text-xs text-text-muted">{{ $course?->language?->value ?? '-' }}</span>
        </div>
        <h3 class="card-title font-semibold text-text-primary text-lg mb-2 line-clamp-2">{{ $course?->title ?? '-' }}</h3>
        <p class="card-description text-sm text-text-secondary mb-4 line-clamp-2 flex-grow">{{ $course?->short_description ?? '' }}</p>
        <div class="flex items-center justify-between mt-auto pt-4 border-t border-border-default">
            <span class="text-xl font-bold text-text-primary">{{ number_format($course?->price_iqd ?? 0) }} <span class="text-sm font-normal text-text-muted">IQD</span></span>
            <a href="/courses/{{ $course?->slug ?? '#' }}" class="text-sm font-medium text-gold-400 hover:text-gold-500 transition-colors">
                {{ __('catalog.view_course') }} &rarr;
            </a>
        </div>
    </div>
</div>
