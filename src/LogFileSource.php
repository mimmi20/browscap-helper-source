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

use BrowscapHelper\Source\Helper\FilePath;
use BrowscapHelper\Source\Reader\LogFileReader;
use BrowscapHelper\Source\Ua\UserAgent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;

class LogFileSource implements SourceInterface
{
    /**
     * @var string
     */
    private $sourcesDirectory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param string                   $sourcesDirectory
     */
    public function __construct(LoggerInterface $logger, string $sourcesDirectory)
    {
        $this->logger           = $logger;
        $this->sourcesDirectory = $sourcesDirectory;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'log-files';
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
        if (!file_exists($this->sourcesDirectory)) {
            return;
        }

        $this->logger->info('    reading path ' . $this->sourcesDirectory);

        $finder = new Finder();
        $finder->files();
        $finder->notName('*.filepart');
        $finder->notName('*.sql');
        $finder->notName('*.rename');
        $finder->notName('*.txt');
        $finder->notName('*.zip');
        $finder->notName('*.rar');
        $finder->notName('*.php');
        $finder->notName('*.gitkeep');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->sourcesDirectory);

        $filepathHelper = new FilePath();
        $reader         = new LogFileReader($this->logger);

        foreach ($finder as $file) {
            /* @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $filepathHelper->getPath($file);

            if (null === $filepath) {
                continue;
            }

            $reader->addLocalFile($filepath);
        }

        foreach ($reader->getAgents($this->logger) as $agent) {
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
        }
    }
}
