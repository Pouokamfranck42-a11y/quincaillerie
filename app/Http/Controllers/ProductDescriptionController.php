<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\Ai\GeminiService;
use Illuminate\Http\Request;

class ProductDescriptionController extends Controller
{
    public function generate(Request $request, GeminiService $gemini)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
        ]);

        $category = isset($data['category_id']) ? Category::find($data['category_id'])?->name : null;

        $prompt = 'Rédige une description commerciale concise (2 à 3 phrases) en français pour ce produit de quincaillerie, '
            ."sans superlatifs excessifs, mentionnant l'usage typique.\n\n"
            ."Nom : {$data['name']}\n"
            .(filled($data['brand'] ?? null) ? "Marque : {$data['brand']}\n" : '')
            .($category ? "Catégorie : {$category}\n" : '');

        // maxTokens généreux : les modèles Gemini récents consomment une partie du budget en
        // "réflexion" interne avant le texte visible — un budget trop juste coupe la réponse
        // en plein milieu de phrase (constaté en vérification live avec le défaut à 1024).
        $description = $gemini->generateText(
            "Tu rédiges des descriptions commerciales courtes et factuelles pour le catalogue d'une quincaillerie.",
            $prompt,
            2048,
        );

        if ($gemini->lastErrorMessage() !== null) {
            return response()->json(['error' => $gemini->lastErrorMessage()], 422);
        }

        return response()->json(['description' => $description]);
    }
}
