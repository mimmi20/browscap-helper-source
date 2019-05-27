<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

final class SinergiSource implements SourceInterface
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
     * @throws \LogicException
     * @throws \RuntimeException
     *
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            $headers = UserAgent::fromString($headers)->getHeader();

            if (!array_key_exists('user-agent', $headers)) {
                continue;
            }

            yield $headers['user-agent'];
        }
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     *
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            yield $headers;
        }
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     *
     * @return array[]|iterable
     */
    public function getProperties(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     *
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

            $content = $file->getContents();

            if (empty($content)) {
                $this->logger->error('    reading file ' . $filepath . ' failed. The file is empty.');
                continue;
            }

            $provider = \simplexml_load_string($content);

            if (false === $provider) {
                $this->logger->error('    reading file ' . $filepath . ' failed.');
                continue;
            }

            foreach ($provider->strings as $string) {
                foreach ($string as $field) {
                    $ua    = explode("\n", (string) $field->field[6]);
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
                    $agent  = (string) UserAgent::fromUseragent($agent);

                    if (empty($agent)) {
                        continue;
                    }

                    yield $agent => [
                        'device' => [
                            'deviceName' => $device,
                            'marketingName' => null,
                            'manufacturer' => null,
                            'brand' => null,
                            'display' => [
                                'width' => null,
                                'height' => null,
                                'touch' => null,
                                'type' => null,
                                'size' => null,
                            ],
                            'dualOrientation' => null,
                            'type' => null,
                            'simCount' => null,
                            'market' => [
                                'regions' => null,
                                'countries' => null,
                                'vendors' => null,
                            ],
                            'connections' => null,
                            'ismobile' => null,
                        ],
                        'browser' => [
                            'name' => $browser,
                            'modus' => null,
                            'version' => $browserVersion,
                            'manufacturer' => null,
                            'bits' => null,
                            'type' => null,
                            'isbot' => null,
                        ],
                        'platform' => [
                            'name' => $platform,
                            'marketingName' => null,
                            'version' => $platformVersion,
                            'manufacturer' => null,
                            'bits' => null,
                        ],
                        'engine' => [
                            'name' => null,
                            'version' => null,
                            'manufacturer' => null,
                        ],
                    ];
                }
            }
        }
    }
}
