<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Porównanie protokołów</title>
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
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .comparison-table th {
            background: #f0f0f0;
            padding: 8px;
            text-align: left;
            border: 1px solid #ddd;
        }
        .comparison-table td {
            padding: 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .comparison-table .room-header {
            background: #e8e8e8;
            font-weight: bold;
        }
        .changed {
            background: #fff3cd;
        }
        .condition-improved {
            color: #28a745;
        }
        .condition-degraded {
            color: #dc3545;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>Porównanie protokołów</h1>
        <p>{{ $checkout->property->full_address ?? 'Adres nieznany' }}</p>
        <p>Przekazanie: {{ $checkin->completed_at?->format('d.m.Y') ?? 'N/A' }} → Zdanie: {{ $checkout->completed_at?->format('d.m.Y') ?? now()->format('d.m.Y') }}</p>
    </div>

    <table class="comparison-table">
        <thead>
            <tr>
                <th width="25%">Element</th>
                <th width="35%">Stan przy przekazaniu</th>
                <th width="35%">Stan przy zdaniu</th>
                <th width="5%">Zmiana</th>
            </tr>
        </thead>
        <tbody>
            @php $currentRoom = null; @endphp
            @foreach($comparisons as $comparison)
                @if($currentRoom !== $comparison['room_name'])
                    @php $currentRoom = $comparison['room_name']; @endphp
                    <tr class="room-header">
                        <td colspan="4">{{ $currentRoom }}</td>
                    </tr>
                @endif
                <tr class="{{ $comparison['condition_changed'] ? 'changed' : '' }}">
                    <td>{{ $comparison['item_name'] }}</td>
                    <td>{{ $comparison['checkin_condition'] ?? '-' }}</td>
                    <td>{{ $comparison['checkout_condition'] ?? '-' }}</td>
                    <td>
                        @if($comparison['condition_changed'])
                            ⚠️
                        @else
                            ✓
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Dokument wygenerowany przez Rent2Proof | {{ $generated_at->format('d.m.Y H:i') }}
    </div>
</body>
</html>
