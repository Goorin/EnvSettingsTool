#!/opt/rh/php55/root/usr/bin/php
<?php

if (version_compare(PHP_VERSION, '5.3.0') <= 0) {
    throw new Exception('EnvSettingsTool needs at least PHP 5.3');
}

define('EST_ROOTDIR', dirname(__FILE__));

include(dirname(__FILE__).'/Est/Autoloading.php');

try {

    if (empty($_SERVER['argv'][1])) {
        throw new Exception('Please specify the environment');
    }

    if (getenv('NO_COLOR')) {
        Est_CliOutput::$active = false;
    }

    $env = $_SERVER['argv'][1];
    $settingsFile = empty($_SERVER['argv'][2]) ? '../settings/settings.csv' : $_SERVER['argv'][2];

    if (empty($_SERVER['argv'][3])) {
        throw new Exception('No handler specified!');
    }
    $handlerName = $_SERVER['argv'][3];

    $param1 = isset($_SERVER['argv'][4]) ? $_SERVER['argv'][4] : '';
    $param2 = isset($_SERVER['argv'][5]) ? $_SERVER['argv'][5] : '';
    $param3 = isset($_SERVER['argv'][6]) ? $_SERVER['argv'][6] : '';

    $processor = new Est_Processor($env, $settingsFile);
    $handler = $processor->getHandler(
        $handlerName,
        (string)$param1,
        (string)$param2,
        (string)$param3
    );

    echo $handler->getValue();

} catch (Exception $e) {
    echo "\nERROR: {$e->getMessage()}\n\n";
    exit(1);
}