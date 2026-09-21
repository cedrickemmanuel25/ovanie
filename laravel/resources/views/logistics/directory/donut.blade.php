@php($total=max(1,array_sum(array_column($segments,1))))
@php($offset=0)
@php($gradient=[])
@foreach($segments as $seg)@php($end=$offset+$seg[1]/$total*100)@php($gradient[]='var(--'.$seg[2].') '.$offset.'% '.$end.'%')@php($offset=$end)@endforeach
<div class="directory-donut"><div style="background:conic-gradient({{ implode(',',$gradient) }})"><span>{{ array_sum(array_column($segments,1)) }}<small>{{ $unit }}</small></span></div><ul>
@foreach($segments as [$label,$count,$color])<li><span><i class="directory-dot" style="background:var(--{{ $color }})"></i>{{ $label }}</span><strong>{{ $count }} <small>({{ round($count/$total*100) }} %)</small></strong></li>
@endforeach
</ul></div>
