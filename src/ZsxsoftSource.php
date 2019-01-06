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

class ZsxsoftSource implements SourceInterface
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
        return 'zsxsoft/php-useragent';
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
        $path = 'vendor/zsxsoft/php-useragent/tests';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $brands = [];
        $file   = new \SplFileObject('vendor/zsxsoft/php-useragent/lib/useragent_detect_device.php');
        $file->setFlags(\SplFileObject::DROP_NEW_LINE);
        while (!$file->eof()) {
            $line = trim($file->fgets());
            preg_match('/^\$brand = ("|\')(.*)("|\');$/', $line, $matches);

            if (0 < count($matches)) {
                $brand = $matches[2];
                if (!empty($brand)) {
                    $brands[] = $brand;
                }
            }
        }
        $brands = array_unique($brands);

        usort($brands, static function ($a, $b) {
            return mb_strlen($b) - mb_strlen($a);
        });

        $finder = new Finder();
        $finder->files();
        $finder->name('UserAgentList.php');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = require $filepath;

            foreach ($provider as $data) {
                $agent = trim($data[0][0]);

                if (empty($agent)) {
                    continue;
                }

                $brand = '';
                $model = '';

                foreach ($brands as $brand) {
                    if (false !== mb_strpos($data[1][8], $brand)) {
                        $model = trim(str_replace($brand, '', $data[1][8]));

                        break;
                    }
                    $brand = '';
                }

                $agent = (string) UserAgent::fromUseragent($agent);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'    => $model,
                        'marketingName' => null,
                        'manufacturer'  => null,
                        'brand'         => $brand,
                        'display'       => [
                            'width'  => null,
                            'height' => null,
                            'touch'  => null,
                            'type'   => null,
                            'size'   => null,
                        ],
                        'dualOrientation' => null,
                        'type'            => null,
                        'simCount'        => null,
                        'market'          => [
                            'regions'   => null,
                            'countries' => null,
                            'vendors'   => null,
                        ],
                        'connections' => null,
                        'ismobile'    => null,
                    ],
                    'browser' => [
                        'name'         => $data[1][2],
                        'modus'        => null,
                        'version'      => $data[1][3],
                        'manufacturer' => null,
                        'bits'         => null,
                        'type'         => null,
                        'isbot'        => null,
                    ],
                    'platform' => [
                        'name'          => $data[1][5],
                        'marketingName' => null,
                        'version'       => $data[1][6],
                        'manufacturer'  => null,
                        'bits'          => null,
                    ],
                    'engine' => [
                        'name'         => null,
                        'version'      => null,
                        'manufacturer' => null,
                    ],
                ];
            }
        }
    }
}
