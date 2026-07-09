import * as React from 'react';
import { Suspense, lazy } from 'react';
import { createRoot, type Root } from 'react-dom/client';

// Lazy-load the heavy Spline hero so the initial bundle stays small and
// Suspense can show a fallback while the scene code is downloading.
const SplineSceneBasic = lazy(() =>
    import('@/components/ui/spline-demo').then((module) => ({
        default: module.SplineSceneBasic,
    })),
);

/**
 * Render the original SSR hero markup as a fallback. This preserves the
 * real glossy black robot image and card text while the Spline scene is
 * downloading, and keeps the hero visible if Spline fails to load.
 */
function HeroFallback({ ssrHtml }: { ssrHtml: string }) {
    // eslint-disable-next-line react/no-danger
    return <div dangerouslySetInnerHTML={{ __html: ssrHtml }} />;
}

/**
 * Error boundary that catches any runtime error inside the Spline React tree.
 * It resets automatically when its children are replaced (via a new mount
 * key), so a fresh page activation can recover from a previous error state.
 */
class SplineErrorBoundary extends React.Component<
    { children: React.ReactNode; fallback: React.ReactNode },
    { hasError: boolean }
> {
    constructor(props: { children: React.ReactNode; fallback: React.ReactNode }) {
        super(props);
        this.state = { hasError: false };
    }

    static getDerivedStateFromError() {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: React.ErrorInfo) {
        // eslint-disable-next-line no-console
        console.error('[SplineErrorBoundary] Caught error:', error, info);
    }

    componentDidUpdate(prevProps: { children: React.ReactNode }) {
        // A fresh mount attempt supplies new React elements; reset the error
        // state so the boundary does not permanently hide the hero.
        if (this.state.hasError && prevProps.children !== this.props.children) {
            // eslint-disable-next-line no-console
            console.log('[SplineErrorBoundary] Resetting error state for new mount attempt');
            this.setState({ hasError: false });
        }
    }

    render() {
        if (this.state.hasError) {
            return this.props.fallback;
        }
        return this.props.children;
    }
}

// Keep track of the current React root so we can cleanly unmount and recreate
// it when the page is restored from bfcache or reused by an SPA router.
let currentRoot: Root | null = null;
let currentMount: HTMLElement | null = null;
let mountAttempt = 0;

function mountSplineApp() {
    const mountNode = document.getElementById('spline-mount');
    if (!mountNode) {
        console.warn('[SplineHero] Mount node #spline-mount not found');
        return;
    }

    // Capture the SSR hero markup before React replaces it. Reusing this HTML
    // as the Suspense/error fallback keeps the real robot image and text
    // visible if Spline is slow to load or fails entirely.
    const ssrHeroHtml = mountNode.innerHTML;

    // If we already have a root on a node, unmount it first. This prevents
    // duplicate React roots, leaking WebGL contexts, and stale React state
    // (including the error boundary) when the page is restored from bfcache.
    if (currentRoot && currentMount) {
        try {
            currentRoot.unmount();
        } catch (e) {
            // ignore
        }
        currentRoot = null;
        currentMount = null;
    }

    // Remove any stale canvas or React portal nodes left by a previous mount
    // or by a DOM swap from an SPA-style router.
    while (mountNode.firstChild) {
        mountNode.removeChild(mountNode.firstChild);
    }

    mountAttempt += 1;
    const attempt = mountAttempt;

    const root = createRoot(mountNode);
    currentRoot = root;
    currentMount = mountNode;

    root.render(
        <SplineErrorBoundary key={`boundary-${attempt}`} fallback={<HeroFallback ssrHtml={ssrHeroHtml} />}>
            <Suspense fallback={<HeroFallback ssrHtml={ssrHeroHtml} />}>
                <SplineSceneBasic key={`scene-${attempt}`} />
            </Suspense>
        </SplineErrorBoundary>,
    );
}

function runMount() {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mountSplineApp);
    } else {
        mountSplineApp();
    }
}

runMount();

// Re-mount when the page is restored from the browser's back-forward cache,
// because the JS/React state may be stale and the WebGL context may be lost.
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        // eslint-disable-next-line no-console
        console.log('[SplineHero] Page restored from bfcache; re-mounting hero');
        mountSplineApp();
    }
});

// Re-mount for SPA-style routers (Turbo, Livewire, custom History API) when
// they swap the DOM without a full page reload. If none are present, the
// events simply never fire.
window.addEventListener('turbo:load', () => mountSplineApp());
window.addEventListener('livewire:navigated', () => mountSplineApp());
window.addEventListener('navigate', () => mountSplineApp());
