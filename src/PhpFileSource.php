<?php
/**
 * This file is part of the browscap-helper package.
 *
 * Copyright (c) 2015-2021, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Ua\UserAgent;
use FilterIterator;
use Iterator;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function array_keys;
use function assert;
use function file_exists;
use function is_string;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function str_replace;
use function trim;

use const STR_PAD_RIGHT;

final class PhpFileSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use OutputAwareTrait;

    private const NAME = 'php-files';

    private string $dir;

    /**
     * @throws void
     */
    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    /**
     * @throws void
     */
    public function isReady(string $parentMessage): bool
    {
        if (file_exists($this->dir)) {
            return true;
        }

        $this->writeln("\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', $this->dir), OutputInterface::VERBOSITY_NORMAL);

        return false;
    }

    /**
     * @return iterable<array<string, string>>
     *
     * @throws UnexpectedValueException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->loadFromPath($message, $messageLength) as $agent) {
            $ua    = UserAgent::fromUseragent($agent);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<string, string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function getProperties(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->loadFromPath($message, $messageLength) as $line) {
            $ua    = UserAgent::fromUseragent($line);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield [
                'headers' => ['user-agent' => $agent],
                'device' => [
                    'deviceName' => null,
                    'marketingName' => null,
                    'manufacturer' => null,
                    'brand' => null,
                    'display' => [
                        'width' => null,
                        'height' => null,
                        'touch' => null,
                        'type' => null,
                        'size' => null,
                    ],
                    'type' => null,
                    'ismobile' => null,
                ],
                'client' => [
                    'name' => null,
                    'modus' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                    'type' => null,
                    'isbot' => null,
                ],
                'platform' => [
                    'name' => null,
                    'marketingName' => null,
                    'version' => null,
                    'manufacturer' => null,
                    'bits' => null,
                ],
                'engine' => [
                    'name' => null,
                    'version' => null,
                    'manufacturer' => null,
                ],
            ];
        }
    }

    /**
     * @return array<string>|iterable
     *
     * @throws UnexpectedValueException
     */
    private function loadFromPath(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir));
        $files    = new class ($iterator, 'php') extends FilterIterator {
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
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $provider = require $filepath;

            foreach (array_keys($provider) as $ua) {
                $agent = trim((string) $ua);

                if (empty($agent)) {
                    continue;
                }

                yield $agent;
            }
        }
    }
}
