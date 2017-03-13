# Log Flume

This plugin is for syncing Wordpress assets between developer machines over Amazon S3.

# Todo

- ~~Get assets to upload from S3~~
- ~~Get assets to download from S3~~
- Create a bucket selection process
- Create a bucket creation process
- Create a config guide is AWS creds aren't available
- Create a config guide is AWS creds aren't correct
- Get assets to sync Asynchronously

# Installation

Add the plugin to your composer file:

```
composer require logsmith/logflume
```

Add these constants to your wp-config.php file:

- AWS_BUCKET
- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY

```
define('AWS_BUCKET','xxxxxx');
define('AWS_ACCESS_KEY_ID','xxxxxx');
define('AWS_SECRET_ACCESS_KEY','xxxxxx');
```

# Changelog

= 0.0.1 =
* Got basic upload and download working with S3
