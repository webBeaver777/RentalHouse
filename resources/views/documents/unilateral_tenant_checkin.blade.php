@extends('documents.layout')

@section('title')
{{ $template_type->polishTitle() }}
@endsection

@section('content')
<div class="header">
    <h1>{{ $template_type->polishTitle() }}</h1>
    <div class="subtitle">{{ $property->full_address }}</div>
</div>

{{-- Informacja o trybie jednostronnym --}}
<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin-bottom: 15px;">
    <strong>Uwaga:</strong> {{ $template_type->legalContext() }}
</div>

{{-- Dane podstawowe --}}
<div class="section">
    <div class="section-title">Dane podstawowe</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Typ dokumentu</div>
            <div class="info-value">Dokumentacja jednostronna najemcy (check-in)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tryb prawny</div>
            <div class="info-value">Jednostronny - sporządzony przez najemcę</div>
        </div>
        <div class="info-row">
            <div class="info-label">Adres nieruchomości</div>
            <div class="info-value">{{ $property->full_address }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data sporządzenia</div>
            <div class="info-value">{{ $protocol->created_at->format('d.m.Y') }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data wprowadzenia</div>
            <div class="info-value">{{ $protocol->scheduled_at?->format('d.m.Y') ?? $protocol->completed_at?->format('d.m.Y') ?? '-' }}</div>
        </div>
    </div>
</div>

{{-- Strony --}}
<div class="section">
    <div class="section-title">Strony</div>
    <table>
        <thead>
            <tr>
                <th>Rola</th>
                <th>Dane</th>
                <th>Status powiadomienia</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }} @if($participant->is_initiator)(autor dokumentacji)@endif</td>
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

{{-- Pomieszczenia i elementy (identyczne jak w bilateral) --}}
@if($rooms->count() > 0)
<div class="section">
    <div class="section-title">Dokumentacja stanu pomieszczeń</div>
    @foreach($rooms as $room)
    <table>
        <thead>
            <tr>
                <th colspan="4" style="background: #e9e9e9;">{{ $room->display_name }}</th>
            </tr>
            <tr>
                <th style="width: 30%;">Element</th>
                <th style="width: 20%;">Stan stwierdzony</th>
                <th style="width: 10%;">Ilość</th>
                <th style="width: 40%;">Uwagi / Dowody fotograficzne</th>
            </tr>
        </thead>
        <tbody>
            @forelse($room->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td>{{ $item->condition_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>
                    @if($item->notes){{ $item->notes }}<br>@endif
                    @if($item->photos && $item->photos->count() > 0)
                        Zdjęcia: {{ $item->photos->count() }} szt.
                        <div class="photo-hash">
                            @foreach($item->photos->take(2) as $photo)
                            SHA: {{ substr($photo->hash, 0, 12) }}...
                            @endforeach
                        </div>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4" style="text-align: center; color: #999;">Brak elementów</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @endforeach
</div>
@endif

{{-- Liczniki --}}
@if($meters->count() > 0)
<div class="section">
    <div class="section-title">Odczyty liczników</div>
    <table>
        <thead><tr><th>Typ</th><th>Numer</th><th>Odczyt</th><th>Jednostka</th></tr></thead>
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

{{-- Usterki --}}
@if($defects->count() > 0)
<div class="section">
    <div class="section-title">Stwierdzone usterki i uszkodzenia</div>
    <table>
        <thead><tr><th>Opis</th><th>Lokalizacja</th><th>Dowody</th></tr></thead>
        <tbody>
            @foreach($defects as $defect)
            <tr>
                <td>{{ $defect->description }}</td>
                <td>{{ $defect->location ?? '-' }}</td>
                <td>
                    @if($defect->photos && $defect->photos->count() > 0)
                    Zdjęcia: {{ $defect->photos->count() }} szt.
                    @else
                    -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Nota prawna --}}
<div class="legal-notice">
    <strong>Oświadczenie:</strong><br>
    Niniejsza dokumentacja została sporządzona jednostronnie przez najemcę w celu udokumentowania
    stanu lokalu w dniu wprowadzenia się. Wynajmujący został powiadomiony o sporządzeniu dokumentacji.
    Dokumentacja ta może służyć jako dowód stanu początkowego lokalu.
</div>

{{-- Podpis autora --}}
<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">
            Sporządził: {{ $protocol->initiator()?->display_name ?? '-' }}
            <br><span style="font-size: 8pt;">Data: {{ $protocol->completed_at?->format('d.m.Y H:i') ?? now()->format('d.m.Y H:i') }}</span>
        </div>
    </div>
</div>
@endsection
