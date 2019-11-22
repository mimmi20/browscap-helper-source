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
use http\Header;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class WhichBrowserSource implements SourceInterface
{
    use GetUserAgentsTrait;

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
        return 'whichbrowser/parser';
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     *
     * @return array[]|iterable
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $row) {
            $lowerHeaders = [];

            foreach ($this->getHeadersFromRow($row) as $header => $value) {
                $lowerHeaders[mb_strtolower((string) $header)] = $value;
            }

            $ua    = UserAgent::fromHeaderArray($lowerHeaders);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
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
        foreach ($this->loadFromPath() as $row) {
            $lowerHeaders = [];

            foreach ($this->getHeadersFromRow($row) as $header => $value) {
                $lowerHeaders[mb_strtolower((string) $header)] = $value;
            }

            $ua    = UserAgent::fromHeaderArray($lowerHeaders);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => [
                'device' => [
                    'deviceName' => $row['device']['model'] ?? null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => $row['device']['manufacturer'] ?? null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'dualOrientation' => null,
                    'type' => $row['device']['type'] ?? null,
                    'simCount' => null,
                    'market' => [
                        'regions' => null,
                        'countries' => null,
                        'vendors' => null,
                    ],
                    'connections' => null,
                    'ismobile' => $this->isMobile($row) ? true : false,
                ],
                'browser' => [
                    'name' => $row['browser']['name'] ?? null,
                    'modus' => null,
                    'version' => (!empty($row['browser']['version']) ? is_array($row['browser']['version']) ? $row['browser']['version']['value'] : $row['browser']['version'] : null),
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $row['os']['name'] ?? null,
                    'marketingName' => null,
                    'version' => (!empty($row['os']['version']) ? is_array($row['os']['version']) ? $row['os']['version']['value'] : $row['os']['version'] : null),
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => $row['engine']['name'] ?? null,
                    'version' => $row['engine']['version'] ?? null,
                    'manufacturer' => null,
                ],
            ];
        }
    }

    /**
     * @throws \LogicException
     * @throws \RuntimeException
     *
     * @return array[]|iterable
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/whichbrowser/parser/tests/data';

        if (!file_exists($path)) {
            $this->logger->warning(sprintf('    path %s not found', $path));

            return;
        }

        $this->logger->info(sprintf('    reading path %s', $path));

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
                yield $row;
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

        if (array_key_exists('headers', $row)) {
            if (is_array($row['headers'])) {
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
     * @return bool
     */
    private function isMobile(array $data): bool
    {
        if (!isset($data['device']['type'])) {
            return false;
        }

        $mobileTypes = ['mobile', 'tablet', 'ereader', 'media', 'watch', 'camera'];

        if (in_array($data['device']['type'], $mobileTypes, true)) {
            return true;
        }

        if ('gaming' === $data['device']['type']) {
            if (isset($data['device']['subtype']) && 'portable' === $data['device']['subtype']) {
                return true;
            }
        }

        return false;
    }
}
