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

    <button class="no-print" onclick="window.print()" style="padding: 10px 20px; margin-bottom: 20px; cursor: pointer;">
        Drucken / Als PDF speichern
    </button>

    <table>
        <thead>
            <tr>
                <th class="employee-name">Mitarbeiter</th>
                @foreach($days as $day)
                    <th class="day-cell {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                        {{ $day->format('D') }}<br>
                        {{ $day->format('d.m.') }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($shiftsByEmployee as $employeeData)
                <tr>
                    <td class="employee-name">
                        {{ $employeeData['employee']->first_name }} {{ $employeeData['employee']->last_name }}
                        @if($employeeData['employee']->employee_number)
                            <br><small style="color: #666;">Nr. {{ $employeeData['employee']->employee_number }}</small>
                        @endif
                    </td>
                    @foreach($days as $day)
                        <td class="day-cell {{ $day->isWeekend() ? 'weekend' : '' }} {{ $day->isToday() ? 'today' : '' }}">
                            @php
                                $dayShifts = $employeeData['shifts'][$day->format('Y-m-d')] ?? collect();
                            @endphp
                            @foreach($dayShifts as $shift)
                                <div class="shift">
                                    <div class="shift-time">
                                        {{ substr($shift->start_time, 0, 5) }} - {{ substr($shift->end_time, 0, 5) }}
                                    </div>
                                    <div class="shift-type">
                                        {{ $shift->shiftType->name ?? 'Schicht' }}
                                    </div>
                                    @if($shift->department)
                                        <div class="shift-type">
                                            {{ $shift->department->name }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Erstellt am {{ now()->format('d.m.Y H:i') }} Uhr</p>
        <p>Hemingway Zeiterfassung</p>
    </div>

    <script>
        // Auto-print dialog on load (optional)
        // window.onload = function() { window.print(); }
    </script>
</body>
</html>