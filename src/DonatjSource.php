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

class DonatjSource implements SourceInterface
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
        return 'donatj/phpuseragentparser';
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
        $path = 'vendor/donatj/phpuseragentparser/Tests';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('user_agents.json');
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
                continue;
            }

            try {
                $provider = (new Json())->decode(
                    $content,
                    true
                );
            } catch (DecodeErrorException $e) {
                $this->logger->critical(new \Exception('    parsing file content [' . $filepath . '] failed', 0, $e));

                continue;
            }

            if (!is_array($provider)) {
                continue;
            }

            foreach ($provider as $test => $data) {
                $agent = trim($test);

                if (empty($agent)) {
                    continue;
                }

                $agent = (string) UserAgent::fromUseragent($agent);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
                    'device' => [
                        'deviceName'    => null,
                        'marketingName' => null,
                        'manufacturer'  => null,
                        'brand'         => null,
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
                        'name'         => $data['browser'],
                        'modus'        => null,
                        'version'      => $data['version'],
                        'manufacturer' => null,
                        'bits'         => null,
                        'type'         => null,
                        'isbot'        => null,
                    ],
                    'platform' => [
                        'name'          => $data['platform'],
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
            }
        }
    }
}
