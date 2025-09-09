<?php

namespace Amplify\System\Ticket\Controllers;

use Amplify\System\Abstracts\BackpackCustomCrudController;
use Amplify\System\Backend\Models\Contact;
use Amplify\System\Ticket\Facades\Ticket;
use Amplify\System\Ticket\Models\Ticket as ModelsTicket;
use Amplify\System\Ticket\Models\TicketThread;
use Amplify\System\Ticket\Requests\TicketRequest;
use Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\Request;

/**
 * Class TicketCrudController
 *
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class TicketCrudController extends BackpackCustomCrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use DeleteOperation {
        destroy as traitDelete;
    }

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     *
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(TicketThread::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/ticket');
        CRUD::setEntityNameStrings('ticket', 'tickets');
    }

    /**
     * Define what happens when the List operation is loaded.
     *
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     *
     * @return void
     */
    protected function setupListOperation()
    {
        $this->crud->orderBy('updated_at', 'desc');

        // dd($this->crud->query->get());

        CRUD::column('id')->type('number')->thousands_sep('');

        CRUD::addColumn([
            'name' => 'subject',
            'label' => 'Subject',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return optional(optional($entry->tickets)->first())->subject;
            },
        ]);

        CRUD::addColumn([
            'name' => 'contact_name',
            'label' => 'Contact Name',
            'type' => 'custom_html',
            'value' => function ($entry) {
                $participant = $entry->participants->where('model', Contact::class)->first();

                return optional(optional($participant)->contact)->name;
            },
        ]);

        CRUD::addColumn([
            'name' => 'priority',
            'label' => 'Priority',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return ModelsTicket::PRIORITY_LABEL[optional(optional($entry->tickets)->first())->priority] ?? '-';
            },
        ]);

        CRUD::addColumn([
            'name' => 'updated_at',
            'label' => 'Updated At',
            'type' => 'custom_html',
            'value' => function ($entry) {
                return $entry->updated_at->diffForHumans();
            },
        ]);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']);
         */
        $this->crud->addButtonFromModelFunction('line', 'view-ticket', 'viewTicket', 'beginning');
        $this->crud->removeButton('show');
        $this->crud->removeButton('create');
        // $this->crud->removeButton('update');
    }

    protected function setupUpdateOperation()
    {
        CRUD::addField([
            'name' => 'status',
            'label' => 'Status',
            'type' => 'select_from_array',
            'options' => [
                'open' => 'Open',
                'pending' => 'Pending',
                'solved' => 'Solved',
            ],
            'allows_null' => false,
        ]);

        CRUD::addField([
            'name' => 'assignee',
            'entity' => 'assignee',
            'label' => 'Assignee',
            'type' => 'select2_ticket',
            'model' => "Amplify\System\Backend\Models\User",
            'attribute' => 'name',
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     *
     * @return void
     */
    public function store(TicketRequest $request, $id)
    {
        $thread = TicketThread::findOrFail($id);

        $files = $request->file('attachments');
        $attachmentTitels = [];

        if ($files) {
            foreach ($files as $file) {
                $attachmentTitels[] = $file->getClientOriginalName();
            }
        }

        Ticket::from(backpack_user())
            ->to($thread)
            ->attachments($request->file('attachments'))
            ->attachmentTitle(json_encode($attachmentTitels))
            ->message($request->message)
            ->send();

        return back();
    }

    public function update(Request $request, $id)
    {
        $thread = TicketThread::findOrFail($id);

        $thread->update([
            'status' => $request->status,
        ]);

        $thread->participants()->where('thread_id', $thread->id)->where('model', get_class(backpack_user()))->delete();

        if ($request->input('assignee')) {
            $thread->participants()->updateOrCreate(
                ['thread_id' => $thread->id, 'user_id' => $request->input('assignee'), 'model' => get_class(backpack_user())],
                ['thread_id' => $thread->id, 'user_id' => $request->input('assignee'), 'model' => get_class(backpack_user())]
            );
        }

        \Alert::success(trans('backpack::crud.update_success'))->flash();
        $this->crud->setSaveAction($request->save_action);

        return $this->crud->performSaveAction($thread->id);
    }

    public function ticketIndex($threadMsg = [])
    {
        $threads = backpack_user()->threads;

        return view('backend::pages.tickets.index', [
            'threads' => $threads,
            'threadMsg' => $threadMsg,
            'crud' => $this->crud,
        ]);
    }

    public function show($id)
    {
        // $ticket = Ticket::findOrFail($id);

        // return view('tickets.index', compact('ticket'));

        $thread = TicketThread::findOrFail($id);
        $thread->with('pivot');

        $hasPermission = false;
        if (backpack_user()->hasRole('Super Admin')) {
            $hasPermission = true;
        }

        $participants = $thread->participants;

        foreach ($participants as $participant) {
            if (($participant->model === get_class(backpack_user()) && $participant->user_id == backpack_user()->id)) {
                $hasPermission = true;
            }
            if ($hasPermission) {
                break;
            }
        }

        if (! $hasPermission) {
            abort(404);
        }

        backpack_user()->markThreadAsRead($id);

        return $this->ticketIndex($thread);
    }

    /**
     * Define what happens when the Update operation is loaded.
     *
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     *
     * @return void
     */
    public function destroy($id)
    {
        $ticketThread = TicketThread::findOrFail($id);
        $ticketThread->tickets()->delete();
        $ticketThread->participants()->delete();
        $ticketThread->delete();

        return true;
    }
}
