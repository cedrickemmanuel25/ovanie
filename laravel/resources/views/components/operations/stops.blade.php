@props(['tour'])
<ol class="ops-stop-list">
    <li>
        <span class="ops-step is-green"><x-operations.icon name="play"/></span>
        <strong>{{ $tour['start']['label'] ?? 'Départ à confirmer' }}</strong>
        <small>{{ $tour['departure'] ?? '—' }}</small>
        <span class="ops-badge is-green">Départ</span>
        <x-operations.icon name="next"/>
    </li>
    @foreach($tour['stops'] as $stop)
        <li>
            <span class="ops-step {{ $stop['status']==='pending'?'is-gray':($stop['status']==='done'?'is-green':'is-blue') }}">{{ $loop->iteration }}</span>
            <strong>{{ $stop['label'] }}</strong>
            <small>{{ $stop['type'] }} · {{ $stop['time'] }}</small>
            <x-operations.badge :status="$stop['status']==='in_progress'?'current':$stop['status']" :label="$stop['status']==='done' ? ($stop['type']==='Collecte'?'Collecté':'Livré').(!empty($stop['completedTime'])?' à '.$stop['completedTime']:'') : null"/>
            <x-operations.icon name="next"/>
        </li>
    @endforeach
</ol>
