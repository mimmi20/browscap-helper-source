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

class JsonFileSource implements SourceInterface
{
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
        return 'json-files';
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
     * @return array[]|iterable
     */
    private function loadFromPath(): iterable
    {
        if (!file_exists($this->dir)) {
            return;
        }

        $this->logger->info('    reading path ' . $this->dir);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->dir);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            try {
                $data = (new Json())->decode(
                    $file->getContents(),
                    true
                );
            } catch (DecodeErrorException $e) {
                $this->logger->error(
                    new \Exception(sprintf('file %s contains invalid json.', $file->getPathname()), 0, $e)
                );
                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $headers) {
                $agent = (string) UserAgent::fromHeaderArray($headers);

                if (empty($agent)) {
                    continue;
                }

                yield $agent => [
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
            }
        }
    }
}
