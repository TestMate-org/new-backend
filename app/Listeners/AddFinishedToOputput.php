<?php

namespace App\Listeners;

use \Illuminate\Console\Events\CommandFinished;

class AddFinishedToOputput
{
    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(CommandFinished $event): void
    {
        $message = sprintf('Thanks for using TestMate CAT %s version %s', config('testmate.version.name'), config('testmate.version.code'));
        $event->output->writeln('');
        $event->output->writeln($message);
    }
}
