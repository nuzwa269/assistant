<?php
require __DIR__ . '/bootstrap.php';
list($script, $operation, $user_id, $reference) = $argv;
if ('deduct' === $operation) {
    $result = CoachPro_Credits::deduct((int)$user_id, 1, $reference, 'gpt-4o-mini');
} else {
    wp_set_current_user((int)$user_id);
    $result = CoachPro_Payments::review($reference, 'approved');
}
echo is_wp_error($result) ? 'rejected' : 'accepted';
