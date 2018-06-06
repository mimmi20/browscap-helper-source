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

use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\DeviceParserAbstract;

class PiwikSource implements SourceInterface
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
        return 'piwik/device-detector';
    }

    /**
     * @return iterable|string[]
     */
    public function getUserAgents(): iterable
    {
        yield from $this->loadFromPath();
    }

    /**
     * @return iterable|string[]
     */
    public function getHeaders(): iterable
    {
        foreach ($this->loadFromPath() as $agent) {
            yield (string) UserAgent::fromUseragent($agent);
        }
    }

    /**
     * @return iterable|array[]
     */
    public function getProperties(): iterable
    {
        $path = 'vendor/piwik/device-detector/Tests/fixtures';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

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

            $data = \Spyc::YAMLLoadString($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (empty($row['user_agent'])) {
                    continue;
                }

                $agent = trim($row['user_agent']);

                if (empty($agent)) {
                    continue;
                }

                yield (string) UserAgent::fromUseragent($agent) => [
                    'browser' => [
                        'name'    => $data['client']['name'],
                        'version' => $data['client']['version'],
                    ],
                    'platform' => [
                        'name'    => $data['os']['name'],
                        'version' => $data['os']['version'],
                    ],
                    'device' => [
                        'name'     => $data['device']['model'],
                        'brand'    => DeviceParserAbstract::getFullName($data['device']['brand']),
                        'type'     => $data['device']['type'],
                        'ismobile' => $this->isMobile($data),
                    ],
                    'engine' => [
                        'name'    => (!empty($data['client']['engine']) ? $data['client']['engine'] : null),
                        'version' => (!empty($data['client']['engine_version']) ? $data['client']['engine_version'] : null),
                    ],
                ];
            }
        }
    }

    /**
     * @return iterable|string[]
     */
    private function loadFromPath(): iterable
    {
        $path = 'vendor/piwik/device-detector/Tests/fixtures';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

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

            $data = \Spyc::YAMLLoadString($file->getContents());

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                if (empty($row['user_agent'])) {
                    continue;
                }

                $agent = trim($row['user_agent']);

                if (empty($agent)) {
                    continue;
                }

                yield $agent;
            }
        }
    }

    private function isMobile(array $data): bool
    {
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
            ])
        ) {
            return true;
        }

        // non mobile device types
        if (!empty($deviceType) && in_array($deviceType, [
                DeviceParserAbstract::DEVICE_TYPE_TV,
                DeviceParserAbstract::DEVICE_TYPE_SMART_DISPLAY,
                DeviceParserAbstract::DEVICE_TYPE_CONSOLE,
            ])
        ) {
            return false;
        }

        // Check for browsers available for mobile devices only
        if ($data['client']['type'] === 'browser' && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
            return true;
        }

        if (empty($os) || 'UNK' === $os) {
            return false;
        }

        return !$this->isDesktop($data);
    }

    private function isDesktop(array $data): bool
    {
        $osShort = $data['os']['short_name'];
        if (empty($osShort) || 'UNK' === $osShort) {
            return false;
        }
        // Check for browsers available for mobile devices only
        if ($data['client']['type'] === 'browser' && Browser::isMobileOnlyBrowser($data['client']['short_name'] ? $data['client']['short_name'] : 'UNK')) {
            return false;
        }

        return in_array($data['os_family'], ['AmigaOS', 'IBM', 'GNU/Linux', 'Mac', 'Unix', 'Windows', 'BeOS', 'Chrome OS']);
    }
}
