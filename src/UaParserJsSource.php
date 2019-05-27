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
use ExceptionalJSON\DecodeErrorException;
use JsonClass\Json;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

final class UaParserJsSource implements SourceInterface
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
        return 'ua-parser-js';
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
        $path = 'node_modules/ua-parser-js/test';

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

        $agents = [];

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            try {
                $provider = (new Json())->decode(
                    $file->getContents(),
                    true
                );
            } catch (DecodeErrorException $e) {
                $this->logger->error(
                    new \Exception(sprintf('file %s contains invalid json.', $file->getPathname()), 0, $e)
                );
                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            $providerName = $file->getFilename();
            $base         = [
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

            foreach ($provider as $data) {
                $agent = trim($data['ua']);

                if (empty($agent)) {
                    continue;
                }

                if (!isset($agents[$agent])) {
                    $agents[$agent] = $base;
                }

                switch ($providerName) {
                    case 'browser-test.json':
                        $agents[$agent]['browser']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                        $agents[$agent]['browser']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

                        break;
                    case 'device-test.json':
                        $agents[$agent]['device']['name']  = 'undefined' === $data['expect']['model'] ? '' : $data['expect']['model'];
                        $agents[$agent]['device']['brand'] = 'undefined' === $data['expect']['vendor'] ? '' : $data['expect']['vendor'];
                        $agents[$agent]['device']['type']  = 'undefined' === $data['expect']['type'] ? '' : $data['expect']['type'];

                        break;
                    case 'os-test.json':
                        $agents[$agent]['platform']['name']    = 'undefined' === $data['expect']['name'] ? '' : $data['expect']['name'];
                        $agents[$agent]['platform']['version'] = 'undefined' === $data['expect']['version'] ? '' : $data['expect']['version'];

                        break;
                    // Skipping cpu-test.json because we don't look at CPU data, which is all that file tests against
                    // Skipping engine-test.json because we don't look at Engine data // @todo: fix
                    // Skipping mediaplayer-test.json because it seems that this file isn't used in this project's actual tests (see test.js)
                }
            }
        }

        foreach ($agents as $agent => $test) {
            $agent = (string) UserAgent::fromUseragent($agent);

            if (empty($agent)) {
                continue;
            }

            yield $agent => $test;
        }
    }
}
