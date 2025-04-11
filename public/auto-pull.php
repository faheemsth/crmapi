<?php
// Configuration
$secret = 'o/fZHG5LL4P59lptCcsdQWv05xoQ=='; // Removed backslash
$project_path = '/home/convosoftserver/public_html/backend.convosoftserver.com';
$branch = 'main';

// 1. Verify IP (GitHub's webhook IP range)
$github_ips = ['192.30.252.0/22', '185.199.108.0/22', '140.82.112.0/20'];
if (!in_array($_SERVER['REMOTE_ADDR'], $github_ips, true) {
    file_put_contents('deploy.log', date('[Y-m-d H:i:s]') . " Invalid IP: " . $_SERVER['REMOTE_ADDR'] . "\n", FILE_APPEND);
    die('IP not allowed');
}

// 2. Verify secret
$headers = getallheaders();
$hubSignature = $headers['X-Hub-Signature-256'] ?? ''; // Updated to SHA-256
$payload = file_get_contents('php://input');

if (empty($hubSignature)) {
    die('No signature');
}

list($algo, $hash) = explode('=', $hubSignature, 2);
$payloadHash = hash_hmac($algo, $payload, $secret);

if (!hash_equals($hash, $payloadHash)) {
    file_put_contents('deploy.log', date('[Y-m-d H:i:s]') . " Invalid secret\n", FILE_APPEND);
    die('Invalid secret');
}

// 3. Execute Git pull
$output = [];
$commands = [
    "cd $project_path",
    "git fetch origin",
    "git reset --hard origin/$branch",
    "git pull origin $branch"
];

$full_command = implode(' && ', $commands);
exec($full_command . ' 2>&1', $output, $return_var);

// 4. Log results
$log_entry = date('[Y-m-d H:i:s]') . " - Return: $return_var\n" . implode("\n", $output) . "\n\n";
file_put_contents('deploy.log', $log_entry, FILE_APPEND);



echo "Deployment completed successfully";
?>
