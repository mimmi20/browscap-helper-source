<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2022, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use FilterIterator;
use Header;
use Iterator;
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
use function assert;
use function class_exists;
use function file_exists;
use function function_exists;
use function http_parse_headers;
use function in_array;
use function is_array;
use function is_string;
use function mb_strlen;
use function mb_strpos;
use function sprintf;
use function str_pad;
use function str_replace;

use const CASE_LOWER;
use const STR_PAD_RIGHT;

final class WhichBrowserSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'whichbrowser/parser';

    private const PATH = 'vendor/whichbrowser/parser/tests/data';

    /** @throws void */
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->writeln("\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', self::PATH), OutputInterface::VERBOSITY_NORMAL);

        return false;
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws SourceException
     */
    public function getProperties(
        string $parentMessage,
        int &$messageLength = 0,
    ): iterable {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $files = new class ($iterator, 'yaml') extends FilterIterator {
            /**
             * @param Iterator<SplFileInfo> $iterator
             *
             * @throws void
             */
            public function __construct(
                Iterator $iterator,
                private readonly string $extension,
            ) {
                parent::__construct($iterator);
            }

            /** @throws void */
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

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            try {
                $data = Yaml::parseFile($filepath);
            } catch (ParseException $e) {
                throw new SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $row) {
                $lowerHeaders = array_change_key_case($this->getHeadersFromRow($row), CASE_LOWER);

                if ([] === $lowerHeaders) {
                    continue;
                }

                $browserName    = null;
                $browserVersion = null;

                if (isset($row['result']['browser']['name'])) {
                    $browserName = $row['result']['browser']['name'];
                }

                if (isset($row['result']['browser']['version'])) {
                    if (is_array($row['result']['browser']['version'])) {
                        $browserVersion = $row['result']['browser']['version']['value'] ?? null;
                    } else {
                        $browserVersion = $row['result']['browser']['version'];
                    }
                }

                $engineName    = null;
                $engineVersion = null;

                if (isset($row['result']['engine']['name'])) {
                    $engineName = $row['result']['engine']['name'];
                }

                if (isset($row['result']['engine']['version'])) {
                    if (is_array($row['result']['engine']['version'])) {
                        $engineVersion = $row['result']['engine']['version']['value'] ?? null;
                    } else {
                        $engineVersion = $row['result']['engine']['version'];
                    }
                }

                $osName    = null;
                $osVersion = null;

                if (isset($row['result']['os']['name'])) {
                    $osName = $row['result']['os']['name'];
                }

                if (isset($row['result']['os']['version'])) {
                    if (is_array($row['result']['os']['version'])) {
                        $osVersion = $row['result']['os']['version']['value'] ?? null;
                    } else {
                        $osVersion = $row['result']['os']['version'];
                    }
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'headers' => $lowerHeaders,
                    'device' => [
                        'deviceName' => $row['result']['device']['model'] ?? null,
                        'marketingName' => null,
                        'manufacturer' => null,
                        'brand' => $row['result']['device']['manufacturer'] ?? null,
                        'display' => [
                            'width' => null,
                            'height' => null,
                            'touch' => null,
                            'type' => null,
                            'size' => null,
                        ],
                        'dualOrientation' => null,
                        'type' => $row['result']['device']['type'] ?? null,
                        'simCount' => null,
                        'ismobile' => $this->isMobile($row['result']),
                    ],
                    'client' => [
                        'name' => $browserName,
                        'modus' => null,
                        'version' => $browserVersion,
                        'manufacturer' => null,
                        'bits' => null,
                        'type' => $row['result']['browser']['type'] ?? null,
                        'isbot' => isset($row['result']['device']['type']) && 'bot' === $row['result']['device']['type'],
                    ],
                    'platform' => [
                        'name' => $osName,
                        'marketingName' => null,
                        'version' => $osVersion,
                        'manufacturer' => null,
                        'bits' => null,
                    ],
                    'engine' => [
                        'name' => $engineName,
                        'version' => $engineVersion,
                        'manufacturer' => null,
                    ],
                    'raw' => $row,
                    'file' => $filepath,
                ];
            }
        }
    }

    /**
     * @param array<string, array<string, string>|string> $row
     *
     * @return array<string, string>
     *
     * @throws void
     */
    private function getHeadersFromRow(array $row): array
    {
        if (array_key_exists('useragent', $row) && is_string($row['useragent'])) {
            return ['user-agent' => $row['useragent']];
        }

        if (array_key_exists('headers', $row)) {
            if (is_array($row['headers'])) {
                return $row['headers'];
            }

            if (!is_string($row['headers'])) {
                return [];
            }

            $headers = false;

            if (class_exists(Header::class)) {
                // pecl_http versions 2.x/3.x
                $headers = Header::parse($row['headers']);
            } elseif (function_exists('\http_parse_headers')) {
                // pecl_http version 1.x
                $headers = http_parse_headers($row['headers']);
            } elseif (0 === mb_strpos($row['headers'], 'User-Agent: ')) {
                return ['user-agent' => str_replace('User-Agent: ', '', $row['headers'])];
            }

            if (!is_array($headers)) {
                return [];
            }

            return $headers;
        }

        return [];
    }

    /**
     * @param array<mixed> $data
     * @phpstan-param array<string, array<string, string>> $data
     *
     * @throws void
     */
    private function isMobile(array $data): bool | null
    {
        if (!isset($data['device']['type'])) {
            return null;
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
