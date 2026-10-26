@php
    $positions = [
        1 => [
            'orsNumber' => 'left: 435pt; top: 38pt; width: 127pt;',
            'date' => 'left: 447pt; top: 63pt; width: 115pt;',
            'payee' => 'left: 79pt; top: 131pt; width: 495pt;',
            'address' => 'left: 79pt; top: 188pt; width: 495pt;',
            'particularsMask' => 'left: 79pt; top: 236pt; width: 95pt;',
            'particulars' => 'left: 79pt; top: 236pt;',
            'mfoNumber' => 'left: 352pt; top: 359pt; width: 42pt;',
            'amount' => 'left: 499pt; top: 359pt; width: 80pt; text-align: right;',
            'fundSource' => 'left: 157pt; top: 398pt; width: 164pt;',
            'total' => 'left: 499pt; top: 475pt; width: 80pt; height: 10pt; line-height: 10pt; text-align: right;',
            'time' => 'left: 9pt; top: 796pt; width: 50pt;',
            'footerDate' => 'left: 106pt; top: 796pt; width: 75pt;',
            'person' => 'left: 239pt; top: 796pt; width: 80pt;',
            'voucherNumber' => 'left: 405pt; top: 796pt; width: 170pt;',
        ],
        2 => [
            'orsNumber' => 'left: 449pt; top: 55pt; width: 113pt;',
            'date' => 'left: 451pt; top: 75pt; width: 110pt;',
            'payee' => 'left: 93pt; top: 143pt; width: 481pt;',
            'address' => 'left: 93pt; top: 198pt; width: 481pt;',
            'particularsMask' => 'left: 93pt; top: 244pt; width: 95pt;',
            'particulars' => 'left: 93pt; top: 244pt;',
            'mfoNumber' => 'left: 359pt; top: 369pt; width: 43pt;',
            'amount' => 'left: 499pt; top: 369pt; width: 80pt; text-align: right;',
            'fundSource' => 'left: 173pt; top: 407pt; width: 155pt;',
            'total' => 'left: 499pt; top: 488pt; width: 80pt; height: 10pt; line-height: 10pt; text-align: right;',
            'time' => 'left: 9pt; top: 810pt; width: 50pt;',
            'footerDate' => 'left: 107pt; top: 810pt; width: 75pt;',
            'person' => 'left: 239pt; top: 810pt; width: 80pt;',
            'voucherNumber' => 'left: 405pt; top: 810pt; width: 170pt;',
        ],
    ];
@endphp
@foreach ([1, 2] as $page)
    <div class="page ors-page">
        <img class="template" src="data:image/png;base64,{{ $pageImages[$page] }}" alt="">
        <div class="field" style="{{ $positions[$page]['orsNumber'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['date'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['payee'] }}">{{ $payee }}</div>
        <div class="field" style="{{ $positions[$page]['address'] }}">{{ $address }}</div>
        <div class="field" style="{{ $positions[$page]['particularsMask'] }}">&nbsp;</div>
        <div class="particulars" style="{{ $positions[$page]['particulars'] }}">{{ $particulars }}</div>
        <div class="field" style="{{ $positions[$page]['mfoNumber'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['amount'] }}">{{ $amount }}</div>
        <div class="field" style="{{ $positions[$page]['fundSource'] }}">{{ $fundSource }}</div>
        <div class="field" style="{{ $positions[$page]['total'] }}">{{ $amount }}</div>
        @if ($page === 2)
            <div class="footer-mask" style="left: 0; top: 806pt; width: 595pt; height: 24pt;"></div>
        @endif
        <div class="field" style="{{ $positions[$page]['time'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['footerDate'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['person'] }}">&nbsp;</div>
        <div class="field" style="{{ $positions[$page]['voucherNumber'] }}">{{ $voucherNumber }}</div>
    </div>
@endforeach
