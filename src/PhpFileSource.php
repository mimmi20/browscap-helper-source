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

final class PhpFileSource implements SourceInterface
{
    use GetUserAgentsTrait;

    /**
     * @var string
     */
    private $dir;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param string                   $dir
     */
    public function __construct(LoggerInterface $logger, string $dir)
    {
        $this->logger = $logger;
        $this->dir    = $dir;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'php-files';
    }

    /**
     * @throws \LogicException
     *
     * @return array[]|iterable
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @throws \LogicException
     *
     * @return array[]|iterable
     */
    public function getProperties(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => [
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
    }

    /**
     * @throws \LogicException
     *
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        if (!file_exists($this->dir)) {
            $this->logger->warning(sprintf('    path %s not found', $this->dir));

            return;
        }

        $this->logger->info(sprintf('    reading path %s', $this->dir));

        $finder = new Finder();
        $finder->files();
        $finder->name('*.php');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->dir);

        foreach ($finder as $file) {
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $provider = require $filepath;

            foreach (array_keys($provider) as $ua) {
                $agent = trim((string) $ua);

                if (empty($agent)) {
                    continue;
                }

                yield $agent;
            }
        }
    }
}
