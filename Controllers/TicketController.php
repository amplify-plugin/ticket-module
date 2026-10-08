<?php

namespace Amplify\System\Ticket\Controllers;

use Amplify\Frontend\Traits\HasDynamicPage;
use Amplify\System\Backend\Models\Event;
use Amplify\System\Factories\NotificationFactory;
use Amplify\System\Ticket\Facades\Ticket;
use Amplify\System\Ticket\Interfaces\TicketableInterface;
use Amplify\System\Ticket\Models\Ticket as TicketAlias;
use Amplify\System\Ticket\Models\TicketThread;
use Amplify\System\Ticket\Requests\TicketRequest;
use Amplify\System\Ticket\TicketService;
use ErrorException;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TicketController extends Controller
{
    use HasDynamicPage;

    public function __construct(
        protected TicketService $chat,
    ) {}

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

        if ($ticket instanceof TicketAlias) {
            session()->flash('success', 'New Ticket created successfully');
            NotificationFactory::callIf(
                true,
                Event::TICKET_CREATED,
                ['ticket' => $ticket]
            );
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

    public function messages(Request $request, int $thread): JsonResponse
    {
        $thread = $this->chat->findThread($thread);
        $this->chat->authorizeCustomer($thread);

        return $this->messageResponse($request, $thread, 'customer');
    }

    public function reply(Request $request, int $thread): JsonResponse|RedirectResponse
    {
        $thread = $this->chat->findThread($thread);
        $this->chat->authorizeCustomer($thread);

        $message = $this->storeReply($request, $thread, customer(true));

        if (! $request->expectsJson()) {
            return redirect()->route('frontend.tickets.show', $thread->id);
        }

        return response()->json([
            'message' => $this->chat->present($message, 'customer'),
        ]);
    }

    private function messageResponse(Request $request, TicketThread $thread, string $audience): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after', 0));

        $messages = $this->chat->messagesAfter($thread, $afterId)
            ->map(fn ($message) => $this->chat->present($message, $audience))
            ->values();

        return response()->json([
            'messages' => $messages,
        ])->header('Cache-Control', 'no-store');
    }

    private function storeReply(Request $request, TicketThread $thread, mixed $sender): TicketAlias
    {
        if (! $sender instanceof TicketableInterface) {
            abort(403);
        }

        $this->chat->prepareReply($request);

        $request->validate([
            'message' => 'required_without:attachments|nullable|string|min:1|max:10000',
            'attachments' => 'required_without:message|array|max:10',
            'attachments.*' => TicketRequest::attachmentItemRules(),
        ], [
            'attachments.*.mimes' => 'Attach an image, PDF, Word, Excel, PowerPoint, CSV, or text file.',
        ]);

        return $this->chat->replyTo($sender, $thread, $request);
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
