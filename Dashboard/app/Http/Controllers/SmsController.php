<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use Illuminate\Http\Request;

class SmsController extends Controller
{
    public function index(Request $request)
    {
        // Get distinct senders with their latest message and unread count (example)
        // We'll group by sender, get the latest message, and count total messages per sender
        $senders = SmsMessage::select('sender')
            ->distinct()
            ->get()
            ->map(function ($item) {
                $sender = $item->sender;
                $messages = SmsMessage::where('sender', $sender)->orderBy('received_at', 'desc');
                $latest = $messages->first();
                $count = $messages->count();
                return (object) [
                    'sender' => $sender,
                    'latest_message' => $latest,
                    'count' => $count,
                ];
            })
            ->sortByDesc(function ($item) {
                return $item->latest_message->received_at ?? now();
            })
            ->values();

        // Determine the selected sender (from query string or default to first)
        $selectedSender = $request->query('sender', $senders->first()->sender ?? null);

        // Get messages for the selected sender
        $conversation = [];
        if ($selectedSender) {
            $conversation = SmsMessage::where('sender', $selectedSender)
                ->orderBy('received_at', 'asc')
                ->get();
        }

        return view('sms.sms', compact('senders', 'selectedSender', 'conversation'));
    }
}