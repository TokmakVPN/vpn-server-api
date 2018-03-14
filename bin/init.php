#!/usr/bin/env php
<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2018, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

$baseDir = dirname(__DIR__);
/** @psalm-suppress UnresolvableInclude */
require_once sprintf('%s/vendor/autoload.php', $baseDir);

use SURFnet\VPN\Common\CliParser;
use SURFnet\VPN\Common\Config;
use SURFnet\VPN\Server\CA\EasyRsaCa;
use SURFnet\VPN\Server\Storage;
use SURFnet\VPN\Server\TlsAuth;

try {
    $p = new CliParser(
        'Initialize the CA',
        [
            'instance' => ['the VPN instance', true, false],
        ]
    );

    $opt = $p->parse($argv);
    if ($opt->hasItem('help')) {
        echo $p->help();
        exit(0);
    }

    $instanceId = $opt->hasItem('instance') ? $opt->getItem('instance') : 'default';

    $configFile = sprintf('%s/config/%s/config.php', $baseDir, $instanceId);
    $config = Config::fromFile($configFile);

    $easyRsaDir = sprintf('%s/easy-rsa', $baseDir);
    $easyRsaDataDir = sprintf('%s/data/%s/easy-rsa', $baseDir, $instanceId);

    $ca = new EasyRsaCa($config, $easyRsaDir, $easyRsaDataDir);

    $dataDir = sprintf('%s/data/%s', $baseDir, $instanceId);
    $storage = new Storage(
        new PDO(
            sprintf('sqlite://%s/db.sqlite', $dataDir)
        ),
        new DateTime('now')
    );
    $storage->init();

    $tlsAuth = new TlsAuth($dataDir);
    $tlsAuth->init();
} catch (Exception $e) {
    echo sprintf('ERROR: %s', $e->getMessage()).PHP_EOL;
    exit(1);
}
