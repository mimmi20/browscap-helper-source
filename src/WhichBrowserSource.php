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
use Override;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use UnexpectedValueException;

use function array_change_key_case;
use function array_key_exists;
use function assert;
use function file_exists;
use function in_array;
use function is_array;
use function is_string;
use function mb_str_pad;
use function mb_strlen;
use function mb_strpos;
use function mb_strtolower;
use function mb_trim;
use function sprintf;
use function str_replace;

use const CASE_LOWER;
use const STR_PAD_RIGHT;

final readonly class WhichBrowserSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'whichbrowser/parser';

    private const string PATH = 'vendor/whichbrowser/parser/tests/data';

    /** @throws void */
    public function __construct(private LoggerInterface $consoleLogger)
    {
        // do nothing
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists(self::PATH)) {
            return true;
        }

        $this->consoleLogger->error(
            $parentMessage . sprintf('- path %s not found', self::PATH),
        );

        return false;
    }

    /**
     * @return iterable<non-empty-string, TestProperties>
     *
     * @throws Exception\SourceException
     */
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->consoleLogger->info(
            mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        } catch (UnexpectedValueException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        $files = new FilterIterator($iterator, 'yaml');

        foreach ($files as $file) {
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->consoleLogger->info(
                mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
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
                /** @var array{result: array{browser: array{name?: string, version?: array{value?: string}|string, type?: string}, engine: array{name?: string, version?: array{value?: string}|string}, os: array{name?: string, version?: array{value?: string}|string}, device: array{type?: string, manufacturer?: string, model?: string}}} $row */
                if (!is_array($row)) {
                    continue;
                }

                $lowerHeaders = array_change_key_case($this->getHeadersFromRow($row), CASE_LOWER);

                if ($lowerHeaders === []) {
                    continue;
                }

                $browserName    = null;
                $browserVersion = null;

                assert(is_array($row['result']));
                assert(is_array($row['result']['browser']));

                if (isset($row['result']['browser']['name'])) {
                    $browserName = $row['result']['browser']['name'];
                }

                if (isset($row['result']['browser']['version'])) {
                    $browserVersion = is_array($row['result']['browser']['version'])
                        ? $row['result']['browser']['version']['value'] ?? null
                        : $row['result']['browser']['version'];
                }

                $engineName    = null;
                $engineVersion = null;

                if (isset($row['result']['engine']['name'])) {
                    $engineName = $row['result']['engine']['name'];
                }

                if (isset($row['result']['engine']['version'])) {
                    $engineVersion = is_array($row['result']['engine']['version'])
                        ? $row['result']['engine']['version']['value'] ?? null
                        : $row['result']['engine']['version'];
                }

                $osName    = null;
                $osVersion = null;

                if (isset($row['result']['os']['name'])) {
                    $osName = $row['result']['os']['name'];
                }

                if (isset($row['result']['os']['version'])) {
                    $osVersion = is_array($row['result']['os']['version'])
                        ? $row['result']['os']['version']['value'] ?? null
                        : $row['result']['os']['version'];
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => new TestProperties(
                    headers: $lowerHeaders,
                    device: [
                        'architecture' => null,
                        'brand' => $row['result']['device']['manufacturer'] ?? null,
                        'deviceName' => $row['result']['device']['model'] ?? null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'ismobile' => $this->isMobile($row['result']),
                        'istv' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => $row['result']['device']['type'] ?? null,
                        'bits' => null,
                    ],
                    os: [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => $osName,
                        'version' => $osVersion,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => $browserName,
                        'type' => $row['result']['browser']['type'] ?? null,
                        'version' => $browserVersion,
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => $engineName,
                        'version' => $engineVersion,
                    ],
                    file: $filepath,
                    dateFirst: null,
                    dateLast: null,
                    raw: $row,
                );
            }
        }
    }

    /**
     * @param array{useragent?: mixed, headers?: mixed} $row
     *
     * @return array<non-empty-string, non-empty-string>
     *
     * @throws void
     */
    private function getHeadersFromRow(array $row): array
    {
        if (array_key_exists('useragent', $row) && is_string($row['useragent'])) {
            $useragent = mb_trim($row['useragent']);

            if ($useragent === '') {
                return [];
            }

            return ['user-agent' => $useragent];
        }

        if (array_key_exists('headers', $row)) {
            if (is_array($row['headers'])) {
                $headers = [];

                foreach ($row['headers'] as $name => $value) {
                    if (!is_string($name) || $name === '') {
                        continue;
                    }

                    if (!is_string($value) || $value === '') {
                        continue;
                    }

                    $headers[mb_strtolower($name)] = $value;
                }

                return $headers;
            }

            if (is_string($row['headers']) && mb_strpos($row['headers'], 'User-Agent: ') === 0) {
                $useragent = mb_trim(str_replace('User-Agent: ', '', $row['headers']));

                if ($useragent === '') {
                    return [];
                }

                return ['user-agent' => $useragent];
            }
        }

        return [];
    }

    /**
     * @param array<string, array<string, array<string, string>|string>> $data
     *
     * @throws void
     */
    private function isMobile(array $data): bool | null
    {
        if (!isset($data['device']['type'])) {
            return null;
        }

        $mobileTypes = ['mobile', 'tablet', 'ereader', 'media', 'watch', 'camera'];

        if (in_array($data['device']['type'], $mobileTypes, strict: true)) {
            return true;
        }

        return $data['device']['type'] === 'gaming' && (isset($data['device']['subtype']) && $data['device']['subtype'] === 'portable');
    }
}
