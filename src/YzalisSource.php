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
use Symfony\Component\Yaml\Yaml;

final class YzalisSource implements SourceInterface
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
        return 'yzalis/ua-parser';
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
        $path = 'vendor/yzalis/ua-parser/tests/UAParser/Tests/Fixtures';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('browsers.yml');
        $finder->name('devices.yml');
        $finder->name('email_clients.yml');
        $finder->name('operating_systems.yml');
        $finder->name('rendering_engines.yml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        $tests = [];

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = Yaml::parse($file->getContents());

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();

            foreach ($provider as $data) {
                $ua = $data[0];

                if (!array_key_exists($ua, $tests)) {
                    $tests[$ua] = [
                        'device' => [
                            'deviceName' => null,
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
                            'name' => null,
                            'modus' => null,
                            'version' => null,
                            'manufacturer' => null,
                            'bits' => null,
                            'type' => null,
                            'isbot' => null,
                        ],
                        'platform' => [
                            'name' => null,
                            'marketingName' => null,
                            'version' => null,
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

                switch ($providerName) {
                    case 'browsers.yml':
                        $tests[$ua]['browser']['name']    = $data[1];
                        $tests[$ua]['browser']['version'] = $data[2] . '.' . $data[3] . '.' . $data[4];

                        break;
                    case 'devices.yml':
                        $tests[$ua]['device']['name']  = $data[2];
                        $tests[$ua]['device']['brand'] = $data[1];
                        $tests[$ua]['device']['type']  = $data[3];

                        break;
                    case 'operating_systems.yml':
                        $tests[$ua]['platform']['name']    = $data[1];
                        $tests[$ua]['platform']['version'] = $data[2] . (null !== $data[3] ? '.' . $data[3] . (null !== $data[4] ? '.' . $data[4] : '') : '');

                        break;
                    case 'rendering_engines.yml':
                        $tests[$ua]['engine']['name']    = $data[1];
                        $tests[$ua]['engine']['version'] = $data[2];

                        break;
                    // Skipping other files because we dont test this
                }
            }
        }

        foreach ($tests as $agent => $test) {
            $agent = (string) UserAgent::fromUseragent((string) $agent);

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }
}
