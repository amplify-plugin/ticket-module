<?php

namespace Amplify\System\Ticket;

use Amplify\System\Ticket\Interfaces\TicketInterface;
use Amplify\System\Ticket\Interfaces\TicketThreadInterface;
use Amplify\System\Ticket\Interfaces\TicketThreadParticipantInterface;
use Amplify\System\Ticket\Models\Ticket as ModelsTicket;
use Amplify\System\Ticket\Models\TicketThread;
use Amplify\System\Ticket\Models\TicketThreadParticipant;
use Illuminate\Support\ServiceProvider;

class TicketServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('tkt', fn () => new TicketService);

        $this->app->bind(TicketInterface::class, ModelsTicket::class);

        $this->app->bind(TicketThreadInterface::class, TicketThread::class);

        $this->app->bind(TicketThreadParticipantInterface::class, TicketThreadParticipant::class);

        $this->loadRoutesFrom(__DIR__.'/Routes/web.php');
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //AliasLoader::getInstance()->alias('Ticket', EasyAsk::class);
    }
}
