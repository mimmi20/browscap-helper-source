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
use Psr\SimpleCache\CacheInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class UapCoreSource implements SourceInterface
{
    use GetUserAgentsTrait;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Psr\SimpleCache\CacheInterface
     */
    private $cache;

    /**
     * @param \Psr\Log\LoggerInterface        $logger
     * @param \Psr\SimpleCache\CacheInterface $cache
     */
    public function __construct(LoggerInterface $logger, CacheInterface $cache)
    {
        $this->logger = $logger;
        $this->cache  = $cache;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'ua-parser/uap-core';
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
            $ua    = UserAgent::fromUseragent(addcslashes($data['user_agent_string'], "\n"));
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
            $ua = addcslashes($data['user_agent_string'], "\n");
            if (empty($ua)) {
                continue;
            }

            if (isset($tests[$ua])) {
                $browser  = $tests[$ua]['browser'];
                $platform = $tests[$ua]['platform'];
                $device   = $tests[$ua]['device'];
                $engine   = $tests[$ua]['engine'];
            } else {
                $browser = [
                    'name' => null,
                    'modus' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ];

                $platform = [
                    'name' => null,
                    'marketingName' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                ];

                $device = [
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'pointingMethod' => null,
                    'resolutionWidth' => null,
                    'resolutionHeight' => null,
                    'dualOrientation' => null,
                    'type' => null,
                    'ismobile' => null,
                ];

                $engine = [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ];
            }

            switch ($providerName) {
                case 'test_device.yaml':
                    $device = [
                        'deviceName' => $data['model'],
                        'marketingName' => null,
                        'manufacturer' => null,
                        'brand' => $data['brand'],
                        'pointingMethod' => null,
                        'resolutionWidth' => null,
                        'resolutionHeight' => null,
                        'dualOrientation' => null,
                        'type' => null,
                        'ismobile' => null,
                    ];

                    break;
                case 'test_os.yaml':
                case 'additional_os_tests.yaml':
                    $platform = [
                        'name' => $data['family'],
                        'marketingName' => null,
                        'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                        'manufacturer' => null,
                        'bits' => null,
                    ];

                    break;
                case 'test_ua.yaml':
                case 'firefox_user_agent_strings.yaml':
                case 'opera_mini_user_agent_strings.yaml':
                case 'pgts_browser_list.yaml':
                    $browser = [
                        'name' => $data['family'],
                        'modus' => null,
                        'version' => $data['major'] . (!empty($data['minor']) ? '.' . $data['minor'] : ''),
                        'manufacturer' => null,
                        'bits' => null,
                        'type' => null,
                        'isbot' => null,
                    ];

                    break;
            }

            $tests[$ua] = [
                'browser' => $browser,
                'platform' => $platform,
                'device' => $device,
                'engine' => $engine,
            ];
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
     * @return array[]|iterable
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/ua-parser/uap-core/tests';

        if (!file_exists($path)) {
            $this->logger->warning(sprintf('    path %s not found', $path));

            return;
        }

        $this->logger->info(sprintf('    reading path %s', $path));

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

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider     = Yaml::parse($file->getContents());
            $providerName = $file->getFilename();

            foreach ($provider['test_cases'] as $data) {
                yield $providerName => $data;
            }
        }
    }
}
