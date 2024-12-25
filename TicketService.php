<?php

namespace Amplify\System\Ticket;

use Amplify\System\Ticket\Exceptions\TicketException;
use Amplify\System\Ticket\Interfaces\TicketableInterface;
use Amplify\System\Ticket\Interfaces\TicketThreadInterface;
use Amplify\System\Ticket\Models\TicketThread;
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
     * @param \App\Models\User
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
}
