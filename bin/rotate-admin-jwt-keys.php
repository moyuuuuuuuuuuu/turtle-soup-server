<?php

declare(strict_types=1);

// Run only against the deployment's environment file; never print key values.
$path = $argv[1] ?? '';
if ($path === '' || !is_file($path) || !is_writable($path)) {
    fwrite(STDERR, "Usage: php bin/rotate-admin-jwt-keys.php /path/to/existing/.env\n");
    exit(1);
}
$content = file_get_contents($path);
if ($content === false) {
    throw new RuntimeException('Cannot read environment file');
}
foreach (['ADMIN_JWT_ACCESS_SECRET', 'ADMIN_JWT_REFRESH_SECRET'] as $name) {
    $line = $name . '=' . bin2hex(random_bytes(32));
    $pattern = '/^' . $name . '=.*$/m';
    $content = preg_match($pattern, $content)
        ? preg_replace($pattern, $line, $content)
        : rtrim($content) . PHP_EOL . $line . PHP_EOL;
}
if (file_put_contents($path, $content, LOCK_EX) === false) {
    throw new RuntimeException('Cannot write environment file');
}
fwrite(STDOUT, "Administrator signing keys rotated. Restart all backend workers to invalidate previous tokens.\n");
