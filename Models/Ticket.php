<?php

namespace Amplify\System\Ticket\Models;

use Amplify\System\Ticket\Interfaces\TicketInterface;
use App\Models\Contact;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Ticket extends Model implements Auditable, TicketInterface
{
    use CrudTrait, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    const PRIORITY = [
        'LOW' => 1,
        'MEDIUM' => 2,
        'HIGH' => 3,
    ];

    const PRIORITY_LABEL = [
        '1' => 'LOW',
        '2' => 'MEDIUM',
        '3' => 'HIGH',
    ];

    const S3_TICKET_ATTACHMENT_PATH = 'images/tickets/attachments';

    protected $table = 'tickets';

    // protected $primaryKey = 'id';
    // public $timestamps = false;
    protected $guarded = ['id'];

    protected $fillable = [
        'customer_id',
        'sender_id',
        'departments_name_id',
        'subject',
        'priority',
        'message',
        'attachments',
        'model',
        'attachment_title',
    ];

    // protected $hidden = [];

    protected $casts = [
        'attachments' => 'object',
    ];

    /*
    |--------------------------------------------------------------------------
    | FUNCTIONS
    |--------------------------------------------------------------------------
    */

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });

        static::created(function ($model) {
            $model->thread()->touch();
        });
    }

    public function viewTicket(): string
    {
        return '<a class="btn btn-sm btn-link" href="'.
            backpack_url('ticket', $this->id).'" data-toggle="tooltip" title="Create Classifcation"><i class="la la-eye"></i> View </a>';
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * Get sender.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function sender()
    {
        return $this->belongsTo(Contact::class, 'sender_id');
    }

    /**
     * Get thread.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function thread()
    {
        return $this->belongsTo(TicketThread::class, 'thread_id');
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope by sender.
     *
     * @param  int  $sender  User ID
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFromSender($query, $sender)
    {
        return $query->where('sender_id', $sender);
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MUTATORS
    |--------------------------------------------------------------------------
    */
}
