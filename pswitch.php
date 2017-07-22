#!/usr/bin/env php
<?php

process($argv);

function process($argv)
{
    if (in_array('--help', $argv) || (count($argv) <= 1)) {
        displayHelp();
        exit(0);
    }
    $config = init();
    in_array('-a', $argv) && addSoftware($config);
    in_array('-s', $argv) && switchSoftware($argv, $config);
}

function init($path = '/etc/switch.json')
{
    $emptyConfig = [];
    if (file_exists($path)) {
        $config = json_decode(file_get_contents($path), true);
    } else {
        touch($path);
    }
    return isset($config) ? $config : $emptyConfig;
}

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

function addSoftware($config)
{
    fwrite(STDOUT, 'Please input software name:');
    $name = trim(fgets(STDIN));
    empty($name) && exit('software name is required' . PHP_EOL);

    fwrite(STDOUT, 'Please input software absolute path (bin directory or file):');
    $path = trim(fgets(STDIN));
    empty($path) && exit('software path is required' . PHP_EOL);
    $path = rtrim($path, '/');

    fwrite(STDOUT, 'Please input software version (default: 1.0.0):');
    $version = trim(fgets(STDIN));
    empty($version) && ($version = '1.0.0');

    fwrite(STDOUT, 'Please input symbolic link directory (default: /usr/local/bin):');
    $linkDir = trim(fgets(STDIN));
    empty($linkDir) && ($linkDir = '/usr/local/bin');
    $linkDir = rtrim($linkDir, '/');

    $info = [];
    $info['version'] = $version;
    $info['path'] = $path;
    $info['linkDir'] = $linkDir;

    if (isset($config[$name][$version])) {
        fwrite(STDOUT, 'this software version is exist, replace it? (y/n, default:n):');
        $temp = trim(fgets(STDIN));
        if ($temp != 'y') {
            exit(0);
        }
    }
    $config[$name][$version] = $info;

    saveConfig($config);

    exit(0);
}

function switchSoftware($argv, $config)
{
    if ((count($argv) != 4) || ($argv[1] != '-s')) {
        exit('format error! e.g: pswitch -s [software name] [software version]');
    }
    $name = $argv[2];
    $version = $argv[3];
    $info = [];
    if (empty($config)) {
        exit('error! the config is empty, please add software!');
    }
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
        exit("error! can't find this software or version" . PHP_EOL);
    }
    if (is_dir($info['path'])) {
        $dir = dir($info['path']);
        while ($file = $dir->read()) {
            $target = $info['path'] . '/' . $file;
            if (!is_dir($target) && ($file != '.') && ($file != '..')) {
                linkSoftware($target, $info['linkDir'], $config);
            }
        }
        $dir->close();
    } else {
        if (file_exists($info['path'])) {
            linkSoftware($info['path'], $info['linkDir'], $config);
        } else {
            exit("software not found, please check the software path: {$info['path']}" . PHP_EOL);
        }
    }
    exit(0);
}

function linkSoftware($target, $linkDir, $config)
{
    $temp = explode('/', $target);
    $link = $linkDir . '/' . end($temp);

    if (checkSymlink($link, $config)) {
        symlink($target, $link);
    } else {
        fwrite(STDOUT, "can't create symlink because link: $link exist" . PHP_EOL);
    }
}

function checkSymlink($link, $config)
{
    if (file_exists($link)) {
        $realPath = realpath($link);
        if (is_link($realPath)) {
            $realPath = readlink($realPath);
        }
        $realPath = dirname($realPath);
        $inConfig = false;
        if (!empty($config)) {
            foreach ($config as $software) {
                if (!$inConfig) {
                    foreach ($software as $version) {
                        if ($realPath == $version['path']) {
                            unlink($link);
                            $inConfig = true;
                            break;
                        }
                    }
                } else {
                    break;
                }
            }
        }
        return $inConfig;
    }
    return true;
}

function saveConfig($config, $path = '/etc/switch.json')
{
    $config = json_encode($config);
    file_put_contents($path, $config);
}