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
use ExceptionalJSON\DecodeErrorException;
use JsonClass\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class DetectorSource implements SourceInterface
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
        return 'mimmi20/browser-detector-tests';
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
        $path = 'vendor/mimmi20/browser-detector-tests/tests/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
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

            if ('' === $content || PHP_EOL === $content) {
                unlink($filepath);

                continue;
            }

            try {
                $data = (new Json())->decode(
                    $content,
                    true
                );
            } catch (DecodeErrorException $e) {
                $this->logger->critical(new \Exception('    parsing file content [' . $filepath . '] failed', 0, $e));

                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $test) {
                $agent = (string) UserAgent::fromHeaderArray($test['headers']);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'    => $test['result']['device']['deviceName'],
                        'marketingName' => $test['result']['device']['marketingName'],
                        'manufacturer'  => $test['result']['device']['manufacturer'],
                        'brand'         => $test['result']['device']['brand'],
                        'display'       => [
                            'width'  => $test['result']['device']['display']['width'],
                            'height' => $test['result']['device']['display']['height'],
                            'touch'  => $test['result']['device']['display']['touch'],
                            'type'   => $test['result']['device']['display']['type'],
                            'size'   => $test['result']['device']['display']['size'],
                        ],
                        'dualOrientation' => $test['result']['device']['dualOrientation'],
                        'type'            => $test['result']['device']['type'],
                        'simCount'        => $test['result']['device']['simCount'],
                        'market'          => [
                            'regions'   => $test['result']['device']['market']['regions'],
                            'countries' => $test['result']['device']['market']['countries'],
                            'vendors'   => $test['result']['device']['market']['vendors'],
                        ],
                        'connections' => $test['result']['device']['connections'],
                        'ismobile'    => (new \UaDeviceType\TypeLoader())->load($test['result']['device']['type'])->isMobile(),
                    ],
                    'browser' => [
                        'name'         => $test['result']['browser']['name'],
                        'modus'        => $test['result']['browser']['modus'],
                        'version'      => ('0.0.0' === $test['result']['browser']['version'] ? null : $test['result']['browser']['version']),
                        'manufacturer' => $test['result']['browser']['manufacturer'],
                        'bits'         => $test['result']['browser']['bits'],
                        'type'         => $test['result']['browser']['type'],
                        'isbot'        => (new \UaBrowserType\TypeLoader())->load($test['result']['browser']['type'])->isBot(),
                    ],
                    'platform' => [
                        'name'          => $test['result']['os']['name'],
                        'marketingName' => $test['result']['os']['marketingName'],
                        'version'       => ('0.0.0' === $test['result']['os']['version'] ? null : $test['result']['os']['version']),
                        'manufacturer'  => $test['result']['os']['manufacturer'],
                        'bits'          => $test['result']['os']['bits'],
                    ],
                    'engine' => [
                        'name'         => $test['result']['engine']['name'],
                        'version'      => $test['result']['engine']['version'],
                        'manufacturer' => $test['result']['engine']['manufacturer'],
                    ],
                ];
            }
        }
    }
}
