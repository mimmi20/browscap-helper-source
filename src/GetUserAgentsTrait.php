<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use Override;

use function assert;
use function is_string;

trait GetUserAgentsTrait
{
    /**
     * @return iterable<non-empty-string, array<non-empty-string, non-empty-string>>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->getProperties($message, $messageLength) as $uid => $property) {
            assert(is_string($uid));

            yield $uid => $property->getHeaders();
        }
    }
}
