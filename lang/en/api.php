<?php

return [
    'header' => [
        'title' => 'API Tokens',
    ],
    'create_token' => [
        'title' => 'Create API Token',
        'description' => 'API tokens allow third-party services to authenticate with our application on your behalf.',
        'token_name' => 'Token Name',
        'permissions' => 'Permissions',
        'created' => 'Created.',
        'create' => 'Create',
    ],
    'manage_tokens' => [
        'title' => 'Manage API Tokens',
        'description' => 'You may delete any of your existing tokens if they are no longer needed.',
        'last_used' => 'Last used',
        'permissions' => 'Permissions',
        'delete' => 'Delete',
    ],
    'token_modal' => [
        'title' => 'API Token',
        'description' => 'Please copy your new API token. For your security, it won\'t be shown again.',
        'close' => 'Close',
    ],
    'permissions_modal' => [
        'title' => 'API Token Permissions',
        'cancel' => 'Cancel',
        'save' => 'Save',
    ],
    'delete_modal' => [
        'title' => 'Delete API Token',
        'description' => 'Are you sure you would like to delete this API token?',
        'cancel' => 'Cancel',
        'delete' => 'Delete',
    ],
];
