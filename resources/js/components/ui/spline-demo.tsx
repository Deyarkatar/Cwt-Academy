import { useRef } from 'react';
import { SplineRuntime } from '@/components/ui/spline-runtime';

function useHeroData() {
    const mount = document.getElementById('spline-mount');
    if (!mount) {
        return {
            title: '',
            highlight: '',
            subtitle: '',
            ctaBrowse: '',
            ctaContact: '',
            ctaBrowseUrl: '/courses',
            ctaContactUrl: '/contact',
            dir: 'ltr',
        };
    }
    return {
        title: mount.dataset.title || '',
        highlight: mount.dataset.highlight || '',
        subtitle: mount.dataset.subtitle || '',
        ctaBrowse: mount.dataset.ctaBrowse || '',
        ctaContact: mount.dataset.ctaContact || '',
        ctaBrowseUrl: mount.dataset.ctaBrowseUrl || '/courses',
        ctaContactUrl: mount.dataset.ctaContactUrl || '/contact',
        dir: mount.dataset.dir || 'ltr',
    };
}

function SplineStage({ className, scene }: { className: string; scene: string }) {
    return <SplineRuntime scene={scene} className={className} />;
}

export function SplineSceneBasic() {
    const data = useHeroData();
    const isRtl = true; // Original design: Kurdish headline on the right, robot on the left.
    const cardRef = useRef<HTMLDivElement>(null);

    const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
        const card = cardRef.current;
        if (!card) return;
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
        card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = ((e.clientY - rect.top - centerY) / centerY) * -6;
        const rotateY = ((e.clientX - rect.left - centerX) / centerX) * 6;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.01, 1.01, 1.01)`;
    };

    const handleMouseLeave = () => {
        const card = cardRef.current;
        if (!card) return;
        card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)';
    };

    return (
        <div
            ref={cardRef}
            dir="ltr"
            onMouseMove={handleMouseMove}
            onMouseLeave={handleMouseLeave}
            className="hero-card group w-full bg-[#0e0e0e] border border-white/10 rounded-[28px] shadow-2xl overflow-hidden relative min-h-[560px] lg:min-h-[680px]"
        >
            {/* Mouse-following white spotlight (desktop hover) */}
            <div
                className="hero-spotlight pointer-events-none absolute inset-0 opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-0"
            />

            {/* Static soft gold backlight on the robot side */}
            <div
                className="pointer-events-none absolute top-1/2 -translate-y-1/2 w-[600px] h-[600px] rounded-full bg-[#FFD700]/[0.08] blur-3xl z-0 left-[-10%]"
            />

            <div className="relative z-10 grid grid-cols-1 lg:grid-cols-[52%_48%] items-center gap-8 lg:gap-12 px-5 py-8 sm:px-8 sm:py-10 lg:p-[clamp(24px,4vw,64px)]">
                {/* Robot column (left) */}
                <div className="hero-robot relative w-full h-[380px] sm:h-[440px] lg:h-[620px] xl:h-[680px] lg:order-1">
                    <div className="hero-robot-stage absolute inset-0 lg:-inset-x-4 lg:-bottom-4">
                        <SplineStage
                            scene="https://prod.spline.design/kZDDjO5HuC9GJUM2/scene.splinecode"
                            className="w-full h-full"
                        />
                    </div>
                </div>

                {/* Text column (right) */}
                <div
                    dir={data.dir}
                    className="flex flex-col justify-center text-center items-center lg:order-2 lg:text-right lg:items-end"
                >
                    <h1
                        className="font-extrabold text-white tracking-tight hero-title-display"
                        data-rtl={isRtl ? 'true' : 'false'}
                    >
                        {data.title}{' '}
                        {data.highlight && (
                            <span className="text-[#FFD700]">{data.highlight}</span>
                        )}
                    </h1>

                    <p
                        className="mt-5 text-base md:text-lg text-[#b7b5b4] max-w-lg leading-relaxed hero-subtitle-display"
                        data-rtl={isRtl ? 'true' : 'false'}
                    >
                        {data.subtitle}
                    </p>

                    <div className="mt-8 flex flex-wrap gap-4 justify-center lg:justify-end">
                        <a
                            href={data.ctaBrowseUrl}
                            className="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl font-semibold text-sm bg-gradient-to-br from-[#FFD700] to-[#FFB800] text-[#3a3000] shadow-[0_0_20px_rgba(255,215,0,0.2)] hover:shadow-[0_0_30px_rgba(255,215,0,0.4)] hover:opacity-95 transition-all"
                        >
                            {data.ctaBrowse}
                        </a>
                        <a
                            href={data.ctaContactUrl}
                            className="inline-flex items-center justify-center gap-2 px-7 py-3 rounded-2xl font-semibold text-sm border border-[#FFD700] text-[#FFD700] hover:bg-[rgba(255,215,0,0.1)] transition-all"
                        >
                            {data.ctaContact}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
