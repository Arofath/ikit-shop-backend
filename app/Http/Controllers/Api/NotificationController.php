<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * ១. ទាញយក Notifications ទាំងអស់របស់អ្នកកំពុង Login
     */
    public function index(Request $request)
    {
        // $request->user() ទាញយក User ដែលកំពុង Login (អាចជា Admin ឬ Customer អាស្រ័យលើ Token)
        $user = $request->user();

        // ទាញយក Notification ដែលមិនទាន់អាន (យក ១៥ ក្នុងមួយទំព័រ)
        $notifications = $user->notifications()->paginate(15);

        // រាប់ចំនួន Notification ដែលមិនទាន់អាន (Unread count)
        $unreadCount = $user->unreadNotifications()->count();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'data' => $notifications
        ]);
    }

    /**
     * ២. សម្គាល់ថាបានអាន (Mark as Read) ១ ដំណឹង
     */
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['success' => true]);
        }

        return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
    }

    /**
     * ៣. សម្គាល់ថាបានអានទាំងអស់ (Mark All as Read)
     */
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
