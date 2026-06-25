<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PurchaseOrderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    public function build(): self
    {
        $mail = $this
            ->subject(__('messages.purchase_order_email_subject', ['po' => $this->purchaseOrder->po_number]))
            ->view('emails.purchase-order', [
                'purchaseOrder' => $this->purchaseOrder->loadMissing(['supplier', 'items.ingredient']),
            ]);

        if (class_exists(Pdf::class)) {
            $pdf = Pdf::loadView('purchase-orders.pdf', [
                'purchaseOrder' => $this->purchaseOrder->loadMissing(['supplier', 'items.ingredient']),
            ]);

            $mail->attachData(
                $pdf->output(),
                "purchase-order-{$this->purchaseOrder->po_number}.pdf",
                ['mime' => 'application/pdf']
            );
        }

        return $mail;
    }
}
