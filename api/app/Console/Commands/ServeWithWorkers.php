<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ServeCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This host starts the API with `php artisan serve`, which on its own answers
 * one request at a time: a slow call to Telegram or to Claude stops the bot and
 * the website for everyone until it returns. PHP's server can run several
 * workers, and Laravel starts them when it sees PHP_CLI_SERVER_WORKERS, but only
 * under --no-reload, which would also hand the app the dashboard's variables it
 * has never had (see "Settings and secrets" in CLAUDE.md; a stale TICK_TOKEN there
 * would close the tick). So the flag stands only while Laravel counts the workers,
 * and everything else about the server stays as it was.
 */
#[AsCommand(name: 'serve')]
class ServeWithWorkers extends ServeCommand
{
    /** The memory limit here is 512 MB and a busy worker takes about 50. */
    private const WORKERS = 4;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        // PHP's server cannot fork on Windows; there it stays the plain development server.
        if (PHP_OS_FAMILY === 'Windows') {
            parent::initialize($input, $output);

            return;
        }

        $_SERVER['PHP_CLI_SERVER_WORKERS'] = (string) self::WORKERS;
        $input->setOption('no-reload', true);
        parent::initialize($input, $output);
        $input->setOption('no-reload', false);
    }
}
