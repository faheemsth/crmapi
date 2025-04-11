<?php
// Verify the secret (same as in GitHub webhook)
$secret = 'o/fZHG5LL4P59lptCcsdQW\v05xoQ==';

// Get headers
$headers = getallheaders();
$hubSignature = $headers['X-Hub-Signature'] ?? '';

// Split signature into algorithm and hash
list($algo, $hash) = explode('=', $hubSignature, 2);

// Get payload
$payload = file_get_contents('php://input');

// Calculate hash
$payloadHash = hash_hmac($algo, $payload, $secret);

// Verify hash
if ($hash !== $payloadHash) {
    die('Invalid secret');
}

// Execute git commands
$output = shell_exec('cd /home/convosoftserver/public_html/backend.convosoftserver.com && git pull origin main 2>&1');

// Log the output
file_put_contents('deploy.log', date('Y-m-d H:i:s') . " - " . $output . "\n", FILE_APPEND);

echo "Deployment successful";
?>
