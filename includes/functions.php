<?php
/**
 * Global Helper Functions
 */

/**
 * Format number to Indonesian Rupiah
 */
function formatRupiah($number, $prefix = 'Rp ') {
    return $prefix . number_format($number, 0, ',', '.');
}

/**
 * Parse Rupiah string back to integer/float
 */
function parseRupiah($string) {
    if ($string === null || $string === '') return 0;
    // Jika sudah berupa int/float PHP murni, kembalikan langsung
    if (is_int($string) || is_float($string)) return $string;
    // Hapus semua karakter non-angka (titik ribuan, "Rp", spasi, dll)
    $parsed = preg_replace('/[^0-9]/', '', (string)$string);
    return $parsed === '' ? 0 : (float)$parsed;
}

/**
 * Parse quantity string back to float, supporting decimals with dot or comma.
 */
function parseQty($string) {
    if ($string === null || $string === '') return 0;
    if (is_int($string) || is_float($string)) return $string;
    $clean = str_replace(',', '.', (string)$string);
    // Hapus semua karakter kecuali angka, titik desimal, dan tanda minus
    $clean = preg_replace('/[^0-9.-]/', '', $clean);
    return $clean === '' ? 0 : (float)$clean;
}

/**
 * Format date to Indonesian format
 */
function formatDate($date, $format = 'd-M-Y') {
    if (empty($date)) return '-';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime) {
    if (empty($datetime)) return '-';
    return date('d-M-Y H:i', strtotime($datetime));
}

/**
 * Generate auto abbreviation from company name (3 chars)
 */
function generateAbbreviation($companyName, $pdo = null, $table = null, $column = 'abbreviation') {
    // Remove common prefixes
    $name = preg_replace('/^(PT\.?\s*|CV\.?\s*|UD\.?\s*|TB\.?\s*)/i', '', trim($companyName));
    $name = trim($name);
    
    // Split into words
    $words = preg_split('/\s+/', $name);
    $words = array_filter($words, function($w) { return strlen($w) > 0; });
    $words = array_values($words);
    
    $count = count($words);
    $abbr = 'XXX';
    
    if ($count === 0) {
        $abbr = 'XXX';
    } elseif ($count === 1) {
        // Single word: take first 3 characters
        $abbr = strtoupper(substr($words[0], 0, 3));
        $abbr = str_pad($abbr, 3, 'X');
    } elseif ($count === 2) {
        // Two words: first letter of 1st + first two letters of 2nd
        $first = strtoupper(substr($words[0], 0, 1));
        $second = strtoupper(substr($words[1], 0, 2));
        $abbr = str_pad($first . $second, 3, 'X');
    } else {
        // 3+ words: first letter of first 3 words
        $abbr = strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1) . substr($words[2], 0, 1));
    }
    
    // Check duplicates if PDO and table are provided
    if ($pdo && $table) {
        $originalAbbr = $abbr;
        $counter = 1;
        while (true) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
            $stmt->execute([$abbr]);
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                break;
            }
            // Duplicate handling strategy: append number or change last letter
            $abbr = substr($originalAbbr, 0, 2) . $counter;
            $counter++;
            if ($counter > 9) {
                $abbr = substr($originalAbbr, 0, 1) . str_pad($counter, 2, '0', STR_PAD_LEFT);
            }
        }
    }
    
    return $abbr;
}

/**
 * Generate document number
 * @param PDO $pdo
 * @param string $type - 'MR', 'PO', 'Q', 'INV', 'TRF'
 * @param string $abbreviation - project/vendor/customer abbreviation
 * @return string
 */
