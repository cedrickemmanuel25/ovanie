<div class="oa-stats">
    @foreach((array) config('ovanie_pro.access_stats', []) as $stat)
        <div class="oa-stats__item">
            @proicon($stat['icon'], 'oa-ico')
            <span><strong>{{ $stat['value'] }}</strong><small>{{ $stat['label'] }}</small></span>
        </div>
    @endforeach
</div>
