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

use BrowscapHelper\Source\Helper\FilePath;
use Ramsey\Uuid\Uuid;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

use function assert;
use function fclose;
use function feof;
use function fgets;
use function file_exists;
use function fopen;
use function is_string;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function str_replace;
use function trim;

use const STR_PAD_RIGHT;

final class DirectorySource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'directory-source';

    /** @throws void */
    public function __construct(private readonly string $dir)
    {
        // nothing to do
    }

    /** @throws void */
    public function isReady(string $parentMessage): bool
    {
        if (file_exists($this->dir)) {
            return true;
        }

        $this->writeln("\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', $this->dir), OutputInterface::VERBOSITY_NORMAL);

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
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        try {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir));
        } catch (UnexpectedValueException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        $fileHelper = new FilePath();

        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            $filepath = $file->getPathname();
            $filepath = str_replace('\\', '/', $filepath);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERY_VERBOSE);

            $fullPath = $fileHelper->getPath($file);

            if (null === $fullPath) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln('<error>could not detect path for file "' . $filepath . '"</error>', OutputInterface::VERBOSITY_NORMAL);

                continue;
            }

            $handle = @fopen($fullPath, 'r');

            if (false === $handle) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln('<error>reading file ' . $filepath . ' caused an error</error>', OutputInterface::VERBOSITY_NORMAL);

                continue;
            }

            while (!feof($handle)) {
                $line = fgets($handle, 65535);

                if (false === $line) {
                    continue;
                }

                $line = trim($line);

                if ('' === $line) {
                    continue;
                }

                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'headers' => ['user-agent' => $line],
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
                        'dualOrientation' => null,
                        'type' => null,
                        'simCount' => null,
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
                    'raw' => $line,
                    'file' => $filepath,
                ];
            }

            fclose($handle);
        }
    }
}
