<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Tests;

use DateTime;
use LC\Common\Config;
use LC\Server\CA\CaInterface;

class TestCa implements CaInterface
{
    /**
     * Generate a certificate for the VPN server.
     *
     * @param string $commonName
     *
     * @return array the certificate, key in array with keys
     *               'cert', 'key', 'valid_from' and 'valid_to'
     */
    public function serverCert($commonName)
    {
        return [
            'certificate' => sprintf('ServerCert for %s', $commonName),
            'private_key' => sprintf('ServerCert for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => 2345678901,
        ];
    }

    /**
     * Generate a certificate for a VPN client.
     *
     * @param string $commonName
     *
     * @return array the certificate and key in array with keys 'cert', 'key',
     *               'valid_from' and 'valid_to'
     */
    public function clientCert($commonName, DateTime $expiresAt)
    {
        return [
            'certificate' => sprintf('ClientCert for %s', $commonName),
            'private_key' => sprintf('ClientKey for %s', $commonName),
            'valid_from' => 1234567890,
            'valid_to' => $expiresAt->getTimestamp(),
        ];
    }
	
	/**
     * Generate a key tls-crypt-v2 for a VPN server.
     *
     * @param string $serverName
     *
     * @return string the tls-crypt-v2-server in PEM format
     */
	public function serverKey($profileId) 
	{
		return 'Serverkey tls-v2';
	}

	/**
     * Generate a key tls-crypt-v2 for a VPN client.
     *
     * @param string $profileId
	 * @param string $userId
     *
     * @return string the tls-crypt-v2-client in PEM format
     */
	public function clientKey($profileId, $userId) 
	{
		return 'Clientkey tls-v2';
	}

    /**
     * Get the CA root certificate.
     *
     * @return string the CA certificate in PEM format
     */
    public function caCert()
    {
        return 'Ca';
    }

    public function init(Config $config)
    {
        // NOP
    }
}
