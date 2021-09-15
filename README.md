# repository_ossbucket 


Instead of giving all users access to your complete OSS account, this plugin makes it
possible to give teachers and managers access to a specific OSS folder (bucket).

Multiple instances are supported, you only have to create a IAM user who has read access
to your OSS root folder, but read and write permissions to your OSS bucket.

Warning:  This plugin is dependent on the local_aws plugin. If you want to use the latest
sdk version, you will have to use the [eWallah version](https://github.com/ewallah/moodle-local_aws) that supports
all new regions.


[![Build Status](https://github.com/ewallah/moodle-repository_s3bucket/workflows/Tests/badge.svg)](https://github.com/ewallah/moodle-repository_s3bucket/actions)
[![Coverage Status](https://coveralls.io/repos/github/ewallah/moodle-repository_s3bucket/badge.svg?branch=main)](https://coveralls.io/github/ewallah/moodle-repository_s3bucket?branch=main)
