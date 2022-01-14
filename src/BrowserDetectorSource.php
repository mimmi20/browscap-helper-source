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
use Iterator;
use JsonException;
use LogicException;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UaDeviceType\TypeLoader;

use function assert;
use function file_exists;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function str_replace;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;
use const STR_PAD_RIGHT;

final class BrowserDetectorSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'mimmi20/browser-detector';
    private const PATH = 'vendor/mimmi20/browser-detector/tests/data';

    /**
     * @throws void
     */
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
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', self::PATH);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PATH));
        $files    = new class ($iterator, 'json') extends FilterIterator {
            private string $extension;

            /**
             * @param Iterator<SplFileInfo> $iterator
             */
            public function __construct(Iterator $iterator, string $extension)
            {
                parent::__construct($iterator);
                $this->extension = $extension;
            }

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

            $content = file_get_contents($filepath);

            if (false === $content || '' === $content || PHP_EOL === $content) {
                continue;
            }

            try {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln('    <error>parsing file content [' . $filepath . '] failed</error>', OutputInterface::VERBOSITY_NORMAL);

                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $test) {
                if (!is_array($test['headers']) || !isset($test['headers']['user-agent'])) {
                    continue;
                }

                if ('this is a fake ua to trigger the fallback' === $test['headers']['user-agent']) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'headers' => $test['headers'],
                    'device' => [
                        'deviceName' => $test['device']['deviceName'],
                        'marketingName' => $test['device']['marketingName'],
                        'manufacturer' => $test['device']['manufacturer'],
                        'brand' => $test['device']['brand'],
                        'display' => [
                            'width' => $test['device']['display']['width'],
                            'height' => $test['device']['display']['height'],
                            'touch' => $test['device']['display']['touch'],
                            'type' => $test['device']['display']['type'] ?? null,
                            'size' => $test['device']['display']['size'],
                        ],
                        'dualOrientation' => $test['device']['dualOrientation'] ?? null,
                        'type' => $test['device']['type'],
                        'simCount' => $test['device']['simCount'] ?? null,
                        'ismobile' => (new TypeLoader())->load($test['device']['type'])->isMobile(),
                    ],
                    'client' => [
                        'name' => $test['browser']['name'],
                        'modus' => $test['browser']['modus'],
                        'version' => ('0.0.0' === $test['browser']['version'] ? null : $test['browser']['version']),
                        'manufacturer' => $test['browser']['manufacturer'],
                        'bits' => $test['browser']['bits'],
                        'type' => $test['browser']['type'],
                        'isbot' => (new \UaBrowserType\TypeLoader())->load($test['browser']['type'])->isBot(),
                    ],
                    'platform' => [
                        'name' => $test['os']['name'],
                        'marketingName' => $test['os']['marketingName'],
                        'version' => ('0.0.0' === $test['os']['version'] ? null : $test['os']['version']),
                        'manufacturer' => $test['os']['manufacturer'],
                        'bits' => $test['os']['bits'],
                    ],
                    'engine' => [
                        'name' => $test['engine']['name'],
                        'version' => $test['engine']['version'],
                        'manufacturer' => $test['engine']['manufacturer'],
                    ],
                    'raw' => $test,
                    'file' => $filepath,
                ];
            }
        }
    }
}
