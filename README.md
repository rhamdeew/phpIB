phpIB
=====

Simple incremental backup script written on php.
Works as a replacement for the standard backups functional ISPmanager.
The advantage of this script is to support incremental backups.

**REQUIREMENTS:**
- ISPmanger
- php-cli
- rsync
- tar
- gzip
- pigz (optional)
- aws (optional)
- curl (optional)
- sendmail (optional)

**USAGE:**

1. Copy config-sample.php to config.php
2. Edit config.php
3. Add in your crontab task

``
30 6 * * * /usr/bin/php /root/phpIB/launch.php
``

**OPTIONAL AMAZON S3 CONFIG:**

1. http://docs.aws.amazon.com/cli/latest/userguide/cli-chap-getting-started.html

**TODO:**

1. ~~Add support to exclude files and directories~~

2. ~~Add mail report with stats and status~~

3. ~~Add filesize limit && split~~

4. ~~Rewrite code to OOP~~

5. Not backup disabled users

6. Complete Test Amazon S3 Upload

7. Support backup without installed ISPmanager 
