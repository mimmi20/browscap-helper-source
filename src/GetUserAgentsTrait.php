<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use LogicException;
use RuntimeException;

use function array_key_exists;
use function assert;
use function is_string;

trait GetUserAgentsTrait
{
    /**
     * @return iterable<non-empty-string, non-empty-string>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getUserAgents(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getHeaders($message, $messageLength) as $uid => $headers) {
            if (!array_key_exists('user-agent', $headers)) {
                continue;
            }

            assert(is_string($uid));

            yield $uid => $headers['user-agent'];
        }
    }

    /**
     * @return iterable<non-empty-string, array<non-empty-string, non-empty-string>>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getProperties($message, $messageLength) as $uid => $row) {
            assert(is_string($uid));

            yield $uid => $row['headers'];
        }
    }
}
