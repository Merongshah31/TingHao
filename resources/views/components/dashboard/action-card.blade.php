@props(['icon', 'eyebrow', 'title', 'description'])

<article class="saas-action-card">
    <div class="saas-action-head">
        <span><i data-lucide="{{ $icon }}"></i></span>
        <i data-lucide="arrow-up-right"></i>
    </div>
    <p>{{ $eyebrow }}</p>
    <h3>{{ $title }}</h3>
    <div class="saas-action-description">{{ $description }}</div>
    <div class="saas-action-links">{{ $slot }}</div>
</article>
