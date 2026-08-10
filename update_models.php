<?php
$files = glob(__DIR__ . '/app/Models/*.php');
foreach ($files as $file) {
    if (basename($file) === 'User.php') continue;
    $content = file_get_contents($file);
    
    // Add guarded if not present
    if (!str_contains($content, 'protected $guarded')) {
        $content = preg_replace('/\{/', "{\n    protected \$guarded = [];\n", $content, 1);
    }
    
    // Add casts for specific models
    if (basename($file) === 'Keluarga.php' && !str_contains($content, 'protected $casts')) {
        $content = str_replace("protected \$guarded = [];\n", "protected \$guarded = [];\n    protected \$casts = ['tags' => 'array'];\n", $content);
    }
    if (basename($file) === 'IuranSampah.php' && !str_contains($content, 'protected $casts')) {
        $content = str_replace("protected \$guarded = [];\n", "protected \$guarded = [];\n    protected \$casts = ['weeks' => 'array', 'weekDates' => 'array'];\n", $content);
    }
    if (basename($file) === 'IuranPadaringan.php' && !str_contains($content, 'protected $casts')) {
        $content = str_replace("protected \$guarded = [];\n", "protected \$guarded = [];\n    protected \$casts = ['months' => 'array', 'monthDates' => 'array'];\n", $content);
    }
    if (basename($file) === 'Role.php' && !str_contains($content, 'protected $casts')) {
        $content = str_replace("protected \$guarded = [];\n", "protected \$guarded = [];\n    protected \$casts = ['permissions' => 'array'];\n", $content);
    }
    
    file_put_contents($file, $content);
}
echo "Models updated successfully.";
