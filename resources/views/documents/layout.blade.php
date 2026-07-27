<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>@yield('title')</title>
    <style>
        @page {
            margin: 20mm 15mm;
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
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16pt;
            margin: 0 0 5px 0;
        }
        .header .subtitle {
            font-size: 11pt;
            color: #666;
        }
        .section {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            background: #f5f5f5;
            padding: 5px 10px;
            margin-bottom: 10px;
            border-left: 3px solid #333;
        }
        .info-grid {
            display: table;
            width: 100%;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            width: 35%;
            padding: 3px 5px;
            font-weight: bold;
            background: #f9f9f9;
            border-bottom: 1px solid #eee;
        }
        .info-value {
            display: table-cell;
            padding: 3px 5px;
            border-bottom: 1px solid #eee;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: #f5f5f5;
            font-weight: bold;
        }
        .photo-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        .photo-item {
            width: 80px;
            text-align: center;
        }
        .photo-item img {
            max-width: 80px;
            max-height: 60px;
            border: 1px solid #ddd;
        }
        .photo-hash {
            font-size: 6pt;
            color: #999;
            word-break: break-all;
        }
        .signatures {
            margin-top: 30px;
            display: table;
            width: 100%;
        }
        .signature-box {
            display: table-cell;
            width: 45%;
            text-align: center;
            padding: 10px;
        }
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 40px;
            padding-top: 5px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #eee;
            padding-top: 5px;
        }
        .qr-section {
            text-align: center;
            margin: 20px 0;
        }
        .qr-section img {
            max-width: 100px;
        }
        .legal-notice {
            font-size: 8pt;
            color: #666;
            background: #f9f9f9;
            padding: 10px;
            margin-top: 20px;
            border: 1px solid #eee;
        }
        .timeline {
            font-size: 9pt;
        }
        .timeline-item {
            padding: 3px 0;
            border-bottom: 1px dotted #eee;
        }
        .timeline-time {
            color: #666;
            font-size: 8pt;
        }
        .status-accepted { color: #28a745; }
        .status-objection { color: #dc3545; }
        .status-pending { color: #ffc107; }
        .damage-cost {
            font-weight: bold;
            color: #dc3545;
        }
        .deposit-return {
            font-weight: bold;
            color: #28a745;
        }
    </style>
</head>
<body>
    @yield('content')

    <div class="footer">
        Wygenerowano: {{ $generated_at->format('d.m.Y H:i') }} |
        Hash dokumentu: {{ $document_hash ?? 'Do wygenerowania' }}
    </div>
</body>
</html>
