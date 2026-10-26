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
        .field { position: absolute; background: #fff; font-size: 8pt; line-height: 12pt; height: 12pt; overflow: hidden; white-space: nowrap; }
        .particulars { position: absolute; font-size: 8pt; line-height: 12pt; width: 230pt; white-space: pre-line; }
        .footer-mask { position: absolute; background: #fff; }
    </style>
</head>
<body>
@include('printing.voucher_ors_pages')
</body>
</html>
