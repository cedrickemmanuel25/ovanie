@props(['rows'])
<div class="directory-bars">@foreach($rows as $row)<div><span>{{ $row[0] }}</span><i><b style="width:{{ min(100,max(0,$row[1]/max(1,$row[3]??100)*100)) }}%;background:var(--{{ $row[2] }})"></b></i><strong>{{ $row[1] }}</strong></div>@endforeach</div>
