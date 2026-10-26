<!DOCTYPE html>
<html>
    <head>
        <style>
            @page {
                size: A4;
                    margin: 10mm;
            }

            body {
                font-family: Arial, sans-serif;
                font-size: 10px;
                padding-left: 10px;
                padding-right: 10px;
                box-sizing: border-box;
                font-size: 13px;

                display: flex;
                flex-direction: column;
                min-height: 100vh;
                gap: 20px;
            }

            .header {
                width: 100%;
                border-collapse: collapse;
            }

            .dswd-container {
                width: 50px;
                vertical-align: top;
                padding-left: 5px;
                padding-right: 5px;
            }

            .bp-container {
                padding-left: 5px;
                padding-right: 5px;
            }

            .dswd-logo {
                width: 200px;
                height: auto;
            }

            .bp-logo {
                width: 70px;
                height: auto;
                padding-bottom: 10px;
            }

            table {
                width: 40%;
                border-collapse: collapse;
            }

            th, td {
                padding: 8px;
            }

            th {
                text-align: left;
                width: 30%;
            }
            .footer {
                margin-top: 10px;
                text-align: center;
            }
            .footer-page {
                font-size: 12px;
                margin: 0 0 10px 0;
            }
            .footer-address {
                border-top: 1px solid black;
                text-align: center;
                font-weight: medium;
                line-height: 1.1;
            }
            .footer-address h6 {
                font-size: 10px;
                font-weight: normal;
                letter-spacing: 1px;
                margin: 0;
            }
            .footer-address h6:last-child {
                font-size: 10px;
            }

            .page {
                page-break-after: always;
            }

            .page:last-child {
                page-break-after: auto;
            }


            .container {
                position: relative;
                height: 20px;
            }

            .creator {
                position: absolute;
                right: 0;
                font-size: 8px;
                line-height: 10px;
            }

            .creator p {
                margin: 0;
            }

            .creator p:first-child {
                margin-bottom: 3px;
            }
            
            .containerforForm{
                position:relative;
                width: 100%;
                height:10px;
            }
            .fillUpForm{
                position:absolute;
                top: -120px;
                right: 0;
                font-size: 10px;
                width: 230px;
                font-family: Arial, sans-serif;
            }

            .receivedByTable {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            .receivedByTable tr {
                width: 100%;
                border: 1px solid black;
            }

            .receivedByTable td {
                text-align: left;
                padding: 3px;
                border: 0;
            }

            .receivedByTable tr td:first-child {
                width: 50%;
            }

            .receivedByTable tr td:last-child {
                width: 50%;
            }
        </style>
    </head>

    <body>
        <div class="page">
            <table class="header">
                <tr>
                    <td class="dswd-container">
                        <img src="{{ public_path('dswd_header.png') }}" class="dswd-logo" alt="DSWD">
                    </td>

                    <td class="bp-container">
                        <img src="{{ public_path('bplogo.png') }}" class="bp-logo" alt="Bagong Pilipinas">
                    </td>
                </tr>
            </table>

            <p style="font-weight:bold;padding-bottom:10px;padding-top:10px;">
                GL No. {{ $gl->gl_no }} <br/>
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
                {{ $glDate }}
            </p>

            <div style="font-size:13px;font-weight:bold;line-height: 0.1;text-transform: uppercase;padding-bottom:10px;padding-top:10px;">
                <p> {{ $gl->serviceProvider->addressee ?? 'Mr/Mrs/Ms geronimo' }} </p>
                <p> {{ $gl->serviceProvider->designation ?? 'President' }} </p>
                <p> {{ $gl->serviceProvider->name ?? 'Hospital Name' }} </p>
                <p> {{ $gl->serviceProvider->address ?? 'Taft, Metro Manila' }} </p>

            </div>

            <p style="padding-bottom:5px;padding-top:10px;">
                {{ $gl->serviceProvider->salutation }}, <br/>
                This has reference to the request for Medical Assistance of herein client {{ $gl->client->full_name }}, from 
                {{ $gl->client->address }} for his relative {{ $gl->beneficiary->full_name }}.
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                The Department of Social Welfare and Development has assessed and validated the said request for
                assistance through the Crisis Intervention Program. Thus, the Department is issuing this letter to guarantee
                the payment of the bill in the amount of {{ $glAmountName }} Pesos Only (Php{{ number_format($gl->gl_amount, 2) }}).
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                To facilitate the payment, please submit to the Crisis Intervention Program through {{ $gl->category->first_reviewer }}
                or {{ $gl->category->second_reviewer }} the following documents for the preparation of Disbursement Voucher within one
                week after the service has been completed.
            </p>

            <ul style="padding-bottom:10px;padding-top:10px;">
                <li>
                    Guarantee Letter (GL) from the DSWD
                    with your company's "received" stamp
                </li>

                <li>
                    Statement of Accounts (SOA) or
                    Billing Statement addressed to DSWD
                </li>

                <li>
                    Sales / Service Invoice
                </li>
            </ul>

            <p style="padding-bottom:10px;padding-top:10px;">
                Please be informed that said payment will be directly deposited to your company’s bank account. Should
                there be any query, you may call us at (02)962-28-13 or (02)951-74-33.
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
                For your consideration.
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
                Thank you.
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
            Approved by:
            </p>

            <div style="height:55px;"></div>

            <div style="line-height: 0.1;text-transform: uppercase;padding-top:20px;">
                <p><strong> {{ $gl->signatory->name }} </strong></p>
                <p> {{ $gl->signatory->designation }}{{ $gl->signatory->office ? ', '.$gl->signatory->office : '' }} </p>
            </div>
            
            <div style="line-height: 0.1;text-transform: uppercase;padding-top:20px;">
                <p style="font-size:8px;"><strong> Valid until {{ $glValidity }} </strong></p>
                <p style="font-size:8px;"> *validity period includes the time of receipt of the guarantee letter by the service provider </p>
                <p style="font-size:8px;"> *not valid without official dryseal </p>
            </div>

            <footer class="footer">
                <div class="footer-page">
                    PAGE 1 of 1
                </div>

                <div class="footer-address">
                    <h6> DSWD Central Office, Batasan Pambansa Complex, Constitution Hills, Quezon City, Philippines 1126</h6>
                    <h6>Website: <span style="text-decoration: underline">http://www.dswd.gov.ph</span> Tel Nos.: (632) 8 931-8101 to 07 Telefax: (632) 8 931-8191 </h6>
                </div>
            </footer>
        
        </div>
        <div class="page">
            <table class="header">
                <tr>
                    <td class="dswd-container">
                        <img src="{{ public_path('dswd_header.png') }}" class="dswd-logo" alt="DSWD">
                    </td>

                    <td class="bp-container">
                        <img src="{{ public_path('bplogo.png') }}" class="bp-logo" alt="Bagong Pilipinas">
                    </td>
                </tr>
            </table>
                        
            <div class="container">

                <!-- other content -->

                <div class="creator">
                    <p>Created by: {{ $gl->creator->email }}</p>
                    <p><strong>Time & Date Created: {{ $glCreatedAt }}</strong></p>
                </div>

            </div>
            
            <p style="font-weight:bold;padding-bottom:10px;padding-top:10px;">
                GL No. {{ $gl->gl_no }} <br/>
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
                {{ $glDate }}
            </p>

            <div style="font-size:13px;font-weight:bold;line-height: 0.1;text-transform: uppercase;padding-bottom:10px;padding-top:10px;">
                <p> {{ $gl->serviceProvider->addressee ?? 'Mr/Mrs/Ms geronimo' }} </p>
                <p> {{ $gl->serviceProvider->designation ?? 'President' }} </p>
                <p> {{ $gl->serviceProvider->name ?? 'Hospital Name' }} </p>
                <p> {{ $gl->serviceProvider->address ?? 'Taft, Metro Manila' }} </p>

            </div>

            
            <p style="padding-bottom:5px;padding-top:10px;">
                {{ $gl->serviceProvider->salutation }}, <br/>
                This has reference to the request for Medical Assistance of herein client {{ $gl->client->full_name }}, from 
                {{ $gl->client->address }} for his relative {{ $gl->beneficiary->full_name }}.
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                The Department of Social Welfare and Development has assessed and validated the said request for
                assistance through the Crisis Intervention Program. Thus, the Department is issuing this letter to guarantee
                the payment of the bill in the amount of {{ $glAmountName }} Pesos Only (Php{{ number_format($gl->gl_amount, 2) }}).
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                To facilitate the payment, please submit to the Crisis Intervention Program through {{ $gl->category->first_reviewer }}
                or {{ $gl->category->second_reviewer }} the following documents for the preparation of Disbursement Voucher within one
                week after the service has been completed.
            </p>

            <ul style="padding-bottom:10px;padding-top:10px;">
                <li>
                    Guarantee Letter (GL) from the DSWD
                    with your company's "received" stamp
                </li>

                <li>
                    Statement of Accounts (SOA) or
                    Billing Statement addressed to DSWD
                </li>

                <li>
                    Sales / Service Invoice
                </li>
            </ul>

            <p style="padding-bottom:5px;padding-top:5px;">
                Please be informed that said payment will be directly deposited to your company’s bank account. Should
                there be any query, you may call us at (02)962-28-13 or (02)951-74-33.
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                For your consideration.
            </p>

            <p style="padding-bottom:5px;padding-top:5px;">
                Thank you.
            </p>

            <p style="padding-bottom:10px;padding-top:10px;">
            Approved by:
            </p>

            <div class="containerforForm">
                <div class="fillUpForm">
                    <p><strong>RECIEVED BY:</strong></p>
                    <table class="receivedByTable">
                        <tr>
                            <td colspan="2">FULL NAME:</td>
                        </tr>
                        <tr>
                            <td colspan="2">SIGNATURE:</td>
                        </tr>
                        <tr>
                            <td colspan="2">CONTACT NO:</td>
                        </tr>
                        <tr>
                            <td>DATE:</td>
                            <td>TIME:</td>
                        </tr>
                        <tr>
                            <td colspan="2">RELEASED BY:</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div style="height:55px;"></div>

            <div style="line-height: 0.1;text-transform: uppercase;padding-top:20px;">
                <p><strong> {{ $gl->signatory->name }} </strong></p>
                <p> {{ $gl->signatory->designation }}{{ $gl->signatory->office ? ', '.$gl->signatory->office : '' }} </p>
            </div>
            
            <div style="line-height: 0.1;text-transform: uppercase;padding-top:20px;">
                <p style="font-size:8px;"><strong> Valid until {{ $glValidity }} </strong></p>
                <p style="font-size:8px;"> *validity period includes the time of receipt of the guarantee letter by the service provider </p>
                <p style="font-size:8px;"> *not valid without official dryseal </p>
            </div>


            <footer class="footer">
                <div class="footer-page">
                    PAGE 1 of 1
                </div>

                <div class="footer-address">
                    <h6> DSWD Central Office, Batasan Pambansa Complex, Constitution Hills, Quezon City, Philippines 1126</h6>
                    <h6>Website: <span style="text-decoration: underline">http://www.dswd.gov.ph</span> Tel Nos.: (632) 8 931-8101 to 07 Telefax: (632) 8 931-8191 </h6>
                </div>
            </footer>

        </div>
    </body>
</html>
