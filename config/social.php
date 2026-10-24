<?php

/*
| Media › Social media. The keys come from GitHub secrets (see deploy.yml);
| a platform shows a "Connect" button once its keys are set.
*/

return [
    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        // Facebook Login for Business: the configuration that says which pages, accounts and permissions are asked.
        'config_id' => env('META_CONFIG_ID'),
        'version' => env('META_GRAPH_VERSION', 'v23.0'),
        'scopes' => ['pages_show_list', 'pages_read_engagement', 'pages_read_user_content', 'pages_manage_posts', 'read_insights', 'business_management',
            'instagram_basic', 'instagram_content_publish', 'instagram_manage_insights'],
        // Page and Instagram metrics asked per day; one that Meta retires is simply skipped.
        'page_metrics' => ['reach' => 'page_impressions_unique', 'views' => 'page_media_view', 'views_old' => 'page_impressions', 'engagements' => 'page_post_engagements'],
        'ig_metrics' => ['reach' => 'reach', 'views' => 'views', 'engagements' => 'total_interactions', 'clicks' => 'website_clicks'],
    ],
    'linkedin' => [
        'client_id' => env('LINKEDIN_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_CLIENT_SECRET'),
        'version' => env('LINKEDIN_API_VERSION', '202509'),
        'scopes' => ['r_organization_social', 'w_organization_social', 'rw_organization_admin'],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'scopes' => ['https://www.googleapis.com/auth/business.manage', 'https://www.googleapis.com/auth/youtube.readonly', 'https://www.googleapis.com/auth/yt-analytics.readonly'],
    ],

    // Per platform: the providers that connect them, the longest text, and whether a picture or video is required.
    'platforms' => [
        'facebook' => ['provider' => 'meta', 'max' => 63206, 'media' => false, 'publish' => true],
        'instagram' => ['provider' => 'meta', 'max' => 2200, 'media' => true, 'publish' => true],
        'linkedin' => ['provider' => 'linkedin', 'max' => 3000, 'media' => false, 'publish' => true],
        'google' => ['provider' => 'google', 'max' => 1500, 'media' => false, 'publish' => false],
        'youtube' => ['provider' => 'google', 'max' => 5000, 'media' => true, 'publish' => false],
        'tiktok' => ['provider' => null, 'max' => 2200, 'media' => true, 'publish' => false],
        'threads' => ['provider' => null, 'max' => 500, 'media' => false, 'publish' => false],
    ],

    // Background work: figures every 3 hours, posts every 6 hours (per account).
    'sync_hours' => 3,
    'posts_hours' => 6,
];