function generateDocNumber($pdo, $type, $abbreviation = '') {
    $year = date('y'); // 2-digit year
    
    // Determine table and column based on type
    $tableMap = [
        'MR'  => ['table' => 'material_requests', 'column' => 'mr_number'],
        'PO'  => ['table' => 'purchase_orders', 'column' => 'po_number'],
        'Q'   => ['table' => 'quotations', 'column' => 'quotation_no'],
        'INV' => ['table' => 'invoices', 'column' => 'invoice_no'],
        'TRF' => ['table' => 'warehouse_transfers', 'column' => 'transfer_number'],
        'CLM' => ['table' => 'nota_claims', 'column' => 'claim_number'],
    ];
    
    if (!isset($tableMap[$type])) {
        return $type . '-' . $abbreviation . '-' . $year . '-0001';
    }
    
    $table = $tableMap[$type]['table'];
    $column = $tableMap[$type]['column'];
    
    // Pattern to find records for the same type and year: [TYPE]-%-[YY]-%
    $searchPattern = $type . '-%-' . $year . '-%';
    
    $stmt = $pdo->prepare("SELECT {$column} FROM {$table} WHERE {$column} LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$searchPattern]);
    $last = $stmt->fetchColumn();
    
    $nextNum = 1;
    if ($last) {
        $parts = explode('-', $last);
        $lastPart = end($parts);
        if (is_numeric($lastPart)) {
            $nextNum = (int)$lastPart + 1;
        }
    }
    
    // Use 'GEN' if abbreviation is empty
    $abbr = !empty($abbreviation) ? strtoupper($abbreviation) : 'GEN';
    
    return $type . '-' . $abbr . '-' . $year . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Flash message helpers
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    $badges = [
        'draft'               => '<span class="badge badge-secondary">Draf</span>',
        'pending'             => '<span class="badge badge-warning">Menunggu Persetujuan</span>',
        'approved'            => '<span class="badge badge-success">Disetujui</span>',
        'rejected'            => '<span class="badge badge-danger">Ditolak</span>',
        'completed'           => '<span class="badge badge-info">Selesai</span>',
        'cancelled'           => '<span class="badge badge-dark">Dibatalkan</span>',
        'partially_received'  => '<span class="badge badge-primary">Diterima Sebagian</span>',
        'invoiced'            => '<span class="badge badge-info">Sudah Ditagih</span>',
        'sent'                => '<span class="badge badge-primary">Terkirim</span>',
        'paid'                => '<span class="badge badge-success">Lunas</span>',
        'partial_paid'        => '<span class="badge badge-warning">Bayar Sebagian</span>',
        'planning'            => '<span class="badge badge-secondary">Perencanaan</span>',
        'active'              => '<span class="badge badge-success">Aktif</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge badge-secondary">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

/**
 * Get role display name
 */
function getRoleName($role) {
    static $roleCache = null;
    if ($roleCache === null) {
        global $pdo;
        try {
            $stmt = $pdo->query("SELECT role_key, role_name FROM roles");
            $roleCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (Exception $e) {
            $roleCache = [
                'super_admin'     => 'Super Admin',
                'finance'         => 'Finance',
                'gudang'          => 'Gudang',
                'project_manager' => 'Project Manager',
            ];
        }
    }
    return $roleCache[$role] ?? $role;
}

/**
 * Get multiple role names for display (comma separated)
 */
function getUserRolesDisplay($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT r.role_name FROM roles r JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = ?");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
    return !empty($roles) ? implode(', ', $roles) : '-';
}

/**
 * Get current logged in user data
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Check if user has specific role
 */
function hasRole($roles) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $userRoles = $user['roles'] ?? (isset($user['role']) ? [$user['role']] : []);
    if (is_string($roles)) $roles = [$roles];
    
    return !empty(array_intersect($userRoles, $roles));
}

/**
 * Compress and optionally resize image using GD library
 */
function compressImage($filepath, $quality = 75, $maxWidth = null, $maxHeight = null) {
    if (!extension_loaded('gd') || !function_exists('imagecreatefromstring')) {
        return false;
    }

    $info = @getimagesize($filepath);
    if ($info === false) {
        return false;
    }
    
    $mime = $info['mime'];
    $width = $info[0];
    $height = $info[1];
    
    switch ($mime) {
        case 'image/jpeg':
            $image = @imagecreatefromjpeg($filepath);
            break;
        case 'image/png':
            $image = @imagecreatefrompng($filepath);
            break;
        case 'image/gif':
            $image = @imagecreatefromgif($filepath);
            break;
        default:
            return false;
    }
    
    if (!$image) {
        return false;
    }
    
    $newWidth = $width;
    $newHeight = $height;
    
    if ($maxWidth && $width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = (int)round($height * ($maxWidth / $width));
    }
    
    if ($maxHeight && $newHeight > $maxHeight) {
        $newHeight = $maxHeight;
        $newWidth = (int)round($width * ($maxHeight / $height));
    }
    
    if ($newWidth != $width || $newHeight != $height) {
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$newImage) {
            imagedestroy($image);
            return false;
        }
        
        if ($mime == 'image/png' || $mime == 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        }
        
        if (!imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
            imagedestroy($image);
            imagedestroy($newImage);
            return false;
        }
        imagedestroy($image);
        $image = $newImage;
    }
    
    $success = false;
    switch ($mime) {
        case 'image/jpeg':
            $success = imagejpeg($image, $filepath, $quality);
            break;
        case 'image/png':
            $pngQuality = (int)max(0, min(9, 9 - round($quality / 10)));
            $success = imagepng($image, $filepath, $pngQuality);
            break;
        case 'image/gif':
            $success = imagegif($image, $filepath);
            break;
    }
    
    imagedestroy($image);
    return $success;
}

/**
 * Upload file helper
 */
function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'csv', 'pdf', 'xls', 'xlsx', 'doc', 'docx'], $maxSize = 5242880, $compressOptions = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Gagal mengunggah file.'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipe file tidak diizinkan. Format yang diperbolehkan: ' . implode(', ', $allowedTypes)];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Ukuran file terlalu besar (maks ' . ($maxSize / 1048576) . 'MB)'];
    }
    
    // MIME type checking
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'], // CSV can be tricky
        'pdf' => 'application/pdf',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];
    
    $isValidMime = false;
    if (isset($allowedMimes[$ext])) {
        $expectedMime = $allowedMimes[$ext];
        if (is_array($expectedMime)) {
            $isValidMime = in_array($mimeType, $expectedMime);
        } else {
            $isValidMime = ($mimeType === $expectedMime);
        }
    }
    
    if (!$isValidMime) {
        return ['success' => false, 'message' => 'Tipe MIME file tidak sesuai dengan ekstensinya.'];
    }
    
    // Create directory if not exists
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    $filename = uniqid() . '_' . time() . '.' . $ext;
    $filepath = $destination . '/' . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Compress image if options are provided and it is a supported image extension
        if ($compressOptions && in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
            $quality = $compressOptions['quality'] ?? 75;
            $maxWidth = $compressOptions['maxWidth'] ?? null;
            $maxHeight = $compressOptions['maxHeight'] ?? null;
            compressImage($filepath, $quality, $maxWidth, $maxHeight);
        }
        return ['success' => true, 'filename' => $filename];
    }
    
    return ['success' => false, 'message' => 'Gagal menyimpan file'];
}

