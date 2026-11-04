<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging
    |--------------------------------------------------------------------------
    |
    | Credentials come from a Firebase service-account JSON file. When the
    | keys below are empty, push silently degrades to an in-app notification
    | (already persisted) and a debug log — nothing breaks.
    |
    */

    'project_id' => env('FCM_PROJECT_ID'),

    // Path to the Firebase service account JSON file.
    'service_account_path' => env('FCM_SERVICE_ACCOUNT_JSON'),

    // Default notification sound / channel used on Android.
    'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'farmmantra_notifications'),

    'android_channel_name' => 'Farmmantra Notifications',

    'android_channel_description' => 'Order, payment, delivery and support alerts',

    // FCM HTTP v1 endpoint (do not change).
    'endpoint' => 'https://fcm.googleapis.com/v1/projects/%s/messages:send',

    'timeout' => 10,
];
