# HAProxy Cluster Control (haproxycc)

CLI for managing clusters of HAProxy servers via HTTP

## Installation

Download `haproxycc.phar`, copy to `/usr/local/sbin`, set executable permissions, and optionally rename to `haproxycc`

## Configuration

### HAProxy

```
listen stats :8000
  mode http
  stats uri /stats
  stats auth username:password
  stats realm HAProxy
  stats admin if TRUE
```

### haproxycc

Create `haproxycc.config.php` in `HOME` or `/etc`:

```php
<?php

return [
    'environments' => [
        'qa' => [
            'servers'  => [
                // FQDN of each haproxy server
            ],
            'port'     => 8000,
            'baseUrl'  => '/stats',
            'username' => 'username',
            'password' => 'password'
        ]
    ],
    'backend_nickname_map' => [
        // optional, association of nicknames to backend names (as set in the haproxy config)
        // i.e. 'www' => 'www_http'
    ]
];
```

## Usage

List available commands with: ```haproxycc list```