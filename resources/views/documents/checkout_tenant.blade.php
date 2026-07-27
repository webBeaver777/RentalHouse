@extends('documents.layout')

@section('title')
{{ $template_type->polishTitle() }}
@endsection

@section('content')
<div class="header">
    <h1>{{ $template_type->polishTitle() }}</h1>
    <div class="subtitle">{{ $property->full_address }}</div>
</div>

{{-- Informacja --}}
<div style="background: #d4edda; border: 1px solid #28a745; padding: 10px; margin-bottom: 15px;">
    <strong>Dokumentacja sporządzona przez najemcę</strong> w celu udokumentowania stanu lokalu
    w dniu zwrotu oraz rozliczenia kaucji.
</div>

{{-- Dane podstawowe --}}
<div class="section">
    <div class="section-title">Dane podstawowe</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Typ dokumentu</div>
            <div class="info-value">Dokumentacja zwrotu lokalu</div>
        </div>
        <div class="info-row">
            <div class="info-label">Sporządzona przez</div>
            <div class="info-value">Najemcę</div>
        </div>
        <div class="info-row">
            <div class="info-label">Adres</div>
            <div class="info-value">{{ $property->full_address }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data zwrotu</div>
            <div class="info-value">{{ $protocol->completed_at?->format('d.m.Y') ?? now()->format('d.m.Y') }}</div>
        </div>
    </div>
</div>

{{-- Strony --}}
<div class="section">
    <div class="section-title">Strony</div>
    <table>
        <thead><tr><th>Rola</th><th>Dane</th><th>Status</th></tr></thead>
        <tbody>
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }} @if($participant->is_initiator)(autor)@endif</td>
                <td>{{ $participant->display_name }}</td>
                <td>
                    @if($participant->is_initiator)
                        <span class="status-accepted">Sporządził dokumentację</span>
                    @else
                        <span class="status-pending">Powiadomiony</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Stan pomieszczeń --}}
@if($rooms->count() > 0)
<div class="section">
    <div class="section-title">Dokumentacja stanu pomieszczeń</div>
    @foreach($rooms as $room)
    <table>
        <thead>
            <tr><th colspan="3" style="background: #e9e9e9;">{{ $room->display_name }}</th></tr>
            <tr><th>Element</th><th>Stan</th><th>Uwagi / Zdjęcia</th></tr>
        </thead>
        <tbody>
            @forelse($room->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td>{{ $item->condition_name }}</td>
                <td>
                    @if($item->notes){{ $item->notes }}@endif
                    @if($item->photos && $item->photos->count() > 0)
                    <br>Zdjęcia: {{ $item->photos->count() }}
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align: center;">Brak</td></tr>
            @endforelse
        </tbody>
    </table>
    @endforeach
</div>
@endif

{{-- Liczniki --}}
@if($meters->count() > 0)
<div class="section">
    <div class="section-title">Odczyty liczników końcowe</div>
    <table>
        <thead><tr><th>Typ</th><th>Numer</th><th>Odczyt końcowy</th><th>Jednostka</th></tr></thead>
        <tbody>
            @foreach($meters as $meter)
            <tr>
                <td>{{ $meter->type->label() }}</td>
                <td>{{ $meter->serial_number ?? '-' }}</td>
                <td>{{ $meter->reading }}</td>
                <td>{{ $meter->unit }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Klucze --}}
@if($keys->count() > 0)
<div class="section">
    <div class="section-title">Zwrócone klucze</div>
    <table>
        <thead><tr><th>Opis</th><th>Ilość</th></tr></thead>
        <tbody>
            @foreach($keys as $key)
            <tr>
                <td>{{ $key->description }}</td>
                <td>{{ $key->quantity }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Nota prawna --}}
<div class="legal-notice">
    {{ $template_type->legalContext() }}
    <br><br>
    Dokumentacja stanowi potwierdzenie stanu lokalu w dniu zwrotu
    i może służyć jako podstawa do rozliczenia kaucji.
</div>

{{-- Podpis --}}
<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">
            Sporządził (Najemca): {{ $protocol->initiator()?->display_name ?? '-' }}
            <br><span style="font-size: 8pt;">{{ $protocol->completed_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }}</span>
        </div>
    </div>
</div>
@endsection
