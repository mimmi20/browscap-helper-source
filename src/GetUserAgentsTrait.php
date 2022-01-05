<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use LogicException;
use RuntimeException;

use function array_key_exists;

trait GetUserAgentsTrait
{
    /**
     * @return iterable|string[]
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->getHeaders() as $headers) {
            if (!array_key_exists('user-agent', $headers)) {
                continue;
            }

            yield $headers['user-agent'];
        }
    }
}
