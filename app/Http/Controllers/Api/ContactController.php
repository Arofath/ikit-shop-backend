<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;
use App\Models\User;
use App\Notifications\NewContactMessageNotification;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    /**
     * មុខងារសម្រាប់អតិថិជនបញ្ជូនសារ (Guest ឬ User)
     */
    public function store(Request $request)
    {
        // ១. Validate ទិន្នន័យពី Form
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        // ២. រក្សាទុកចូល Database
        $contact = Contact::create([
            'name'    => $request->name,
            'email'   => $request->email,
            'phone'   => $request->phone,
            'subject' => $request->subject,
            'message' => $request->message,
            'status'  => 'unread' // Default status
        ]);

        // 🌟 ៣. បាញ់ Notification ទៅកាន់ Admin និង Super Admin
        $admins = User::whereIn('role', ['admin', 'super_admin'])->get();
        if ($admins->isNotEmpty()) {
            Notification::send($admins, new NewContactMessageNotification($contact));
        }

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully. We will get back to you soon!',
            'data'    => $contact
        ], 201);
    }
}
