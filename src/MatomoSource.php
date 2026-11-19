<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2026, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Entity\TestProperties;
use BrowserDetector\Iterator\FilterIterator;
use DeviceDetector\Parser\Device\AbstractDeviceParser;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
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
use function is_array;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function mb_trim;
use function sprintf;
use function str_contains;
use function str_replace;

use const CASE_LOWER;
use const STR_PAD_RIGHT;

final class MatomoSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'matomo/device-detector';

    private const array PATHS = [
        'vendor/matomo/device-detector/Tests/fixtures',
        'vendor/matomo/device-detector/Tests/Parser/fixtures',
        'vendor/matomo/device-detector/Tests/Parser/Client/fixtures',
        'vendor/matomo/device-detector/Tests/Parser/Device/fixtures',
    ];

    /** @throws void */
    public function __construct()
    {
        // do nothing
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        $notFound = [];
        $anyFound = false;

        foreach (self::PATHS as $path) {
            if (!file_exists($path)) {
                $notFound[] = $path;

                continue;
            }

            $anyFound = true;
        }

        foreach ($notFound as $path) {
            $this->writeln(
                "\r" . '<error>' . $parentMessage . sprintf('- path %s not found', $path) . '</error>',
            );
        }

        return $anyFound;
    }

    /**
     * @return iterable<non-empty-string, TestProperties>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        foreach (self::PATHS as $path) {
            if (!file_exists($path)) {
                continue;
            }

            yield from $this->getPropertiesFromFile($path, $parentMessage, $messageLength);
        }
    }

    /**
     * @return iterable<non-empty-string, TestProperties>
     *
     * @throws Exception\SourceException
     */
    private function getPropertiesFromFile(string $path, string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', $path);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            options: OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
        } catch (UnexpectedValueException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        $files = new FilterIterator($iterator, 'yml');

        foreach ($files as $file) {
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                options: OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

            try {
                $data = Yaml::parseFile($filepath);
            } catch (ParseException $e) {
                throw new Exception\SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                /** @var array{user_agent?: string, headers?: array<non-empty-string, non-empty-string>, os: array{name?: string, short_name: string|null, version?: string}, client?: array{name?: string, type: string, short_name?: string, engine?: string, engine_version?: string}, bot?: array{name: string, category: string}, os_family: string, device: array{type?: string, model?: string, brand?: string}} $row */
                if (!is_array($row)) {
                    continue;
                }

                if (!array_key_exists('user_agent', $row) && !array_key_exists('headers', $row)) {
                    continue;
                }

                $headers = [];

                if (array_key_exists('user_agent', $row) && is_string($row['user_agent'])) {
                    if (str_contains($row['user_agent'], "\n")) {
                        $ua    = explode("\n", $row['user_agent']);
                        $ua    = array_map(mb_trim(...), $ua);
                        $agent = mb_trim(implode(' ', $ua));
                    } else {
                        $agent = mb_trim($row['user_agent']);
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

                yield $uid => new TestProperties(
                    headers: array_change_key_case($headers, CASE_LOWER),
                    device: [
                        'architecture' => null,
                        'brand' => !empty($row['device']['brand'])
                            ? AbstractDeviceParser::getFullName($row['device']['brand'])
                            : null,
                        'deviceName' => $row['device']['model'] ?? null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => $row['device']['type'] ?? null,
                        'bits' => null,
                    ],
                    os: [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => $row['os']['name'] ?? null,
                        'version' => $row['os']['version'] ?? null,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => !empty($row['bot']) ? ($row['bot']['name'] ?? null) : ($row['client']['name'] ?? null),
                        'type' => !empty($row['bot']) ? ($row['bot']['category'] ?? null) : ($row['client']['type'] ?? null),
                        'version' => !empty($row['bot']) ? null : ($row['client']['version'] ?? null),
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => (!empty($row['client']['engine']) ? $row['client']['engine'] : null),
                        'version' => (!empty($row['client']['engine_version']) ? $row['client']['engine_version'] : null),
                    ],
                    file: $filepath,
                    dateFirst: null,
                    dateLast: null,
                    raw: $row,
                    sources: [
                        $this->getName() => [
                            'date-first' => null,
                            'date-last' => null,
                        ],
                    ],
                );
            }
        }
    }
}
