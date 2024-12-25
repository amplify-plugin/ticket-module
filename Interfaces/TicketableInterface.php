<?php

namespace Amplify\System\Ticket\Interfaces;

interface TicketableInterface
{
    public function ticketThreads();

    public function scopeFindTicketThread($query, $thread_id);

    public function ticketsSent();

    public function getUnreadTicketsCountAttribute();

    public function markTicketThreadAsRead($thread_id);
}
