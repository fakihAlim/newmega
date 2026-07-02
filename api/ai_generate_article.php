<?php
/**
 * API: AI Generate Article (Tips & Tricks)
 * Uses Google Gemini API to generate an article title, excerpt, HTML content, and suggest an image.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check permission
if (!canAccess('cms_landing')) {
    echo json_encode(['error' => 'Anda tidak memiliki akses ke fitur ini.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Metode request tidak valid.']);
    exit;
}

$topic = trim($_POST['topic'] ?? '');
$tone = trim($_POST['tone'] ?? 'Edukatif');

if (empty($topic)) {
    echo json_encode(['error' => 'Topik artikel wajib diisi.']);
    exit;
}

if (!defined('GOOGLE_GEMINI_API_KEY') || empty(GOOGLE_GEMINI_API_KEY)) {
    echo json_encode(['error' => 'Google Gemini API Key belum dikonfigurasi di halaman Pengaturan.']);
    exit;
}

try {
    // Prepare the prompt for Gemini
    $prompt = "You are a professional Content Creator and Expert Writer specializing in construction, civil engineering, interior design, and home improvement tips in Indonesia.
Generate a high-quality, informative, and engaging article in Indonesian about: \"{$topic}\" with a \"{$tone}\" tone.

For the content:
- Write it in HTML format.
- Use <h4> or <h5> for section subheadings.
- Use <p> for paragraphs.
- Use <ul> or <ol> with <li> for lists.
- Avoid using <html>, <body>, or <h1>/<h2>/<h3> tags. Focus only on body content formatting.
- Do NOT include any <img> tags or raw image URLs inside the \"content\" field (the image is handled separately via \"image_url\").
- Make it comprehensive, detailed, and practically useful (at least 3-4 paragraphs with clear steps/tips).

Choose the most relevant header image URL from the following list based on the topic:
1. Wall cracks, cement, concrete: https://images.unsplash.com/photo-1533000609349-05991e6b8531?auto=format&fit=crop&w=800&q=80
2. General construction, buildings: https://images.unsplash.com/photo-1541888946425-d81bb19240f5?auto=format&fit=crop&w=800&q=80
3. Painting, tools, interior decoration: https://images.unsplash.com/photo-1562259949-e8e7689d7828?auto=format&fit=crop&w=800&q=80
4. Garden, plants, landscaping: https://images.unsplash.com/photo-1585320806297-9794b3e4eeae?auto=format&fit=crop&w=800&q=80
5. Woodworking, carpentry: https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&w=800&q=80
6. Electrical, wiring: https://images.unsplash.com/photo-1621905251189-08b45d6a269e?auto=format&fit=crop&w=800&q=80
7. Plumbing, pipes: https://images.unsplash.com/photo-1504307651254-35680f356dfd?auto=format&fit=crop&w=800&q=80
8. House architecture, interior design, roofing: https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=800&q=80
9. Workplace safety, helmet, tools: https://images.unsplash.com/photo-1508450859948-4e04fabaa4ea?auto=format&fit=crop&w=800&q=80
10. Generic engineer, technology: https://images.unsplash.com/photo-1581094288338-2314dddb7ecc?auto=format&fit=crop&w=800&q=80

Respond ONLY with a raw JSON object (without markdown code blocks) using this exact format:
{
    \"title\": \"string (a catchy title for the article)\",
    \"excerpt\": \"string (a brief 1-2 sentence summary, around 100-150 characters)\",
    \"content\": \"string (the full article body formatted in HTML)\",
    \"image_url\": \"string (one selected URL from the image list above)\"
}";

    // Call Google Gemini API
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
            "temperature" => 0.7,
            "topK" => 40,
            "topP" => 0.95,
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
        throw new Exception("Format respons dari Gemini API tidak valid.");
    }
    
    $aiText = trim($resData['candidates'][0]['content']['parts'][0]['text']);
    
    // Remove markdown code blocks if Gemini accidentally included them
    $aiText = preg_replace('/^```json\s*/i', '', $aiText);
    $aiText = preg_replace('/```$/i', '', trim($aiText));
    
    $generatedData = json_decode($aiText, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Gagal mengurai format JSON hasil AI: " . $aiText);
    }

    echo json_encode([
        'success' => true,
        'data' => $generatedData
    ]);

} catch (Exception $e) {
    error_log('[NEWMEGA] AI Generate Article Error: ' . $e->getMessage());
    echo json_encode([
        'error' => 'Terjadi kesalahan sistem saat membuat artikel: ' . $e->getMessage()
    ]);
}
