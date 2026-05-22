<?php
// ============================================
// form-quote.php - Main quote form handler
// Connected to: <form id="quoteForm"> on index.html
// Fields: name, phone, email, location, pump, service, details, recaptcha_token
// ============================================

require __DIR__ . '/_bootstrap.php';

// ----- Collect input -----
$name     = clean_input($_POST['name']     ?? '');
$phone    = clean_input($_POST['phone']    ?? '');
$email    = clean_input($_POST['email']    ?? '');
$location = clean_input($_POST['location'] ?? '');
$pump     = clean_input($_POST['pump']     ?? '');
$service  = clean_input($_POST['service']  ?? '');
$details  = clean_input($_POST['details']  ?? '');
$token    = clean_input($_POST['recaptcha_token'] ?? '');

// ----- Honeypot -----
if (!empty($_POST['website'])) {
    respond(true, 'Thanks.');
}

// ----- Allowed select values -----
$allowedPump    = ['Line Pump', 'Boom Pump', 'Not Sure - Help Me Choose'];
$allowedService = ['Residential', 'Commercial', 'Industrial', 'Civil / Infrastructure'];

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
if ($location === '' || mb_strlen($location) < 2) {
    $errors[] = 'Please enter your job location / suburb.';
}
if ($pump === '' || !in_array($pump, $allowedPump, true)) {
    $errors[] = 'Please select a valid pump type.';
}
if ($service === '' || !in_array($service, $allowedService, true)) {
    $errors[] = 'Please select a valid service type.';
}
if (mb_strlen($name) > 120 || mb_strlen($phone) > 40 || mb_strlen($email) > 160
    || mb_strlen($location) > 160 || mb_strlen($details) > 2000) {
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
$to        = $ENV['MAIL_TO']            ?? 'info@pinnacleconcretepumping.com.au';
$cc        = $ENV['MAIL_CC']            ?? '';
$bcc       = $ENV['MAIL_BCC']           ?? '';
$fromEmail = $ENV['MAIL_FROM']          ?? 'no-reply@pinnacleconcretepumping.com.au';
$fromName  = $ENV['MAIL_FROM_NAME']     ?? 'Pinnacle Concrete Pumping Website';
$subject   = $ENV['MAIL_SUBJECT_QUOTE'] ?? 'New Quote Request - Pinnacle Concrete Pumping';

$html = build_html_email(
    'New Quote Request',
    'A new quote request was just submitted via the website. Reach out to the customer ASAP to lock in the booking.',
    [
        'Full Name'        => $name,
        'Phone'            => $phone,
        'Email'            => $email,
        'Job Location'     => $location,
        'Pump Type'        => $pump,
        'Service Type'     => $service,
        'Project Details'  => $details !== '' ? $details : '(none provided)',
        'Source'           => 'Main quote form (#quote)',
    ]
);

$sent = send_html_mail($to, $subject, $html, $fromEmail, $fromName, $email, $name, $cc, $bcc);

if (!$sent) {
    respond(false, 'We could not send your message right now. Please call 1300 688 390.', 500);
}

respond(true, 'Thanks! Your quote request has been received.');