/**
 * Get user's profile photo URL or default avatar
 */
function getProfilePhoto($photo) {
    if ($photo && file_exists(PROFILES_PATH . '/' . $photo)) {
        return APP_URL . '/assets/uploads/profiles/' . $photo;
    }
    return APP_URL . '/assets/img/default-avatar.png';
}

/**
 * Get company logo URL
 */
function getCompanyLogo($logo) {
    if ($logo && file_exists(LOGOS_PATH . '/' . $logo)) {
        return APP_URL . '/assets/uploads/company_logos/' . $logo;
    }
    return null;
}

/**
 * CSRF Protection Helpers
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Log user activity to the database
 *
 * @param string $action  Action type: 'login', 'logout', 'create', 'update', 'delete', 'approve', 'reject', 'truncate', etc.
 * @param string $module  Module name: 'material_request', 'purchase_order', 'users', etc.
 * @param string $description  Human-readable description
 * @param string|null $referenceType  Table name being referenced
 * @param int|null $referenceId  ID of the referenced record
 * @param array|null $oldData  Data before change (optional)
 * @param array|null $newData  Data after change (optional)
 */
function logActivity($action, $module = null, $description = null, $referenceType = null, $referenceId = null, $oldData = null, $newData = null) {
    global $pdo;
    try {
        $user = getCurrentUser();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, user_name, action, module, description, reference_type, reference_id, ip_address, user_agent, old_data, new_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user['id'] ?? null,
            $user['full_name'] ?? ($user['username'] ?? 'System'),
            $action,
            $module,
            $description,
            $referenceType,
            $referenceId,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (Exception $e) {
        // Silently fail - logging should never break the main operation
        error_log("Activity log error: " . $e->getMessage());
    }
}

