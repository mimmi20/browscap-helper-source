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

namespace BrowscapHelper\Source\Reader;

interface ReaderInterface
{
    /** @throws void */
    public function addLocalFile(string $file): void;

    /**
     * @return iterable<string>
     *
     * @throws void
     */
    public function getAgents(): iterable;
}
