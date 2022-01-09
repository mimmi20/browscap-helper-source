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
use BrowscapHelper\Source\Reader\LogFileReader;
use BrowscapHelper\Source\Ua\UserAgent;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

use function assert;
use function file_exists;
use function mb_strlen;
use function sprintf;
use function str_pad;

use const STR_PAD_RIGHT;

final class LogFileSource implements OutputAwareInterface, SourceInterface
{
    use GetNameTrait;
    use GetUserAgentsTrait;
    use OutputAwareTrait;

    private const NAME = 'log-files';

    private string $dir;

    /**
     * @throws void
     */
    public function __construct(string $sourcesDirectory)
    {
        $this->dir = $sourcesDirectory;
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
     * @return iterable<array<mixed>>
     * @phpstan-return iterable<non-empty-string, array{headers: array<non-empty-string, non-empty-string>, device: array{deviceName: string|null, marketingName: string|null, manufacturer: string|null, brand: string|null, display: array{width: int|null, height: int|null, touch: bool|null, type: string|null, size: float|int|null}, type: string|null, ismobile: bool|null}, client: array{name: string|null, modus: string|null, version: string|null, manufacturer: string|null, bits: int|null, type: string|null, isbot: bool|null}, platform: array{name: string|null, marketingName: string|null, version: string|null, manufacturer: string|null, bits: int|null}, engine: array{name: string|null, version: string|null, manufacturer: string|null}}>
     *
     * @throws DirectoryNotFoundException
     */
    public function getProperties(string $parentMessage, int &$messageLength = 0): iterable
    {
        $message = $parentMessage . sprintf('- reading path %s', $this->dir);

        if (mb_strlen($message) > $messageLength) {
            $messageLength = mb_strlen($message);
        }

        $this->write("\r" . '<info>' . str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>', false, OutputInterface::VERBOSITY_VERBOSE);

        $finder = new Finder();
        $finder->files();
        $finder->notName('*.filepart');
        $finder->notName('*.sql');
        $finder->notName('*.rename');
        $finder->notName('*.txt');
        $finder->notName('*.ctxt');
        $finder->notName('*.zip');
        $finder->notName('*.rar');
        $finder->notName('*.php');
        $finder->notName('*.gitkeep');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($this->dir);

        $filepathHelper = new FilePath();
        $reader         = new LogFileReader();

        if (null !== $this->output) {
            $reader->setOutput($this->output);
        }

        foreach ($finder as $file) {
            assert($file instanceof SplFileInfo);
            $filepath = $filepathHelper->getPath($file);

            if (null === $filepath) {
                continue;
            }

            $reader->addLocalFile($filepath);
        }

        foreach ($reader->getAgents($parentMessage, $messageLength) as $file => $line) {
            $ua    = UserAgent::fromUseragent($line);
            $agent = (string) $ua;

            if ('' === $agent) {
                continue;
            }

            $uid = Uuid::uuid4()->toString();

            yield $uid => [
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
                'file' => $file,
            ];
        }
    }
}
