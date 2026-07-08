@php
    $isHome = request()->is('/');
    $isDashboard = request()->is('dashboard');
    $isHomePage = request()->is('/');
    $isCourses = request()->is('courses', 'courses/*');
    $isContact = request()->is('contact');
    $isProfile = request()->is('profile', 'dashboard/profile');
    $locale = app()->getLocale();
@endphp

<header class="bg-bg-base/80 backdrop-blur-xl fixed top-0 w-full z-50">
    <div class="flex justify-between items-center h-20 px-4 sm:px-6 max-w-(--spacing-container) mx-auto gap-3">
        <a href="/" class="font-(--font-headline) text-base sm:text-xl font-bold text-gold-400 tracking-tight shrink-0">
            Cwt Academy
        </a>

        {{-- Nav links: visible on all screens, scrollable on small screens --}}
        <nav class="flex gap-4 md:gap-6 text-xs md:text-sm font-medium items-center flex-wrap">
            <a href="/" class="no-underline {{ $isHomePage ? 'text-gold-400' : 'text-text-secondary hover:text-gold-400' }} transition-colors duration-200">
                {{ __('nav.home') }}
            </a>

            @auth
                <a href="/dashboard" class="no-underline {{ $isDashboard ? 'text-gold-400' : 'text-text-secondary hover:text-gold-400' }} transition-colors duration-200">
                    {{ __('nav.dashboard') }}
                </a>
            @endauth

            <a href="/courses" class="no-underline {{ $isCourses ? 'text-gold-400' : 'text-text-secondary hover:text-gold-400' }} transition-colors duration-200">
                {{ __('nav.courses') }}
            </a>

            <a href="/contact" class="no-underline {{ $isContact ? 'text-gold-400' : 'text-text-secondary hover:text-gold-400' }} transition-colors duration-200">
                {{ __('nav.contact') }}
            </a>

            @auth
                <a href="/profile" class="no-underline {{ $isProfile ? 'text-gold-400' : 'text-text-secondary hover:text-gold-400' }} transition-colors duration-200">
                    {{ __('nav.profile') }}
                </a>
            @endauth
        </nav>

        {{-- Right actions: language + auth --}}
        <div class="flex items-center gap-2 md:gap-3 shrink-0">
            {{-- Language switcher --}}
            <div class="flex items-center gap-1 rounded-lg bg-bg-elevated/50 border border-border-default/20 p-0.5">
                <a href="{{ route('locale.switch', ['locale' => 'en']) }}"
                   class="no-underline px-2 py-1 text-xs font-medium rounded-md transition-colors {{ $locale === 'en' ? 'bg-gold-400/15 text-gold-400 border border-gold-400/20' : 'text-text-muted hover:text-text-secondary' }}">
                    {{ __('nav.english') }}
                </a>
                <a href="{{ route('locale.switch', ['locale' => 'ku']) }}"
                   class="no-underline px-2 py-1 text-xs font-medium rounded-md transition-colors {{ $locale === 'ku' ? 'bg-gold-400/15 text-gold-400 border border-gold-400/20' : 'text-text-muted hover:text-text-secondary' }}">
                    {{ __('nav.kurdish') }}
                </a>
            </div>

            @guest
                <a href="/login" class="no-underline hidden sm:inline text-xs md:text-sm font-medium text-text-secondary hover:text-gold-400 transition-colors">
                    {{ __('nav.sign_in') }}
                </a>
                <a href="/register" class="btn-primary text-xs md:text-sm font-semibold px-3 md:px-5 py-2 md:py-2.5">
                    {{ __('nav.get_started') }}
                </a>
            @endguest

            @auth
                <form method="POST" action="/logout" class="inline-flex">
                    @csrf
                    <button type="submit" class="btn-primary text-xs md:text-sm font-semibold px-3 md:px-5 py-2 md:py-2.5">
                        {{ __('nav.logout') }}
                    </button>
                </form>
            @endauth
        </div>
    </div>

</header>
