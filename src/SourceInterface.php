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

use LogicException;
use RuntimeException;
use UaResult\Result\Result;

interface SourceInterface
{
    /** @throws void */
    public function isReady(string $parentMessage): bool;

    /**
     * @return iterable<non-empty-string, array<non-empty-string, non-empty-string>>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable;

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, result: Result, file: string|null, date-first: string|null, date-last: string|null, raw: mixed}>
     *
     * @throws Exception\SourceException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable;

    /** @throws void */
    public function getName(): string;
}
