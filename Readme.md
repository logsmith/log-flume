# Log Flume

This plugin is for syncing Wordpress assets between developer machines over Amazon S3.

# Todo

- ~~Get assets to upload from S3~~
- ~~Get assets to download from S3~~
- Create a bucket selection process
- Create a bucket creation process
- ~~Create a config guide is AWS creds aren't available~~
- Create a config guide is AWS creds aren't correct
- Get assets to sync Asynchronously

# Installation

Add the plugin to your composer file:

```
composer require logsmith/logflume
```

Add these constants to your wp-config.php file:

- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY

```
define('AWS_ACCESS_KEY_ID','xxxxxx');
define('AWS_SECRET_ACCESS_KEY','xxxxxx');
```

# Changelog

= 0.0.1 =
* Got basic upload working with S3

= 0.0.2 =
* Got basic download working with S3

= 0.0.3 =
* Tetsing and debugging

= 0.0.4 =
* Added AWS settings detection and helpers to admin page
