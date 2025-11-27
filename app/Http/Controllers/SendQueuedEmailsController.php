<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailSendingQueue;
use Illuminate\Support\Facades\Mail;
use App\Mail\CampaignEmail;

class SendQueuedEmailsController extends Controller
{
    public function handle(Request $request)
    {
        $queues = EmailSendingQueue::where('is_send', 0)
            ->where('status', 1)
            ->where('priority', 3)
            ->limit(200) // batch size
            ->get();

        foreach ($queues as $queue) {

            // Replace placeholders dynamically
            $content = str_replace(
                ['{email}', '{name}', '{activation_link}'],
                [
                    $queue->to,
                    $queue->related_type ?? 'User',
                    $queue->related_id ? "https://erp.scorp.co/activate/{$queue->related_id}" : ""
                ],
                $queue->content
            );

            $queue->content = $content;

            try {
                Mail::to($queue->to)->send(new CampaignEmail($queue));
                $queue->is_send = 1;
                $queue->save();
            } catch (\Exception $e) {
                $queue->status = 2; // failed
                $queue->rejectapprovecoment = $e->getMessage();
                $queue->save();
            }
        }

        return response()->json([
            'status' => 'ok',
            'emails_sent' => $queues->count()
        ]);
    }
}
