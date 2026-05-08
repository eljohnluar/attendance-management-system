<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';
$res = safeQuery('SHOW COLUMNS FROM teachers');
while ($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " ";
}
echo "\n";
?>
