<?php
// Email Configuration
define('SMTP_HOST', 'mail.nitel.mw');
define('SMTP_USERNAME', 'malpaynpms@nitel.mw');
define('SMTP_PASSWORD', 'Ch1t3t3z0');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_SENDER_EMAIL', 'malpaynpms@nitel.mw');
define('SMTP_SENDER_NAME', 'MalPay/NPMS Alert System');

// Alert Thresholds
define('FAILED_TRANSACTIONS_THRESHOLD', 5);
define('ALERT_RECIPIENTS', [
    'bmikwala@nitel.mw',
    'bmikwala@nitel.mw'
]);
?>