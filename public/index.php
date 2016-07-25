<?php
$data = [
    'code' => -1,
    'msg' => 'I really have no idea how did you get there! Probably you set up api wrong way. Check out your mod_rewrite or something else.. idk.'
];
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
?>
