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

use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class TxtFileSource implements SourceInterface
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param string                   $dir
     */
    public function __construct(LoggerInterface $logger, string $dir)
    {
        $this->logger = $logger;
        $this->dir    = $dir;
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return array[]|iterable
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            yield ['user-agent' => $agent];
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $allLines = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('*.txt');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->dir);

        foreach ($finder as $file) {
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $handle = @fopen($filepath, 'r');

            if (false === $handle) {
                $this->logger->emergency(new \RuntimeException('reading file ' . $filepath . ' caused an error'));
                continue;
            }

            $i = 1;

            while (!feof($handle)) {
                $line = fgets($handle, 65535);

                if (false === $line) {
                    continue;
                }
                ++$i;

                if (empty($line)) {
                    continue;
                }

                $line = trim($line);

                if (empty($line) || array_key_exists($line, $allLines)) {
                    continue;
                }

                yield $line;
                $allLines[$line] = 1;
            }

            fclose($handle);
        }
    }
}
