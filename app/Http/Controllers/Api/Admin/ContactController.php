<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Contact;

class ContactController extends Controller
{
    /**
     * ១. បង្ហាញបញ្ជីសារទាំងអស់ (មាន Filter តាម Status និង Search)
     */
    public function index(Request $request)
    {
        $query = Contact::latest();

        // 🌟 មុខងារ Filter តាម Status (unread, read, replied)
        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // 🌟 មុខងារ Search តាមឈ្មោះ, Email ឬ Subject
        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%")
                    ->orWhere('subject', 'LIKE', "%{$search}%");
            });
        }

        $contacts = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => $contacts
        ]);
    }

    /**
     * ២. មើលព័ត៌មានលម្អិតនៃសារណាមួយ (ពេលចុចមើល វាគួរតែដូរទៅជា Read ស្វ័យប្រវត្តិ)
     */
    public function show($id)
    {
        $contact = Contact::findOrFail($id);

        // បើវានៅ Unread ពេល Admin បើកមើល ត្រូវ Update វាទៅជា Read ភ្លាម
        if ($contact->status === 'unread') {
            $contact->update(['status' => 'read']);
        }

        return response()->json([
            'success' => true,
            'data'    => $contact
        ]);
    }

    /**
     * ៣. មុខងារប្តូរ Status (ឧ. ដូរពី Read ទៅ Replied)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:unread,read,replied'
        ]);

        $contact = Contact::findOrFail($id);
        $contact->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => "Contact status updated to {$request->status}.",
            'data'    => $contact
        ]);
    }
}
