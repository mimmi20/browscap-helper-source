<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use DeviceDetector\Parser\Client\Browser;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use FilterIterator;
use Iterator;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

use function array_change_key_case;
use function array_key_exists;
use function array_map;
use function array_merge;
use function assert;
use function explode;
use function file_exists;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_contains;
use function str_replace;
use function trim;

use const CASE_LOWER;
use const STR_PAD_RIGHT;

final class MatomoSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'matomo/device-detector';

    private const string PATH = 'vendor/matomo/device-detector/Tests/fixtures';

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln(
            "\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH),
            OutputInterface::VERBOSITY_NORMAL,
        );

        return false;
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $files = new class ($iterator, 'yml') extends FilterIterator {
            /**
             * @param Iterator<SplFileInfo> $iterator
             *
             * @throws void
             */
            public function __construct(Iterator $iterator, private readonly string $extension)
            {
                parent::__construct($iterator);
            }

            /** @throws void */
            #[Override]
            public function accept(): bool
            {
                $file = $this->getInnerIterator()->current();

                assert($file instanceof SplFileInfo);

                return $file->isFile() && $file->getExtension() === $this->extension;
            }
        };

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                false,
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

            try {
                $data = Yaml::parseFile($filepath);
            } catch (ParseException $e) {
                throw new SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                assert(is_array($row));

                /** @phpstan-var array{user_agent?: string, headers?: array<non-empty-string, non-empty-string>, os: array{name?: string, short_name: string|null, version?: string}, client?: array{name?: string, type: string, short_name?: string, engine?: string, engine_version?: string}, bot?: array{name: string, category: string}, os_family: string, device: array{type?: int, model?: string, brand?: string}} $row */
                if (!array_key_exists('user_agent', $row) && !array_key_exists('headers', $row)) {
                    continue;
                }

                $headers = [];

                if (array_key_exists('user_agent', $row) && is_string($row['user_agent'])) {
                    if (str_contains($row['user_agent'], "\n")) {
                        $ua    = explode("\n", $row['user_agent']);
                        $ua    = array_map('trim', $ua);
                        $agent = trim(implode(' ', $ua));
                    } else {
                        $agent = trim($row['user_agent']);
                    }

                    if ($agent !== '') {
                        $headers = ['user-agent' => $agent];
                    }
                }

                if (array_key_exists('headers', $row) && is_array($row['headers'])) {
                    $headers = array_merge($headers, $row['headers']);
                }

                if ($headers === []) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'client' => [
                        'bits' => null,
                        'isbot' => !empty($data['bot']),
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => isset($data['bot']) ? ($data['bot']['name'] ?? null) : ($row['client']['name'] ?? null),
                        'type' => isset($data['bot']) ? ($data['bot']['category'] ?? null) : ($data['client']['type'] ?? null),
                        'version' => isset($data['bot']) ? null : ($data['client']['version'] ?? null),
                    ],
                    'device' => [
                        'brand' => (!empty($row['device']['brand']) ? AbstractDeviceParser::getFullName(
                            $row['device']['brand'],
                        ) : null),
                        'deviceName' => $row['device']['model'] ?? null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'type' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'ismobile' => $this->isMobile($row),
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => $row['device']['type'] ?? null,
                    ],
                    'engine' => [
                        'manufacturer' => null,
                        'name' => (!empty($row['client']['engine']) ? $row['client']['engine'] : null),
                        'version' => (!empty($row['client']['engine_version']) ? $row['client']['engine_version'] : null),
                    ],
                    'file' => $filepath,
                    'headers' => array_change_key_case($headers, CASE_LOWER),
                    'platform' => [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => $row['os']['name'] ?? null,
                        'version' => $row['os']['version'] ?? null,
                    ],
                    'raw' => $row,
                ];
            }
        }
    }

    /**
     * @param array<mixed> $data
     * @phpstan-param array{os: array{short_name: string|null}, client?: array{type: string, short_name?: string}, bot?: array{name: string, category: string}, os_family: string, device: array{type?: int}} $data
     *
     * @throws void
     */
    private function isMobile(array $data): bool
    {
        if (empty($data['device']['type'])) {
            return false;
        }

        $device     = $data['device']['type'];
        $deviceType = AbstractDeviceParser::getAvailableDeviceTypes()[$device];

        if (!empty($deviceType)) {
            // Mobile device types
            if (
                in_array(
                    $deviceType,
                    [
                        AbstractDeviceParser::DEVICE_TYPE_FEATURE_PHONE,
                        AbstractDeviceParser::DEVICE_TYPE_SMARTPHONE,
                        AbstractDeviceParser::DEVICE_TYPE_TABLET,
                        AbstractDeviceParser::DEVICE_TYPE_PHABLET,
                        AbstractDeviceParser::DEVICE_TYPE_CAMERA,
                        AbstractDeviceParser::DEVICE_TYPE_PORTABLE_MEDIA_PAYER,
                    ],
                    true,
                )
            ) {
                return true;
            }

            // non mobile device types
            if (
                in_array(
                    $deviceType,
                    [
                        AbstractDeviceParser::DEVICE_TYPE_TV,
                        AbstractDeviceParser::DEVICE_TYPE_SMART_DISPLAY,
                        AbstractDeviceParser::DEVICE_TYPE_CONSOLE,
                    ],
                    true,
                )
            ) {
                return false;
            }
        }

        // Check for browsers available for mobile devices only
        if (
            isset($data['client']['type'])
            && $data['client']['type'] === 'browser'
            && Browser::isMobileOnlyBrowser($data['client']['short_name'] ?? 'UNK')
        ) {
            return true;
        }

        $osShort = $data['os']['short_name'] ?? null;

        if (empty($osShort) || $osShort === 'UNK') {
            return false;
        }

        return !$this->isDesktop($data);
    }

    /**
     * @param array<mixed> $data
     * @phpstan-param array{os: array{short_name: string|null}, client?: array{type: string, short_name?: string}, bot?: array{name: string, category: string}, os_family: string, device: array{type?: int}} $data
     *
     * @throws void
     */
    private function isDesktop(array $data): bool
    {
        $osShort = $data['os']['short_name'];

        if (empty($osShort) || $osShort === 'UNK') {
            return false;
        }

        // Check for browsers available for mobile devices only
        if (
            isset($data['client']['type'])
            && $data['client']['type'] === 'browser'
            && Browser::isMobileOnlyBrowser($data['client']['short_name'] ?? 'UNK')
        ) {
            return false;
        }

        return in_array(
            $data['os_family'],
            ['AmigaOS', 'IBM', 'GNU/Linux', 'Mac', 'Unix', 'Windows', 'BeOS', 'Chrome OS'],
            true,
        );
    }
}
