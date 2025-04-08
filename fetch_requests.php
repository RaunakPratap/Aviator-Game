<?php
session_start();
$users_folder = "users/";
$users = array_filter(glob($users_folder . '*'), 'is_dir');

$output = [];

foreach ($users as $userPath) {
    $mobile = basename($userPath);
    $deposits = json_decode(@file_get_contents("$userPath/deposits.json"), true) ?? [];
    $withdrawals = json_decode(@file_get_contents("$userPath/withdrawals.json"), true) ?? [];

    $pending_deposits = [];
    foreach ($deposits as $i => $d) {
        if (!isset($d['status']) || $d['status'] === 'pending') {
            $d['index'] = $i;
            $pending_deposits[] = $d;
        }
    }

    $pending_withdrawals = [];
    foreach ($withdrawals as $i => $w) {
        if (!isset($w['status']) || $w['status'] === 'pending') {
            $w['index'] = $i;
            $pending_withdrawals[] = $w;
        }
    }

    if ($pending_deposits || $pending_withdrawals) {
        $output[] = [
            'mobile' => $mobile,
            'deposits' => $pending_deposits,
            'withdrawals' => $pending_withdrawals
        ];
    }
}

header('Content-Type: application/json');
echo json_encode($output);
?>
