<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Podsumowanie protokołu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.5;
            color: #333;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 18pt;
            margin-bottom: 10px;
        }
        .summary-box {
            border: 2px solid #333;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-label {
            font-weight: bold;
        }
        .summary-value {
            text-align: right;
        }
        .highlight {
            background: #f8f8f8;
            padding: 15px;
            margin-top: 20px;
            font-size: 14pt;
        }
        .highlight .amount {
            font-size: 24pt;
            font-weight: bold;
            color: #2e7d32;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 9pt;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Podsumowanie protokołu</h1>
        <p>{{ $property->full_address ?? 'Adres nieznany' }}</p>
        <p>{{ $generated_at->format('d.m.Y') }}</p>
    </div>

    <div class="summary-box">
        <div class="summary-row">
            <span class="summary-label">Typ protokołu</span>
            <span class="summary-value">
                {{ $protocol->type->value === 'check_in' ? 'Przekazanie lokalu' : 'Zdanie lokalu' }}
            </span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Status</span>
            <span class="summary-value">{{ $protocol->status->label() }}</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Liczba usterek</span>
            <span class="summary-value">{{ $defects_count }}</span>
        </div>
        @if($deposit_amount)
        <div class="summary-row">
            <span class="summary-label">Kaucja</span>
            <span class="summary-value">{{ number_format($deposit_amount, 2) }} PLN</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Koszt usterek</span>
            <span class="summary-value">{{ number_format($total_damage_cost, 2) }} PLN</span>
        </div>
        @endif
    </div>

    @if($deposit_amount)
    <div class="highlight">
        <p>Kwota do zwrotu:</p>
        <p class="amount">{{ number_format($amount_to_return, 2) }} PLN</p>
    </div>
    @endif

    <div class="summary-box">
        <h3 style="margin-bottom: 10px;">Strony protokołu</h3>
        @foreach($participants as $participant)
        <div class="summary-row">
            <span class="summary-label">{{ $participant->role->label() }}</span>
            <span class="summary-value">
                {{ $participant->display_name }}
                @if($participant->hasSigned())
                    ✓
                @endif
            </span>
        </div>
        @endforeach
    </div>

    <div class="footer">
        Dokument wygenerowany przez Rent2Proof | {{ $generated_at->format('d.m.Y H:i') }}
    </div>
</body>
</html>
