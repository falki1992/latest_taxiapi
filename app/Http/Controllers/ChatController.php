<?php

namespace App\Http\Controllers;

use App\Models\Chat;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChatController extends Controller
{
    // Send message
    public function sendMessage(Request $request)
{
    // Validate the request data
    $validated = $request->validate([
        'receiver_id' => 'required|exists:users,id',  // Receiver must be an existing user
        'message' => 'required|string|max:1000',      // Validate message content
    ]);

    // Get the logged-in user (either a customer or a driver)
    $sender = Auth::user() ?? Auth::guard('driver')->user();

    // Ensure the sender is not trying to send a message to themselves
    if ($sender->id == $validated['receiver_id']) {
        return response()->json([
            'success' => false,
            'message' => 'You cannot send a message to yourself.'
        ], 400);
    }

    // Create the message
    $message = Chat::create([
        'sender_id' => $sender->id,
        'receiver_id' => $validated['receiver_id'],
        'message' => $validated['message'],
        'status' => '1',  // Default status is 'sent'
    ]);

    // Optionally, you could also send a notification to the receiver here

    // Return the response with the message data
    return response()->json([
        'success' => true,
        'message' => 'Message sent successfully',
        'data' => [
            'message' => $message,
            'sender' => $message->sender,
            'receiver' => $message->receiver
        ]
    ]);
}

    // Fetch conversation between two users
    public function getConversation(Request $request, $receiverId)
    {
        $validated = $request->validate([
            'receiver_id' => 'required|exists:users,id',
        ]);

        // Get logged-in user (sender)
        $sender = Auth::user();

        // Retrieve messages between the sender and receiver
        $messages = Message::where(function ($query) use ($sender, $receiverId) {
            $query->where('sender_id', $sender->id)
                ->where('receiver_id', $receiverId);
        })
            ->orWhere(function ($query) use ($sender, $receiverId) {
                $query->where('sender_id', $receiverId)
                    ->where('receiver_id', $sender->id);
            })
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages
        ]);
    }
}
