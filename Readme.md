![log-flume-logo](https://cloud.githubusercontent.com/assets/1636310/24171665/407f51a2-0e7d-11e7-974f-f80e0c45e1ed.jpg)

This allows developers to sync WordPress media libraries between machines. Currently the focus is on development setups and is not intended to be used for transferring media between live and development environments.

# Installation

Add the Wordpress plugin to your composer file:

```
composer require logsmith/log-flume
```

Add these constants to your wp-config.php file:

- AWS_ACCESS_KEY_ID
- AWS_SECRET_ACCESS_KEY

```
define('AWS_ACCESS_KEY_ID','xxxxxx');
define('AWS_SECRET_ACCESS_KEY','xxxxxx');
```

### Don't have AWS details?

Here is [our guide](https://github.com/logsmith/log-flume/wiki/Getting-AWS-credentials) on how to setup an IAM Amazon user and get the access and secret key that you need.

# Todo

- ~~Get assets to upload from S3~~
- ~~Get assets to download from S3~~
- ~~Create a bucket selection process~~
- ~~Create a config guide is AWS creds aren't available~~
- ~~Create a warning if AWS creds aren't correct~~
- ~~File table: Total count isn't working~~
- ~~File table: Pagination isn't working~~
- ~~Hide sync button if nothing is actually available to sync~~
- ~~Hide tabs that shouldn't be seen~~
- Create a bucket creation process
- There needs to be a list of skippable/ignored files
- Get assets to sync Asynchronously
