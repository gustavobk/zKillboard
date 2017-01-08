#!/usr/bin/php5
<?php

use cvweiss\redistools\RedisTtlCounter;

require_once '../init.php';

$redisQueues = [];
$priorKillLog = 0;

$deltaArray = [];

$iterations = 0;
while ($iterations++ <= 1200) {
    ob_start();
    $infoArray = [];

    $isHardened = $redis->ttl('zkb:isHardened');
    if ($isHardened > 0) {
        addInfo('seconds remaining in Cached/Hardened Mode', $isHardened);
        addInfo('', 0);
    }

    $queues = $redis->sMembers('queues');
    foreach ($queues as $queue) {
        $redisQueues[$queue] = true;
    }
    ksort($redisQueues);

    foreach ($redisQueues as $queue => $v) {
        addInfo($queue, $redis->lLen($queue));
    }

    addInfo('', 0);

    addInfo('Kills remaining to be fetched.', $mdb->count('crestmails', ['processed' => false]));
    $killsLastHour = new RedisTtlCounter('killsLastHour', 3600);
    addInfo('Kills last hour', $killsLastHour->count());
    addInfo('Total Kills', $redis->get('zkb:totalKills'));
    addInfo('Top killID', $mdb->findField('killmails', 'killID', [], ['killID' => -1]));

    addInfo('', 0);
    $cached = new RedisTtlCounter('ttlc:cached', 300);
    addInfo('Cached requests in last 5 minutes', $cached->count());
    $nonApiR = new RedisTtlCounter('ttlc:nonApiRequests', 300);
    addInfo('User requests in last 5 minutes', $nonApiR->count());
    $apiR = new RedisTtlCounter('ttlc:apiRequests', 300);
    addInfo('API requests in last 5 minutes', $apiR->count());
    $visitors = new RedisTtlCounter('ttlc:visitors', 300);
    addInfo('Unique IPs in last 5 minutes', $visitors->count());
    $requests = new RedisTtlCounter('ttlc:requests', 300);
    addInfo('Requests in last 5 minutes', $requests->count());

    $crestSuccess = new RedisTtlCounter('ttlc:CrestSuccess', 300);
    addInfo('Successful CREST calls in last 5 minutes', $crestSuccess->count(), false);
    $crestFailure = new RedisTtlCounter('ttlc:CrestFailure', 300);
    addInfo('Failed CREST calls in last 5 minutes', $crestFailure->count(), false);

    addInfo('', 0, false);
    addInfo('Api KeyInfos to check', $redis->zCount('zkb:apis', 0, time()), false);
    addInfo('Char KillLogs to check', $redis->zCount('zkb:chars', 0, time()), false);
    addInfo('Corp KillLogs to check', $redis->zCount('zkb:corps', 0, time()), false);
    addInfo('Char Apis', $redis->zCard('zkb:chars'), false);
    addInfo('Corp Apis', $redis->zCard('zkb:corps'), false);
    $xmlSuccess = new RedisTtlCounter('ttlc:XmlSuccess', 300);
    addInfo('Successful XML calls in last 5 minutes', $xmlSuccess->count(), false);
    $xmlFailure = new RedisTtlCounter('ttlc:XmlFailure', 300);
    addInfo('Failed XML calls in last 5 minutes', $xmlFailure->count(), false);

    addInfo('', 0, false);
    addInfo('SSO KillLogs to check', $redis->zCount('tqApiSSO', 0, time()), false);
    addInfo('Distinct Character XML/SSO RefreshTokens', $redis->zCard('tqApiSSO'), false);
    $authSuccess = new RedisTtlCounter('ttlc:AuthSuccess', 300);
    addInfo('Successful Auth calls in last 5 minutes', $authSuccess->count(), false);
    $authFailure = new RedisTtlCounter('ttlc:AuthFailure', 300);
    addInfo('Failed Auth calls in last 5 minutes', $authFailure->count(), false);

    addInfo('', 0, false);
    addInfo('ESI KillLogs to check', $redis->zCount('tqApiESI', 0, time()), false);
    addInfo('Distinct Character ESI/SSO RefreshTokens', $redis->zCard('tqApiESI'), false);
    $esiSuccess = new RedisTtlCounter('ttlc:esiSuccess', 300);
    addInfo('Successful ESI calls in last 5 minutes', $esiSuccess->count(), false);
    $esiFailure = new RedisTtlCounter('ttlc:esiFailure', 300);
    addInfo('Failed ESI calls in last 5 minutes', $esiFailure->count(), false);

    $info = $redis->info();
    $mem = $info['used_memory_human'];

    $stats = $mdb->getDb()->command(['dbstats' => 1]);
    $dataSize = number_format(($stats['dataSize'] + $stats['indexSize']) / (1024 * 1024 * 1024), 2);
    $storageSize = number_format(($stats['storageSize'] + $stats['indexStorageSize']) / (1024 * 1024 * 1024), 2);

    $memory = getSystemMemInfo();
    $memTotal = number_format($memory['MemTotal'] / (1024 * 1024), 2);
    $memUsed = number_format(($memory['MemTotal'] - $memory['MemFree'] - $memory['Cached']) / (1024 * 1024), 2);

    $maxLen = 0;
    foreach ($infoArray as $i) {
        foreach ($i as $key => $value) {
            $maxLen = max($maxLen, strlen("$value"));
        }
    }

    $cpu = exec("top -d 0.5 -b -n2 | grep \"Cpu(s)\"| tail -n 1 | awk '{print $2 + $4}'");
    $output = [];
    $output[] = exec('date')." CPU: $cpu% Load: ".Load::getLoad()."  Memory: ${memUsed}G/${memTotal}G  Redis: $mem  TokuDB: ${storageSize}G / ${dataSize}G\n";

    $leftCount = 1;
    $rightCount = 1;
    $line = "                                                                                                               ";
    $line = str_repeat(" ", 80);
    foreach ($infoArray as $i) {
        $num = trim($i['num']);
        $text = trim($i['text']);
        $lr = $i['lr'];
        $start = $lr == true ? 15 : 70;
        $leftCount = $lr == true ? $leftCount + 1 : $leftCount;
        $rightCount = $lr == false ? $rightCount + 1 : $rightCount;

        $lineIndex = $lr == true ? $leftCount : $rightCount;
        $nextLine = isset($output[$lineIndex]) ? $output[$lineIndex] : $line;

        if (strlen($text) != '') {
            $nextLine = substr_replace($nextLine, $num, ($start - strlen($num)), strlen($num));
            $nextLine = substr_replace($nextLine, $text, $start + 2, strlen($text));
        }
        $output[$lineIndex] = $nextLine;
    }
    foreach($output as $line) echo "$line\n";
    $output = ob_get_clean();
    file_put_contents("${baseDir}/public/ztop.txt", $output);
    sleep(3);
}

function addInfo($text, $number, $left = true)
{
    global $infoArray, $deltaArray;
    $prevNumber = (int) @$deltaArray[$text];
    $delta = $number - $prevNumber;
    $deltaArray[$text] = $number;

    if ($delta > 0) {
        $delta = "+$delta";
    }
    $dtext = $delta == 0 ? '' : "($delta)";
    $infoArray[] = ['text' => "$text $dtext", 'num' => number_format($number, 0), 'lr' => $left];
}

function getSystemMemInfo()
{
    $data = explode("\n", file_get_contents('/proc/meminfo'));
    $meminfo = array();
    foreach ($data as $line) {
        if ($line == '') {
            continue;
        }
        list($key, $val) = explode(':', $line);
        $meminfo[$key] = trim($val);
    }

    return $meminfo;
}
