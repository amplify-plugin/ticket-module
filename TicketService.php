<?php

namespace Amplify\System\Ticket;

use Amplify\System\Backend\Models\Contact;
use Amplify\System\Ticket\Exceptions\TicketException;
use Amplify\System\Ticket\Interfaces\TicketableInterface;
use Amplify\System\Ticket\Interfaces\TicketThreadInterface;
use Amplify\System\Ticket\Models\Ticket as TicketMessage;
use Amplify\System\Ticket\Models\TicketThread;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

class TicketService
{
    protected $from;

    protected $to;

    protected $message;

    protected $attachments;

    protected $priority;

    protected $subject;

    protected $departments_name_id;

    /**
     * Attachment Title.
     *
     * @var string
     */
    protected $attachment_title;

    public function message($message)
    {
        $this->message = $message;

        return $this;
    }

    public function attachments(?array $attachments = null)
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function attachmentTitle($title)
    {
        $this->attachment_title = $title;

        return $this;

    }

    protected function hasAttachments()
    {
        return ! empty($this->attachments);
    }

    protected function getAttachments()
    {
        if ($this->hasAttachments()) {
            $file_path = [];

            foreach ($this->attachments as $image) {
                if ($image->isValid()) {
                    $file_path[] = fileUploads($image, 'tickets');
                }
            }

            $this->attachments = json_encode($file_path);
        }

        return null;
    }

    /**
     * Message sender.
     *
     * @param \Amplify\System\Backend\Models\User
     * @return $this
     */
    public function from(TicketableInterface $from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * Message recipients.
     *
     * @param mixed
     * @return $this
     */
    public function to(TicketThread $to)
    {
        $this->to = $to;

        return $this;
    }

    /**
     * Message recipients.
     *
     * @param mixed
     * @return $this
     */
    public function otherTicketInfo($priority, $subject, $departments_name_id)
    {
        $this->priority = $priority;
        $this->subject = $subject;
        $this->departments_name_id = $departments_name_id;

        return $this;
    }

    /** Sending ticket function
     *
     */
    public function send()
    {
        if (! $this->from) {
            throw new TicketException('Sender not provided.');
        }

        if (! $this->message && ! $this->attachments) {
            throw new TicketException('Message not provided');
        }

        $this->getAttachments();
        $from = $this->from;
        $thread = $this->getThread();
        $message = $this->message;
        $attachment_title = $this->attachment_title;
        $attachments = $this->attachments;
        $priority = $this->priority;
        $subject = $this->subject;
        $departments_name_id = $this->departments_name_id;

        return $thread->tickets()->create([
            'message' => $message,
            'attachments' => $attachments,
            'attachment_title' => $attachment_title,
            'sender_id' => $from->id,
            'model' => get_class($from),
            'priority' => $priority,
            'subject' => $subject,
            'departments_name_id' => $departments_name_id,
        ]);
    }

    protected function getThread()
    {
        $thread = null;

        // If recipient is already a thread
        // let's use it!
        if ($this->to instanceof TicketThreadInterface) {
            return $this->to;
        }

        return $this->createThread();
    }

    protected function createThread()
    {
        $from = $this->from;

        return DB::transaction(function () use ($from) {
            $thread = App::make(TicketThreadInterface::class);
            $thread->status = 'pending';
            $thread->save();
            // Build participants array
            $participants = [
                ['thread_id' => $thread->id, 'user_id' => $from->id, 'model' => get_class($from)],
            ];

            $thread->participants()->insert($participants);

            return $thread;
        });
    }

    /**
     * @return array<int, mixed>
     */
    public static function listValues(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values($decoded) : [$value];
        }

        if (is_object($value)) {
            return array_values((array) $value);
        }

        if (is_array($value)) {
            return array_values($value);
        }

        return [];
    }

    public static function humanTime(mixed $date): string
    {
        if (! $date) {
            return '';
        }

        return \Carbon\CarbonImmutable::parse($date)->diffForHumans();
    }

