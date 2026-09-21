@extends('layouts.logistics')

@section('title', $title)
@section('crumb', $title)

@section('content')
<div class="p-6">
    <div class="card p-10 text-center max-w-xl mx-auto mt-10">
        <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background:#DCFCE7">
            <svg width="26" height="26" fill="none" stroke="#16a34a" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <h1 class="text-lg font-800 text-slate-900">{{ $title }}</h1>
        <p class="text-sm text-slate-500 mt-2">{{ $description }}</p>
        <p class="text-xs text-slate-400 mt-4">Ce module est en cours de construction.</p>
    </div>
</div>
@endsection
