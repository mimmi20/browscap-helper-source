<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source\Helper;

final class Regex
{
    /** @throws void */
    public function getRegex(): string
    {
        return '/^'
            // remote host (IP)
            . '(?P<remotehost>\S+)'
            . '\s+'
            // remote logname
            . '(?P<logname>\S+)'
            . '\s+'
            // remote user
            . '(?P<user>\S+)'
            . '[^\[]+'
            // date/time
            . '\[(?P<time>[^\]]+)\]'
            . '[^"]+'
            // Verb(GET|POST|HEAD) Path HTTP Version
            . '\"(?P<http>.*)\"'
            . '\s+'
            // Status
            . '(?P<status>\d+)'
            . '\D+'
            // Length (include Header)
            . '(?P<length>\d+)'
            . '[^\d"]+'
            // Referrer
            . '\"(?P<referrer>.*)\"'
            . '[^"]+'
            // User Agent
            . '\"(?P<userAgentString>[^"]*)\".*'
            . '$/x';
    }
}
