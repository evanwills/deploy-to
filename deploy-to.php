<?php

/**
 * This file is to be used by deploy-to.sh to create a bash script
 * with a custom list of files to be uploaded
 *
 * It is assumed that this script will be called by the shell script
 * deploy-to.sh
 *
 * It expects two or three parameters:
 * 1. (environment) A string that matches one of the "name"s of
 *                  servers listed in deploy-to.json in the current
 *                  working directory
 * 2. (scriptName)  The name of the file the output of this script
 *                  will be written to
 * 3. (force)       [optional] Whether or not to force deploying all
 *                  files regardless of when they were updated
 *
 * It depends on the presence of deploy-to.json in the current
 * working directory to provide the necessary config to be able to
 * select which files are to be deployed to the appropriate server.
 * (see deploy-to.json in this directory for template)
 *
 * PHP version 7.4
 *
 * @category Deployto
 * @package  Deployto
 * @author   Evan Wills <evan.wills@acu.edu.au>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/evanwills/useful-bash-scripts
 */



// ===================================================================
// START: Boot-strapping


$debugPath =  realpath(__DIR__.'/../../../ACU/PHP/debug.inc.php');

if (is_string($debugPath) && substr($debugPath, -13) === 'debug.inc.php'
    && is_file($debugPath)
) {
    include_once $debugPath;
} else {
    function debug() { } // phpcs:ignore
}

define('PWD', realpath($_SERVER['PWD']).DIRECTORY_SEPARATOR);

require_once realpath(__DIR__.'/deploy-to.inc.php');

//  END:  Boot-strapping
// ===================================================================
// START: Initial setup & validation


/**
 * Name of the config data source for this script
 *
 * @var string
 */
$jsonFile = 'deploy-to.json';

/**
 * Absolute file path to the config data source for this script
 *
 * @var string
 */
$json = PWD.$jsonFile;

if (!is_file($json)) {
    trigger_error(
        __FILE__." expects to be called from a directory that ".
        "contains a $jsonFile file, used to define what is ".
        "to be deployed & where it should be deployed to\n".
        PWD." does not cotain a file named \"$jsonFile\"\n",
        E_USER_ERROR
    );
} else {
    try {
        $data = json_decode(file_get_contents($json));
    } catch (Exception $e) {
        trigger_error(
            $json.' contained invalid JSON: '.$e->getMessage(),
            E_USER_ERROR
        );
    };

    if (!property_exists($data, 'default')
        || !is_string($data->default)
        || trim($data->default) === ''
    ) {
        trigger_error(
            $json.' does not contain the name of a default server',
            E_USER_ERROR
        );
    }

    if (!property_exists($data, 'servers')
        || !is_array($data->servers)
        || count($data->servers) === 0
    ) {
        trigger_error(
            $json.' does not contain any server details',
            E_USER_ERROR
        );
    }

    if (!property_exists($data, 'sourceList')
        || !is_array($data->sourceList)
        || count($data->sourceList) === 0
    ) {
        trigger_error(
            $json.' does not contain any files to deploy',
            E_USER_ERROR
        );
    }
    if (!property_exists($data, 'sleep') || !is_int($data->sleep * 1)) {
        $sleepDuration = 0;
    } else {
        $sleepDuration = $data->sleep;
    }
}

if (!array_key_exists('argv', $_SERVER)) {
    trigger_error(
        __FILE__.' must be called via called via the commandline '.
        'and have a Server "argv" value',
        E_USER_ERROR
    );
}
if ($_SERVER['argc'] < 2) {
    trigger_error(
        __FILE__." expects at least one parameter to be passed: \n".
        "       file name - The name of the output script file if items are\n".
        "                   found to deploy\n\n".
        E_USER_ERROR
    );
}

/**
 * Whether or not it's OK to proceed with executing the rest
 * of this script
 *
 * @var boolean
 */
$ok = false;

/**
 * Object containing basic information about the server selected
 * to deploy to
 *
 * @var object|false (FALSE if no target server was found)
 */
$server = false;


/**
 * Object containing basic information about the default server
 * to deploy to
 *
 * @var object|false (FALSE if no default server was found)
 */
$defaultServer = false;

$tmpEnv = (array_key_exists(2, $_SERVER['argv']) && trim($_SERVER['argv'][2]) !== '')
    ? trim($_SERVER['argv'][2])
    : '[[DEFAULT]]'; // "[[default]]" should not match any server name

for ($a = 0; $a < count($data->servers); $a += 1) {
    /**
     * Details about one of the deployment targets for the
     * current context
     *
     * @var object
     */
    $tmp = $data->servers[$a];

    if (!is_object($tmp)) {
        trigger_error(
            'Every item in the Servers list must be an object. Item '.
            ($a + 1).' is '.gettype($tmp),
            E_USER_ERROR
        );
    }

    if (!property_exists($tmp, 'name')
        || !property_exists($tmp, 'host')
        || !property_exists($tmp, 'user')
        || !property_exists($tmp, 'path')
    ) {
        trigger_error(
            'Every item Servers must be an object '.
            'containing the following properties: '.
            '"name", "host", "user", "path"'.
            ($a + 1).' is '.gettype($tmp),
            E_USER_ERROR
        );
    }

    if (isRightServer($tmp, $tmpEnv)) {
        $server = $tmp;
        $ok = true;
        break;
    } elseif ($tmp->name === $data->default && $server === false) {
        // We'll set the default server as the selected server
        // but keep checking in case we can match a specific server
        $server = $tmp;
    }
}

