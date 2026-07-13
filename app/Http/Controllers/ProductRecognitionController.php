<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\Ai\GeminiService;
use Illuminate\Http\Request;

class ProductRecognitionController extends Controller
{
    public function recognize(Request $request, GeminiService $gemini)
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $file = $request->file('photo');
        $data = base64_encode((string) file_get_contents($file->getRealPath()));
        $mediaType = $file->getMimeType() ?: 'image/jpeg';

        $result = $gemini->extractStructured(
            "Tu identifies des articles de quincaillerie à partir d'une photo pour aider un magasinier à retrouver ou créer une fiche produit.",
            [
                ['type' => 'image', 'source' => ['type' => 'base64', 'data' => $data, 'mediaType' => $mediaType]],
                ['type' => 'text', 'text' => "Identifie cet objet de quincaillerie. Donne un type d'objet, une matière probable, un usage typique en une phrase, et 3 à 5 mots-clés de recherche en français."],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'object_type' => ['type' => 'string'],
                    'probable_material' => ['type' => 'string'],
                    'probable_use' => ['type' => 'string'],
                    'keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['object_type', 'keywords'],
                'additionalProperties' => false,
            ],
        );

        $keywords = array_filter((array) ($result['keywords'] ?? []));

        $matches = collect();
        if ($keywords !== []) {
            $matches = Product::where('active', true)
                ->where(function ($q) use ($keywords) {
                    foreach ($keywords as $keyword) {
                        $q->orWhere('name', 'ilike', "%{$keyword}%")
                            ->orWhere('reference', 'ilike', "%{$keyword}%")
                            ->orWhere('description', 'ilike', "%{$keyword}%");
                    }
                })
                ->limit(10)
                ->get(['id', 'name', 'reference'])
                ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name, 'reference' => $p->reference, 'url' => route('products.edit', $p)]);
        }

        return response()->json([
            'object_type' => $result['object_type'] ?? null,
            'probable_material' => $result['probable_material'] ?? null,
            'probable_use' => $result['probable_use'] ?? null,
            'keywords' => $keywords,
            'matches' => $matches,
        ]);
    }
}
