<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8pt 14pt; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 9pt; margin: 0; }
        .header { text-align: center; margin-bottom: 7pt; }
        .header .logos img { vertical-align: middle; }
        .header .dswd-logo { width: 60pt; margin-right: 8pt; }
        .header .bp-logo { width: 23pt; }
        .header .agency-name { margin-top: 5pt; font-size: 9pt; font-weight: bold; }
        .header .program-name { font-size: 9pt; font-weight: bold; }
        .voucher-number { font-size: 9pt; font-weight: bold; margin-bottom: 10pt; }
        .voucher-number .value { margin-left: 8pt; }
        .provider { text-align: center; }
        .provider-name { font-size: 11pt; font-weight: bold; border-bottom: 1pt dashed #333; padding-bottom: 3pt; }
        .provider-address { font-size: 9pt; padding: 3pt 0 10pt; }
        .letters { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .letters th, .letters td { border: .8pt solid #111; padding: 2pt; vertical-align: middle; }
        .letters th { text-align: center; font-size: 8pt; height: 11pt; }
        .letters td { height: 11pt; overflow-wrap: break-word; }
        .letters .number { width: 4%; text-align: center; }
        .letters .gl-number { width: 9%; }
        .letters .client { width: 21%; }
        .letters .relationship { width: 16%; }
        .letters .beneficiary { width: 28%; }
        .letters .amount { width: 22%; text-align: right; white-space: nowrap; }
        .total-row td { border-top: 2pt solid #111; height: 11pt; }
        .total-row .amount { font-weight: bold; }
        .signatures { width: 100%; margin-top: 12pt; border-collapse: collapse; }
        .signatures td { width: 50%; vertical-align: top; padding: 0 4pt; }
        .signatures .reviewed { padding-left: 29%; }
        .signatures .name { margin-top: 25pt; font-weight: bold; }
        .signatures .position { font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logos">
            <img class="dswd-logo" src="data:image/png;base64,{{ $logo }}" alt="DSWD">
            <img class="bp-logo" src="data:image/png;base64,{{ $bagongPilipinasLogo }}" alt="Bagong Pilipinas">
        </div>
        <div class="agency-name">Department of Social Welfare and Development</div>
        <div class="program-name">Crisis Intervention Program</div>
    </div>

    <div class="voucher-number">Voucher No.<span class="value">{{ $voucherNumber }}</span></div>

    <div class="provider">
        <div class="provider-name">{{ $providerName }}</div>
        <div class="provider-address">{{ $providerAddress }}</div>
    </div>

    <table class="letters">
        <thead><tr>
            <th class="number">No.</th>
            <th class="gl-number">GL NO.</th>
            <th class="client">CLIENT</th>
            <th class="relationship">RELATIONSHIP</th>
            <th class="beneficiary">Beneficiary Name</th>
            <th class="amount">Obligation Amount</th>
        </tr></thead>
        <tbody>
            @for ($index = 0; $index < 10; $index++)
                @php($letter = $letters->get($index))
                <tr>
                    <td class="number">{{ $index + 1 }}</td>
                    <td>{{ $letter?->gl_no }}</td>
                    <td>{{ $letter?->client?->full_name }}</td>
                    <td>{{ $letter?->relationship?->name }}</td>
                    <td>{{ $letter?->beneficiary?->full_name }}</td>
                    <td class="amount">{{ $letter ? number_format((float) $letter->gl_amount, 2) : '' }}</td>
                </tr>
            @endfor
            <tr class="total-row"><td></td><td></td><td></td><td></td><td></td><td class="amount">{{ $totalAmount }}</td></tr>
        </tbody>
    </table>

    <table class="signatures"><tr>
        <td>PREPARED BY :<div class="name">{{ $preparedBy }}</div><div class="position">{{ $preparedByPosition }}</div></td>
        <td class="reviewed">REVIEWED BY:<div class="name">{{ $reviewedBy ?: ' ' }}</div><div class="position">{{ $reviewedByPosition }}</div></td>
    </tr></table>
</body>
</html>
