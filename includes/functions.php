<?php
/**
 * General-purpose helper functions used across the app.
 */

function clean(string $value): string
{
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function base_path(): string
{
    $scriptBase = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if ($scriptBase === '/' || $scriptBase === '\\' || $scriptBase === '.') {
        return '';
    }

    return rtrim($scriptBase, '/');
}

function redirect(string $path): void
{
    if (preg_match('#^https?://#i', $path)) {
        header('Location: ' . $path);
        exit;
    }

    $base = base_path();
    $target = str_starts_with($path, '/') ? $path : '/' . $path;

    if ($base !== '') {
        $target = $base . $target;
    }

    header('Location: ' . $target);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

// ---- CSRF protection --------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

// ---- Doctor credential generation -------------------------------------

/**
 * Generates a unique doctor login ID like DOC-1001.
 */
function generate_doctor_login_id(PDO $db): string
{
    do {
        $candidate = 'DOC-' . random_int(1000, 9999);
        $stmt = $db->prepare('SELECT 1 FROM users WHERE doctor_login_id = ?');
        $stmt->execute([$candidate]);
    } while ($stmt->fetch());

    return $candidate;
}

/**
 * Generates a random temporary password to email to a newly verified doctor.
 */
function generate_temp_password(int $length = 10): string
{
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// ---- File upload validation --------------------------------------------

/**
 * Validates and stores an uploaded doctor image.
 * Returns the relative path to store in DB, or throws Exception on failure.
 */
function handle_doctor_image_upload(array $file): string
{
    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxBytes = 2 * 1024 * 1024; // 2MB

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Image upload failed.');
    }
    if ($file['size'] > $maxBytes) {
        throw new Exception('Image must be smaller than 2MB.');
    }

    // Check the real mime type server-side; never trust the client extension.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$mime])) {
        throw new Exception('Only JPG, PNG, or WEBP images are allowed.');
    }

    $ext = $allowedTypes[$mime];
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $destDir = __DIR__ . '/../assets/uploads/doctors/';
    $destPath = $destDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Could not save uploaded image.');
    }

    // Path stored in DB, relative to web root, used in <img src="...">
    return 'assets/uploads/doctors/' . $filename;
}
