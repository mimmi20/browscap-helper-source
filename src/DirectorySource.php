<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2019, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use BrowscapHelper\Source\Helper\FilePath;
use BrowscapHelper\Source\Ua\UserAgent;
use LogicException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

use function fclose;
use function feof;
use function fgets;
use function file_exists;
use function fopen;
use function mb_strlen;
use function sprintf;
use function str_pad;
use function trim;

use const STR_PAD_RIGHT;

final class DirectorySource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use OutputAwareTrait;

    private const NAME = 'directory-source';

    private string $dir;

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
     * @return iterable<array<non-empty-string, non-empty-string>>
     *
     * @throws RuntimeException
     */
    public function getHeaders(string $message, int &$messageLength = 0): iterable
    {
        foreach ($this->loadFromPath($message, $messageLength) as $line) {
            $ua    = UserAgent::fromUseragent($line);
            $agent = (string) $ua;

            if (empty($agent)) {
                continue;
            }

            yield $ua->getHeaders();
        }
    }

    /**
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
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
     * @return iterable<array<non-empty-string, non-empty-string>>
     *
     * @throws RuntimeException
     */
    private function loadFromPath(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir));

        $fileHelper = new FilePath();

        foreach ($files as $file) {
            $filepath = $file->getPathname();

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

            $handle = @fopen($filepath, 'r');

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

                yield $line;
            }

            fclose($handle);
        }
    }
}
