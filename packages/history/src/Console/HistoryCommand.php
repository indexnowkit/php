<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `indexnow:history [--host=] [--status=] [--url=] [--since=] [--limit=] [--json] [--purge[=days]]`: the recorded
 * submissions of the graph's submission store (the store of `history.store`, or the application's own), newest
 * first; `--purge` runs the retention. The command every adapter on symfony/console registers (wave L, spec 18): the
 * adapter builds the {@see HistoryRunner} (`History\Adapter\HistoryServices::historyRunner()` / `historyRunnerFor()`)
 * and hands it over; without the package it registers `Console\Command\HistoryNotInstalledCommand` of
 * `indexnowkit/console` under the same name.
 */
#[AsCommand(name: 'indexnow:history', description: 'List the recorded IndexNow submissions (what was sent, when, with what answer), newest first')]
final class HistoryCommand extends Command
{
    /** The number of records when `--limit` is not a number. */
    public const DEFAULT_LIMIT = 50;

    public function __construct(private readonly HistoryRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        Definitions::history()->applyTo($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = $input->getOption('limit');
        $purge = $input->getOption('purge'); // false = absent, null = --purge without a value, a string = --purge=<days>

        return $this->runner->run(new SymfonyStyle($input, $output), new HistoryOptions(
            host: self::str($input->getOption('host')),
            status: self::str($input->getOption('status')),
            url: self::str($input->getOption('url')),
            since: self::str($input->getOption('since')),
            limit: \is_string($limit) || \is_int($limit) ? $limit : self::DEFAULT_LIMIT,
            json: (bool) $input->getOption('json'),
            purge: $purge === false ? null : ($purge === null ? true : (\is_string($purge) ? $purge : true)),
        ));
    }

    private static function str(mixed $value): ?string
    {
        return \is_string($value) ? $value : null;
    }
}
