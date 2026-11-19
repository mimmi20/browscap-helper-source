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
use JsonException;
use Override;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function array_key_exists;
use function assert;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function mb_str_pad;
use function mb_strlen;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_RIGHT;

final class JsonFileSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const string NAME = 'json-files';

    /** @throws void */
    public function __construct(private string $dir)
    {
        // do nothing
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists($this->dir)) {
            return true;
        }

        $this->writeln(
            "\r" . '<error>' . $parentMessage . sprintf('- path %s not found', $this->dir) . '</error>',
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
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            options: OutputInterface::VERBOSITY_VERBOSE,
        );

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir));
        } catch (UnexpectedValueException $e) {
            throw new Exception\SourceException($e->getMessage(), 0, $e);
        }

        $files = new FilterIterator($iterator, 'json');

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

            $content = file_get_contents($filepath);

            if (in_array($content, [false, '', PHP_EOL], strict: true)) {
                continue;
            }

            try {
                $data = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Exception\SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $testCase) {
                if (!is_array($testCase)) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                $headers = array_key_exists('headers', $testCase) ? $testCase['headers'] : $testCase;

                yield $uid => new TestProperties(
                    headers: $headers,
                    device: [
                        'architecture' => null,
                        'brand' => null,
                        'deviceName' => null,
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
                        'type' => null,
                        'bits' => null,
                    ],
                    os: [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => null,
                        'type' => null,
                        'version' => null,
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    file: $filepath,
                    dateFirst: $testCase['date-first'] ?? null,
                    dateLast: $testCase['date-last'] ?? null,
                    raw: $testCase,
                    sources: [
                        $this->getName() => [
                            'date-first' => $testCase['date-first'] ?? null,
                            'date-last' => $testCase['date-last'] ?? null,
                        ],
                    ],
                );
            }
        }
    }
}
