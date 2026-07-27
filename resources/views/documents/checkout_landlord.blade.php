@extends('documents.layout')

@section('title')
{{ $template_type->polishTitle() }}
@endsection

@section('content')
<div class="header">
    <h1>{{ $template_type->polishTitle() }}</h1>
    <div class="subtitle">{{ $property->full_address }}</div>
</div>

{{-- Dane podstawowe --}}
<div class="section">
    <div class="section-title">Dane podstawowe</div>
    <div class="info-grid">
        <div class="info-row">
            <div class="info-label">Typ dokumentu</div>
            <div class="info-value">Protokół zwrotu lokalu (check-out)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tryb prawny</div>
            <div class="info-value">{{ $protocol->legal_mode?->label() ?? 'Jednostronny z oknem zastrzeżeń' }}</div>
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
        @if(isset($linked_checkin))
        <div class="info-row">
            <div class="info-label">Protokół wjazdowy</div>
            <div class="info-value">z dnia {{ $linked_checkin->completed_at?->format('d.m.Y') ?? '-' }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Strony --}}
<div class="section">
    <div class="section-title">Strony protokołu</div>
    <table>
        <thead>
            <tr><th>Rola</th><th>Dane</th><th>Status</th><th>Uwagi</th></tr>
        </thead>
        <tbody>
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }} @if($participant->is_initiator)(sporządzający)@endif</td>
                <td>{{ $participant->display_name }}</td>
                <td>
                    @if($participant->is_initiator && $participant->hasSigned())
                        <span class="status-accepted">Wystawił akt</span>
                    @elseif(!$participant->is_initiator)
                        @if($protocol->isObjectionWindowOpen())
                            <span class="status-pending">Może zgłosić zastrzeżenia</span>
                        @else
                            <span class="status-accepted">Okno zastrzeżeń zamknięte</span>
                        @endif
                    @endif
                </td>
                <td>
                    @if(!$participant->is_initiator && isset($objections) && $objections->where('raised_by_user_id', $participant->user_id)->count() > 0)
                        <span class="status-objection">Zgłosił zastrzeżenia</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Rozliczenie kaucji --}}
@if(isset($deposit_calculation))
<div class="section">
    <div class="section-title">Rozliczenie kaucji</div>
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

{{-- Stan pomieszczeń --}}
@if($rooms->count() > 0)
<div class="section">
    <div class="section-title">Stan pomieszczeń przy zwrocie</div>
    @foreach($rooms as $room)
    <table>
        <thead>
            <tr><th colspan="4" style="background: #e9e9e9;">{{ $room->display_name }}</th></tr>
            <tr><th>Element</th><th>Stan przy zwrocie</th><th>Stan przy przekazaniu</th><th>Zmiana</th></tr>
        </thead>
        <tbody>
            @forelse($room->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td>{{ $item->condition_name }}</td>
                <td>{{ $item->baselineItem?->condition_name ?? '-' }}</td>
                <td>
                    @if($item->baselineItem && $item->condition_catalog_item_id !== $item->baselineItem->condition_catalog_item_id)
                        <span class="status-objection">Zmiana stanu</span>
                    @else
                        <span class="status-accepted">Bez zmian</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="4" style="text-align: center;">Brak elementów</td></tr>
            @endforelse
        </tbody>
    </table>
    @endforeach
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

{{-- Zdjęcia drugiej strony --}}
@if(isset($counterparty_photos) && $counterparty_photos->count() > 0)
<div class="section">
    <div class="section-title">Dokumentacja fotograficzna najemcy</div>
    <table>
        <thead><tr><th>Lokalizacja</th><th>Czas</th><th>Hash SHA-256</th></tr></thead>
        <tbody>
            @foreach($counterparty_photos as $photo)
            <tr>
                <td>{{ $photo->room?->display_name ?? 'Ogólne' }}</td>
                <td>{{ $photo->uploaded_at->format('d.m.Y H:i') }}</td>
                <td class="photo-hash">{{ $photo->hash }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Nota prawna --}}
<div class="legal-notice">
    {{ $template_type->legalContext() }}
</div>

{{-- Podpisy --}}
<div class="signatures">
    <div class="signature-box">
        <div class="signature-line">
            Wynajmujący (wystawiający akt)
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
