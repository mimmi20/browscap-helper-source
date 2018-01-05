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

use FileLoader\Loader;
use Psr\SimpleCache\CacheInterface as PsrCacheInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class CrawlerDetectSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \FileLoader\Loader
     */
    private $loader;

    /**
     * @param \Psr\Log\LoggerInterface          $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->loader = new Loader();
    }

    /**
     * @param int $limit
     *
     * @return iterable|string[]
     * @throws \FileLoader\Exception
     */
    public function getUserAgents(int $limit = 0): iterable
    {
        $counter = 0;

        foreach ($this->loadFromPath() as $agent) {
            if ($limit && $counter >= $limit) {
                return;
            }

            if (empty($agent)) {
                continue;
            }

            yield $agent;
            ++$counter;
        }
    }

    /**
     * @return iterable|string[]
     * @throws \FileLoader\Exception
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/jaybizzle/crawler-detect/tests/';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $allLines = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('crawlers.txt');
        $finder->name('devices.txt');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            if (!$file->isFile()) {
                $this->logger->emergency('not-files selected with finder');

                continue;
            }

            if ('txt' !== $file->getExtension()) {
                $this->logger->emergency('wrong file extension [' . $file->getExtension() . '] found with finder');

                continue;
            }

            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));
            $this->loader->setLocalFile($file->getPathname());

            /** @var \GuzzleHttp\Psr7\Response $response */
            $response = $this->loader->load();

            /** @var \FileLoader\Psr7\Stream $stream */
            $stream = $response->getBody();

            try {
                $stream->read(1);
            } catch (\Throwable $e) {
                $this->logger->emergency(new \RuntimeException('reading file ' . $file->getPathname() . ' caused an error on line 0', 0, $e));
            }

            try {
                $stream->rewind();
            } catch (\Throwable $e) {
                $this->logger->emergency(new \RuntimeException('rewinding file ' . $file->getPathname() . ' caused an error on line 0', 0, $e));
            }

            $i = 1;

            while (!$stream->eof()) {
                try {
                    $line = $stream->read(65535);
                } catch (\Throwable $e) {
                    $this->logger->emergency(new \RuntimeException('reading file ' . $file->getPathname() . ' caused an error on line ' . $i, 0, $e));
                }
                ++$i;

                if (empty($line)) {
                    continue;
                }

                $line = trim($line);

                if (array_key_exists($line, $allLines)) {
                    continue;
                }

                yield $line;
                $allLines[$line] = 1;
            }
        }
    }
}
