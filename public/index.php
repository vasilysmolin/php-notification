<?php

use Dotenv\Dotenv;
use Framework\Connection;
use Framework\Jwt;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

try {
    $conn = Connection::getInstance();
} catch (PDOException $e) {
    echo 'Connection failed: ' . $e->getMessage();
}

$jwt = new Jwt($_SERVER['HTTP_AUTHORIZATION']);

header('Content-Type: application/json');
$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
list($path, $params) = explode('?', $uri);

if ($path === '/api/calendar-php' && $method === 'GET') {
    if (!$jwt->verifyToken()) {
        echo json_encode(['errors' =>
            [
                'code' => 101,
                'message' => 'Пользователь не авторизован'
            ],
        ]);
        return;
    }
    $currentUserID = $jwt->getUserID();
    $query = $conn->prepare('select * from users WHERE `userID` = ? and `users`.`deleted_at` is null');
    $query->execute([$currentUserID]);
    $currentUser = $query->fetch();
    $addressID = $_REQUEST['addressID'];
    $view = $_REQUEST['view'];
    $date = $_REQUEST['date'];
    $userID = $_REQUEST['userID'];
    $position = $_REQUEST['position'] ?? 0;
    $countMaster = $_REQUEST['countMaster'] ?? 50;
    $monday = $_REQUEST['monday'];
    $sunday = $_REQUEST['sunday'];

    $skip = $position * ($countMaster ?? 50);

    if (empty($currentUser['phone'])) {
        $query = $conn->prepare('select `profiles`.*, `user_studio`.`userID` from `profiles` 
             inner join `user_studio` on `profiles`.`profileID` = `user_studio`.`profileID` where
            `user_studio`.`userID` = ?');
        $query->execute([$currentUserID]);
        $currentProfile = $query->fetch();
    } else {
        $query = $conn->prepare('select profileID from profiles WHERE `userID` = ?');
        $query->execute([$currentUserID]);
        $currentProfile = $query->fetch();
    }

    $query = $conn->prepare('select addressID from addresses WHERE `profileID` = ? and `addresses`.`deleted_at` is null');
    $query->execute([$currentProfile['profileID']]);
    $currentAddress = $query->fetch();

    if ($view === 'unit') {
        $userSql = $userID ?  "`users`.`userID` = ? and"  : '';
        $select = "select users.userID,name,avatar,
        MIN(schedule_address_user.sort) AS sort,
        schedule_address_user.itemID,addressID from `users` left join `schedule_address_user` on `users`.`userID` =
        `schedule_address_user`.`userID` where $userSql `schedule_address_user`.`addressID` = ? 
        and `users`.`deleted_at` is null group by `users`.`userID`,
        `schedule_address_user`.`itemID` order by `sort` asc";
        $query = $conn->prepare($select);
        $userID ? $query->execute([$userID, $currentAddress['addressID']]) : $query->execute([$currentAddress['addressID']]);
        $employees = $query->fetchAll();
        $employeesIds = collect($employees)->pluck('userID')->toArray();

        $placeholders = implode(',', array_fill(0, count($employees), '?'));
        $select = "select * from `bids` where `bids`.`masterID` in ($placeholders)
                       and exists (select * from `bids_visits` where
                      `bids`.`visitID` = `bids_visits`.`visitID` and `status` != ? and date(`date`) = ?
                       and `bids_visits`.`deleted_at` is null) and `bids`.`deleted_at` is null order by `timeFrom` asc";

        $query = $conn->prepare($select);
        $query->execute([...$employeesIds, 'cancel', $date]);
        $bids = $query->fetchAll();

        if ($bids) {
            $placeholders = implode(',', array_fill(0, count($bids), '?'));
            $visitIDs = collect($bids)->pluck('visitID')->toArray();
            $select = "select * from `bids_visits` where `bids_visits`.`visitID` in ($placeholders)";

            $query = $conn->prepare($select);
            $query->execute([...$visitIDs]);
            $visits = $query->fetchAll();
        } else {
            $visits = [];
        }


        if ($employees) {
            $placeholders = implode(',', array_fill(0, count($employees), '?'));
            $visitIDs = collect($bids)->pluck('visitID')->toArray();
            $select = "select * from `schedule` where `schedule`.`userID` in ($placeholders) and `addressID` = ? and date(`date`) = ?";

            $query = $conn->prepare($select);
            $query->execute([...$employeesIds, $currentAddress['addressID'], $date]);
            $schedules = $query->fetchAll();
        } else {
            $schedules = [];
        }


        if ($employees) {
            $placeholders = implode(',', array_fill(0, count($employees), '?'));
            $visitIDs = collect($bids)->pluck('visitID')->toArray();
            $select = "select `positions`.`positionID`, `positions`.`name`, `employee_position`.`userID` as `userID`,
            `employee_position`.`positionID` as `pivot_positionID` from `positions` inner join `employee_position` on
            `positions`.`positionID` = `employee_position`.`positionID` where `employee_position`.`userID` in ($placeholders)";

            $query = $conn->prepare($select);
            $query->execute([...$employeesIds]);
            $positions = $query->fetchAll();
        } else {
            $positions = [];
        }

        if ($employees) {
            $placeholders = implode(',', array_fill(0, count($employees), '?'));
            $visitIDs = collect($bids)->pluck('visitID')->toArray();
            $select = "select `blocking_times`.*, `users_blocking_times`.`userID` as `pivot_userID`,
                `users_blocking_times`.`blockTimeID` as `pivot_blockTimeID` from `blocking_times` inner join `users_blocking_times` on
                `blocking_times`.`blockTimeID` = `users_blocking_times`.`blockTimeID` where `users_blocking_times`.`userID` in ($placeholders) and
                date(`date`) = ? and `addressID` = ? order by `date` asc, `timeFrom` asc";

            $query = $conn->prepare($select);
            $query->execute([...$employeesIds, $date, $currentAddress['addressID']]);
            $blockingTimes = $query->fetchAll();
        } else {
            $blockingTimes = [];
        }

        if ($visits) {
            $placeholders = implode(',', array_fill(0, count($visits), '?'));
            $clientIDs = collect($visits)->pluck('clientID')->toArray();
            $select = "select `clientID`, `name`, `phone` from `clients` where `clients`.`clientID` in ($placeholders)";

            $query = $conn->prepare($select);
            $query->execute([...$clientIDs]);
            $clients = $query->fetchAll();
        } else {
            $clients = [];
        }

        if ($bids) {
            $placeholders = implode(',', array_fill(0, count($bids), '?'));
            $serviceIDs = collect($bids)->pluck('serviceID')->toArray();
            $select = "select `serviceID`, `title`, `customerTitle` from `crm_services` where `crm_services`.`serviceID` in ($placeholders)";

            $query = $conn->prepare($select);
            $query->execute([...$serviceIDs]);
            $services = $query->fetchAll();
        } else {
            $services = [];
        }

        if ($bids) {
            $placeholders = implode(',', array_fill(0, count($bids), '?'));
            $mastersIDs = collect($bids)->pluck('masterID')->toArray();
            $select = "select `userID`, `associatePhone` as `phone`, `name` from `users` where `users`.`userID` in ($placeholders)";

            $query = $conn->prepare($select);
            $query->execute([...$mastersIDs]);
            $masters = $query->fetchAll();
        } else {
            $masters = [];
        }


        $employees = collect($employees)->map(function($employee) use ($bids, $visits, $positions, $services, $masters, $schedules, $blockingTimes, $clients) {
            if (!empty($employee['avatar'])) {
                list($name, $extention) = explode('.', $employee['avatar']);
                $bucket = $_ENV['APP_ENV'] === 'production' ?  'bb-avatar' : 'bb-avatar-dev';
                $employee['avatar'] = "https://$bucket.storage.yandexcloud.net/users/" . substr($name, 0, 2) . '/'. substr($name, 2, 2) . '/' . $name . '/' . $name . '_120x120.' . $extention;
            }
            $positionName = collect($positions)->where('userID', $employee['userID'])->first() ?? null;
            $employee['positionName'] = $positionName['name'] ?? null;

            $schedule = collect($schedules)->where('userID', $employee['userID'])->first() ?? null;
            if ($schedule['timeFrom']) {
                $schedule['timeFrom'] = substr($schedule['timeFrom'], 0, 5);
            }
            if ($schedule['timeTo']) {
                $schedule['timeTo'] = substr($schedule['timeTo'], 0, 5);
            }
            if ($schedule['timeBreakTo']) {
                $schedule['timeBreakTo'] = substr($schedule['timeBreakTo'], 0, 5);
            }
            if ($schedule['timeBreakFrom']) {
                $schedule['timeBreakFrom'] = substr($schedule['timeBreakFrom'], 0, 5);
            }
            $employee['schedules'] = $schedule ? [$schedule] : [];

            $blockingTimes = collect($blockingTimes)->where('userID', $employee['userID']) ?? [];

            $blockingTimes = $blockingTimes->map(function($block){
                $block['onlineIsBlocked'] = (bool) $block['onlineIsBlocked'];
                $block['blockOnAllDay'] = $block['timeFrom'] === '00:00:00' &&
                    $block['timeTo'] >= '23:45:00';

                return $block;
            });


            $employee['blockingTime'] = $blockingTimes;

            $masterBids = collect($bids)->where('masterID', $employee['userID'])->map(function($bid) use ($services, $masters) {
                if ($bid['timeFrom']) {
                    $bid['timeFrom'] = substr($bid['timeFrom'], 0, 5);
                }
                if ($bid['timeTo']) {
                    $bid['timeTo'] = substr($bid['timeTo'], 0, 5);
                }
                $bid['timeDuration'] = (int) $bid['timeDuration'];
                $service = collect($services)->where('serviceID', $bid['serviceID'])->first() ?? null;
                $bid['serviceName'] = $service['customTitle'] ?? $service['title'];

                $master = collect($masters)->where('userID', $bid['masterID'])->first() ?? null;
                $bid['masterName'] = $master['name'];

                return $bid;
            })
                ->groupBy('visitID');
            $employee['visits'] = collect($visits)->filter(function($visit) use ($masterBids){
                return in_array($visit['visitID'], $masterBids->keys()->toArray());
            })->map(function($visit) use ($masterBids, $clients) {
                $bids = $masterBids[$visit['visitID']];
                $client = collect($clients)->where('clientID', $visit['clientID'])->first() ?? null;
                $visit['client'] = $client;
                $visit['isOnline'] = (bool) $visit['isOnline'];
                $visit['timeStart'] = $bids->first()['timeFrom'];
                $visit['bids'] = $bids;
                $visit['notification'] = ["notificationRepeatReminder" => null, "notificationReminder" => null];
                return $visit;
            })->values();
            return $employee;
        });


    }

    $start = !empty($bids) ? substr(collect($bids)->first()['timeFrom'],0, 5) : '9:00';


    echo json_encode([
        'count' => count($employees),
        'position' => $position,
        'next' => empty($userID) && ($skip + $countMaster) < count($employees),
        'prev' => empty($userID) && $position !== 0,
        'employees' => $employees,
        'startTime' => $start,
        'utc' => [
            'regularFormat' => "1000",
            'format' => "10",
        ],
    ]);
}
