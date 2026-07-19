<?php

namespace App\Mail;

use App\Models\SupplierEmailDraft;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SupplierEmailDraftMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public SupplierEmailDraft $draft) {}

    public function build(): self
    {
        return $this
            ->subject($this->draft->subject)
            ->view('emails.supplier-email-draft', [
                'body' => $this->draft->body,
            ]);
    }
}
