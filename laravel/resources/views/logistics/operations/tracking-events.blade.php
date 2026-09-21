<div class="ops-tracking-events">
@forelse($trackingEvents as $event)<div><i class="{{ ($event['type']??'')==='alert'?'is-red':'' }}"></i><time>{{ \Carbon\Carbon::parse($event['at'])->format('H:i') }}</time><span>{{ $event['label'] }}</span></div>
@empty<p class="ops-empty">Aucune activité enregistrée.</p>
@endforelse
</div>
