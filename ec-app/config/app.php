<?php

declare(strict_types=1);

$config = [
	'payjp' => [
		'public_key' => '',
		'secret_key' => '',
	],
];

$localConfigPath = __DIR__ . '/app.local.php';
if (is_file($localConfigPath)) {
	$localConfig = require $localConfigPath;
	if (is_array($localConfig)) {
		$config = array_replace_recursive($config, $localConfig);
	}
}

return $config;
