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

class PiwikSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $path = 'vendor/piwik/device-detector/Tests/fixtures';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $allTests = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('*.yml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = \Spyc::YAMLLoadString($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (empty($row['user_agent'])) {
                    continue;
                }

                $agent = trim($row['user_agent']);

                if (empty($agent) || array_key_exists($agent, $allTests)) {
                    continue;
                }

                yield $agent;
                $allTests[$agent] = 1;
            }
        }
    }
}
