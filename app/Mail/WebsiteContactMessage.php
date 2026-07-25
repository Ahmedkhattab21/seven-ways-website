<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WebsiteContactMessage extends Mailable
{
    use Queueable;
    use SerializesModels;

    public array $contact;

    public function __construct(array $contact)
    {
        $this->contact = $contact;
    }

    public function build(): self
    {
        $mail = $this
            ->subject('[Seven Ways Website] '.$this->contact['subject'])
            ->view('emails.website-contact');

        if (! empty($this->contact['email'])) {
            $mail->replyTo($this->contact['email'], $this->contact['name']);
        }

        return $mail;
    }
}
