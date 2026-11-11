<style>
    .mobile-scroll-indicator{position:fixed;top:20%;right:3px;bottom:20%;z-index:1040;width:4px;overflow:hidden;border-radius:99px;background:rgba(16,35,52,.16);opacity:0;pointer-events:none;transition:opacity .2s ease}
    .mobile-scroll-indicator.is-visible{opacity:1}
    .mobile-scroll-indicator-thumb{position:absolute;top:0;left:0;width:100%;min-height:28px;border:1px solid rgba(8,104,206,.38);border-radius:99px;background:#fff;box-shadow:0 1px 5px rgba(16,35,52,.35);will-change:transform,height}
    @media(min-width:768px){.mobile-scroll-indicator{display:none}}
    @media(prefers-reduced-motion:reduce){.mobile-scroll-indicator{transition:none}}
</style>
<div class="mobile-scroll-indicator" data-mobile-scroll-indicator aria-hidden="true">
    <span class="mobile-scroll-indicator-thumb" data-mobile-scroll-thumb></span>
</div>
<script>
(() => {
    const indicator = document.querySelector('[data-mobile-scroll-indicator]');
    const thumb = indicator?.querySelector('[data-mobile-scroll-thumb]');
    if (!indicator || !thumb) return;

    let frame = 0;
    const updateIndicator = () => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const scrollableHeight = document.documentElement.scrollHeight - window.innerHeight;
            const mobile = window.matchMedia('(max-width: 767.98px)').matches;
            if (!mobile || scrollableHeight <= 1) {
                indicator.classList.remove('is-visible');
                return;
            }

            const trackHeight = indicator.clientHeight;
            const thumbHeight = Math.min(64, Math.max(36, trackHeight * window.innerHeight / document.documentElement.scrollHeight));
            const availableTravel = trackHeight - thumbHeight;
            const progress = Math.min(1, Math.max(0, window.scrollY / scrollableHeight));

            thumb.style.height = `${thumbHeight}px`;
            thumb.style.transform = `translateY(${availableTravel * progress}px)`;
            indicator.classList.add('is-visible');
        });
    };

    window.addEventListener('scroll', updateIndicator, {passive: true});
    window.addEventListener('resize', updateIndicator);
    window.addEventListener('load', updateIndicator);
    new ResizeObserver(updateIndicator).observe(document.documentElement);
    updateIndicator();
})();
</script>
