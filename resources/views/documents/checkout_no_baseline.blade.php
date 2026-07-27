@extends('documents.layout')

@section('title')
{{ $template_type->polishTitle() }}
@endsection

@section('content')
<div class="header">
    <h1>{{ $template_type->polishTitle() }}</h1>
    <div class="subtitle">{{ $property->full_address }}</div>
</div>

{{-- Ostrzeżenie o braku protokołu wjazdowego --}}
<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin-bottom: 15px;">
    <strong>Uwaga:</strong> Dla niniejszego lokalu nie istnieje protokół zdawczo-odbiorczy przy wprowadzeniu.
    Brak możliwości porównania stanu początkowego i końcowego. Rozliczenie kaucji może być utrudnione.
</div>

{{-- Dane podstawowe --}}
<div class="section">
    <div class="section-title">Dane podstawowe</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Typ dokumentu</div>
            <div class="info-value">Protokół zwrotu lokalu (bez baseline)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tryb prawny</div>
            <div class="info-value">{{ $protocol->legal_mode?->label() ?? 'Jednostronny' }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Adres nieruchomości</div>
            <div class="info-value">{{ $property->full_address }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Data zwrotu</div>
            <div class="info-value">{{ $protocol->act_issued_at?->format('d.m.Y') ?? $protocol->completed_at?->format('d.m.Y') ?? '-' }}</div>
        </div>
        @if($protocol->objection_window_ends_at)
        <div class="info-row">
            <div class="info-label">Okno zastrzeżeń do</div>
            <div class="info-value">{{ $protocol->objection_window_ends_at->format('d.m.Y H:i') }}</div>
        </div>
        @endif
        <div class="info-row">
            <div class="info-label">Protokół wjazdowy</div>
            <div class="info-value" style="color: #dc3545;">Brak w systemie</div>
        </div>
    </div>
</div>

{{-- Strony --}}
<div class="section">
    <div class="section-title">Strony protokołu</div>
    <table>
        <thead>
            <tr><th>Rola</th><th>Dane</th><th>Status</th></tr>
        </thead>
        <tbody>
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }} @if($participant->is_initiator)(sporządzający)@endif</td>
                <td>{{ $participant->display_name }}</td>
                <td>
                    @if($participant->is_initiator && $participant->hasSigned())
                        <span class="status-accepted">Wystawił protokół</span>
                    @elseif(!$participant->is_initiator)
                        @if($protocol->isObjectionWindowOpen())
                            <span class="status-pending">Może zgłosić zastrzeżenia</span>
                        @else
                            <span class="status-accepted">Okno zastrzeżeń zamknięte</span>
                        @endif
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Stan pomieszczeń (tylko końcowy, bez porównania) --}}
@if($rooms->count() > 0)
<div class="section">
    <div class="section-title">Stan pomieszczeń przy zwrocie</div>
    <p style="font-size: 9pt; color: #666; margin-bottom: 10px;">
        Brak danych porównawczych ze stanu początkowego.
    </p>
    @foreach($rooms as $room)
    <table>
        <thead>
            <tr><th colspan="3" style="background: #e9e9e9;">{{ $room->display_name }}</th></tr>
            <tr><th>Element</th><th>Stan przy zwrocie</th><th>Uwagi / Zdjęcia</th></tr>
        </thead>
        <tbody>
            @forelse($room->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td>{{ $item->condition_name }}</td>
                <td>
                    @if($item->notes){{ $item->notes }}<br>@endif
                    @if($item->photos && $item->photos->count() > 0)
                    Zdjęcia: {{ $item->photos->count() }} szt.
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="3" style="text-align: center;">Brak elementów</td></tr>
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

{{-- Rozliczenie kaucji --}}
@if(isset($deposit_calculation))
<div class="section">
    <div class="section-title">Rozliczenie kaucji</div>
    <div style="background: #fff3cd; padding: 8px; margin-bottom: 10px; font-size: 9pt;">
        <strong>Uwaga:</strong> Rozliczenie dokonane bez protokołu wjazdowego.
        Brak możliwości obiektywnego porównania stanu początkowego i końcowego.
    </div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Kaucja wpłacona</div>
            <div class="info-value">{{ number_format($deposit_calculation['deposit_amount'] ?? 0, 2, ',', ' ') }} PLN</div>
        </div>
        <div class="info-row">
            <div class="info-label">Suma potrąceń</div>
            <div class="info-value damage-cost">{{ number_format($deposit_calculation['total_deductions'] ?? 0, 2, ',', ' ') }} PLN</div>
        </div>
        <div class="info-row">
            <div class="info-label">Do zwrotu</div>
            <div class="info-value deposit-return">{{ number_format($deposit_calculation['amount_to_return'] ?? 0, 2, ',', ' ') }} PLN</div>
        </div>
    </div>

    @if(isset($deposit_calculation['deductions']) && count($deposit_calculation['deductions']) > 0)
    <table style="margin-top: 10px;">
        <thead><tr><th>Pozycja potrącenia</th><th>Kwota</th><th>Uzasadnienie</th></tr></thead>
        <tbody>
            @foreach($deposit_calculation['deductions'] as $deduction)
            <tr>
                <td>{{ $deduction['description'] }}</td>
                <td class="damage-cost">{{ number_format($deduction['amount'], 2, ',', ' ') }} PLN</td>
                <td>{{ $deduction['reason'] ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endif

{{-- Zastrzeżenia --}}
@if(isset($objections) && $objections->count() > 0)
<div class="section">
    <div class="section-title">Zgłoszone zastrzeżenia</div>
    @foreach($objections as $objection)
    <div style="border: 1px solid #dc3545; padding: 10px; margin-bottom: 10px; background: #fff5f5;">
        <strong>{{ $objection->raisedBy?->name ?? 'Najemca' }}</strong>
        <span style="color: #999; font-size: 8pt;">{{ $objection->created_at->format('d.m.Y H:i') }}</span>
        <br>{{ $objection->description }}
        @if($objection->resolved_at)
        <div style="margin-top: 5px; padding-top: 5px; border-top: 1px solid #ddd;">
            <strong>Rozstrzygnięcie:</strong> {{ $objection->resolution }}
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Nota prawna --}}
<div class="legal-notice">
    {{ $template_type->legalContext() }}
    <br><br>
    <strong>Brak protokołu wjazdowego:</strong> Rozliczenie kaucji dokonane bez możliwości porównania
    stanu początkowego i końcowego lokalu. W przypadku sporu, ciężar dowodu spoczywa na stronie
    powołującej się na zmianę stanu lokalu.
</div>

{{-- Podpisy --}}
<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">
            Wynajmujący (wystawiający protokół)
            <br>{{ $protocol->initiator()?->display_name ?? '-' }}
        </div>
    </div>
    <div class="signature-box">
        <div class="signature-line">
            Najemca
            <br>{{ $protocol->counterparty()?->display_name ?? '-' }}
            @if($protocol->objection_window_ends_at)
            <br><span style="font-size: 8pt;">Okno zastrzeżeń: {{ $protocol->isObjectionWindowOpen() ? 'otwarte' : 'zamknięte' }}</span>
            @endif
        </div>
    </div>
</div>
@endsection
