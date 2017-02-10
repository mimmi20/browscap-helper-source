<?php

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
