<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schichtende Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            padding: 30px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #2563eb;
        }
        
        .header h1 {
            font-size: 24pt;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 12pt;
            color: #64748b;
        }
        
        .department-section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }
        
        .department-title {
            background-color: #f1f5f9;
            padding: 10px 15px;
            font-size: 14pt;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 10px;
            border-left: 4px solid #2563eb;
        }
        
        .employee-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        
        .employee-table th {
            background-color: #e2e8f0;
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            color: #334155;
            border-bottom: 2px solid #cbd5e1;
        }
        
        .employee-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .employee-table tr:hover {
            background-color: #f8fafc;
        }
        
        .amount {
            text-align: right;
            font-weight: 600;
            color: #059669;
        }
        
        .status-paid {
            color: #059669;
            font-weight: 600;
        }
        
        .status-unpaid {
            color: #dc2626;
            font-weight: 600;
        }
        
        .total-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #f0f9ff;
            border: 2px solid #2563eb;
            border-radius: 8px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16pt;
            font-weight: bold;
            color: #1e40af;
        }
        
        .warning {
            margin-top: 10px;
            padding: 10px;
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            font-size: 10pt;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #cbd5e1;
            text-align: center;
            font-size: 9pt;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Schichtende {{ $date }}</h1>
        <p>Erstellt um {{ $time }} Uhr</p>
    </div>

    @if($unpaid_count > 0)
        <div class="warning">
            ⚠️ Achtung: {{ $unpaid_count }} Mitarbeiter {{ $unpaid_count === 1 ? 'wurde' : 'wurden' }} noch nicht als ausgezahlt markiert.
        </div>
    @endif

    @foreach($grouped_entries as $department => $entries)
        <div class="department-section">
            <div class="department-title">{{ $department }}</div>
            
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Mitarbeiter</th>
                        <th>Arbeitszeit</th>
                        <th>Pause</th>
                        <th>Stundenlohn</th>
                        <th style="text-align: right;">Betrag</th>
                        <th style="text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($entries as $entry)
                        <tr>
                            <td>{{ $entry->employee->full_name }}</td>
                            <td>{{ number_format($entry->total_hours ?? 0, 2) }} Std</td>
                            <td>{{ $entry->break_minutes ?? 0 }} Min</td>
                            <td>€{{ number_format($entry->override_hourly_rate ?? $entry->employee->hourly_rate ?? 0, 2) }}</td>
                            <td class="amount">€{{ number_format($entry->gross_wage ?? 0, 2) }}</td>
                            <td style="text-align: center;">
                                @if($entry->paid_out_at)
                                    <span class="status-paid">✓ Bezahlt</span>
                                @else
                                    <span class="status-unpaid">○ Offen</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="total-section" style="background-color: #f8fafc; border: 1px solid #cbd5e1;">
        <div class="total-row" style="font-size: 12pt; margin-bottom: 10px;">
            <span>Subsumme Löhne:</span>
            <span>€{{ number_format($total_wages, 2) }}</span>
        </div>
    </div>

    @if(!empty($expenses['purchases']) || !empty($expenses['advances']) || !empty($expenses['other']))
        <div class="department-section">
            <div class="department-title">Andere Ausgaben</div>
            
            <table class="employee-table">
                <thead>
                    <tr>
                        <th>Typ</th>
                        <th>Beschreibung</th>
                        <th style="text-align: right;">Betrag</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expenses['purchases'] as $purchase)
                        @if(!empty($purchase['amount']))
                            <tr>
                                <td>Einkauf</td>
                                <td>An: {{ $purchase['recipient'] ?? '-' }}</td>
                                <td class="amount">€{{ number_format($purchase['amount'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    
                    @foreach($expenses['advances'] as $advance)
                        @if(!empty($advance['amount']))
                            <tr>
                                <td>Auslage</td>
                                <td>An: {{ $advance['recipient'] ?? '-' }}</td>
                                <td class="amount">€{{ number_format($advance['amount'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                    
                    @foreach($expenses['other'] as $other)
                        @if(!empty($other['amount']))
                            <tr>
                                <td>Sonstige Entnahme</td>
                                <td>Grund: {{ $other['reason'] ?? '-' }}</td>
                                <td class="amount">€{{ number_format($other['amount'], 2) }}</td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="total-section" style="background-color: #f8fafc; border: 1px solid #cbd5e1;">
            <div class="total-row" style="font-size: 12pt; margin-bottom: 10px;">
                <span>Subsumme andere Ausgaben:</span>
                <span>€{{ number_format($total_expenses, 2) }}</span>
            </div>
        </div>
    @endif

    <div class="total-section">
        <div class="total-row">
            <span>Gesamtsumme Barauszahlung:</span>
            <span>€{{ number_format($grand_total, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Dieser Report wurde automatisch generiert am {{ now()->format('d.m.Y') }} um {{ now()->format('H:i') }} Uhr</p>
    </div>
</body>
</html>