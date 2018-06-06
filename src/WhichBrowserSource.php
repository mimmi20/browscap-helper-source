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
use http\Header;
use Psr\Log\LoggerInterface;
use Seld\JsonLint\JsonParser;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class WhichBrowserSource implements SourceInterface
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Seld\JsonLint\JsonParser
     */
    private $jsonParser;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;

        $this->jsonParser = new JsonParser();
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'whichbrowser/parser';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        foreach ($this->loadFromPath() as $headers) {
            if (empty($headers)) {
                continue;
            }

            $agent = trim($headers['User-Agent']);

            if (empty($agent)) {
                continue;
            }

            yield $agent;
        }
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $headers) {
            if (empty($headers)) {
                continue;
            }

            $lowerHeaders = [];

            foreach ($headers as $header => $value) {
                $lowerHeaders[mb_strtolower($header)] = $value;
            }

            yield (string) UserAgent::fromHeaderArray($lowerHeaders);
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        $path = 'vendor/whichbrowser/parser/tests/data';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.yaml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = Yaml::parse($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                $headers = $this->getHeadersFromRow($row);

                if (empty($headers)) {
                    continue;
                }

                $lowerHeaders = [];

                foreach ($headers as $header => $value) {
                    $lowerHeaders[mb_strtolower($header)] = $value;
                }

                yield (string) UserAgent::fromHeaderArray($lowerHeaders) => [
                    'browser' => [
                        'name'    => $row['browser']['name'],
                        'version' => is_array($row['browser']['version']) ? $row['browser']['version']['value'] : $row['browser']['version'],
                    ],
                    'platform' => [
                        'name'    => $row['os']['name'],
                        'version' => is_array($row['os']['version']) ? $row['os']['version']['value'] : $row['os']['version'],
                    ],
                    'device' => [
                        'name'     => $row['device']['model'],
                        'brand'    => $row['device']['manufacturer'],
                        'type'     => $row['device']['type'],
                        'ismobile' => $this->isMobile($row) ? true : false,
                    ],
                    'engine' => [
                        'name'    => null,
                        'version' => null,
                    ],
                ];
            }
        }
    }

    /**
     * @return array[]|iterable
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/whichbrowser/parser/tests/data';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $finder = new Finder();
        $finder->files();
        $finder->name('*.yaml');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));

            $data = Yaml::parse($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                $headers = $this->getHeadersFromRow($row);

                if (empty($headers)) {
                    continue;
                }

                yield $headers;
            }
        }
    }

    /**
     * @param array $row
     *
     * @return array
     */
    private function getHeadersFromRow(array $row): array
    {
        $headers = [];

        if (isset($row['headers'])) {
            if (isset($row['headers']) && is_array($row['headers'])) {
                return $row['headers'];
            }

            if (class_exists(Header::class)) {
                // pecl_http versions 2.x/3.x
                $headers = Header::parse($row['headers']);
            } elseif (function_exists('\http_parse_headers')) {
                // pecl_http version 1.x
                $headers = \http_parse_headers($row['headers']);
            } elseif (0 === mb_strpos($row['headers'], 'User-Agent: ')) {
                $headers = ['user-agent' => str_replace('User-Agent: ', '', $row['headers'])];
            } else {
                return [];
            }
        }

        if (is_array($headers)) {
            return $headers;
        }

        return [];
    }

    /**
     * @param array $data
     *
     * @return bool|null
     */
    private function isMobile(array $data): ?bool
    {
        if (!isset($data['device']['type'])) {
            return null;
        }

        $mobileTypes = ['mobile', 'tablet', 'ereader', 'media', 'watch', 'camera'];

        if (in_array($data['device']['type'], $mobileTypes)) {
            return true;
        }

        if ($data['device']['type'] === 'gaming') {
            if (isset($data['device']['subtype']) && $data['device']['subtype'] === 'portable') {
                return true;
            }
        }

        return false;
    }
}
