<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8pt 12pt; }
        body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 5.4pt; margin: 0; }
        .heading { text-align: center; height: 62pt; }
        .heading img { height: auto; margin-top: 1pt; vertical-align: middle; }
        .heading .dswd { width: 92pt; }
        .heading .bagong-pilipinas { width: 22pt; margin-left: 3pt; }
        .heading p { margin: 1pt 0; }
        .title { font-size: 7.7pt; letter-spacing: .2pt; }
        .details { width: 100%; margin: 1pt 0 2pt; font-size: 6.5pt; }
        .details td { width: 50%; }
        .details .right { text-align: right; }
        .field { display: inline-block; border-bottom: .6pt solid #111; min-width: 115pt; padding: 0 4pt 2pt; text-align: center; font-weight: bold; }
        table.masterlist { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .masterlist th, .masterlist td { border: .6pt solid #111; padding: .3pt 1pt; text-align: center; vertical-align: middle; line-height: .95; }
        .masterlist th { background: #8e8e8e; color: #fff; font-weight: normal; height: 8pt; }
        .masterlist td { height: 8.5pt; overflow: hidden; }
        .masterlist .amount { white-space: nowrap; }
        .signatures { width: 100%; table-layout: fixed; margin-top: 3pt; border-collapse: collapse; text-align: center; }
        .signatures td { width: 25%; padding: 0 10pt; vertical-align: top; }
        .signatures .line { margin-top: 28pt; border-top: .6pt solid #111; padding-top: 2pt; font-size: 5.5pt; }
        .signatures .date { text-align: left; margin-top: 3pt; font-size: 5.5pt; }
        @if ($compact)
        body { font-size: 4.5pt; }
        .heading { height: 54pt; }
        .masterlist th { height: 7pt; }
        .masterlist td { height: 7pt; white-space: nowrap; }
        .signatures .line { margin-top: 24pt; }
        @endif
    </style>
</head>
<body>
    <div class="heading">
        <img class="dswd" src="data:image/png;base64,{{ $logo }}" alt="DSWD"><img class="bagong-pilipinas" src="data:image/png;base64,{{ $bagongPilipinasLogo }}" alt="Bagong Pilipinas">
        <p class="title">GUARANTEE LETTER MASTERLIST {{ $transmittal->created_at->timezone('Asia/Manila')->year }}</p>
        <p>Crisis Intervention Program - Operations Section</p>
        <p>Guarantee Letter Team</p>
    </div>

    <table class="details"><tr>
        <td>Bundle No.: <span class="field">{{ $transmittal->bundle_no }}</span></td>
        <td class="right">Category: <span class="field">{{ mb_strtoupper($transmittal->category->name) }}</span></td>
    </tr></table>

    <table class="masterlist">
        <colgroup>
            <col style="width: 2%"><col style="width: 9%"><col style="width: 14%"><col style="width: 16%">
            <col style="width: 22%"><col style="width: 9%"><col style="width: 10%"><col style="width: 10%"><col style="width: 8%">
        </colgroup>
        <thead><tr>
            <th>NO.</th><th></th><th>CLIENT NAME</th><th>BENEFICIARY NAME</th><th>SERVICE PROVIDER</th>
            <th>AMOUNT</th><th>DATE RELEASED</th><th>GL DATE</th><th>REMARKS</th>
        </tr></thead>
        <tbody>
            @for ($index = 0; $index < 30; $index++)
                @php($letter = $transmittal->gls->get($index))
                <tr>
                    <td>{{ $letter ? $index + 1 : '' }}</td>
                    <td>{{ $letter?->gl_no }}</td>
                    <td>{{ mb_strtoupper($letter?->client?->full_name ?? '') }}</td>
                    <td>{{ mb_strtoupper($letter?->beneficiary?->full_name ?? '') }}</td>
                    <td>{{ mb_strtoupper($letter?->serviceProvider?->name ?? '') }}</td>
                    <td class="amount">{{ $letter ? '₱'.number_format((float) $letter->gl_amount, 2) : '' }}</td>
                    <td>{{ $letter?->date_released ? \Illuminate\Support\Carbon::parse($letter->date_released)->format('F j, Y') : '' }}</td>
                    <td>{{ $letter?->gl_date?->format('F j, Y') }}</td>
                    <td>{{ $letter?->remarks }}</td>
                </tr>
            @endfor
        </tbody>
    </table>

    <table class="signatures"><tr>
        <td>PREPARED BY:<div class="line">{{ mb_strtoupper($transmittal->creator->name) }}</div><div class="date">DATE: {{ $transmittal->created_at->timezone('Asia/Manila')->format('F j, Y') }}</div></td>
        <td>RECEIVED BY:<div class="line">SIGNATURE OVER PRINTED NAME</div><div class="date">DATE:</div></td>
        <td>RECEIVED BY:<div class="line">SIGNATURE OVER PRINTED NAME</div><div class="date">DATE:</div></td>
        <td>RECEIVED BY:<div class="line">SIGNATURE OVER PRINTED NAME</div><div class="date">DATE:</div></td>
    </tr></table>
</body>
</html>
