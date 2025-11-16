<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dienstplan {{ $weekStart->format('d.m.Y') }} - {{ $weekEnd->format('d.m.Y') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #333;
        }
        .header h1 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        .header p {
            font-size: 14px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
            text-align: center;
        }
        .employee-name {
            font-weight: bold;
            min-width: 150px;
        }
        .day-cell {
            min-width: 100px;
            vertical-align: top;
        }
        .shift {
            background-color: #e3f2fd;
            padding: 4px;
            margin: 2px 0;
            border-radius: 3px;
            font-size: 10px;
        }
        .shift-time {
            font-weight: bold;
            color: #1976d2;
        }
        .shift-type {
            color: #666;
            font-size: 9px;
        }
        .weekend {
            background-color: #fff9e6;
        }
        .today {
            background-color: #e8f5e9;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        @media print {
            body {
                padding: 10px;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dienstplan</h1>
        <p>{{ $weekStart->format('d.m.Y') }} - {{ $weekEnd->format('d.m.Y') }}</p>
        <p>KW {{ $weekStart->week() }}</p>
    </div>

    @foreach($shiftsByDepartment as $deptData)
        @if(!empty(array_filter($deptData['grid'], fn($day) => !empty(array_filter($day)))))
            <div style="page-break-after: always;">
                <h2 style="margin-bottom: 15px; color: #333; border-bottom: 2px solid #333; padding-bottom: 10px;">
                    {{ $deptData['department']->name }}
                </h2>
                
                <table>
                    <thead>
                        <tr>
                            <th style="min-width: 120px;">Wochentag</th>
                            @foreach($shiftTypes as $shiftType)
                                <th class="day-cell">{{ $shiftType->name }} (ID: {{ $shiftType->id }})</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($days as $day)
                            <tr>
                                <td class="employee-name {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                                    <strong>{{ $day->locale('de')->isoFormat('dddd') }}</strong><br>
                                    <small>{{ $day->format('d.m.Y') }}</small>
                                </td>
                                @foreach($shiftTypes as $shiftType)
                                    @php
                                        $employees = $deptData['grid'][$day->format('Y-m-d')][$shiftType->id] ?? [];
                                    @endphp
                                    <td class="day-cell {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                                        @if(!empty($employees))
                                            @foreach($employees as $employee)
                                                <div class="shift">{{ $employee }} <small style="color: #999;">({{ $shiftType->name }})</small></div>
                                            @endforeach
                                        @else
                                            <span style="color: #ccc;">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endforeach

    <div class="footer">
        <p>Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr</p>
        <p>Hemingway Zeiterfassung</p>
    </div>

</body>
</html>