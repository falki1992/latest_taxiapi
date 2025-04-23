<?php

namespace App\Http\Controllers;

use App\Models\CustomerSupport;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerMessageController extends Controller
{
    // Display a paginated list of messages
    public function index(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        $perPage = $request->query('per_page', 10);
        $messages = CustomerSupport::orderBy('created_at', 'desc')
        ->where('sender_id', $loggedIn)
        ->orWhere('receiver_id', $loggedIn)->paginate($perPage);
        return response()->json([
            'status_code' => 200,
            'data' => $messages
        ], 200);
    }

    // Create a new message
    public function store(Request $request)
    {
        $loggedIn = \Auth::user()->id;
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            // 'sender_id' => 'required|integer',
            // 'receiver_id' => 'required|integer',
            // 'ticket_no' => 'required|string|max:50',
            // 'message_type' => 'required|integer',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            'status' => 'nullable|in:active,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'errors' => $validator->errors()
            ], 400);
        }
        $ticketNo = 'TICKET-' . time() . '-' . rand(1000, 9999);


        // Create the message record
        $message = CustomerSupport::create([
            'sender_id' => $loggedIn,
            'receiver_id' => 1,
            'ticket_no' => $ticketNo,
            // 'message_type' => $request->message_type,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => $request->status ?? 'active'
        ]);

        return response()->json([
            'status_code' => 200,
            'message' => 'Message created successfully.',
            'data' => $message
        ], 200);
    }

    // Update an existing message record by ID
    public function updateMessage(Request $request, $id)
    {
        // Validate incoming data
        $validator = Validator::make($request->all(), [
            'sender_id' => 'nullable|integer',
            'receiver_id' => 'nullable|integer',
            'ticket_no' => 'nullable|string|max:50',
            'message_type' => 'nullable|integer',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'status' => 'nullable|in:active,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status_code' => 400,
                'errors' => $validator->errors(),
            ], 400);
        }

        // Find the message by its primary key (id)
        $message = CustomerSupport::find($id);

        if (!$message) {
            return response()->json([
                'status_code' => 404,
                'message' => 'Message not found.',
            ], 404);
        }

        // Update fields (only update those present in the request)
        $message->update($request->only([
            'sender_id',
            'receiver_id',
            'ticket_no',
            'message_type',
            'subject',
            'message',
            'status'
        ]));

        // The updated_at field will be updated automatically

        return response()->json([
            'status_code' => 200,
            'message' => 'Message updated successfully.',
            'data' => $message,
        ], 200);
    }
}
