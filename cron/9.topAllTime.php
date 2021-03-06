<?php

use cvweiss\redistools\RedisQueue;

require_once '../init.php';

if ($redis->llen('queueStats') >= 1000) exit();

$date = date('Ymd');
$redisKey = "tq:topAllTime";
$queueTopAlltime = new RedisQueue('queueTopAlltime');
if ($redis->get($redisKey) != true && $queueTopAlltime->size() == 0) {
    $iter = $mdb->getCollection('statistics')->find([], ['months' => 0, 'groups' => 0])->sort(['type' => 1, 'id' => 1]);
    while ($row = $iter->next()) {
        $allTimeSum = (int) @$row['allTimeSum'];
        $shipsDestroyed = (int) @$row['shipsDestroyed'];
        $nextTopRecalc = floor($allTimeSum * 1.01);
        if ($shipsDestroyed <=  100 || $shipsDestroyed < $nextTopRecalc) continue;

        $queueTopAlltime->push($row['_id']);
    }
    $redis->setex($redisKey, 64800, true);
}

$minute = date('Hi');
while ($id = $queueTopAlltime->pop()) {
    $row = $mdb->findDoc('statistics', ['_id' => $id]);
    calcTop($row);
    if ($minute != date('Hi')) exit();
}

function calcTop($row)
{
    global $mdb;

    if ($row['id'] == 0 || $row['type'] == null) return;

    $currentSum = (int) @$row['shipsDestroyed'];

    $parameters = [$row['type'] => $row['id']];
    $parameters['limit'] = 10;
    $parameters['kills'] = true;

    $topLists[] = array('type' => 'character', 'data' => Stats::getTop('characterID', $parameters));
    $topLists[] = array('type' => 'corporation', 'data' => Stats::getTop('corporationID', $parameters));
    $topLists[] = array('type' => 'alliance', 'data' => Stats::getTop('allianceID', $parameters));
    $topLists[] = array('type' => 'faction', 'data' => Stats::getTop('factionID', $parameters));
    $topLists[] = array('type' => 'ship', 'data' => Stats::getTop('shipTypeID', $parameters));
    $topLists[] = array('type' => 'system', 'data' => Stats::getTop('solarSystemID', $parameters));

    $nextTopRecalc = floor($currentSum * 1.01);

    $mdb->set('statistics', $row, ['topAllTime' => $topLists, 'allTimeSum' => $currentSum, 'nextTopRecalc' => $nextTopRecalc]);
}
