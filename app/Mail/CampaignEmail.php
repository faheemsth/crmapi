<?php
namespace App\Mail;

use Illuminate\Mail\Mailable;

class CampaignEmail extends Mailable
{
    public $queue;

    public function __construct($queue)
    {
        $this->queue = $queue;
    }

    public function build()
    {
        // SendGrid custom headers
        $headerData = [
            'category' => 'convosoft-campaign',
            'unique_args' => [
                'queue_id' => $this->queue->id // Important!
            ]
        ];

        $this->withSwiftMessage(function ($message) use ($headerData) {
            $message->getHeaders()->addTextHeader('X-SMTPAPI', json_encode($headerData));
        });

        return $this->from($this->queue->from_email)
                    ->subject($this->queue->subject)
                    ->html($this->queue->content);
    }
}
