<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weryfikacja dokumentu</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 480px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: #28a745;
            color: white;
            padding: 24px;
            text-align: center;
        }
        .header .icon {
            font-size: 48px;
            margin-bottom: 12px;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 600;
        }
        .header p {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 8px;
        }
        .content {
            padding: 24px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-size: 14px;
        }
        .info-value {
            font-weight: 500;
            color: #333;
            text-align: right;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        .status-info {
            background: #cce5ff;
            color: #004085;
        }
        .hash-display {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 11px;
            word-break: break-all;
            color: #666;
            margin-top: 16px;
        }
        .hash-label {
            font-size: 12px;
            color: #999;
            margin-bottom: 4px;
        }
        .footer {
            text-align: center;
            padding: 16px 24px 24px;
            color: #999;
            font-size: 12px;
        }
        .signatures {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
        .signature-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
        }
        .signature-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 12px;
        }
        .signature-dot.signed {
            background: #28a745;
        }
        .signature-dot.pending {
            background: #ffc107;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="icon">✓</div>
            <h1>Dokument zweryfikowany</h1>
            <p>Ten dokument istnieje w systemie i jest autentyczny</p>
        </div>

        <div class="content">
            <div class="info-row">
                <span class="info-label">Typ dokumentu</span>
                <span class="info-value">{{ $document_type }}</span>
            </div>

            <div class="info-row">
                <span class="info-label">Rodzaj protokołu</span>
                <span class="info-value">{{ $protocol_type_label }}</span>
            </div>

            <div class="info-row">
                <span class="info-label">Tryb prawny</span>
                <span class="info-value">{{ $legal_mode_label }}</span>
            </div>

            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value">
                    <span class="status-badge {{ $all_signed ? 'status-success' : 'status-warning' }}">
                        {{ $protocol_status_label }}
                    </span>
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Data utworzenia</span>
                <span class="info-value">{{ $protocol_created_at }}</span>
            </div>

            @if($protocol_completed_at)
            <div class="info-row">
                <span class="info-label">Data zakończenia</span>
                <span class="info-value">{{ $protocol_completed_at }}</span>
            </div>
            @endif

            <div class="info-row">
                <span class="info-label">Wygenerowano</span>
                <span class="info-value">{{ $generated_at }}</span>
            </div>

            <div class="signatures">
                <div class="signature-item">
                    <div class="signature-dot {{ $initiator_signed ? 'signed' : 'pending' }}"></div>
                    <span>Strona inicjująca: {{ $initiator_signed ? 'Podpisano' : 'Oczekuje' }}</span>
                </div>
                <div class="signature-item">
                    <div class="signature-dot {{ $counterparty_signed ? 'signed' : 'pending' }}"></div>
                    <span>Druga strona: {{ $counterparty_signed ? 'Podpisano' : 'Oczekuje' }}</span>
                </div>
            </div>

            <div class="hash-display">
                <div class="hash-label">Hash SHA-256 dokumentu:</div>
                {{ $document_hash }}
            </div>
        </div>

        <div class="footer">
            Weryfikacja wykonana: {{ now()->format('d.m.Y H:i') }}<br>
            System protokołów najmu mieszkań
        </div>
    </div>
</body>
</html>
