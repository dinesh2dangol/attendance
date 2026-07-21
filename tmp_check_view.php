<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make('Illuminate\\Contracts\\Console\\Kernel');
$kernel->bootstrap();
$db = Illuminate\\Support\\Facades\\DB::getPdo();
try {
    $view = $db->query('SHOW CREATE VIEW wiseyak_everyday_v2')->fetchAll();
    var_export($view);
} catch (PDOException $e) {
    echo "SHOW CREATE VIEW ERROR: " . $e->getMessage() . "\n";
}
try {
    $cols = $db->query("SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME, CHARACTER_SET_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('master_daily_attendance','wiseyak_everyday_v2')")->fetchAll();
    var_export($cols);
} catch (PDOException $e) {
    echo "COLUMNS ERROR: " . $e->getMessage() . "\n";
}
