#!/opt/rh/php55/root/usr/bin/php
<?php

if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
    throw new Exception('EnvSettingsTool needs at least PHP 5.3');
}

define('EST_ROOTDIR', dirname(__FILE__));


include(dirname(__FILE__).'/Est/Autoloading.php');

function parseArgs() {
    $arguments = array();
    $current = null;
    foreach ($_SERVER['argv'] as $arg) {
        $match = array();
        if (preg_match('#^--([\w\d_-]{1,})$#', $arg, $match) || preg_match('#^-([\w\d_]{1,})$#', $arg, $match)) {
            $current = $match[1];
            $arguments[$current] = true;
        } else {
            if ($current) {
                $arguments[$current] = $arg;
            } else if (preg_match('#^([\w\d_]{1,})$#', $arg, $match)) {
                $arguments[$match[1]] = true;
            }
        }
    }
    return $arguments;
}

try {

    if (empty($_SERVER['argv'][1])) {
        throw new Exception('Please specify the environment');
    }

    if (getenv('NO_COLOR')) {
        Est_CliOutput::$active = false;
    }

    $env = $_SERVER['argv'][1];
    $settingsFile = empty($_SERVER['argv'][2]) ? '../settings/settings.csv' : $_SERVER['argv'][2];

    $processor = new Est_Processor($env, $settingsFile, parseArgs());
    try {
        $res = $processor->apply();
        $processor->printResults();
    } catch (Exception $e) {
        $processor->printResults();
        echo "\n\n\n";
        echo Est_CliOutput::getColoredString('ERROR: Stopping execution because an error has occured!', 'red') . "\n";
        echo Est_CliOutput::getColoredString("Detail: {$e->getMessage()}", 'red') . "\n";
        echo "Trace:\n{$e->getTraceAsString()}\n";
        exit(1);
    }

} catch (Exception $e) {
    echo "\n" . Est_CliOutput::getColoredString("ERROR: {$e->getMessage()}", 'red') . "\n\n";
    echo "\nERROR: {$e->getMessage()}\n";
    exit(1);
}

echo "\n\n";