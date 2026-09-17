<?php
require_once 'config.php';
/** @var mysqli $conn */

// Migrate Users
$users = $conn->query("SELECT id, allowed_device_id FROM users WHERE allowed_device_id IS NOT NULL AND allowed_device_id != '' AND allowed_device_id != 'REVOKED'");
if ($users) {
    while ($u = $users->fetch_assoc()) {
        $u_id = $u['id'];
        $dev_id = $conn->real_escape_string($u['allowed_device_id']);
        
        $check = $conn->query("SELECT id FROM device_requests WHERE account_type = 'user' AND account_id = $u_id AND requested_device_id = '$dev_id' AND status = 'approved'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status) VALUES ('user', $u_id, '$dev_id', 'Migrated', 'Migrated', 'approved')");
        }
    }
}

// Migrate Admins
$admins = $conn->query("SELECT id, allowed_device_id FROM admins WHERE allowed_device_id IS NOT NULL AND allowed_device_id != '' AND allowed_device_id != 'REVOKED'");
if ($admins) {
    while ($a = $admins->fetch_assoc()) {
        $a_id = $a['id'];
        $dev_id = $conn->real_escape_string($a['allowed_device_id']);
        
        $check = $conn->query("SELECT id FROM device_requests WHERE account_type = 'admin' AND account_id = $a_id AND requested_device_id = '$dev_id' AND status = 'approved'");
        if ($check->num_rows == 0) {
            $conn->query("INSERT INTO device_requests (account_type, account_id, requested_device_id, ip_address, user_agent, status) VALUES ('admin', $a_id, '$dev_id', 'Migrated', 'Migrated', 'approved')");
        }
    }
}

echo "Migration completed.";
?>
