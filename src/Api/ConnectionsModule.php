<?php

/*
 * eduVPN - End-user friendly VPN.
 *
 * Copyright: 2016-2019, The Commons Conservancy eduVPN Programme
 * SPDX-License-Identifier: AGPL-3.0+
 */

namespace LC\Server\Api;

use DateTime;
use LC\Common\Config;
use LC\Common\Http\ApiErrorResponse;
use LC\Common\Http\ApiResponse;
use LC\Common\Http\AuthUtils;
use LC\Common\Http\InputValidation;
use LC\Common\Http\Request;
use LC\Common\Http\Service;
use LC\Common\Http\ServiceModuleInterface;
use LC\Common\ProfileConfig;
use LC\Server\Api\Exception\ConnectionsModuleException;
use LC\Server\Storage;

class ConnectionsModule implements ServiceModuleInterface
{
    /** @var \DateTime */
    protected $dateTime;

    /** @var \LC\Common\Config */
    private $config;

    /** @var \LC\Server\Storage */
    private $storage;

    public function __construct(Config $config, Storage $storage)
    {
        $this->config = $config;
        $this->storage = $storage;
        $this->dateTime = new DateTime();
    }

    /**
     * @return void
     */
    public function init(Service $service)
    {
        $service->post(
            '/connect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                try {
                    $this->connect($request);

                    return new ApiResponse('connect');
                } catch (ConnectionsModuleException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserMessage($userId, 'notification', '[CONNECT] ERROR: '.$e->getMessage());
                    }

                    return new ApiErrorResponse('connect', '[CONNECT] ERROR: '.$e->getMessage());
                }
            }
        );

        $service->post(
            '/disconnect',
            /**
             * @return \LC\Common\Http\Response
             */
            function (Request $request, array $hookData) {
                AuthUtils::requireUser($hookData, ['vpn-server-node']);

                try {
                    $this->disconnect($request);

                    return new ApiResponse('disconnect');
                } catch (ConnectionsModuleException $e) {
                    if (null !== $userId = $e->getUserId()) {
                        $this->storage->addUserMessage($userId, 'notification', '[DISCONNECT] ERROR: '.$e->getMessage());
                    }

                    return new ApiErrorResponse('disconnect', '[DISCONNECT] ERROR: '.$e->getMessage());
                }
            }
        );
    }

    /**
     * @return void
     */
    public function connect(Request $request)
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->requirePostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->requirePostParameter('ip6'));
        $connectedAt = InputValidation::connectedAt($request->requirePostParameter('connected_at'));

        $this->verifyConnection($profileId, $commonName);
        $this->storage->clientConnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)));
    }

    /**
     * @return void
     */
    public function disconnect(Request $request)
    {
        $profileId = InputValidation::profileId($request->requirePostParameter('profile_id'));
        $commonName = InputValidation::commonName($request->requirePostParameter('common_name'));
        $ip4 = InputValidation::ip4($request->requirePostParameter('ip4'));
        $ip6 = InputValidation::ip6($request->requirePostParameter('ip6'));

        $connectedAt = InputValidation::connectedAt($request->requirePostParameter('connected_at'));
        $disconnectedAt = InputValidation::disconnectedAt($request->requirePostParameter('disconnected_at'));
        $bytesTransferred = InputValidation::bytesTransferred($request->requirePostParameter('bytes_transferred'));

        $this->storage->clientDisconnect($profileId, $commonName, $ip4, $ip6, new DateTime(sprintf('@%d', $connectedAt)), new DateTime(sprintf('@%d', $disconnectedAt)), $bytesTransferred);
    }

    /**
     * @param string $profileId
     * @param string $commonName
     *
     * @return void
     */
    private function verifyConnection($profileId, $commonName)
    {
        // verify status of certificate/user
        if (false === $userCertInfo = $this->storage->getUserCertificateInfo($commonName)) {
            // we do not (yet) know the user as only an existing *//* certificate can be linked back to a user...
            throw new ConnectionsModuleException(null, sprintf('user or certificate does not exist [profile_id: %s, common_name: %s]', $profileId, $commonName));
        }

        $userId = $userCertInfo['user_id'];

        if (false === strpos($userId, '!!')) {
            // FIXME "!!" indicates it is a remote guest user coming in with a
            // foreign OAuth token, for those we do NOT check expiry.. this is
            // really ugly hack, we need to get rid of sessionExpiresAt
            // completely instead! This check is skipped when a non remote
            // guest user id contains '!!' for some reason...
            //
            // this is always string, but DB gives back scalar|null
            $sessionExpiresAt = new DateTime((string) $this->storage->getSessionExpiresAt($userId));
            if ($sessionExpiresAt->getTimestamp() < $this->dateTime->getTimestamp()) {
                throw new ConnectionsModuleException($userId, sprintf('the certificate is still valid, but the session expired at %s', $sessionExpiresAt->format(DateTime::ATOM)));
            }
        }

        if ($userCertInfo['user_is_disabled']) {
            throw new ConnectionsModuleException($userId, 'unable to connect, account is disabled');
        }
		
		if ($userCertInfo['user_connected']) {
			throw new ConnectionsModuleException($userId, 'You are already connected from another device');
		}

        $this->verifyAcl($profileId, $userId);
    }

    /**
     * @param string $profileId
     * @param string $userId
     *
     * @return void
     */
    private function verifyAcl($profileId, $userId)
    {
        $profileConfig = new ProfileConfig($this->config->s('vpnProfiles')->s($profileId));
        if ($profileConfig->enableAcl()) {
            // ACL is enabled for this profile
            $userPermissionList = $this->storage->getPermissionList($userId);
            $profilePermissionList = $profileConfig->aclPermissionList();
            if (false === self::hasPermission($userPermissionList, $profilePermissionList)) {
                throw new ConnectionsModuleException($userId, sprintf('unable to connect, user permissions are [%s], but requires any of [%s]', implode(',', $userPermissionList), implode(',', $profilePermissionList)));
            }
        }
    }

    /**
     * @return bool
     */
    private static function hasPermission(array $userPermissionList, array $aclPermissionList)
    {
        // one of the permissions must be listed in the profile ACL list
        foreach ($userPermissionList as $userPermission) {
            if (\in_array($userPermission, $aclPermissionList, true)) {
                return true;
            }
        }

        return false;
    }
}
