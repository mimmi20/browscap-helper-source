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

class SinergiSource implements SourceInterface
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
        return 'sinergi/browser-detector';
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
        $path = 'vendor/sinergi/browser-detector/tests/BrowserDetector/Tests/_files';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.xml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = simplexml_load_file($filepath);

            foreach ($provider->strings as $string) {
                foreach ($string as $field) {
                    $ua    = explode("\n", $field->field[6]);
                    $ua    = array_map('trim', $ua);
                    $agent = trim(implode(' ', $ua));

                    if (empty($agent)) {
                        continue;
                    }

                    $browser        = (string) $field->field[0];
                    $browserVersion = (string) $field->field[1];

                    $platform        = (string) $field->field[2];
                    $platformVersion = (string) $field->field[3];

                    $device = (string) $field->field[4];

                    yield (string) UserAgent::fromUseragent($agent) => [
                        'browser' => [
                            'name'    => $browser,
                            'version' => $browserVersion,
                        ],
                        'platform' => [
                            'name'    => $platform,
                            'version' => $platformVersion,
                        ],
                        'device' => [
                            'name'     => $device,
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
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/sinergi/browser-detector/tests/BrowserDetector/Tests/_files';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.xml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = simplexml_load_file($filepath);

            foreach ($provider->strings as $string) {
                foreach ($string as $field) {
                    $ua    = explode("\n", $field->field[6]);
                    $ua    = array_map('trim', $ua);
                    $agent = trim(implode(' ', $ua));

                    if (empty($agent)) {
                        continue;
                    }

                    yield $agent;
                }
            }
        }
    }
}
