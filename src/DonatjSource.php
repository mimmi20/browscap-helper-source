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
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

use function assert;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function mb_str_pad;
use function mb_strlen;
use function mb_trim;
use function sprintf;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_RIGHT;

final readonly class DonatjSource implements SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;

    private const string NAME = 'donatj/phpuseragentparser';

    private const string PATH = 'vendor/donatj/phpuseragentparser/tests';

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

        $files = new FilterIterator($iterator, 'json');

        foreach ($files as $file) {
            $filepath = $file->getPathname();
            $filepath = str_replace('\\', '/', $filepath);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->consoleLogger->info(
                mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT),
            );

            $content = file_get_contents($filepath);

            if (in_array($content, [false, '', PHP_EOL], strict: true)) {
                continue;
            }

            try {
                $provider = json_decode($content, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Exception\SourceException($e->getMessage(), 0, $e);
            }

            if (!is_array($provider)) {
                continue;
            }

            foreach ($provider as $test => $testData) {
                if (is_int($test)) {
                    continue;
                }

                if (!is_array($testData)) {
                    continue;
                }

                $agent = mb_trim($test);

                if ($agent === '') {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => new TestProperties(
                    headers: ['user-agent' => $agent],
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
                        'ismobile' => null,
                        'istv' => null,
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
                        'name' => is_string($testData['platform'])
                            ? $testData['platform']
                            : null,
                        'version' => null,
                    ],
                    client: [
                        'bits' => null,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => is_string($testData['browser']) ? $testData['browser'] : null,
                        'type' => null,
                        'version' => is_string($testData['version'])
                            ? $testData['version']
                            : null,
                    ],
                    engine: [
                        'manufacturer' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    file: $filepath,
                    dateFirst: null,
                    dateLast: null,
                    raw: $test,
                );
            }
        }
    }
}
