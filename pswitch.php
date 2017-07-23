#!/usr/bin/env php
<?php

process($argv);

/**
 * process argv and start
 *
 * @param $argv
 */
function process($argv)
{
    $config = init();
    in_array('-a', $argv) && addSoftware($config);
    in_array('-s', $argv) && switchSoftware($argv, $config);
    displayHelp();
}

/**
 * init
 *
 * @param string $path
 * @return array
 */
function init($path = '/etc/switch.json')
{
    $emptyConfig = [];
    if (file_exists($path)) {
        //decode config json to array
        $config = json_decode(file_get_contents($path), true);
    } else {
        //make new config file
        if (is_writeable(dirname($path))) {
            touch($path);
        } else {
            error("can't create config file [$path]. check the permission to write");
        }
    }
    return empty($config) ? $emptyConfig : $config;
}

/**
 * display help information
 */
function displayHelp()
{
    echo <<<EOF
PSWITCH Help
------------------
Options
--help           this help
-a               add software
-s               switch software version. 
                 e.g: pswitch -s [software name] [software version]
-l               show the software list
-v               show this version

EOF;
}

/**
 * add software info to config
 *
 * @param $config
 */
function addSoftware($config)
{
    //get the software info
    fwrite(STDOUT, 'Please input software name:');
    $name = trim(fgets(STDIN));
    empty($name) && error('software name is required');

    fwrite(STDOUT, 'Please input software absolute path (bin directory or file):');
    $path = trim(fgets(STDIN));
    empty($path) && error('software path is required');
    $path = rtrim($path, '/');

    fwrite(STDOUT, 'Please input software version (default: 1.0.0):');
    $version = trim(fgets(STDIN));
    empty($version) && ($version = '1.0.0');

    fwrite(STDOUT, 'Please input symbolic link directory (default: /usr/local/bin):');
    $linkDir = trim(fgets(STDIN));
    empty($linkDir) && ($linkDir = '/usr/local/bin');
    $linkDir = rtrim($linkDir, '/');


    //get all file path from software path
    $files = [];
    if (is_dir($path)) {
        $dir = dir($path);
        while ($filename = $dir->read()) {
            $file = $path . '/' . $filename;
            if (!is_dir($file) && ($filename != '.') && ($filename != '..')) {
                $files[] = $file;
            }
        }
        $dir->close();
    } else {
        $files[] = $path;
    }

    //set all software info
    $info = [];
    $info['files'] = $files;
    $info['linkDir'] = $linkDir;

    //make sure the software version does not exist or replace it
    if (isset($config[$name][$version])) {
        fwrite(STDOUT, 'this software version is exist, replace it? (y/n, default:n):');
        $temp = trim(fgets(STDIN));
        if ($temp != 'y') {
            exit(0);
        }
    }

    //save info
    $config[$name][$version] = $info;
    saveConfig($config);

    exit(0);
}

/**
 * switch software version
 *
 * @param $argv
 * @param $config
 */
function switchSoftware($argv, $config)
{
    //make sure $argv & $config are right
    if ((count($argv) != 4) || ($argv[1] != '-s')) {
        error('wrong input! e.g: pswitch -s [software name] [software version]');
    }
    $name = $argv[2];
    $version = $argv[3];
    $info = [];
    if (empty($config)) {
        error('the config is empty, please add software!');
    }

    //find the version of this software
    foreach ($config as $softwareName => $software) {
        if ($softwareName == $name) {
            foreach ($config[$softwareName] as $softwareVersion => $value) {
                if ($softwareVersion == $version) {
                    $info = $value;
                    break;
                }
            }
            break;
        }
    }
    if (empty($info)) {
        error("can't find this software or version");
    }

    //make sure symlink path are writable
    if (!is_writeable($info['linkDir'])) {
        error('the link path can\' write, check the permission');
    }

    //link all files of this software
    foreach ($info['files'] as $file) {
        if (file_exists($file)) {
            linkSoftware($name, $file, $info['linkDir'], $config);
        } else {
            echo "\033[0;31m Warning: file [ $file ] not found, please check it." . PHP_EOL;
        }
    }

    exit(0);
}

function linkSoftware($softwareName, $target, $linkDir, $config)
{
    //set the symlink path
    $temp = explode('/', $target);
    $link = $linkDir . '/' . end($temp);

    //set symlink
    if (checkSymlink($softwareName, $link, $config)) {
        symlink($target, $link);
    } else {
        echo "\033[0;31m Warning: can't create symlink because $link exist" . PHP_EOL;
    }
}

function checkSymlink($softwareName, $link, $config)
{
    if (file_exists($link)) {
        if (!is_link($link)) {
            return false;
        }
        $realPath = readlink($link);
        //if this link in config, delete it
        $inConfig = false;
        foreach ($config[$softwareName] as $version) {
            if (!$inConfig) {
                foreach ($version['files'] as $file) {
                    if ($realPath == $file) {
                        unlink($link);
                        $inConfig = true;
                        break;
                    }
                }
            }
        }
        return $inConfig;
    }
    return true;
}

function saveConfig($config, $path = '/etc/switch.json')
{
    $config = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($path, $config);
}

function error($info)
{
    echo "\e[0;31m Error: $info" . PHP_EOL;
    exit(1);
}