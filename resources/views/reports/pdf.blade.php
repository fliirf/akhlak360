<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>AKHLAK360 Assessment Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #212529; }
        h1 { margin: 0 0 4px; font-size: 18px; }
        p { margin: 0 0 12px; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #adb5bd; padding: 4px; text-align: left; vertical-align: top; }
        th { background: #e9ecef; }
        .number { text-align: right; }
    </style>
</head>
<body>
    <h1>AKHLAK360 Assessment Report</h1>
    <p>Generated {{ now()->format('d M Y H:i') }} - {{ $results->count() }} record(s)</p>
    <table>
        <thead>
            <tr>
                <th>Period</th><th>Employee</th><th>Department</th><th>Position</th>
                <th class="number">Final</th><th>Category</th><th>Talent</th><th>IDP</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($results as $result)
                @php($idp = $result->employee?->idpRecommendations->first())
                <tr>
                    <td>{{ $result->assessmentPeriod?->name ?? '-' }}</td>
                    <td>{{ $result->employee?->employee_number ?? '-' }}<br>{{ $result->employee?->name ?? '-' }}</td>
                    <td>{{ $result->employee?->department?->name ?? '-' }}</td>
                    <td>{{ $result->employee?->position?->name ?? '-' }}</td>
                    <td class="number">{{ number_format((float) $result->final_score, 2) }}</td>
                    <td>{{ $result->category ?? '-' }}</td>
                    <td>{{ $result->talent_mapping_category ?? '-' }}</td>
                    <td>{{ $idp?->weakest_core_value ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No report data matched the selected filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
