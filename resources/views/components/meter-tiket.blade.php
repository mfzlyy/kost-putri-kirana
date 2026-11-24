{{-- Meter tiket (docs/design/DESIGN.md, bagian Status): 4 ruas 14x5 px, terisi Primary. Dekoratif; selalu dipasang bersama teks status. --}}
@props([
    'status',
])

@php
    $terisi = match ($status) {
        \App\Enums\StatusTiket::Diajukan => 1,
        \App\Enums\StatusTiket::Ditugaskan => 2,
        \App\Enums\StatusTiket::Dikerjakan => 3,
        \App\Enums\StatusTiket::Selesai => 4,
    };
@endphp

<span {{ $attributes->class(['inline-flex shrink-0 gap-[3px]']) }} aria-hidden="true" data-test="meter-tiket" data-terisi="{{ $terisi }}">
    @for ($ruas = 1; $ruas <= 4; $ruas++)
        <i @class(['block h-[5px] w-3.5 rounded-sm', 'bg-primary' => $ruas <= $terisi, 'bg-border' => $ruas > $terisi])></i>
    @endfor
</span>
