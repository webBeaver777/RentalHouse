<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dokument nie znaleziony</title>
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
            background: #dc3545;
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
            text-align: center;
        }
        .content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 16px;
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
        .help-text {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 16px;
            border-radius: 8px;
            margin-top: 16px;
            text-align: left;
        }
        .help-text h3 {
            color: #856404;
            font-size: 14px;
            margin-bottom: 8px;
        }
        .help-text ul {
            color: #856404;
            font-size: 13px;
            margin-left: 20px;
        }
        .help-text li {
            margin-bottom: 4px;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="icon">✗</div>
            <h1>Dokument nie znaleziony</h1>
            <p>Nie udało się zweryfikować tego dokumentu</p>
        </div>

        <div class="content">
            <p>
                Dokument o podanym identyfikatorze nie został znaleziony w naszym systemie.
                Może to oznaczać, że dokument nie istnieje lub został usunięty.
            </p>

            <div class="help-text">
                <h3>Możliwe przyczyny:</h3>
                <ul>
                    <li>Nieprawidłowy kod QR</li>
                    <li>Dokument został usunięty po upływie okresu przechowywania</li>
                    <li>Kod QR został uszkodzony</li>
                    <li>Dokument pochodzi z innego systemu</li>
                </ul>
            </div>

            <div class="hash-display">
                <div class="hash-label">Szukany identyfikator:</div>
                {{ $hash }}
            </div>
        </div>

        <div class="footer">
            Próba weryfikacji: {{ now()->format('d.m.Y H:i') }}<br>
            System protokołów najmu mieszkań
        </div>
    </div>
</body>
</html>
