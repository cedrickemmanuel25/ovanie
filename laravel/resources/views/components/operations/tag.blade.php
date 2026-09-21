@props(['tone'=>'green'])
<span {{ $attributes->class(['directory-tag','tag-'.$tone]) }}><i></i>{{ $slot }}</span>
