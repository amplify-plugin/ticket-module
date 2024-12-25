<?php

namespace Amplify\System\Ticket\Interfaces;

interface TicketThreadInterface
{
    public function tickets();

    public function participants();

    public function getTitleAttribute();

    public function getLastTicketAttribute();

    public function getUnreadTicketsCountAttribute();

    public function getCreatorAttribute();

    public function scopeBetween($query, $participants);
}
