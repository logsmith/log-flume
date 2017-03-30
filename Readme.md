![log-flume-logo](https://cloud.githubusercontent.com/assets/1636310/24171665/407f51a2-0e7d-11e7-974f-f80e0c45e1ed.jpg)

This allows developers to sync WordPress media libraries between machines over Amazon S3. Currently the focus is on local setups and is not intended to be used for transferring media between live and development environments.

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

# Feature requests

Got a feature request? Add an issue! Simple....
