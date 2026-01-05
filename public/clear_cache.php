<?php
// Clear OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache cleared successfully\n";
} else {
    echo "✗ OPcache is not enabled\n";
}

// Clear APCu if available
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    echo "✓ APCu cache cleared\n";
}

echo "\nCache clear completed. Try accessing the endpoint now.\n";
