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

class BrowscapSource implements SourceInterface
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
        return 'browscap/browscap';
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
     * @return array[]|iterable
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
        $path = 'vendor/browscap/browscap/tests/issues';

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

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (!array_key_exists('ua', $row)) {
                    continue;
                }

                $agent = trim($row['ua']);

                if (empty($agent)) {
                    continue;
                }

                $agent = (string) UserAgent::fromUseragent($agent);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'    => $row['properties']['Device_Code_Name'] ?? null,
                        'marketingName' => $row['properties']['Device_Name'] ?? null,
                        'manufacturer'  => $row['properties']['Device_Maker'] ?? null,
                        'brand'         => $row['properties']['Device_Brand_Name'] ?? null,
                        'display'       => [
                            'width'  => null,
                            'height' => null,
                            'touch'  => ('touchscreen' === $row['properties']['Device_Pointing_Method'] ?? null),
                            'type'   => null,
                            'size'   => null,
                        ],
                        'dualOrientation' => null,
                        'type'            => $row['properties']['Device_Type'] ?? null,
                        'simCount'        => null,
                        'market'          => [
                            'regions'   => null,
                            'countries' => null,
                            'vendors'   => null,
                        ],
                        'connections' => null,
                        'ismobile'    => (new \UaDeviceType\TypeLoader())->load($row['properties']['Device_Type'])->isMobile(),
                    ],
                    'browser' => [
                        'name'         => $row['properties']['Browser'] ?? null,
                        'modus'        => $row['properties']['Browser_Modus'] ?? null,
                        'version'      => $row['properties']['Version'] ?? null,
                        'manufacturer' => $row['properties']['Browser_Maker'] ?? null,
                        'bits'         => $row['properties']['Browser_Bits'] ?? null,
                        'type'         => $row['properties']['Browser_Type'] ?? null,
                        'isbot'        => (new \UaBrowserType\TypeLoader())->load($row['properties']['Browser_Type'])->isBot(),
                    ],
                    'platform' => [
                        'name'          => $row['properties']['Platform'] ?? null,
                        'marketingName' => null,
                        'version'       => $row['properties']['Platform_Version'] ?? null,
                        'manufacturer'  => $row['properties']['Platform_Maker'] ?? null,
                        'bits'          => $row['properties']['Platform_Bits'] ?? null,
                    ],
                    'engine' => [
                        'name'         => $row['properties']['RenderingEngine_Name'] ?? null,
                        'version'      => $row['properties']['RenderingEngine_Version'] ?? null,
                        'manufacturer' => $row['properties']['RenderingEngine_Maker'] ?? null,
                    ],
                ];
            }
        }
    }
}
