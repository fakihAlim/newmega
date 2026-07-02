<?php
/**
 * AI Chatbot Backend - Text-to-SQL Processor
 */
require_once __DIR__ . '/../../includes/auth.php';

if (!canAccess('ai_chat')) {
    echo json_encode(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menggunakan fitur ini.']);
    exit;
}

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');

if (empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Pesan tidak boleh kosong.']);
    exit;
}

if (!defined('GOOGLE_GEMINI_API_KEY') || empty(GOOGLE_GEMINI_API_KEY)) {
    echo json_encode(['status' => 'success', 'answer' => 'Sistem AI belum dikonfigurasi (API Key kosong).']);
    exit;
}

/**
 * Call Google Gemini API
 */
function callGemini($systemInstruction, $prompt) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent?key=" . GOOGLE_GEMINI_API_KEY;
    
    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'systemInstruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.1,
            'maxOutputTokens' => 1024
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Gemini cURL error: " . $error);
    }
    
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($statusCode !== 200) {
        throw new Exception("Gemini API returned status code " . $statusCode);
    }
    
    $data = json_decode($response, true);
    return trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
}

// Stage 1: Text-to-SQL System Instruction & Prompts
$sqlInstruction = "
You are a database query generator for a procurement and timesheet database.
Database tables and their columns:
1. `projects` (id, name, budget, start_date, end_date, status, description)
   - Note: status can be 'planning', 'active', 'completed', 'cancelled'
2. `material_requests` (id, mr_number, request_date, requested_by, project_id, status)
   - Note: status can be 'draft', 'pending', 'approved', 'rejected', 'completed'
3. `material_request_items` (id, mr_id, item_id, description, qty, uom, qty_ordered, remark)
4. `purchase_orders` (id, po_number, po_date, vendor_id, company_id, status, subtotal, discount, tax, shipping, other_cost, total)
   - Note: status can be 'draft', 'pending', 'approved', 'rejected', 'partially_received', 'completed', 'cancelled'
5. `purchase_order_items` (id, po_id, item_id, mr_item_id, item_name, qty, uom, unit_price, total)
6. `vendors` (id, company_name, email, phone, address, is_active)
7. `items` (id, item_code, category_id, description, uom, current_stock, minimum_stock, is_active)
8. `goods_receivings` (id, po_id, receive_date, surat_jalan_no, received_at)
   - Note: received_at can be 'warehouse' or a project name
9. `goods_receiving_items` (id, receiving_id, po_item_id, qty_received, qty_rejected)
10. `users` (id, username, full_name, email, role, is_active)
11. `timesheet_entries` (id, employee_id, company_id, project_id, work_date, work_type, overtime_hours, daily_wage_at_time, notes, status, approved_by, approved_at)
   - Note: status can be 'pending', 'approved', 'rejected'
   - Note: work_type can be 'full', 'half'
12. `employees` (id, employee_code, user_id, wage_id, is_active)
13. `master_wages` (id, jabatan_name, daily_wage)

CRITICAL RULES:
- If the user's input is a greeting, small talk, chit-chat, or unrelated to the database or projects, you MUST strictly output exactly 'REJECT'.
- Do NOT query sensitive columns like password, password_hash, or keys. If the user asks for sensitive information, output exactly 'REJECT'.
- If the input is relevant, generate a single valid MySQL SELECT query that will retrieve the requested data.
- The SQL query must use exact table and column names from the schema.
- Limit the query output to a maximum of 50 rows.
- Output ONLY the raw SQL query. No explanation, no markdown backticks, no comments.
- CRUCIAL: Always select the primary key `id` column (e.g., `po.id` for POs, `mr.id` for MRs, `p.id` for Projects, `t.id` for Timesheet Entries) when querying Purchase Orders, Material Requests, Projects, or Timesheet Entries, so that we can construct clickable links for them.
";

try {
    // 1. Generate SQL from prompt
    $rawResponse = callGemini($sqlInstruction, $message);
    
    // Clean markdown block wrappers if present
    $sql = preg_replace('/^```(?:sql)?\s*/i', '', $rawResponse);
    $sql = preg_replace('/\s*```$/', '', $sql);
    $sql = trim($sql);
    
    if ($sql === 'REJECT') {
        echo json_encode(['status' => 'success', 'answer' => 'Hanya dapat menjawab terkait Proyek']);
        exit;
    }
    
    // Safety check in PHP
    $isSelect = preg_match('/^\s*SELECT\b/i', $sql);
    $hasForbiddenWords = preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|REPLACE|CREATE|RENAME|GRANT|UNION\s+ALL|UNION|LOAD_FILE|OUTFILE)\b/i', $sql);
    
    if (!$isSelect || $hasForbiddenWords) {
        echo json_encode(['status' => 'success', 'answer' => 'Pertanyaan Anda tidak dapat diproses demi alasan keamanan database.']);
        exit;
    }
    
    // 2. Execute SQL query
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Format results to concise natural language (Indonesian, no icons)
    $formatInstruction = "
    You are a helpful procurement database assistant.
    The user asked: \"$message\"
    The database returned the following rows:
    " . json_encode($rows) . "

    CRITICAL RULES:
    - Format the database results into a concise, brief, direct, and clear answer in Indonesian. Do NOT be long-winded.
    - Do NOT use any emojis, icons, or visual symbols (like checkmarks, warning signs, robot icons, bullet icons etc.) in your response. Output clean text only.
    - Do NOT make up any information. If the results are empty, reply that no data was found.
    - Keep the response direct to the point.
    - Whenever you list or mention specific entities in your response, you MUST format them as Markdown links using the data from the query results:
      * Purchase Order (PO): Use format `[PO_NUMBER](/newmega/modules/procurement/po/view.php?id=ID)`. Example: `[PO-2026-0001](/newmega/modules/procurement/po/view.php?id=12)`
      * Material Request (MR): Use format `[MR_NUMBER](/newmega/modules/procurement/mr/view.php?id=ID)`. Example: `[MR-2026-0002](/newmega/modules/procurement/mr/view.php?id=15)`
      * Project: Use format `[PROJECT_NAME](/newmega/modules/master/projects/dashboard.php?id=ID)`. Example: `[Proyek Rukan](/newmega/modules/master/projects/dashboard.php?id=5)`
    ";
    
    $finalAnswer = callGemini($formatInstruction, "Format this data into a short direct text answer.");
    
    echo json_encode(['status' => 'success', 'answer' => $finalAnswer]);
    exit;
    
} catch (Exception $e) {
    file_put_contents(__DIR__ . '/error.log', date('Y-m-d H:i:s') . ' [ERROR] ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n", FILE_APPEND);
    error_log('[AI_CHAT] ' . $e->getMessage());
    echo json_encode(['status' => 'success', 'answer' => 'Maaf, terjadi kendala saat memproses data proyek. Silakan coba tanyakan dengan cara lain.']);
    exit;
}
