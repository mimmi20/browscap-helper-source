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

use BrowscapHelper\Source\Ua\UserAgent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class MobileDetectSource implements SourceInterface
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
     * @return string
     */
    public function getName(): string
    {
        return 'mobiledetect/mobiledetectlib';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            yield (string) UserAgent::fromUseragent($agent);
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        $path = 'vendor/mobiledetect/mobiledetectlib/tests/providers/vendors';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.php');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = include $filepath;
            $key  = $file->getBasename('.php');

            if (!is_array($data) || !array_key_exists($key, $data) || !is_array($data[$key])) {
                continue;
            }

            foreach (array_keys($data[$key]) as $agent) {
                $agent = trim($agent);

                if (empty($agent)) {
                    continue;
                }

                yield (string) UserAgent::fromUseragent($agent) => [
                    'browser' => [
                        'name'    => null,
                        'version' => null,
                    ],
                    'platform' => [
                        'name'    => null,
                        'version' => null,
                    ],
                    'device' => [
                        'name'     => null,
                        'brand'    => null,
                        'type'     => null,
                        'ismobile' => null,
                    ],
                    'engine' => [
                        'name'    => null,
                        'version' => null,
                    ],
                ];
            }
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/mobiledetect/mobiledetectlib/tests/providers/vendors';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.php');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = include $filepath;
            $key  = $file->getBasename('.php');

            if (!is_array($data) || !array_key_exists($key, $data) || !is_array($data[$key])) {
                continue;
            }

            foreach (array_keys($data[$key]) as $agent) {
                $agent = trim($agent);

                if (empty($agent)) {
                    continue;
                }

                yield $agent;
            }
        }
    }
}
