@php
    $positions = [
        1 => [
            'date' => 'left: 375pt; top: 70pt; width: 80pt;',
            'dvNumber' => 'left: 375pt; top: 86pt; width: 80pt;',
            'payee' => 'left: 55pt; top: 145pt; width: 170pt;',
            'payeeMask' => 'left: 55pt; top: 137pt; width: 170pt; height: 22pt;',
            'address' => 'left: 55pt; top: 176pt; width: 520pt;',
            'addressMask' => 'left: 55pt; top: 170pt; width: 520pt; height: 20pt;',
            'particularsMask' => 'left: 17pt; top: 218pt; width: 95pt;',
            'particulars' => 'left: 17pt; top: 218pt; width: 190pt;',
            'mfoNumber' => 'left: 290pt; top: 267pt; width: 50pt;',
            'amount' => 'left: 450pt; top: 267pt; width: 130pt; text-align: right;',
            'fundSource' => 'left: 96pt; top: 333pt; width: 145pt;',
            'total' => 'left: 450pt; top: 337pt; width: 130pt; text-align: right;',
        ],
        2 => [
            'date' => 'left: 375pt; top: 64pt; width: 80pt;',
            'dvNumber' => 'left: 375pt; top: 79pt; width: 80pt;',
            'payee' => 'left: 55pt; top: 135pt; width: 170pt;',
            'payeeMask' => 'left: 55pt; top: 129pt; width: 170pt; height: 20pt;',
            'address' => 'left: 55pt; top: 164pt; width: 520pt;',
            'addressMask' => 'left: 55pt; top: 160pt; width: 520pt; height: 20pt;',
            'particularsMask' => 'left: 17pt; top: 204pt; width: 95pt;',
            'particulars' => 'left: 17pt; top: 204pt; width: 190pt;',
            'mfoNumber' => 'left: 290pt; top: 257pt; width: 50pt;',
            'amount' => 'left: 450pt; top: 257pt; width: 130pt; text-align: right;',
            'fundSource' => 'left: 96pt; top: 354pt; width: 145pt;',
            'total' => 'left: 450pt; top: 355pt; width: 130pt; text-align: right;',
            'voucherNumber' => 'left: 17pt; top: 798pt; width: 140pt;',
        ],
        3 => [
            'date' => 'left: 375pt; top: 84pt; width: 80pt;',
            'dvNumber' => 'left: 375pt; top: 99pt; width: 80pt;',
            'payee' => 'left: 55pt; top: 146pt; width: 170pt;',
            'payeeMask' => 'left: 55pt; top: 143pt; width: 170pt; height: 22pt;',
            'address' => 'left: 55pt; top: 176pt; width: 520pt;',
            'addressMask' => 'left: 55pt; top: 172pt; width: 520pt; height: 20pt;',
            'particularsMask' => 'left: 17pt; top: 225pt; width: 95pt;',
            'particulars' => 'left: 17pt; top: 210pt; width: 190pt;',
            'mfoNumber' => 'left: 290pt; top: 279pt; width: 50pt;',
            'amount' => 'left: 450pt; top: 279pt; width: 130pt; text-align: right;',
            'fundSource' => 'left: 96pt; top: 342pt; width: 145pt;',
            'total' => 'left: 450pt; top: 343pt; width: 130pt; text-align: right;',
        ],
    ];
@endphp
@foreach ([1, 2, 3] as $page)
    <div class="page dv-page">
        <img class="template" src="data:image/png;base64,{{ $pageImages[$page] }}" alt="">
        <div class="field single-line" style="{{ $positions[$page]['date'] }}">{{ $date }}</div>
        @if ($dvNumber !== '')
            <div class="field single-line" style="{{ $positions[$page]['dvNumber'] }}">{{ $dvNumber }}</div>
        @endif
        <div class="mask" style="{{ $positions[$page]['payeeMask'] }}"></div>
        <div class="field single-line" style="{{ $positions[$page]['payee'] }}">{{ $payee }}</div>
        <div class="mask" style="{{ $positions[$page]['addressMask'] }}"></div>
        <div class="field single-line" style="{{ $positions[$page]['address'] }}">{{ $address }}</div>
        <div class="field single-line" style="{{ $positions[$page]['particularsMask'] }}">&nbsp;</div>
        <div class="field particulars" style="{{ $positions[$page]['particulars'] }}">{{ $particulars }}</div>
        <div class="field single-line" style="{{ $positions[$page]['mfoNumber'] }}">&nbsp;</div>
        <div class="field single-line" style="{{ $positions[$page]['amount'] }}">{{ $amount }}</div>
        <div class="field single-line" style="{{ $positions[$page]['fundSource'] }}">{{ $fundSource }}</div>
        <div class="field single-line" style="{{ $positions[$page]['total'] }}">{{ $amount }}</div>
        @isset($positions[$page]['voucherNumber'])
            <div class="mask" style="left: 12pt; top: 796pt; width: 155pt; height: 23pt;"></div>
            <div class="field single-line" style="{{ $positions[$page]['voucherNumber'] }}">{{ $voucherNumber }}</div>
        @endisset
    </div>
@endforeach