    public function findThread(int $threadId): TicketThread
    {
        $thread = TicketThread::query()
            ->without('tickets')
            ->with('participants')
            ->find($threadId);

        if (! $thread instanceof TicketThread) {
            abort(404);
        }

        return $thread;
    }

    public function authorizeCustomer(TicketThread $thread): void
    {
        $contact = customer(true);

        if (! $contact || ! $contact->can('ticket.tickets')) {
            abort(403);
        }

        $allowed = $thread->participants->contains(function ($participant) use ($contact) {
            return $participant->model === Contact::class
                && (int) $participant->user_id === (int) $contact->id;
        });

        if (! $allowed) {
            abort(403);
        }
    }

    public function authorizeAdmin(TicketThread $thread): void
    {
        $user = backpack_user();

        if (! $user) {
            abort(403);
        }

        if ($user->hasRole('Super Admin')) {
            return;
        }

        $allowed = $thread->participants->contains(function ($participant) use ($user) {
            return $participant->model === $user::class
                && (int) $participant->user_id === (int) $user->id;
        });

        if (! $allowed) {
            abort(404);
        }
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    public function messagesAfter(TicketThread $thread, int $afterId): Collection
    {
        return $thread->tickets()
            ->where('tickets.id', '>', max(0, $afterId))
            ->orderBy('tickets.id')
            ->limit(100)
            ->get();
    }

    /**
     * Drop empty file inputs so a blank attachment field does not satisfy
     * "message or attachment" validation.
     */
    public function prepareReply(Request $request): void
    {
        $message = $request->input('message');
        if (is_string($message)) {
            $message = trim($message);
            $request->merge(['message' => $message === '' ? null : $message]);
        }

        $files = $this->validFiles($request);
        $request->files->remove('attachments');

        if ($files !== []) {
            $request->files->set('attachments', $files);
        }
    }

    public function replyTo(TicketableInterface $sender, TicketThread $thread, Request $request): TicketMessage
    {
        $files = $this->validFiles($request);
        $titles = array_map(
            fn ($file) => $file->getClientOriginalName(),
            $files
        );

        $message = $this->from($sender)
            ->to($thread)
            ->attachments($files === [] ? null : $files)
            ->attachmentTitle(json_encode($titles))
            ->message($request->input('message'))
            ->otherTicketInfo(null, null, null)
            ->send();

        if (! $message instanceof TicketMessage) {
            abort(500, 'Unable to save the ticket message.');
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(TicketMessage $message, string $audience): array
    {
        $urls = self::listValues($message->attachments);
        $titles = self::listValues($message->attachment_title);

        $attachments = [];
        foreach ($urls as $index => $url) {
            if (! is_string($url) || ! $this->isSafeUrl($url)) {
                continue;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: $url;
            $name = $titles[$index] ?? basename($path);

            $attachments[] = [
                'url' => $url,
                'name' => is_string($name) && $name !== '' ? $name : basename($path),
                'is_image' => $this->isImage($path),
            ];
        }

        return [
            'id' => (int) $message->id,
            'body' => (string) ($message->message ?? ''),
            'mine' => $this->isMine($message, $audience),
            'time' => self::humanTime($message->created_at),
            'sent_at' => optional($message->created_at)->toIso8601String() ?? '',
            'attachments' => $attachments,
        ];
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
     */
    private function validFiles(Request $request): array
    {
        return collect($request->file('attachments', []))
            ->filter(fn ($file) => $file instanceof \Illuminate\Http\UploadedFile && $file->isValid())
            ->values()
            ->all();
    }

    private function isMine(TicketMessage $message, string $audience): bool
    {
        if ($audience === 'customer') {
            $contact = customer(true);

            return $contact
                && $message->model === Contact::class
                && (int) $message->sender_id === (int) $contact->id;
        }

        if ($audience !== 'admin') {
            return false;
        }

        $user = backpack_user();

        return $user
            && $message->model === $user::class
            && (int) $message->sender_id === (int) $user->id;
    }

    private function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '//')) {
            return false;
        }

        return str_starts_with($url, '/')
            || str_starts_with($url, 'http://')
            || str_starts_with($url, 'https://');
    }

    private function isImage(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }
}
