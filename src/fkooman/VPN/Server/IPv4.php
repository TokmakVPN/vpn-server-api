<?php
/**
 * Copyright 2015 François Kooman <fkooman@tuxed.net>.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace fkooman\VPN\Server;

use InvalidArgumentException;

class IPv4
{
    /** @var string */
    private $ip;

    /** @var int */
    private $prefix;

    public function __construct($cidrIp)
    {
        // must be of form IP/PREFIX
        if (1 !== substr_count($cidrIp, '/')) {
            throw new InvalidArgumentException('not in CIDR format');
        }
        list($ip, $prefix) = explode('/', $cidrIp);

        // check IP
        self::validateIP($ip);
        $this->ip = $ip;

        // check prefix
        if (!is_numeric($prefix) || 0 > $prefix || 32 < $prefix) {
            throw new InvalidArgumentException('invalid prefix, must be >0 and <32');
        }

        $this->prefix = intval($prefix);
    }

    public function getFamily()
    {
        return 4;
    }

    public function getRange()
    {
        return sprintf('%s/%d', $this->ip, $this->prefix);
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function getNetmask()
    {
        return long2ip(-1 << (32 - $this->prefix));
    }

    public function getNetwork()
    {
        return long2ip(ip2long($this->ip) & ip2long($this->getNetmask()));
    }

    public function getFirstHost()
    {
        return long2ip(ip2long($this->getNetwork()) + 1);
    }

    public function getLastHost()
    {
        return long2ip(ip2long($this->getBroadcast()) + -1);
    }

    public function getBroadcast()
    {
        return long2ip(
            ip2long($this->getNetwork()) | ~ip2long($this->getNetmask())
        );
    }

    public function getNumberOfHosts()
    {
        return pow(2, 32 - $this->prefix) - 2;
    }

    /**
     * Check if a given IP address is in the range of the network.
     *
     * @param string $ip                      the IP address to check
     * @param bool   $includeNetworkBroadcast whether or not to consider the
     *                                        network and broadcast address of the network also part of the range
     */
    public function inRange($ip, $includeNetworkBroadcast = false)
    {
        self::validateIP($ip);

        $longIp = ip2long($ip);
        $startIp = ip2long($this->getNetwork());
        $endIp = ip2long($this->getBroadcast());

        if ($includeNetworkBroadcast) {
            return $longIp >= $startIp && $longIp <= $endIp;
        }

        return $longIp > $startIp && $longIp < $endIp;
    }

    /**
     * Split the provided range in $no equal sized CIDRs.
     */
    public function splitRange($no)
    {
        if (1 === $no) {
            $prefixNo = 1;
        } elseif (2 === $no) {
            $prefixNo = 2;
        } elseif (3 === $no || 4 === $no) {
            $prefixNo = 4;
        } else {
            throw new InvalidArgumentException('too many instances, only 1,2,3 or 4 allowed');
        }

        $prefix = $this->prefix + floor($prefixNo / 2);
        $ranges = [];
        for ($i = 0; $i < $no; ++$i) {
            $noHosts = pow(2, 32 - $prefix);
            $networkAddress = long2ip($i * $noHosts + ip2long($this->ip));
            $ip = new self($networkAddress.'/'.$prefix);
            $ranges[] = $ip->getRange();
        }

        return $ranges;
    }

    private static function validateIP($ip)
    {
        if (false === filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            throw new InvalidArgumentException('invalid IP address');
        }
    }
}
