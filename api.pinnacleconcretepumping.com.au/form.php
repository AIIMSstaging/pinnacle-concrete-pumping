<?php
// ============================================
// form.php - Mini contact form handler
// Connected to: <form id="miniForm"> on index.html
// Fields: name, phone, email, recaptcha_token
// ============================================

require __DIR__ . '/_bootstrap.php';

// ----- Collect input -----
$name  = clean_input($_POST['name']  ?? '');
$phone = clean_input($_POST['phone'] ?? '');
$email = clean_input($_POST['email'] ?? '');
$token = clean_input($_POST['recaptcha_token'] ?? '');

// ----- Honeypot (optional anti-bot, ignore if absent) -----
if (!empty($_POST['website'])) {
    respond(true, 'Thanks.'); // silently drop bots
}

// ----- Field validation -----
$errors = [];
if ($name === '' || mb_strlen($name) < 2) {
    $errors[] = 'Please enter your full name.';
}
if ($phone === '' || !valid_phone($phone)) {
    $errors[] = 'Please enter a valid phone number.';
}
if ($email === '' || !valid_email($email)) {
    $errors[] = 'Please enter a valid email address.';
}
if (mb_strlen($name) > 120 || mb_strlen($phone) > 40 || mb_strlen($email) > 160) {
    $errors[] = 'One or more fields exceed the allowed length.';
}

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

// ----- reCAPTCHA -----
$secret   = $ENV['RECAPTCHA_SECRET_KEY'] ?? '';
$minScore = isset($ENV['RECAPTCHA_MIN_SCORE']) ? (float)$ENV['RECAPTCHA_MIN_SCORE'] : 0.5;

if (!verify_recaptcha($token, $secret, $minScore)) {
    respond(false, 'reCAPTCHA verification failed. Please refresh and try again.', 403);
}

// ----- Build & send email -----
$to          = $ENV['MAIL_TO']             ?? 'info@pinnacleconcretepumping.com.au';
$cc          = $ENV['MAIL_CC']             ?? '';
$bcc         = $ENV['MAIL_BCC']            ?? '';
$fromEmail   = $ENV['MAIL_FROM']           ?? 'no-reply@pinnacleconcretepumping.com.au';
$fromName    = $ENV['MAIL_FROM_NAME']      ?? 'Pinnacle Concrete Pumping Website';
$subject     = $ENV['MAIL_SUBJECT_CONTACT']?? 'New Contact Enquiry - Pinnacle Concrete Pumping';

$html = build_html_email(
    'New Contact Enquiry',
    'A new enquiry just came in from the website mini-form. Get back to them as soon as possible.',
    [
        'Full Name' => $name,
        'Phone'     => $phone,
        'Email'     => $email,
        'Source'    => 'Mini contact form (Lock in your pour)',
    ]
);

$sent = send_html_mail($to, $subject, $html, $fromEmail, $fromName, $email, $name, $cc, $bcc);

if (!$sent) {
    respond(false, 'We could not send your message right now. Please call 1300 688 390.', 500);
}

respond(true, 'Thanks! We will be in touch shortly.');
