<?php
header('Content-Type: application/json; charset=UTF-8');
echo json_encode(['ok' => true, 'ts' => date('c')], JSON_UNESCAPED_UNICODE);
