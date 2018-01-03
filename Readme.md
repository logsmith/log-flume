![log-flume-logo](https://cloud.githubusercontent.com/assets/1636310/24171665/407f51a2-0e7d-11e7-974f-f80e0c45e1ed.jpg)

This allows developers to sync WordPress media libraries between machines over Amazon S3.

It can also be used for backing up websites or even moving websites between servers

### How Log Flume talks to S3

The setup will ask you to add these constants to your wp-config.php file:

- LOG_FLUME_REGION
- LOG_FLUME_ACCESS_KEY_ID
- LOG_FLUME_SECRET_ACCESS_KEY

```
define('LOG_FLUME_REGION','eu-west-2'); //London
define('LOG_FLUME_ACCESS_KEY_ID','');
define('LOG_FLUME_SECRET_ACCESS_KEY','');
```

You can obtain these details by creating an IAM user. Here is [our guide](https://github.com/logsmith/log-flume/wiki/Getting-AWS-credentials) on how to setup an IAM Amazon user and get the access and secret key that you need.

# Installation

Add the Wordpress plugin to your composer file by running:

```
composer require logsmith/log-flume
```

Then open a terminal and run (will will need the constants above):

```
wp logflume setup
```

# Setting up a cronjob



```
/usr/local/bin/wp logflume backup --path=/path/to/www.website.co.uk
```

# Functions

**logflume backup**
> This function runs `sync` and a DB backup. 

**logflume check_credentials**
> Sees if the saved credentials can give access to 
logflume select_bucket
logflume setup <bucket_name> [--expiry=<number-of-days>]
logflume sync [--direction=<up-or-down>]

