<?php

namespace Amplify\System\Ticket\Controllers;

use Amplify\Frontend\Traits\HasDynamicPage;
use Amplify\System\Ticket\Facades\Ticket;
use Amplify\System\Ticket\Models\TicketThread;
use Amplify\System\Ticket\Requests\TicketRequest;
use ErrorException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
    use HasDynamicPage;

    public function newTicket(TicketRequest $request)
    {
        $sender = customer(true);

        $files = $request->file('attachments');
        $attachmentTitels = [];

        if ($files) {
            foreach ($files as $file) {
                $attachmentTitels[] = $file->getClientOriginalName();
            }
        }

        $ticket = Ticket::from($sender)
            ->message($request->message)
            ->otherTicketInfo($request->priority, $request->subject, $request->departments_name_id)
            ->attachments($request->file('attachments'))
            ->attachmentTitle(json_encode($attachmentTitels))
            ->send();

        return redirect(url('tickets/'.$ticket->thread_id));
    }

    public function replyToTicket(TicketRequest $request, $id)
    {
        $thread = TicketThread::findOrFail($id);
        $from = customer(true);
        $attachmentTitels = [];

        if ($request->hasFile('attachments')) {
            $files = $request->file('attachments');
            foreach ($files as $file) {
                $attachmentTitels[] = $file->getClientOriginalName();
            }
        }

        Ticket::from($from)
            ->to($thread)
            ->attachments($request->attachments)
            ->attachmentTitle(json_encode($attachmentTitels))
            ->message($request->message)
            ->send();

        return back();
    }

    /**
     * Return All Message on Customer Panel
     *
     * @return string
     *
     * @throws ErrorException
     */
    public function index()
    {
        if (! customer(true)->can('ticket.tickets')) {
            abort(403);
        }
        $this->loadPageByType('ticket');

        return $this->render();
    }

    public function store(TicketRequest $request): RedirectResponse
    {
        if (! customer(true)->can('ticket.tickets')) {
            abort(403);
        }
        $sender = customer(true);

        $files = $request->file('attachments');
        $attachmentTitels = [];

        if ($files) {
            foreach ($files as $file) {
                $attachmentTitels[] = $file->getClientOriginalName();
            }
        }

        $ticket = Ticket::from($sender)
            ->message($request->message)
            ->otherTicketInfo($request->priority, $request->subject, $request->departments_name_id)
            ->attachments($request->file('attachments'))
            ->attachmentTitle(json_encode($attachmentTitels))
            ->send();

        if ($ticket instanceof \Amplify\System\Ticket\Models\Ticket) {
            session()->flash('success', 'New Ticket created successfully');
        }

        return redirect()->route('frontend.tickets.show', $ticket->thread_id);
    }

    /**
     * @throws ErrorException|BindingResolutionException
     */
    public function show($id): string
    {
        if (! customer(true)->can('ticket.tickets')) {
            abort(403);
        }
        $this->loadPageByType('ticket_detail');

        return $this->render();
    }

    /**
     * @param string $thread
     * @param TicketRequest $request
     * @return RedirectResponse
     */
    public function update(string $thread, TicketRequest $request): RedirectResponse
    {
        $from = customer(true);

        $thread = TicketThread::findOrFail($thread);

        $files = $request->file('attachments');

        $attachmentTitles = [];

        if ($files) {
            foreach ($files as $file) {
                $attachmentTitles[] = $file->getClientOriginalName();
            }
        }

        Ticket::from($from)
            ->to($thread)
            ->attachments($request->attachments)
            ->attachmentTitle(json_encode($attachmentTitles))
            ->message($request->message)
            ->send();

        return redirect()->route('frontend.tickets.show', $thread->id);
    }

    /**
     * @throws ErrorException|BindingResolutionException
     */
    public function create(): string
    {
        if (! customer(true)->can('ticket.tickets')) {
            abort(403);
        }
        $this->loadPageByType('ticket_open');

        return $this->render();
    }
}
