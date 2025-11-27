<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailSendingQueue;
use Carbon\Carbon;

class SendGridWebhookController extends Controller
{
    public function handle(Request $request)
    {
        foreach ($request->all() as $event) {

            if (!isset($event['unique_args']['queue_id'])) continue;

            $queue = EmailSendingQueue::find($event['unique_args']['queue_id']);
            if (!$queue) continue;

            switch ($event['event']) {
                case 'delivered':
                    $queue->delivered_at = Carbon::now();
                    break;
                case 'open':
                    $queue->opened_at = Carbon::now();
                    break;
                case 'click':
                    $queue->clicked_at = Carbon::now();
                    break;
                case 'bounce':
                case 'dropped':
                    $queue->bounced_at = Carbon::now();
                    break;
            }
            $queue->save();
        }

        return response()->json(['status'=>'ok']);
    }
}

