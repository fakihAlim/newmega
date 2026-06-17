<?php
/**
 * API: AI Suggest Quotation Items
 * Uses Google Gemini API to translate project specs into quotation items.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$specs = trim($_POST['project_specs'] ?? '');

if (empty($specs)) {
    echo json_encode(['error' => 'Project specifications are required.']);
    exit;
}

if (!defined('GOOGLE_GEMINI_API_KEY') || empty(GOOGLE_GEMINI_API_KEY)) {
    echo json_encode(['error' => 'Google Gemini API Key is not configured.']);
    exit;
}

try {
    $prompt = "You are an expert procurement and construction estimator in Indonesia. 
Translate the following project specifications or requirements into a list of detailed quotation items.
Also, estimate the current market prices in Indonesia (IDR) for materials and manpower for each item.

Project Specifications:
\"{$specs}\"

Return ONLY a valid JSON array of objects (NO markdown blocks, NO other text).
Each object must have exactly these keys:
- \"description\": string (Item or work description)
- \"type_specification\": string (Detailed specs, brands, or sizes. Keep it concise)
- \"qty\": number (Estimated quantity, use 1 if it's a lump sum job)
- \"uom\": string (Standard Indonesian Unit of Measure, e.g., PCS, ZAK, M2, M3, LTR, KG, BTG, LS)
- \"material_price\": number (Estimated unit price for materials in IDR, 0 if purely manpower)
- \"manpower_price\": number (Estimated unit price for manpower/installation in IDR, 0 if purely material)

Example Output:
[
  {
    \"description\": \"Pemasangan Baja Ringan\",
    \"type_specification\": \"Canal C75 tebal 0.75mm\",
    \"qty\": 10,
    \"uom\": \"BTG\",
    \"material_price\": 85000,
    \"manpower_price\": 25000
  }
]";

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . GOOGLE_GEMINI_API_KEY;
    
    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.3, // slight creativity for estimating
            "topK" => 1,
            "topP" => 1,
            "maxOutputTokens" => 4096,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API request failed with HTTP code {$httpCode}: " . ($response ?: $curlError));
    }

    $resData = json_decode($response, true);
    
    if (!isset($resData['candidates'][0]['content']['parts'][0]['text'])) {
        throw new Exception("Invalid response format from Gemini API.");
    }
    
    $aiText = trim($resData['candidates'][0]['content']['parts'][0]['text']);
    $aiText = preg_replace('/^```json\s*/i', '', $aiText);
    $aiText = preg_replace('/```$/i', '', trim($aiText));
    
    $suggestedData = json_decode($aiText, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse JSON from AI response.");
    }
    
    echo json_encode([
        'success' => true,
        'data' => $suggestedData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
