<?php
defined('ESY') or exit;
// Media picker data for the image fields.
header('Content-Type: application/json');
echo json_encode(q_all('SELECT id, path, name FROM media ORDER BY id DESC LIMIT 200'));
