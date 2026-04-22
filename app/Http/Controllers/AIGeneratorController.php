<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIGeneratorController extends Controller
{
    /**
     * Generate SEO-friendly product description using Google Gemini API
     */
    public function generateDescription(Request $request)
    {
        // ១. Validation យ៉ាងតឹងរ៉ឹង ដើម្បីការពារការវាយប្រហារ (Injection/XSS)
        $request->validate([
            'product_name' => 'required|string|max:255',
        ]);

        $productName = $request->product_name;
        $apiKey = env('GEMINI_API_KEY');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'AI API Key is missing in server configuration.'
            ], 500);
        }

        // ២. រៀបចំ Prompt បញ្ជាទៅកាន់ AI ឱ្យសរសេរជាទម្រង់ Markdown ងាយស្រួលអាន
        $prompt = "Act as an expert E-commerce copywriter. Write a short, punchy, and SEO-friendly product description for a technical hardware product named: '{$productName}'. 
        Strict rules:
        - Keep the description strictly between 2 to 3 short sentences (maximum 50 words).
        - Focus only on the main selling point and key benefit.
        - Write in pure plain text ONLY. Do not use any markdown formatting, asterisks, bold text, or HTML tags.";

        try {
            // ៣. បាញ់ Request ទៅកាន់ Gemini 1.5 Flash (ម៉ូដែលលឿន និងសន្សំសំចៃ)
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent?key={$apiKey}", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt]
                        ]
                    ]
                ]
            ]);

            // ៤. ត្រួតពិនិត្យចម្លើយដែលទទួលបាន
            if ($response->successful()) {
                $result = $response->json();

                // ទាញយកអត្ថបទចេញពី JSON របស់ Gemini
                $generatedText = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';

                return response()->json([
                    'success' => true,
                    'description' => trim($generatedText)
                ], 200);
            }

            // ប្រសិនបើ API របស់ Google មានបញ្ហា
            Log::error('Gemini API Error: ' . $response->body());
            return response()->json([
                'success' => false,
                // 🌟 បង្ហាញសារ Error ពិតប្រាកដពី Google
                'message' => 'Gemini Error: ' . $response->body()
            ], 502);
        } catch (\Exception $e) {
            // ចាប់ Error ទូទៅ (ដូចជាដាច់អ៊ីនធឺណិតពី Server)
            Log::error('AI Generator Exception: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error while connecting to AI.'
            ], 500);
        }
    }
}
