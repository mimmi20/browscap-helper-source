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

/**
 * Source interface
 *
 * @author Thomas Mueller <mimmi20@live.de>
 */
interface SourceInterface
{
    /**
     * @param int $limit
     *
     * @return string[]
     */
    public function getUserAgents($limit = 0);

    /**
     * @return \UaResult\Result\Result[]
     */
    public function getTests();
}
