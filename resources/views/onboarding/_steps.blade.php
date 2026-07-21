<div class="brand-top"><a href="/">TrinetPay</a></div>
<div class="steps">
    <div class="step {{ $current >= 1 ? ($current > 1 ? 'done' : 'active') : 'upcoming' }}">
        <div class="step-num">{!! $current > 1 ? '<i class="fa-solid fa-check"></i>' : '1' !!}</div>
        <span class="step-label">Router</span>
    </div>
    <div class="step-line {{ $current > 1 ? 'done' : '' }}"></div>
    <div class="step {{ $current >= 2 ? ($current > 2 ? 'done' : 'active') : 'upcoming' }}">
        <div class="step-num">{!! $current > 2 ? '<i class="fa-solid fa-check"></i>' : '2' !!}</div>
        <span class="step-label">Packages</span>
    </div>
    <div class="step-line {{ $current > 2 ? 'done' : '' }}"></div>
    <div class="step {{ $current >= 3 ? 'active' : 'upcoming' }}">
        <div class="step-num">3</div>
        <span class="step-label">Payment</span>
    </div>
</div>