if ($server === false) {
    trigger_error(
        'Could not find any information about the server we should '.
        'push to',
        E_USER_ERROR
    );
}

/**
 * Regular expression to ensure that file name matches expected
 * output file name
 *
 * @var string
 */
$regex = '/^deployList__20(?:\d{2}-){3}(?:-\d{2}){3}\.sh$/i';

$_SERVER['argv'][1] = trim($_SERVER['argv'][1]);
if (is_string($_SERVER['argv'][1])
    && preg_match($regex, $_SERVER['argv'][1])
) {
    /**
     * Absolute path to where the bash script generated by this
     * PHP script will be stored.
     *
     * @var string
     */
    $fileName = PWD.$_SERVER['argv'][1];
    $ok = true;
} else {
    trigger_error(
        __FILE__." expects the second argument to be a string \n".
        "for the file name for the shell script to\n".
        "do the actual work of uploading files.",
        E_USER_ERROR
    );
}

if ($ok === false) {
    echo "\n\nEnding now due to insufficient or unreliable supplied data\n\n";
    exit;
}

/**
 * Absolute path to where the time check file for the last
 * deployment is stored.
 *
 * @var string
 */
$timeCheckFile = PWD.'.lastDeployment-'.$server->name;

if (array_key_exists(3, $_SERVER['argv']) && $_SERVER['argv'][3] === 'force') {
    /**
     * Unix timestamp for when the last deployment to the chosen
     * environment was run
     *
     * @var integer
     */
    $sinceTime = 0;
} else {
    if (is_file($timeCheckFile)) {
        $sinceTime = filemtime($timeCheckFile);
    } else {
        $sinceTime = 0;
    }
}

/**
 * Basic SCP user/host identifier
 *
 * @var string
 */
define('HOST', $server->user.'@'.$server->host.':');

/**
 * SCP Username, host and root path for application files are
 * being uploaded to
 *
 * @var string
 */
$host = HOST.$server->path;


/**
 * Date/time string to use for reporting when deployment was
 * last run
 *
 * @var string
 */
define('FANCY_DATE', 'H:i:s \o\n l, \t\h\e jS \o\f F Y');

/**
 * Absolute path to template bash script
 *
 * @var string
 */
define('TMPL', __DIR__.DIRECTORY_SEPARATOR.'deploy-to.tmpl.sh');


//  END:  Initial setup & validation
// ===================================================================
// START: Function declarations

/**
 * List of all files to be uploaded (not grouped)
 *
 * @var array
 */
$tmp = array();
// Get all eligible files for each path
for ($a = 0; $a < count($data->sourceList); $a += 1) {
    $tmp = array_merge(
        $tmp,
        getDeployable($data->sourceList[$a], $sinceTime)
    );
}

$grouped = array();

$totalFiles = count($tmp);

// Group files by their destination path (speeds up SCP calls)
for ($a = 0; $a < $totalFiles; $a += 1) {
    $key = trim($tmp[$a]['remote']);
    $value = $tmp[$a]['local'];
    if (!array_key_exists($key, $grouped)) {
        $grouped[$key] = array($value);
    } else {
        $grouped[$key][] = $value;
    }
}

/**
 * List of all the SCP calls to deploy all the appropriate files
 * to the deployment target
 *
 * @var string
 */
$output = '';

/**
 * Count of all individual SCP calls to fulfill deployment
 *
 * @var integer
 */
$groupC = 0;

/**
 * Count of total number of files to be deployed
 */
$fileC = 0;

if ($totalFiles > 100 && $sleepDuration > 0) {
    $makeSleep = function ($b) {
        return ($b > 100);
    };
    $secs = ($sleepDuration > 1)
        ? 's'
        : '';
} else {
    $makeSleep = function ($b) {
        return false;
    };
}

// Build a list of calls to SCP
$b = 0;
foreach ($grouped as $path => $source) {
    if (count($source) > 0) {
        /**
         * List of all files being deployed to a specific
         * location on the destination server
         *
         * @var string
         */
        $srcList = '';
        $sep = ' ';

        $c = 0;
        for ($a = 0; $a < count($source); $a += 1) {
            $srcList .= $sep.unixPath($source[$a]);
            $fileC += 1;
            $b += 1;
            if ($c > 10) {
                $c = 0;
                $output .= "\nscp$srcList \\\n{$host}$path;\n";
                $srcList = '';
                $sep = ' ';
            } else {
                $sep = " \\\n    ";
            }
            $c += 1;
        }

        if ($makeSleep($b)) {
            $b = 0;
            $output .= "\n\necho;\n".
                       "\necho 'Pausing for $sleepDuration ".
                       "second$secs to prevent timeouts';".
                       "\necho; echo;\nsleep $sleepDuration;\n\n";
        }

        if ($srcList !== '') {
            $output .= "\nscp$srcList \\\n{$host}$path;\n";
        }
        $groupC += 1;
    }
}

if ($output !== '') {
    $parts = array(
        'host' => $server->host,
        'server_name' => $server->name,
        'created' => date('Y-m-d H:i:s'),
        'user' => $server->user,
        'time_check_file' => unixPath($timeCheckFile),
        'file_C' => $fileC,
        'file_S' =>  ($fileC > 1) ? 's' : '',
        'group_C' => $groupC,
        'group_S' =>  ($groupC > 1) ? 's' : '',
        'since' => ($sinceTime > 0)
            ? "'that have been updated since';\necho\t'".
                date(FANCY_DATE, $sinceTime)."';"
            : "'\t(Full deployment)';",
        'output' => $output
    );

    file_put_contents($fileName, populateScript($parts));
}
