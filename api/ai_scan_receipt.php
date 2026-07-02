<?php
/**
 * API: AI Scan Receipt
 * Receives an uploaded receipt image, calls Gemini API to perform OCR and extract structured items.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No image file uploaded or upload error.']);
    exit;
}

if (!defined('GOOGLE_GEMINI_API_KEY') || empty(GOOGLE_GEMINI_API_KEY)) {
    echo json_encode(['error' => 'Google Gemini API Key is not configured.']);
    exit;
}

$fileTmpPath = $_FILES['receipt']['tmp_name'];
$fileName = $_FILES['receipt']['name'];
$fileSize = $_FILES['receipt']['size'];
$fileType = $_FILES['receipt']['type'];

// Validate file type
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($fileType, $allowedMimeTypes)) {
    echo json_encode(['error' => 'Only JPG, PNG, and WebP images are allowed.']);
    exit;
}

// Limit file size to 10MB
if ($fileSize > 10 * 1024 * 1024) {
    echo json_encode(['error' => 'File size exceeds 10MB limit.']);
    exit;
}

try {
    // Read the image file and convert to base64
    $imageData = base64_encode(file_get_contents($fileTmpPath));

    // Fetch existing groups for context
    $existingGroups = $pdo->query("SELECT DISTINCT group_name FROM nota_claim_items WHERE group_name IS NOT NULL AND group_name != '' ORDER BY group_name ASC LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
    $groupsContext = !empty($existingGroups) ? implode(', ', $existingGroups) : 'BBM, ATK, Konsumsi, Transportasi, Material, Alat Kerja, Lain-lain';

    // Prepare prompt
    $prompt = "You are an AI assistant for a financial accounting system. 
Analyze the uploaded receipt/invoice photo and extract the transaction details.
Translate the data into a structured JSON format.

Available categories (group_name) you should use if they match, or suggest a suitable general category:
[{$groupsContext}]

Please extract:
1. The transaction date (format: YYYY-MM-DD). If not found, output null.
2. The name of the store/shop where the purchase was made. If not found, output null.
3. The list of items purchased. For each item, specify:
   - item_name: Description of the product or service purchased (clean up formatting, keep it concise).
   - qty: Quantity purchased. If not clear, default to 1.
   - price: Unit price. If not listed, calculate it by dividing the total item cost by qty.
   - group_name: The matching category name from the list above.

Respond ONLY with a raw JSON object (without markdown code blocks or additional text) using this exact schema:
{
    \"claim_date\": \"YYYY-MM-DD or null\",
    \"store_name\": \"string or null\",
    \"items\": [
        {
            \"item_name\": \"string\",
            \"qty\": number,
            \"price\": number,
            \"group_name\": \"string\"
        }
    ]
}";

    // Call Gemini API
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . GOOGLE_GEMINI_API_KEY;

    $data = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt],
                    [
                        "inlineData" => [
                            "mimeType" => $fileType,
                            "data" => $imageData
                        ]
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
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
    
    // Clean markdown json tags
    $aiText = preg_replace('/^```json\s*/i', '', $aiText);
    $aiText = preg_replace('/```$/i', '', trim($aiText));
    
    $parsedData = json_decode($aiText, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse JSON from AI response: " . $aiText);
    }
    
    echo json_encode([
        'success' => true,
        'data' => $parsedData
    ]);

} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
