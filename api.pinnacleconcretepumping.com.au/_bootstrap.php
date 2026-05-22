<?php
// ============================================
// Pinnacle Concrete Pumping - Shared form helpers
// ============================================

// ----- Load .env -----
function load_env($path) {
    if (!is_readable($path)) {
        return [];
    }
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        // Strip surrounding quotes
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }
        $env[$key] = $value;
    }
    return $env;
}

$ENV = load_env(__DIR__ . '/.env');

// ----- CORS -----
$allowedOrigin = isset($ENV['ALLOWED_ORIGIN']) ? $ENV['ALLOWED_ORIGIN'] : '*';
header('Access-Control-Allow-Origin: ' . $allowedOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Reject non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ----- Helpers -----
function respond($success, $message, $code = 200, $extra = []) {
    http_response_code($code);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function clean_input($v) {
    if (!is_string($v)) return '';
    $v = trim($v);
    $v = stripslashes($v);
    return $v;
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone($phone) {
    return preg_match('/^[\d\s\+\-\(\)]{6,}$/', $phone) === 1;
}

function safe_html($v) {
    return htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ----- reCAPTCHA verification -----
function verify_recaptcha($token, $secret, $minScore = 0.5) {
    if (empty($token) || empty($secret)) return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    curl_close($ch);

    if (!$body) return false;
    $data = json_decode($body, true);
    if (!is_array($data)) return false;
    if (empty($data['success'])) return false;

    if (isset($data['score']) && $data['score'] < $minScore) return false;

    return true;
}

// ----- HTML mail builder -----
function build_html_email($title, $intro, $rows) {
    $rowsHtml = '';
    foreach ($rows as $label => $value) {
        if ($value === '' || $value === null) continue;
        $rowsHtml .= '<tr>'
            . '<td style="padding:10px 14px;background:#f7f7f9;border:1px solid #e5e7eb;font-weight:600;color:#1a1a1a;width:35%;vertical-align:top;">' . safe_html($label) . '</td>'
            . '<td style="padding:10px 14px;background:#ffffff;border:1px solid #e5e7eb;color:#333;vertical-align:top;">' . nl2br(safe_html($value)) . '</td>'
            . '</tr>';
    }

    $year = date('Y');

    return '<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f1f3f5;font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f3f5;padding:30px 12px;">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 14px rgba(0,0,0,0.06);">
        <tr>
          <td style="background:#1a1a1a;padding:24px 30px;color:#ffffff;">
            <div style="font-size:12px;letter-spacing:1px;text-transform:uppercase;color:#E91E8C;font-weight:700;">Pinnacle Concrete Pumping</div>
            <h1 style="margin:6px 0 0;font-size:22px;font-weight:800;color:#ffffff;">' . safe_html($title) . '</h1>
          </td>
        </tr>
        <tr>
          <td style="padding:26px 30px 8px;">
            <p style="margin:0 0 18px;font-size:15px;line-height:1.5;color:#333;">' . safe_html($intro) . '</p>
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
              ' . $rowsHtml . '
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 30px 26px;">
            <p style="margin:0;font-size:12px;color:#888;">Submitted: ' . safe_html(date('d M Y, g:i a')) . ' &middot; IP: ' . safe_html($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '</p>
          </td>
        </tr>
        <tr>
          <td style="background:#f7f7f9;padding:14px 30px;text-align:center;font-size:11px;color:#888;border-top:1px solid #e5e7eb;">
            &copy; ' . $year . ' Pinnacle Concrete Pumping Group &middot; Automated message from website form
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body></html>';
}

// Normalise a CC/BCC value (string or comma-separated list) into a clean,
// validated, comma-separated string. Returns '' if nothing valid.
function normalise_address_list($value) {
    if (empty($value)) return '';
    $parts = preg_split('/[,;]+/', $value);
    $clean = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p !== '' && valid_email($p)) {
            $clean[] = $p;
        }
    }
    return implode(', ', $clean);
}

function send_html_mail($to, $subject, $html, $fromEmail, $fromName, $replyToEmail = null, $replyToName = null, $cc = '', $bcc = '') {
    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromEmail);

    $cc  = normalise_address_list($cc);
    $bcc = normalise_address_list($bcc);
    if ($cc  !== '') $headers[] = 'Cc: '  . $cc;
    if ($bcc !== '') $headers[] = 'Bcc: ' . $bcc;

    if ($replyToEmail) {
        $headers[] = 'Reply-To: ' . sprintf('"%s" <%s>', addslashes($replyToName ?: $replyToEmail), $replyToEmail);
    }
    $headers[] = 'X-Mailer: PHP/' . phpversion();

    $headerStr = implode("\r\n", $headers);

    return @mail($to, $subject, $html, $headerStr);
}
