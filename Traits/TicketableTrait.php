<?php

namespace Amplify\System\Ticket\Traits;

use Amplify\System\Backend\Models\Ticket;
use Amplify\System\Ticket\Models\TicketThread;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait TicketableTrait
{
    /**
     * Get all threads.
     *
     * @return BelongsToMany
     */
    public function ticketThreads()
    {
        return $this->belongsToMany(
            TicketThread::class,
            'ticket_thread_participants',
            'user_id',
            'thread_id'
        )->withPivot('model')
            ->wherePivot('model', get_class($this));
    }

    /**
     * Scope user existing thread.
     *
     * @param  int  $thread_id
     * @return BelongsToMany
     */
    public function scopeFindTicketThread($query, $thread_id)
    {
        return $this->ticketThreads()->where('thread_id', $thread_id);
    }

    /**
     * Get all messages sent.
     *
     * @return HasMany
     */
    public function ticketsSent()
    {
        return $this->hasMany(Ticket::class, 'sender_id');
    }

    /**
     * Get count of all unread messages.
     *
     * @return int
     */
    public function getUnreadTicketsCountAttribute()
    {
        $count = 0;

        $this->ticketThreads()->withCount(['messages as unread_messages_count' => function ($query) {
            $query->where('sender_id', '!=', $this->id)
                ->whereRaw('created_at > message_thread_participants.last_read');
        }])->chunk(200, function ($threads) use (&$count) {
            $count += $threads->sum('unread_messages_count');
        });

        return $count;
    }

    /**
     * Mark user thread as read.
     */
    public function markTicketThreadAsRead($thread_id)
    {
        $this->ticketThreads()->updateExistingPivot($thread_id, [
            'last_read' => $this->freshTimestamp(),
        ]);
    }
}
