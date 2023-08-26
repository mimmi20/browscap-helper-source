<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2023, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;

use function assert;
use function file;
use function file_exists;
use function is_string;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function str_replace;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;
use const STR_PAD_RIGHT;

final class CrawlerDetectSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'jaybizzle/crawler-detect';

    private const PATH = 'vendor/jaybizzle/crawler-detect/tests';

    /** @throws void */
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
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $filepath = self::PATH . '/crawlers.txt';

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $message = $parentMessage . sprintf('- reading file %s', $filepath);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE,
        );

        if ($lines !== false) {
            foreach ($lines as $ua) {
                if (empty($ua)) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'client' => [
                        'bits' => null,
                        'isbot' => true,
                        'manufacturer' => null,
                        'modus' => null,
                        'name' => null,
                        'type' => null,
                        'version' => null,
                    ],
                    'device' => [
                        'brand' => null,
                        'deviceName' => null,
                        'display' => [
                            'height' => null,
                            'size' => null,
                            'touch' => null,
                            'type' => null,
                            'width' => null,
                        ],
                        'dualOrientation' => null,
                        'ismobile' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'simCount' => null,
                        'type' => null,
                    ],
                    'engine' => [
                        'manufacturer' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    'file' => $filepath,
                    'headers' => ['user-agent' => $ua],
                    'platform' => [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    'raw' => $ua,
                ];
            }
        }

        $filepath = self::PATH . '/devices.txt';
        $filepath = str_replace('\\', '/', $filepath);
        assert(is_string($filepath));

        $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        $message = $parentMessage . sprintf('- reading file %s', $filepath);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERY_VERBOSE,
        );

        if ($lines === false) {
            return;
        }

        foreach ($lines as $ua) {
            if (empty($ua)) {
                continue;
            }

            $uid = Uuid::uuid4()->toString();

            yield $uid => [
                'client' => [
                    'bits' => null,
                    'isbot' => false,
                    'manufacturer' => null,
                    'modus' => null,
                    'name' => null,
                    'type' => null,
                    'version' => null,
                ],
                'device' => [
                    'brand' => null,
                    'deviceName' => null,
                    'display' => [
                        'height' => null,
                        'size' => null,
                        'touch' => null,
                        'type' => null,
                        'width' => null,
                    ],
                    'dualOrientation' => null,
                    'ismobile' => null,
                    'manufacturer' => null,
                    'marketingName' => null,
                    'simCount' => null,
                    'type' => null,
                ],
                'engine' => [
                    'manufacturer' => null,
                    'name' => null,
                    'version' => null,
                ],
                'file' => $filepath,
                'headers' => ['user-agent' => $ua],
                'platform' => [
                    'bits' => null,
                    'manufacturer' => null,
                    'marketingName' => null,
                    'name' => null,
                    'version' => null,
                ],
                'raw' => $ua,
            ];
        }
    }
}
