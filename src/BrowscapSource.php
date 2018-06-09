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

                $isMobile = false;

                switch ($data['properties']['Device_Type']) {
                    case 'Mobile Phone':
                    case 'Tablet':
                    case 'Console':
                    case 'Digital Camera':
                    case 'Ebook Reader':
                    case 'Mobile Device':
                        $isMobile = true;

                        break;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'       => $data['properties']['Device_Code_Name'],
                        'marketingName'    => $data['properties']['Device_Name'],
                        'manufacturer'     => $data['properties']['Device_Maker'],
                        'brand'            => $data['properties']['Device_Brand_Name'],
                        'pointingMethod'   => $data['properties']['Device_Pointing_Method'],
                        'resolutionWidth'  => null,
                        'resolutionHeight' => null,
                        'dualOrientation'  => null,
                        'type'             => $data['properties']['Device_Type'],
                        'ismobile'         => $isMobile,
                    ],
                    'browser' => [
                        'name'         => $data['properties']['Browser'],
                        'modus'        => $data['properties']['Browser_Modus'],
                        'version'      => $data['properties']['Version'],
                        'manufacturer' => $data['properties']['Browser_Maker'],
                        'bits'         => $data['properties']['Browser_Bits'],
                        'type'         => $data['properties']['Browser_Type'],
                        'isbot'        => $data['properties']['Crawler'],
                    ],
                    'platform' => [
                        'name'          => $data['properties']['Platform'] ?? 'unknown',
                        'marketingName' => null,
                        'version'       => $data['properties']['Platform_Version'],
                        'manufacturer'  => $data['properties']['Platform_Maker'],
                        'bits'          => $data['properties']['Platform_Bits'],
                    ],
                    'engine' => [
                        'name'         => $data['properties']['RenderingEngine_Name'],
                        'version'      => $data['properties']['RenderingEngine_Version'],
                        'manufacturer' => $data['properties']['RenderingEngine_Maker'],
                    ],
                ];
            }
        }
    }
}
