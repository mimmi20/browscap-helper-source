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
use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\DeviceParserAbstract;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

final class PiwikSource implements SourceInterface
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
        return 'piwik/device-detector';
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
            $ua    = explode("\n", $row['user_agent']);
            $ua    = array_map('trim', $ua);
            $agent = trim(implode(' ', $ua));

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
     * @throws \RuntimeException
     *
     * @return array[]|iterable
     */
    public function getProperties(): iterable
    {
        foreach ($this->loadFromPath() as $row) {
            $ua    = explode("\n", $row['user_agent']);
            $ua    = array_map('trim', $ua);
            $agent = trim(implode(' ', $ua));

            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $agent => [
                'device' => [
                    'deviceName' => $row['device']['model'] ?? null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => (!empty($row['device']['brand']) ? DeviceParserAbstract::getFullName($row['device']['brand']) : null),
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
                    'ismobile' => $this->isMobile($row),
                ],
                'browser' => [
                    'name' => $row['client']['name'] ?? null,
                    'modus' => null,
                    'version' => $row['client']['version'] ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => $row['os']['name'] ?? null,
                    'marketingName' => null,
                    'version' => $row['os']['version'] ?? null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => (!empty($row['client']['engine']) ? $row['client']['engine'] : null),
                    'version' => (!empty($row['client']['engine_version']) ? $row['client']['engine_version'] : null),
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
        $path = 'vendor/piwik/device-detector/Tests/fixtures';

        if (!file_exists($path)) {
            $this->logger->warning(sprintf('    path %s not found', $path));

            return;
        }

        $this->logger->info(sprintf('    reading path %s', $path));

        $finder = new Finder();
        $finder->files();
        $finder->name('*.yml');
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
                if (empty($row['user_agent'])) {
                    continue;
                }

                yield $row;
            }
        }
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function isMobile(array $data): bool
    {
        if (empty($data['device']['type'])) {
            return false;
        }

        $device     = $data['device']['type'];
        $os         = $data['os']['short_name'];
        $deviceType = DeviceParserAbstract::getAvailableDeviceTypes()[$device];

        // Mobile device types
        if (!empty($deviceType) && in_array($deviceType, [
            DeviceParserAbstract::DEVICE_TYPE_FEATURE_PHONE,
            DeviceParserAbstract::DEVICE_TYPE_SMARTPHONE,
            DeviceParserAbstract::DEVICE_TYPE_TABLET,
            DeviceParserAbstract::DEVICE_TYPE_PHABLET,
            DeviceParserAbstract::DEVICE_TYPE_CAMERA,
            DeviceParserAbstract::DEVICE_TYPE_PORTABLE_MEDIA_PAYER,
        ], true)
        ) {
            return true;
        }

        // non mobile device types
        if (!empty($deviceType) && in_array($deviceType, [
            DeviceParserAbstract::DEVICE_TYPE_TV,
            DeviceParserAbstract::DEVICE_TYPE_SMART_DISPLAY,
            DeviceParserAbstract::DEVICE_TYPE_CONSOLE,
        ], true)
        ) {
            return false;
        }

        // Check for browsers available for mobile devices only
        if ('browser' === $data['client']['type'] && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
            return true;
        }

        if (empty($os) || 'UNK' === $os) {
            return false;
        }

        return !$this->isDesktop($data);
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function isDesktop(array $data): bool
    {
        $osShort = $data['os']['short_name'];
        if (empty($osShort) || 'UNK' === $osShort) {
            return false;
        }
        // Check for browsers available for mobile devices only
        if ('browser' === $data['client']['type'] && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
            return false;
        }

        return in_array($data['os_family'], ['AmigaOS', 'IBM', 'GNU/Linux', 'Mac', 'Unix', 'Windows', 'BeOS', 'Chrome OS'], true);
    }
}
