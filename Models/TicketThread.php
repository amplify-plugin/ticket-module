<?php

namespace Amplify\System\Ticket\Models;

use Amplify\System\Ticket\Interfaces\TicketThreadInterface;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class TicketThread extends Model implements Auditable, TicketThreadInterface
{
    use CrudTrait, HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['status'];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['tickets'];

    /**
     * Get thread tickets.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'thread_id');
    }

    /**
     * Get thread participants.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function participants()
    {
        return $this->hasMany(TicketThreadParticipant::class, 'thread_id');
    }

    public function getTitleAttribute()
    {
        return optional($this->tickets)->first()->subject;
    }

    public function viewTicket(): string
    {
        return '<a class="btn btn-sm btn-link" href="'.backpack_url('ticket', [$this->id, 'show']).'" data-toggle="tooltip"><i class="la la-eye"></i> Details </a>';
    }

    public function getLastTicketAttribute()
    {
        return $this->tikcets->sortBy('created_at')->last();
    }

    public function getUnreadTicketsCountAttribute()
    {
        // We need the pivot relation
        if (! $this->relationLoaded('pivot')) {
            return null;
        }

        $last_read = $this->pivot->last_read;
        $user_id = $this->pivot->user_id;

        // If ticket date is greater than the
        // last_read, the ticket is unread.
        return $this->tickets->filter(function ($msg, $key) use ($last_read, $user_id) {
            // Exclude tickets that were sent
            // by this user.
            if ($user_id == $msg->sender_id) {
                return false;
            }

            // If last_read is null this means
            // all tickets are unread since
            // the user hasn't opened the
            // thread yet.
            if (is_null($last_read)) {
                return true;
            }

            // Return new tickets only
            return $msg->created_at > $last_read;
        })->count();
    }

    public function getCreatorAttribute()
    {
        return $this->tickets->sortBy('created_at')->first()->sender;
    }

    public function assignee()
    {
        return $this->hasMany(TicketThreadParticipant::class, 'thread_id')->where('model', get_class(backpack_user()));
    }

    public function scopeBetween($query, $participants)
    {
        if (! is_array($participants)) {
            $participants = func_get_args();
            array_shift($participants);
        }

        return $query->whereHas('participants', function ($query) use ($participants) {
            $query->select('thread_id')
                ->whereIn('user_id', $participants)
                ->groupBy('thread_id')
                ->havingRaw('COUNT(thread_id) = '.count($participants));
        });
    }
}
