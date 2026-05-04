<?php

namespace App\Mail;

use App\Domains\Commerce\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPaidMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        $shortId = strtoupper(substr((string) $this->order->id, -8));

        return new Envelope(
            subject: "Pagamento confirmado #{$shortId} — MM Sports",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-paid',
            with: [
                'order' => $this->order->loadMissing('items'),
            ],
        );
    }
}
