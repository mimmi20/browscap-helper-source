<?php
/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2024, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source;

use Exception;
use JsonException;
use Override;
use Ramsey\Uuid\Uuid;
use SplFileInfo;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

use function assert;
use function file_exists;
use function file_get_contents;
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

    private const NAME = 'json-files';

    /** @throws void */
    public function __construct(private readonly string $dir)
    {
    }

    /** @throws void */
    #[Override]
    public function isReady(string $parentMessage): bool
    {
        if (file_exists($this->dir)) {
            return true;
        }

        $this->writeln(
            "\r" . '<error>' . $parentMessage . sprintf('- path %s not found</error>', $this->dir),
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
    #[Override]
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write(
            "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
            false,
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $finder = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();

        try {
            $finder->in($this->dir);
        } catch (DirectoryNotFoundException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        foreach ($finder as $file) {
            assert($file instanceof SplFileInfo);
            $pathName = $file->getPathname();
            $filepath = str_replace('\\', '/', $pathName);
            assert(is_string($filepath));

            $message = $parentMessage . sprintf('- reading file %s', $filepath);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                false,
                OutputInterface::VERBOSITY_VERY_VERBOSE,
            );

            $content = file_get_contents($filepath);

            if ($content === false || $content === '' || $content === PHP_EOL) {
                continue;
            }

            try {
                $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln(
                    '<error>' . (new Exception(
                        sprintf('file %s contains invalid json.', $filepath),
                        0,
                        $e,
                    )) . '</error>',
                );

                continue;
            }

            if (!is_array($data)) {
                continue;
            }

            foreach ($data as $headers) {
                $uid = Uuid::uuid4()->toString();

                yield $uid => [
                    'client' => [
                        'bits' => null,
                        'isbot' => null,
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
                    'headers' => $headers,
                    'platform' => [
                        'bits' => null,
                        'manufacturer' => null,
                        'marketingName' => null,
                        'name' => null,
                        'version' => null,
                    ],
                    'raw' => $headers,
                ];
            }
        }
    }
}
