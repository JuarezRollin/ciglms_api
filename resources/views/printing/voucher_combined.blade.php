<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { size: A4 portrait; margin: 0; }
        body { margin: 0; font-family: Arial, sans-serif; }
        .page { position: relative; width: 595pt; height: 841pt; overflow: hidden; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .template { position: absolute; left: 0; top: 0; width: 595pt; height: 841pt; }
        .ors-page .field { position: absolute; background: #fff; font-size: 8pt; line-height: 12pt; height: 12pt; overflow: hidden; white-space: nowrap; }
        .ors-page .particulars { position: absolute; font-size: 8pt; line-height: 12pt; width: 230pt; white-space: pre-line; }
        .ors-page .footer-mask { position: absolute; background: #fff; }
        .dv-page .field { position: absolute; background: #fff; font-size: 8pt; line-height: 11pt; min-height: 11pt; overflow: hidden; }
        .dv-page .single-line { white-space: nowrap; }
        .dv-page .mask { position: absolute; background: #fff; }
        .dv-page .particulars { line-height: 10pt; }
    </style>
</head>
<body>
@include('printing.voucher_ors_pages', $ors)
@include('printing.voucher_dv_pages', $dv)
</body>
</html>
