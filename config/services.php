<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pdf_signer' => [
        'base_url' => env('PDF_SIGNER_BASE_URL', 'https://pdf-signer-staging.dswd.gov.ph'),
        'project_folder' => env('PDF_SIGNER_PROJECT_FOLDER'),
        'page_index' => (int) env('PDF_SIGNER_PAGE_INDEX', 0),
        'x' => (int) env('PDF_SIGNER_X', 40),
        'y' => (int) env('PDF_SIGNER_Y', 150),
        'width' => (int) env('PDF_SIGNER_WIDTH', 180),
        'height' => (int) env('PDF_SIGNER_HEIGHT', 55),
        'additional_signature_placements' => env('PDF_SIGNER_ADDITIONAL_PLACEMENTS', '1,40,150,180,55'),
    ],

];
