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
		echo PHP_EOL.PHP_EOL.PHP_EOL."ERROR: Stopping execution because an error has occured!".PHP_EOL;
		echo "\tDetail:".$e->getMessage().$e->getTraceAsString().PHP_EOL.PHP_EOL;
		exit(1);
	}

} catch (Exception $e) {
	echo "\nERROR: {$e->getMessage()}\n\n";
	exit(1);
}
