<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

interface SourceInterface
{
    /**
     * @param int $limit
     *
     * @return iterable
     */
    public function getUserAgents(int $limit = 0): iterable;
}
