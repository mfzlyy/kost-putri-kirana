@props([
    'status',
])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-status-ok teks-badan-kuat']) }}>
        {{ $status }}
    </div>
@endif
