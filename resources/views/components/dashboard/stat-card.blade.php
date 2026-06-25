@props(['metric'])

<article class="saas-stat-card tone-{{ $metric['tone'] }}">
    <div class="saas-stat-icon"><i data-lucide="{{ $metric['icon'] }}"></i></div>
    <div class="saas-stat-copy">
        <span>{{ $metric['label'] }}</span>
        <strong>{{ number_format($metric['value']) }}</strong>
        <small>{{ $metric['hint'] }}</small>
    </div>
    <i class="saas-stat-arrow" data-lucide="arrow-up-right"></i>
</article>
