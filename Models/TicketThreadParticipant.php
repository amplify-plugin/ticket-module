<?php

namespace Amplify\System\Ticket\Models;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class TicketThreadParticipant extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'thread_id',
        'user_id',
        'model',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    public $casts = ['deleted_at' => 'datetime'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var array
     */
    public $timestamps = false;

    public function thread()
    {
        return $this->belongsTo(TicketThread::class);
    }

    /**
     * Get user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'user_id');
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class, 'user_id');
    }
}
