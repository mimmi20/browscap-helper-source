<?php

/**
 * This file is part of the browscap-helper-source package.
 *
 * Copyright (c) 2016-2025, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace BrowscapHelper\Source\Reader;

use BrowscapHelper\Source\Helper\Regex;
use BrowscapHelper\Source\OutputAwareInterface;
use BrowscapHelper\Source\OutputAwareTrait;
use Override;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function array_pop;
use function explode;
use function fclose;
use function feof;
use function fgets;
use function fopen;
use function mb_str_pad;
use function mb_strlen;
use function mb_trim;
use function preg_match;
use function sprintf;

use const STR_PAD_RIGHT;

final class LogFileReader implements OutputAwareInterface, ReaderInterface
{
    use OutputAwareTrait;

    /** @var array<string> */
    private array $files = [];

    /** @throws void */
    #[Override]
    public function addLocalFile(string $file): void
    {
        $this->files[] = $file;
    }

    /**
     * @return iterable<string>
     *
     * @throws void
     */
    #[Override]
    public function getAgents(string $parentMessage = '', int &$messageLength = 0): iterable
    {
        $regex = (new Regex())->getRegex();

        foreach ($this->files as $file) {
            $message = $parentMessage . sprintf('- reading file %s', $file);

            if (mb_strlen($message) > $messageLength) {
                $messageLength = mb_strlen($message);
            }

            $this->write(
                "\r" . '<info>' . mb_str_pad($message, $messageLength, ' ', STR_PAD_RIGHT) . '</info>',
                false,
                OutputInterface::VERBOSITY_VERBOSE,
            );

            $handle = @fopen($file, 'r');

            if ($handle === false) {
                $this->writeln('', OutputInterface::VERBOSITY_VERBOSE);
                $this->writeln(
                    "\r" . '<error>' . $parentMessage . sprintf(
                        '- reading file %s caused an error</error>',
                        $file,
                    ),
                    OutputInterface::VERBOSITY_NORMAL,
                );

                continue;
            }

            while (!feof($handle)) {
                $line = fgets($handle, 65535);

                if ($line === false) {
                    continue;
                }

                if (empty($line)) {
                    continue;
                }

                $lineMatches = [];

                if (!(bool) preg_match($regex, $line, $lineMatches)) {
                    $this->writeln(
                        "\r" . '<error>' . $parentMessage . sprintf(
                            '- no useragent found in line "%s" used regex: "%s"</error>',
                            $line,
                            $regex,
                        ),
                        OutputInterface::VERBOSITY_NORMAL,
                    );

                    continue;
                }

                $agentOfLine = array_key_exists('userAgentString', $lineMatches)
                    ? mb_trim($lineMatches['userAgentString'])
                    : mb_trim(
                        $this->extractAgent($line),
                    );

                if (empty($agentOfLine)) {
                    continue;
                }

                yield $file => $agentOfLine;
            }

            fclose($handle);
        }
    }

    /** @throws void */
    private function extractAgent(string $text): string
    {
        $parts = explode('"', $text);
        array_pop($parts);

        return (string) array_pop($parts);
    }
}
