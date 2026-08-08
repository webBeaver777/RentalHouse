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
            <div class="info-value">Protokół przekazania (check-in)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Tryb prawny</div>
            <div class="info-value">{{ $protocol->legal_mode?->label() ?? 'Bilateralny' }}</div>
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
            <div class="info-label">Data przekazania</div>
            <div class="info-value">{{ $protocol->scheduled_at?->format('d.m.Y') ?? $protocol->completed_at?->format('d.m.Y') ?? '-' }}</div>
        </div>
        @if($protocol->completed_at)
        <div class="info-row">
            <div class="info-label">Data zakończenia</div>
            <div class="info-value">{{ $protocol->completed_at->format('d.m.Y H:i') }}</div>
        </div>
        @endif
    </div>
</div>

{{-- Strony protokołu --}}
<div class="section">
    <div class="section-title">Strony protokołu</div>
    <table>
        <thead>
            <tr>
                <th>Rola</th>
                <th>Imię i nazwisko</th>
                <th>Status</th>
                <th>Data podpisu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }} @if($participant->is_initiator)(inicjator)@endif</td>
                <td>{{ $participant->display_name }}</td>
                <td>
                    @if($participant->hasSigned())
                        <span class="status-accepted">Podpisano</span>
                    @elseif($participant->isDeclined())
                        <span class="status-objection">Odmówiono</span>
                    @else
                        <span class="status-pending">Oczekuje</span>
                    @endif
                </td>
                <td>{{ $participant->signed_at?->format('d.m.Y H:i') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Pomieszczenia i elementy --}}
@if($rooms->count() > 0)
<div class="section">
    <div class="section-title">Pomieszczenia i elementy</div>
    @foreach($rooms as $room)
    <table>
        <thead>
            <tr>
                <th colspan="4" style="background: #e9e9e9;">{{ $room->display_name }}</th>
            </tr>
            <tr>
                <th style="width: 30%;">Element</th>
                <th style="width: 20%;">Stan</th>
                <th style="width: 10%;">Ilość</th>
                <th style="width: 40%;">Uwagi / Zdjęcia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($room->items as $item)
            <tr>
                <td>{{ $item->display_name }}</td>
                <td>{{ $item->condition_name }}</td>
                <td>{{ $item->quantity }}</td>
                <td>
                    @if($item->notes)
                        {{ $item->notes }}<br>
                    @endif
                    @if($item->photos && $item->photos->count() > 0)
                        <div class="photo-grid">
                            @foreach($item->photos->take(3) as $photo)
                            <div class="photo-item">
                                <div class="photo-hash">SHA: {{ substr($photo->hash, 0, 8) }}...</div>
                            </div>
                            @endforeach
                            @if($item->photos->count() > 3)
                            <div class="photo-item">+{{ $item->photos->count() - 3 }} więcej</div>
                            @endif
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
        <thead>
            <tr>
                <th>Typ licznika</th>
                <th>Numer</th>
                <th>Odczyt</th>
                <th>Jednostka</th>
                <th>Data odczytu</th>
            </tr>
        </thead>
        <tbody>
            @foreach($meters as $meter)
            <tr>
                <td>{{ $meter->type->label() }}</td>
                <td>{{ $meter->serial_number ?? '-' }}</td>
                <td>{{ $meter->reading }}</td>
                <td>{{ $meter->unit }}</td>
                <td>{{ $meter->read_at?->format('d.m.Y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Klucze --}}
@if($keys->count() > 0)
<div class="section">
    <div class="section-title">Przekazane klucze</div>
    <table>
        <thead>
            <tr>
                <th>Opis</th>
                <th>Ilość</th>
                <th>Uwagi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($keys as $key)
            <tr>
                <td>{{ $key->description }}</td>
                <td>{{ $key->quantity }}</td>
                <td>{{ $key->notes ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Usterki --}}
@if($defects->count() > 0)
<div class="section">
    <div class="section-title">Stwierdzone usterki</div>
    <table>
        <thead>
            <tr>
                <th>Opis usterki</th>
                <th>Lokalizacja</th>
                <th>Priorytet</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($defects as $defect)
            <tr>
                <td>{{ $defect->description }}</td>
                <td>{{ $defect->location ?? '-' }}</td>
                <td>{{ $defect->priority?->label() ?? '-' }}</td>
                <td>{{ $defect->status?->label() ?? 'Zgłoszona' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Komentarze drugiej strony --}}
@if(isset($comments) && $comments->count() > 0)
<div class="section">
    <div class="section-title">Komentarze stron</div>
    @foreach($comments as $comment)
    <div style="padding: 5px; border-bottom: 1px solid #eee;">
        <strong>{{ $comment->author_role->label() }}</strong>
        <span style="color: #999; font-size: 8pt;">{{ $comment->created_at->format('d.m.Y H:i') }}</span>
        <br>
        {{ $comment->body }}
        @if($comment->commentable_type)
        <span style="font-size: 8pt; color: #666;">(dot. {{ $comment->commentable_type }})</span>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- Oś czasu --}}
@if(isset($timeline) && $timeline->count() > 0)
<div class="section">
    <div class="section-title">Oś czasu</div>
    <div class="timeline">
        @foreach($timeline->take(15) as $event)
        <div class="timeline-item">
            <span class="timeline-time">{{ $event->created_at->format('d.m.Y H:i') }}</span>
            {{ $event->event_type->shortLabel() }}
            @if($event->actor_role)
            <span style="color: #666;">({{ $event->actor_role }})</span>
            @endif
        </div>
        @endforeach
        @if($timeline->count() > 15)
        <div style="color: #999; font-size: 8pt;">... i {{ $timeline->count() - 15 }} więcej zdarzeń</div>
        @endif
    </div>
</div>
@endif

{{-- Nota prawna --}}
<div class="legal-notice">
    {{ $template_type->legalContext() }}
    <br><br>
    Niniejszy dokument został wygenerowany elektronicznie i stanowi potwierdzenie stanu lokalu
    w dniu przekazania. Wszelkie zmiany w dokumencie są rejestrowane w systemie.
</div>

{{-- Podpisy --}}
<div class="signatures">
    @foreach($participants as $participant)
    <div class="signature-box">
        <div class="signature-line">
            {{ $participant->role->label() }}: {{ $participant->display_name }}
            @if($participant->signed_at)
            <br><span style="font-size: 8pt;">Podpisano: {{ $participant->signed_at->format('d.m.Y H:i') }}</span>
            @endif
        </div>
    </div>
    @endforeach
</div>

{{-- QR do weryfikacji --}}
@if(isset($qr_url))
<div class="qr-section">
    <p style="font-size: 8pt;">Zeskanuj kod QR aby zweryfikować autentyczność dokumentu:</p>
    <img src="{{ $qr_url }}" alt="QR Code">
</div>
@endif
@endsection
