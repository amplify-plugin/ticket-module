<?php

namespace Amplify\System\Ticket\Interfaces;

interface TicketInterface
{
    public function sender();

    public function thread();

    public function scopeFromSender($query, $sender);
}
