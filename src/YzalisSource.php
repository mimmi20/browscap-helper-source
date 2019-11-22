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
    use GetUserAgentsTrait;

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
     * @return array[]|iterable
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $providerName => $data) {
            $ua    = UserAgent::fromUseragent(trim($data[0]));
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
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
        $tests = [];

        foreach ($this->loadFromPath() as $providerName => $data) {
            $agent = trim($data[0]);

            if (empty($agent)) {
                continue;
            }

            if (!array_key_exists($agent, $tests)) {
                $tests[$agent] = [
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
                    $tests[$agent]['browser']['name']    = $data[1];
                    $tests[$agent]['browser']['version'] = $data[2] . '.' . $data[3] . '.' . $data[4];

                    break;
                case 'devices.yml':
                    $tests[$agent]['device']['name']  = $data[2];
                    $tests[$agent]['device']['brand'] = $data[1];
                    $tests[$agent]['device']['type']  = $data[3];

                    break;
                case 'operating_systems.yml':
                    $tests[$agent]['platform']['name']    = $data[1];
                    $tests[$agent]['platform']['version'] = $data[2] . (null !== $data[3] ? '.' . $data[3] . (null !== $data[4] ? '.' . $data[4] : '') : '');

                    break;
                case 'rendering_engines.yml':
                    $tests[$agent]['engine']['name']    = $data[1];
                    $tests[$agent]['engine']['version'] = $data[2];

                    break;
                // Skipping other files because we dont test this
            }
        }

        foreach ($tests as $agent => $test) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
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
            $this->logger->warning(sprintf('    path %s not found', $path));

            return;
        }

        $this->logger->info(sprintf('    reading path %s', $path));

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
                yield $providerName => $data;
            }
        }
    }
}
