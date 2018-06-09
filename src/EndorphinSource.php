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

class EndorphinSource implements SourceInterface
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
        return 'endorphin-studio/browser-detector';
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
        $path = 'vendor/endorphin-studio/browser-detector/tests/data/ua';

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

        $uas = [];

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = simplexml_load_file($filepath);

            foreach ($provider->test as $test) {
                $expected = [
                    'device' => [
                        'deviceName'       => null,
                        'marketingName'    => null,
                        'manufacturer'     => null,
                        'brand'            => null,
                        'pointingMethod'   => null,
                        'resolutionWidth'  => null,
                        'resolutionHeight' => null,
                        'dualOrientation'  => null,
                        'type'             => null,
                        'ismobile'         => null,
                    ],
                    'browser' => [
                        'name'         => null,
                        'modus'        => null,
                        'version'      => null,
                        'manufacturer' => null,
                        'bits'         => null,
                        'type'         => null,
                        'isbot'        => null,
                    ],
                    'platform' => [
                        'name'          => null,
                        'marketingName' => null,
                        'version'       => null,
                        'manufacturer'  => null,
                        'bits'          => null,
                    ],
                    'engine' => [
                        'name'         => null,
                        'version'      => null,
                        'manufacturer' => null,
                    ],
                ];

                foreach ($test->CheckList as $list) {
                    foreach ($list->Item as $item) {
                        switch ($item->Property) {
                            case 'OS->getName()':
                                $expected['platform']['name'] = (string) $item->Value;

                                break;
                            case 'OS->getVersion()':
                                $expected['platform']['version'] = (string) $item->Value;

                                break;
                            case 'Browser->getName()':
                                $expected['browser']['name'] = (string) $item->Value;

                                break;
                            case 'Device->getName()':
                                $expected['device']['name'] = (string) $item->Value;

                                break;
                            case 'Device->getType()':
                                $expected['device']['type'] = (string) $item->Value;

                                break;
                            case 'isMobile':
                                $expected['device']['ismobile'] = (bool) $item->Value ? 'true' : 'false';

                                break;
                            case 'Robot->getName()':
                                $expected['browser']['name'] = (string) $item->Value;

                                break;
                        }
                    }
                }

                foreach ($test->UAList->UA as $ua) {
                    $agent = (string) $ua;

                    if (!isset($uas[$agent])) {
                        $uas[$agent] = $expected;
                    } else {
                        $toInsert             = $expected;
                        $toInsert['browser']  = array_filter($expected['browser']);
                        $toInsert['platform'] = array_filter($expected['platform']);
                        $toInsert['device']   = array_filter($expected['device']);
                        $toInsert             = array_filter($expected);
                        $uas[$agent]          = array_replace_recursive($uas[$agent], $toInsert);
                    }
                }
            }
        }

        foreach ($uas as $ua => $test) {
            $agent = (string) UserAgent::fromUseragent($ua);

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }
}
