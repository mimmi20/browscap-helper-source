<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2018, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Helper\FilePath;
use BrowscapHelper\Source\Reader\LogFileReader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class LogFileSource implements SourceInterface
{
    /**
     * @var string
     */
    private $sourcesDirectory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param string                   $sourcesDirectory
     */
    public function __construct(LoggerInterface $logger, string $sourcesDirectory)
    {
        $this->logger           = $logger;
        $this->sourcesDirectory = $sourcesDirectory;
    }

    /**
     * @param int $limit
     *
     * @return iterable|string[]
     */
    public function getUserAgents(int $limit = 0): iterable
    {
        $counter = 0;

        foreach ($this->loadFromPath() as $agent) {
            if ($limit && $counter >= $limit) {
                return;
            }

            $agent = trim($agent);

            if (empty($agent)) {
                continue;
            }

            yield $agent;
            ++$counter;
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $finder = new Finder();
        $finder->files();
        $finder->notName('*.filepart');
        $finder->notName('*.sql');
        $finder->notName('*.rename');
        $finder->notName('*.txt');
        $finder->notName('*.zip');
        $finder->notName('*.rar');
        $finder->notName('*.php');
        $finder->notName('*.gitkeep');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->sourcesDirectory);

        $filepathHelper = new FilePath();
        $reader         = new LogFileReader($this->logger);

        foreach ($finder as $file) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            $this->logger->info('    reading file ' . $file->getPathname());

            $filepath = $filepathHelper->getPath($file);

            if (null === $filepath) {
                continue;
            }

            $reader->addLocalFile($filepath);

            foreach ($reader->getAgents($this->logger) as $agentOfLine) {
                yield trim($agentOfLine);
            }
        }
    }
}
