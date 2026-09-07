<?php

declare(strict_types=1);

namespace IndexNowKit\History\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `indexnow:status [--json]`, read-only: switches, dispatch (and what the adapter knows about its queue), the
 * debounce store, the 403 counter of every host, the last successful submission, the history size. Nothing is
 * fetched. The command every adapter on symfony/console registers (wave L, spec 18): the adapter builds the
 * {@see StatusRunner} (`History\Adapter\HistoryServices::statusRunner()` / `statusRunnerFor()`, with the description
 * of its debounce store and its queue facts) and hands it over; without the package it registers
 * `Console\Command\StatusNotInstalledCommand` of `indexnowkit/console` under the same name.
 */
#[AsCommand(name: 'indexnow:status', description: 'Print the IndexNow status: switches, dispatch, debounce store, 403 counters per host, the last successful submission, history size')]
final class StatusCommand extends Command
{
    public function __construct(private readonly StatusRunner $runner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        Definitions::status()->applyTo($this);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->runner->run(new SymfonyStyle($input, $output), (bool) $input->getOption('json'));
    }
}
