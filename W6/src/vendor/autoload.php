<?php
// Load the project's vendor autoload (when running inside the container
// the vendor directory lives at /var/www/html/vendor). The original
// absolute path was created by the builder stage and doesn't exist at
// runtime, so use a path relative to this file.
return require __DIR__ . '/../../vendor/autoload.php';