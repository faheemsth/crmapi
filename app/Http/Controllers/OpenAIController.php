<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIController extends Controller
{
    public function generateSop()
    {
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Write a short poem about Laravel and AI.'],
            ],
        ]);

        return response()->json([
            'reply' => $response->choices[0]->message->content,
        ]);
    }
}
