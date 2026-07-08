import { useEffect, useRef, useState } from 'react';
import { Application } from '@splinetool/runtime';

interface SplineRuntimeProps {
    scene: string;
    className?: string;
}

export function SplineRuntime({ scene, className }: SplineRuntimeProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [status, setStatus] = useState<'idle' | 'loading' | 'loaded' | 'error'>('idle');
    const [errorMsg, setErrorMsg] = useState('');

    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        let app: Application | null = null;
        let canvas: HTMLCanvasElement | null = null;
        let cancelled = false;
        let disposed = false;

        const safeSetStatus = (next: typeof status) => {
            if (!cancelled && !disposed) {
                setStatus(next);
            }
        };

        const safeSetError = (msg: string) => {
            if (!cancelled && !disposed) {
                setErrorMsg(msg);
                setStatus('error');
            }
        };

        const init = () => {
            // eslint-disable-next-line no-console
            console.log('[SplineRuntime] Mounting canvas for scene:', scene);

            // Create canvas manually — gives us full control.
            canvas = document.createElement('canvas');
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.display = 'block';
            canvas.style.opacity = '1';
            canvas.style.visibility = 'visible';
            canvas.style.background = 'transparent';
            canvas.addEventListener('webglcontextlost', handleContextLost);
            canvas.addEventListener('webglcontextrestored', handleContextRestored);
            container.appendChild(canvas);

            safeSetStatus('loading');

            try {
                app = new Application(canvas, {
                    renderOnDemand: false, // always animate
                });
            } catch (initErr) {
                const msg = initErr instanceof Error ? initErr.message : String(initErr);
                // eslint-disable-next-line no-console
                console.error('[SplineRuntime] Failed to create Application:', msg);
                safeSetError('WebGL init failed: ' + msg);
                return;
            }

            app
                .load(scene)
                .then(() => {
                    if (cancelled || disposed) return;
                    // eslint-disable-next-line no-console
                    console.log('[SplineRuntime] Scene loaded successfully');
                    safeSetStatus('loaded');
                })
                .catch((err: unknown) => {
                    if (cancelled || disposed) return;
                    const msg = err instanceof Error ? err.message : String(err);
                    // eslint-disable-next-line no-console
                    console.error('[SplineRuntime] Scene load failed:', msg, err);
                    safeSetError('Scene load failed: ' + msg);
                });
        };

        const handleContextLost = (event: Event) => {
            // eslint-disable-next-line no-console
            console.warn('[SplineRuntime] WebGL context lost');
            event.preventDefault();
            safeSetStatus('loading');
        };

        const handleContextRestored = () => {
            // eslint-disable-next-line no-console
            console.log('[SplineRuntime] WebGL context restored');
            // Dispose the old application and rebuild from scratch so the
            // Spline runtime receives a fresh canvas/context.
            if (app) {
                try {
                    app.dispose();
                } catch (e) {
                    // ignore
                }
                app = null;
            }
            if (canvas && container.contains(canvas)) {
                container.removeChild(canvas);
            }
            canvas = null;
            init();
        };

        init();

        return () => {
            cancelled = true;
            disposed = true;

            // eslint-disable-next-line no-console
            console.log('[SplineRuntime] Cleaning up Application');

            if (canvas) {
                canvas.removeEventListener('webglcontextlost', handleContextLost);
                canvas.removeEventListener('webglcontextrestored', handleContextRestored);
            }

            if (app) {
                try {
                    app.dispose();
                } catch (e) {
                    // ignore disposal errors
                }
                app = null;
            }

            if (canvas && container.contains(canvas)) {
                // Explicitly destroy the WebGL context to release GPU resources
                // quickly, especially when the user navigates away and the
                // browser may keep the page in bfcache.
                const gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
                const loseContext = gl?.getExtension('WEBGL_lose_context');
                if (loseContext) {
                    try {
                        loseContext.loseContext();
                    } catch (e) {
                        // ignore
                    }
                }
                container.removeChild(canvas);
            }
            canvas = null;
        };
    }, [scene]);

    return (
        <div ref={containerRef} className={`${className} relative`}>
            <img
                src="/images/hero-robot.svg"
                alt="Cwt Academy robot"
                className="absolute inset-0 w-full h-full object-contain drop-shadow-[0_0_40px_rgba(255,215,0,0.15)]"
                loading="eager"
                decoding="async"
            />
            {status === 'error' && (
                <div className="absolute bottom-4 left-1/2 -translate-x-1/2 bg-[#0e0e0e]/80 text-red-400 text-xs px-3 py-1.5 rounded-full border border-red-400/20">
                    {errorMsg}
                </div>
            )}
        </div>
    );
}
