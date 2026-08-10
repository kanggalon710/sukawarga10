<?php echo implode(chr(10), array_filter(get_loaded_extensions(), fn($e) => str_contains(strtolower($e), 'mysql') || str_contains(strtolower($e), 'pdo')));
