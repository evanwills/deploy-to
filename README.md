# `deploy-to`

* [Introduction](#introduction)
* [Arguments](#arguments)
* [Configuration](#configuration)
  * [`servers` configuration](#servers-config)
* [Deploying files](#deploying-files)
* [Globally executable](#globally-executable)
* [Authentication](#authentication)
* [`.lastDeployment-`*`target`*](#lastdeployment-target)
---


## Introduction

`deploy-to.sh` allows you to quickly and easily deploy recently 
updated files to a chosen environment.

--- 
## Arguments

The script expects up to two arguments.

If no arguments are supplied, recently updated files are deployed
to the default environment. 

e.g.

```bash
deployto
```

If *"force"* is passed as an argument, all eligible files will be
uploaded to the environment specified by `default` in the 
`deploy-to.json` config file regardless of when they were updated.

e.g.
```bash
deployto force
```

If an argument matches the name of one of the deployment targets, 
the files will be uploaded to that server.

e.g.
```bash
deployto prod
```

If two arguments are passed, one of which is "force", all eligible 
files will be uploaded to the appropriate environment, regardless 
of when they were updated.

e.g.
```bash
deployto prod force
```
or 
```bash
deployto force prod
```

This script depends on a PHP script (`deploy-to.php`) to do the
heavy lifting of working out which files out of all the eligible
files should be deployed. deploy-to.php generates a Bash script
that is then executed by deploy-to.sh before being deleted.

> NOTE: This file does not hold any of the required values for
>       uploading. Instead it extracts config data from the
>       `deploy-to.json` file in the current working directory

> NOTE ALSO: If you wish to initialise a deployment script for an
>      application, you can pass "new" as the first argument and
>      a new deployt-to.json file will be created in the current
>      working directoy.

> FINAL NOTE: There are times when you want to deploy all eligible
>       files regardless of when they were updated. You can do this
>       by passing "force" as the only argument, or the second to
>       this script

---

## Configuration

You need a `deploy-to.json` file in the root directory of the 
application you wish to deploy to an environment.

```json
{
  "default": "local",
  "servers": [
    {
      "name": "prod",
      "aliases": [],
      "host": "",
      "user": "username",
      "path": "/var/www/html/"
    },
    {
      "name": "dev",
      "aliases": ["test"],
      "host": "",
      "user": "username",
      "path": "/var/www/html/"
    },
    {
      "name": "local",
      "aliases": [],
      "host": "",
      "user": "username",
      "path": "/var/www/html/"
    }
  ],
  "sourceList": [

  ]
}
```
* `default` *{`string`}* Sets the default target deployment server so 
  when no other arguments are passed, files are automatically 
  deployed to the default server
* `servers` *{`object[]`}* List of details for each deployment target 
  the application can deploy to (see [`servers`](#servers) for more 
  info on config for each server.)
* `sourceList` *{`string[]`}* List of files and/or directories in the 
  application that should be deployed.

  Items can be any of the following format:

  * `relative-path/` All the files in a directory
  * `filename` or `relative-path/filename` single file to be uploaded 
    to the matching path on the deployment target (relative to the 
    application root)
  * `*.ext` or `relative-path/*.ext` to upload all the files matching 
    a given extension to the target server

  > __NOTE__ All items should be relative to the repos root on your local file system

  example `sourceList`:
  ```json
  {
    "sourceList": [
      "_inc",               // everything in `_inc/` (including sub-directories and ALL their children)
      "_config",            // everything `_config/` (including sub-directories and ALL their children)
      "classes/*.php",      // Only PHP files in `classes/`
      "classes/data.json",  // Specific file in `classes/`
      "templates",          // everything in `templates/` (including sub-directories and ALL their children)
      "taxPDF.php",         // Specific file
      "index.php",          // Specific file
      "responseOnline.php", // Specific file
      "test-reconcile.php", // Specific file
      "composer.json",      // Specific file
      "*.md",               // All MarkDown files in the application root
      ".ENV",               // Specific file
      "js",                 // Everything in `js/` (including sub-directories and ALL their children)
      "style"               // Everything in `style/` (including sub-directories and ALL their children)
    ]
  }
  ```


### `servers` config

Server objects have four required properties and one optional 
property

* `name` *{`string`}* - [required] The name used to identify which 
  server to deployto
* `host` *{`string`}* - [required] Server host/domain name or IP 
   address of deployment target
* `user` *{`string`}* - [required] Username to use when connecting to 
  the server
  > __NOTE__ Ideally you have a SSH key authentication enabled so you 
    don't have to keep entering your password
* `path` *{`string`}* - [required] File system path to application root 
   on the deployment target server
   > __NOTE__ If your application is split between public and 
     non-public parts of the server, the path must be the deepest 
     common path used by all parts of the server e.g. if your 
     application is split between `/var/www/html/app-name/` and 
     `/var/www/includes/app-name/` then `path` should be `/var/www/` 
     and your repo should be structured so that the copy will work 
     like so: `scp [relative-path] [user]@host:/path/[relative-path]`
* `aliases` *{`string[]`}* - [optional] List of alternate names for the 
  same deployment target. <br />
  E.g. An appliation only has two enviroments: Production & Testing, 
  And for that application, The "Testing" server is also called 
  "Dev", Then the `name` should be *'test'* and `aliases` could have 
  all of the following:, *'dev'*, *'development'*, *'testing'* so 
  when you call deployto you could pass the name or any of the 
  aliases and it will always deploy to the right place<br /> 
  e.g. `deployto `*`dev`* is the same as `deployto `*`test`*

---

## Deploying files

```bash
$ /bin/sh /[path to where deployto files are stored]/deploy-to.sh [deployment target]
```

Or if you've set up a bash alias:
```bash
$ deployto [?deployment target] [?force]
```

---

## Globally executable

It is recommended that you set up a global command alias 
(e.g. `deployto`) in your `.bashrc` file to allow you to deploy 
from any directory with a `deploy-to.json` config file

To make it super easy to do, add the following alias to your `.bashrc` file

```bash
alias deployto='/bin/sh /[path to where deploy-to files are stored]/deploy-to.sh';
```
This will allow you to execute

---

## Authentication

`deploy-to` expects you have passwordless SSH Key authetication
set up for each environment. Otherwise you will have to enter you 
password many times, which is tedious.

---

## `.lastDeployment-`*`[target]`*

Each time deploy-to.sh is run it creates an empty `.lastDeployment` 
file for the deployment target. The modified time is checked next 
time you run deploy-to.sh for that target. Only files that have been 
updated since the modified time of the `.lastDeployment-`*`[env]`* 
will be deployed. If you delete this file it will be the same as 
running  `deployto `*`[target]`*` force`

---

Author:  Evan Wills<br />
Created: 2021-09-23 15:08 <br />
Updated: 2022-12-16 11:07
