@props(['capabilities'])

<section class="phase-one-capability-map" aria-labelledby="phase-one-capability-title">
    <div class="agent-card-heading compact">
        <div>
            <p class="eyebrow">Phase 1 Autopilot Capability Map</p>
            <h2 id="phase-one-capability-title">Observe -> Predict -> Decide -> Approve -> Act -> Verify -> Audit</h2>
        </div>
    </div>

    <div class="phase-one-capability-grid">
        @foreach ($capabilities as $capability)
            <article class="phase-one-capability status-{{ $capability['status'] }}">
                <div>
                    <span>{{ $loop->iteration }}</span>
                    <strong>{{ $capability['label'] }}</strong>
                </div>
                <em>{{ str_replace('-', ' ', $capability['status']) }}</em>
                <h3>{{ $capability['summary'] }}</h3>
                <p>{{ $capability['detail'] }}</p>
                @if ($capability['human'])
                    <b>Human-in-the-loop</b>
                @endif
                <a href="{{ $capability['url'] }}">View evidence</a>
            </article>
        @endforeach
    </div>
</section>
