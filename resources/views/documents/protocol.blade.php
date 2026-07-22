<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $type_label }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        .header h1 {
            font-size: 16pt;
            margin-bottom: 5px;
        }
        .header .date {
            font-size: 10pt;
            color: #666;
        }
        .section {
            margin-bottom: 20px;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 5px;
            background: #f0f0f0;
            border-left: 3px solid #333;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-table td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        .info-table td:first-child {
            width: 30%;
            font-weight: bold;
            background: #f9f9f9;
        }
        .room {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .room-title {
            font-size: 11pt;
            font-weight: bold;
            padding: 5px;
            background: #e8e8e8;
            margin-bottom: 5px;
        }
        .item {
            padding: 5px;
            border-bottom: 1px solid #eee;
        }
        .item-name {
            font-weight: bold;
        }
        .item-condition {
            color: #666;
            font-size: 9pt;
        }
        .meters-table, .keys-table, .defects-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meters-table th, .keys-table th, .defects-table th {
            background: #f0f0f0;
            padding: 5px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .meters-table td, .keys-table td, .defects-table td {
            padding: 5px;
            border: 1px solid #ddd;
        }
        .signatures {
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            margin: 10px 2%;
            padding: 20px;
            border: 1px solid #ddd;
            min-height: 100px;
        }
        .signature-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 40px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }
        .defect-severity-minor { color: #f0ad4e; }
        .defect-severity-moderate { color: #f0ad4e; }
        .defect-severity-major { color: #d9534f; }
        .defect-severity-critical { color: #d9534f; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $type_label }}</h1>
        <div class="date">Data sporządzenia: {{ $generated_at->format('d.m.Y H:i') }}</div>
    </div>

    <div class="section">
        <div class="section-title">Dane nieruchomości</div>
        <table class="info-table">
            <tr>
                <td>Adres</td>
                <td>{{ $property->full_address ?? 'Brak danych' }}</td>
            </tr>
            <tr>
                <td>Typ nieruchomości</td>
                <td>{{ $property->declaration_type ?? 'Nieokreślony' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Strony protokołu</div>
        <table class="info-table">
            @foreach($participants as $participant)
            <tr>
                <td>{{ $participant->role->label() }}</td>
                <td>{{ $participant->display_name }}</td>
            </tr>
            @endforeach
        </table>
    </div>

    @if($rooms->count() > 0)
    <div class="section">
        <div class="section-title">Pomieszczenia i wyposażenie</div>
        @foreach($rooms as $room)
        <div class="room">
            <div class="room-title">{{ $room->display_name }}</div>
            @foreach($room->items as $item)
            <div class="item">
                <span class="item-name">{{ $item->display_name }}</span>
                @if($item->quantity > 1)
                    (x{{ $item->quantity }})
                @endif
                <span class="item-condition">- {{ $item->condition_name ?? 'Brak oceny' }}</span>
            </div>
            @endforeach
        </div>
        @endforeach
    </div>
    @endif

    @if($meters->count() > 0)
    <div class="section">
        <div class="section-title">Liczniki</div>
        <table class="meters-table">
            <thead>
                <tr>
                    <th>Typ</th>
                    <th>Numer</th>
                    <th>Odczyt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($meters as $meter)
                <tr>
                    <td>{{ $meter->type->label() }}</td>
                    <td>{{ $meter->meter_number ?? '-' }}</td>
                    <td>{{ $meter->reading_value }} {{ $meter->unit }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($keys->count() > 0)
    <div class="section">
        <div class="section-title">Klucze</div>
        <table class="keys-table">
            <thead>
                <tr>
                    <th>Opis</th>
                    <th>Ilość</th>
                </tr>
            </thead>
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

    @if($defects->count() > 0)
    <div class="section">
        <div class="section-title">Usterki i uszkodzenia</div>
        <table class="defects-table">
            <thead>
                <tr>
                    <th>Opis</th>
                    <th>Waga</th>
                    <th>Koszt</th>
                </tr>
            </thead>
            <tbody>
                @foreach($defects as $defect)
                <tr>
                    <td>{{ $defect->title }}</td>
                    <td class="defect-severity-{{ $defect->severity->value }}">
                        {{ $defect->severity->label() }}
                    </td>
                    <td>{{ $defect->estimated_cost ? number_format($defect->estimated_cost, 2) . ' PLN' : '-' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @if($protocol->deposit_amount)
    <div class="section">
        <div class="section-title">Rozliczenie kaucji</div>
        <table class="info-table">
            <tr>
                <td>Kaucja</td>
                <td>{{ number_format($protocol->deposit_amount, 2) }} PLN</td>
            </tr>
            <tr>
                <td>Suma kosztów usterek</td>
                <td>{{ number_format($protocol->total_damage_cost, 2) }} PLN</td>
            </tr>
            <tr>
                <td>Do zwrotu</td>
                <td><strong>{{ number_format($protocol->amount_to_return, 2) }} PLN</strong></td>
            </tr>
        </table>
    </div>
    @endif

    <div class="signatures">
        <div class="section-title">Podpisy</div>
        @foreach($participants as $participant)
        <div class="signature-box">
            <div class="signature-label">
                {{ $participant->role->label() }}: {{ $participant->display_name }}
            </div>
            <div class="signature-line">
                @if($participant->hasSigned())
                    Podpisano: {{ $participant->signed_at->format('d.m.Y H:i') }}
                @else
                    Podpis: ________________________
                @endif
            </div>
        </div>
        @endforeach
    </div>

    <div class="footer">
        Dokument wygenerowany przez Rent2Proof | {{ $generated_at->format('d.m.Y H:i') }}
    </div>
</body>
</html>
