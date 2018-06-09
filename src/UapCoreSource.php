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
use Doctrine\Common\Cache\PhpFileCache;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

class UapCoreSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Doctrine\Common\Cache\PhpFileCache
     */
    private $cache;

    /**
     * @param \Psr\Log\LoggerInterface            $logger
     * @param \Doctrine\Common\Cache\PhpFileCache $cache
     */
    public function __construct(LoggerInterface $logger, PhpFileCache $cache)
    {
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'ua-parser/uap-core';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            $headers = UserAgent::fromString($headers)->getHeader();

            if (!isset($headers['user-agent'])) {
                continue;
            }

            yield $headers['user-agent'];
        }
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $headers => $test) {
            yield $headers;
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/ua-parser/uap-core/tests';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.yaml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        if (file_exists('vendor/ua-parser/uap-core/test_resources')) {
            $finder->in('vendor/ua-parser/uap-core/test_resources');
        }

        $tests = [];

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $this->processFixture($file, $tests);
        }

        foreach ($tests as $agent => $test) {
            $agent = (string) UserAgent::fromUseragent($agent);

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }

    /**
     * @param \Symfony\Component\Finder\SplFileInfo $fixture
     * @param array                                 $tests
     */
    private function processFixture(SplFileInfo $fixture, array &$tests): void
    {
        $key = sha1_file($fixture->getPathname());
        if ($this->cache->contains($key)) {
            $records = $this->cache->fetch($key);

            foreach ($records as $ua => $data) {
                $ua = addcslashes($ua, "\n");
                if (!isset($tests[$ua])) {
                    $tests[$ua] = [
                        'device'   => [
                            'deviceName'      => null,
                            'marketingName'   => null,
                            'manufacturer'    => null,
                            'brand'           => null,
                            'pointingMethod'  => null,
                            'resolutionWidth' => null,
                            'resolutionHeight' => null,
                            'dualOrientation' => null,
                            'type'            => null,
                            'ismobile'        => null,
                        ],
                        'browser'  => [
                            'name'         => null,
                            'modus' => null,
                            'version'      => null,
                            'manufacturer' => null,
                            'bits' => null,
                            'type'         => null,
                            'isbot'        => null,
                        ],
                        'platform' => [
                            'name'          => null,
                            'marketingName' => null,
                            'version'       => null,
                            'manufacturer'  => null,
                            'bits' => null,
                        ],
                        'engine'   => [
                            'name'         => null,
                            'version'      => null,
                            'manufacturer' => null,
                        ],
                    ];
                }

                $tests[$ua] = array_replace_recursive($tests[$ua], $data);
            }
        } else {
            $provider = Yaml::parse(file_get_contents($fixture->getPathname()));

            $records = [];

            foreach ($provider['test_cases'] as $data) {
                $ua = $data['user_agent_string'];
                if (!empty($ua)) {
                    if (isset($tests[addcslashes($ua, "\n")])) {
                        $browser  = $tests[$ua]['browser'];
                        $platform = $tests[$ua]['platform'];
                        $device   = $tests[$ua]['device'];
                        $engine   = $tests[$ua]['engine'];
                    } else {
                        $browser = [
                            'name'         => null,
                            'modus' => null,
                            'version'      => null,
                            'manufacturer' => null,
                            'bits' => null,
                            'type'         => null,
                            'isbot'        => null,
                        ];

                        $platform = [
                            'name'          => null,
                            'marketingName' => null,
                            'version'       => null,
                            'manufacturer'  => null,
                            'bits' => null,
                        ];

                        $device = [
                            'deviceName'      => null,
                            'marketingName'   => null,
                            'manufacturer'    => null,
                            'brand'           => null,
                            'pointingMethod'  => null,
                            'resolutionWidth' => null,
                            'resolutionHeight' => null,
                            'dualOrientation' => null,
                            'type'            => null,
                            'ismobile'        => null,
                        ];

                        $engine = [
                            'name'         => null,
                            'version'      => null,
                            'manufacturer' => null,
                        ];
                    }

                    switch ($fixture->getFilename()) {
                        case 'test_device.yaml':
                            $device = [
                                'name'     => $data['model'],
                                'brand'    => $data['brand'],
                                'type'     => null,
                                'ismobile' => null,
                            ];

                            $records[$ua]['device'] = $device;

                            break;
                        case 'test_os.yaml':
                        case 'additional_os_tests.yaml':
                            $platform = [
                                'name'    => $data['family'],
                                'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                            ];

                            $records[$ua]['platform'] = $platform;

                            break;
                        case 'test_ua.yaml':
                        case 'firefox_user_agent_strings.yaml':
                        case 'opera_mini_user_agent_strings.yaml':
                        case 'pgts_browser_list.yaml':
                            $browser = [
                                'name'    => $data['family'],
                                'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                            ];

                            $records[$ua]['browser'] = $browser;

                            break;
                    }

                    $expected = [
                        'browser'  => $browser,
                        'platform' => $platform,
                        'device'   => $device,
                        'engine'   => $engine,
                    ];

                    $tests[addcslashes($ua, "\n")] = $expected;
                }
            }

            $this->cache->save($key, $records);
        }
    }
}
