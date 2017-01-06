<?php

namespace BrowscapHelper\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Source interface
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
interface SourceInterface
{
    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int                                               $limit
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, OutputInterface $output, $limit = 0);

    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @throws \BrowscapPHP\Helper\Exception
     *
     * @return \Generator
     */
    public function getTests(Logger $logger, OutputInterface $output);
}
