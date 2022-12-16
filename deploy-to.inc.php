<?php

/**
 * This file contains all the helper functions for deployto.php
 *
 * All functions are pure (no side effects) and return a value
 *
 * PHP version 7.4
 *
 * @category Deployto
 * @package  Deployto
 * @author   Evan Wills <evan.wills@acu.edu.au>
 * @license  MIT https://opensource.org/licenses/MIT
 * @link     https://github.com/evanwills/useful-bash-scripts
 */


/**
 * Test whether the supplied server matches the target deployment
 * server
 *
 * @param stdClass $server Data about one of the deployment targes
 *                         listed in th `deploy-to.json` file
 * @param string   $target Name of target deployment server for this
 *                         execution
 *
 * @return boolean TRUE if the server should be used for this
 *                 deployment. FALSE otherwise.
 */
function isRightServer(stdClass $server, string $target) : bool
{
    if ($server->name === $target) {
        // Direct match
        return true;
    } elseif (property_exists($server, 'aliases') && is_array($server->aliases)) {
        // Possibly an alias match
        return in_array($target, $server->aliases);
    } else {
        // No match
        return false;
    }
}

/**
 * Convert a windows file system path string to a unix file
 * system path string
 *
 * @param string $path Path to be converted
 *
 * @return string
 */
function unixPath(string $path) : string
{
    return str_replace(
        array(' ', 'C:', '\\'),
        array('\\ ', '/c', '/'),
        trim($path)
    );
}

/**
 * Make the path minTTY compliant
 *
 * @param string $path Path to be cleaned up
 *
 * @return string
 */
function clean(string $path) : string
{
    return preg_replace_callback(
        '/^([a-z]):(?=\/)/i',
        function ($matches) {
            return '/'.strtolower($matches[1]);
        },
        $path
    );
}

/**
 * Get a list of files that have been updated since $last
 *
 * @param string  $path Path to directory or file
 * @param integer $last Unix timestamp after which updated files
 *                      should be included
 *
 * @return array
 */
function getDeployable(string $path, int $last) : array
{
    $real = realpath($path);
    $output = array();

    if (is_file($real)) {
        if (filemtime($real) > $last) {
            $output[] = array(
                'local' => clean(trim($path)),
                'remote' => trim(
                    preg_replace(
                        '`^(.*?/)?[^/]*$`',
                        '\1',
                        str_replace(PWD, '', $path)
                    )
                )
            );
        }
    } elseif (is_dir($real)) {
        $children = scandir($real);

        for ($a = 0; $a < count($children); $a += 1) {
            if ($children[$a] !== '..' && $children[$a] !== '.') {
                $output = array_merge(
                    $output,
                    getDeployable("$path/{$children[$a]}", $last)
                );
            }
        }
    } elseif (preg_match('/\*\.[a-z]+$/i', $path)) {
        // Get wildcard files by file extension

        $ext = preg_replace('/^[^*]*\*/', '', $path);
        $l = strlen($ext) * -1;

        $isRoot = false;

        // strip the wildcard pattern from the path
        $oldPath = preg_replace('`(?<=/)[^/]+$`', '', $path);
        if (preg_match('/^\*\.[a-z]+$/i', $path)) {
            // wildcard pattern is for root so just
            $oldPath = PWD;
            $isRoot = true;
        }

        $children = scandir($oldPath);

        $oldPath = ($isRoot === true)
            ? ''
            : $oldPath;

        for ($a = 0; $a < count($children); $a += 1) {
            if (substr($children[$a], $l) === $ext) {
                $output = array_merge(
                    $output,
                    getDeployable(
                        "$oldPath{$children[$a]}",
                        $last
                    )
                );
            }
        }
    }

    return $output;
}

/**
 * Populate template bash script with values generated by this
 * PHP script
 *
 * @param array $data Associative array containing key/value pairs
 *                    used to populate template bash script
 *
 * @return string
 */
function populateScript(array $data) : string
{
    $tmpl = realpath(TMPL);

    if (!is_file($tmpl)) {
        trigger_error(
            'Could not find bash script template file at "'.TMPL.'"',
            E_USER_ERROR
        );
    }

    $find = array();
    $replace = array();

    foreach ($data as $key => $value) {
        $find[] = '[['.strtoupper($key).']]';
        $replace[] = $value;
    }

    return str_replace($find, $replace, file_get_contents($tmpl));
}

