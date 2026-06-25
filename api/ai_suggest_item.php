<?php
/**
 * API: AI Suggest Item Details
 * Uses Google Gemini API to suggest category, specs, and UoM based on item name.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

$itemName = trim($_POST['item_name'] ?? '');

if (empty($itemName)) {
    echo json_encode(['error' => 'Item name is required.']);
    exit;
}

if (!defined('GOOGLE_GEMINI_API_KEY') || empty(GOOGLE_GEMINI_API_KEY)) {
    echo json_encode(['error' => 'Google Gemini API Key is not configured.']);
    exit;
}

try {
    // 1. Fetch available categories
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $catListText = "";
    foreach ($categories as $cat) {
        $catListText .= "- ID {$cat['id']} : {$cat['name']}\n";
    }

    // 2. Prepare the prompt for Gemini
    $prompt = "You are an AI assistant for a procurement and inventory system (mostly construction and mechanical materials in Indonesia). 
Given an item name, suggest the most appropriate category, type/specification (extract extra details from the name), a standard Unit of Measure (UoM), and a short manual item code.

Item Name: \"{$itemName}\"

Available Categories:
{$catListText}

Common UoMs in Indonesia: PCS, MTR, KG, BTG (Batang), ZAK, LTR, SET, ROLL, BKS, DUS.

Respond ONLY with a raw JSON object (without markdown code blocks) using this exact format:
{
    \"category_id\": integer (the closest matching ID from the list above),
    \"type_specification\": \"string (extract any sizes, brands, or specific types from the name, or leave empty)\",
    \"uom\": \"string (choose an appropriate UoM, must be uppercase)\",
    \"manual_code\": \"string (generate a short 2-5 letter code representing the item's BRAND or specific model, EXCLUDING general category words like Cat, Kabel, Pipa, Besi. e.g. for 'Cat APP 37' -> 'APP', for 'Kabel Supreme' -> 'SUP')\"
}";

    // 3. Call Google Gemini API
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
            "temperature" => 0.2,
            "topK" => 1,
            "topP" => 1,
            "maxOutputTokens" => 2048,
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    // Disable SSL verification for local dev (Laragon) if needed, but best to keep it if cainfo is set.
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
    
    // Remove markdown code blocks if Gemini accidentally included them
    $aiText = preg_replace('/^```json\s*/i', '', $aiText);
    $aiText = preg_replace('/```$/i', '', trim($aiText));
    
    $suggestedData = json_decode($aiText, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse JSON from AI response: " . $aiText);
    }
    
    // Append sequence number to manual code
    if (!empty($suggestedData['manual_code']) && !empty($suggestedData['category_id'])) {
        $shortCode = strtoupper(str_replace(' ', '', $suggestedData['manual_code']));
        
        $stmt = $pdo->prepare("SELECT prefix FROM categories WHERE id = ?");
        $stmt->execute([$suggestedData['category_id']]);
        $prefix = $stmt->fetchColumn();
        
        if ($prefix) {
            $stmt = $pdo->prepare("
                SELECT item_code 
                FROM items 
                WHERE item_code LIKE ?
                ORDER BY CAST(SUBSTRING_INDEX(item_code, '-', -1) AS UNSIGNED) DESC 
                LIMIT 1
            ");
            $stmt->execute([$prefix . '-' . $shortCode . '-%']);
            $lastCode = $stmt->fetchColumn();
            
            $nextSeq = 1;
            if ($lastCode) {
                $parts = explode('-', $lastCode);
                $nextSeq = intval(end($parts)) + 1;
            }
            
            $suggestedData['manual_code'] = $shortCode . '-' . str_pad($nextSeq, 4, '0', STR_PAD_LEFT);
        }
    }
    
    // 4. Return success
    echo json_encode([
        'success' => true,
        'data' => $suggestedData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
