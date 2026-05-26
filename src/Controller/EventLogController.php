<?php

namespace Light\Controller;

use Light\App as LightApp;
use Light\Model\Config;
use Light\Model\EventLog;
use Light\Type\LogCleanup;
use TheCodingMachine\GraphQLite\Annotations\Autowire;
use TheCodingMachine\GraphQLite\Annotations\InjectUser;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Right;
use TheCodingMachine\GraphQLite\Annotations\Logged;

class EventLogController
{
    #[Query]
    #[Logged]
    /**
     * @return \Light\Model\EventLog[]
     * @param ?mixed $filters
     * @deprecated use app.eventLogs instead
     */
    #[Right("eventlog.list")]
    public function listEventLog(#[InjectUser] \Light\Model\User $user, $filters = null,  ?string $sort = ''): \Light\Db\Query
    {
        $app = new \Light\Type\App($filters, $sort);
        return $app->listEventLog($filters, $sort);
    }

    #[Query]
    #[Logged]
    #[Right("system.eventlog.cleanup")]
    public function getEventLogCleanup(#[Autowire] LightApp $app): LogCleanup
    {
        $db = $app->getDatabase();
        $result = $db->query(
            "SELECT COUNT(*) as cnt FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME = 'light_eventlog_cleanup'"
        )->execute()->current();

        $enabled = (int)$result['cnt'] > 0;

        $config = Config::Get(["name" => "eventlog_cleanup_days"]);
        $days = $config ? (int)$config->value : 30;

        return new LogCleanup($enabled, $days);
    }

    #[Mutation]
    #[Logged]
    #[Right("system.eventlog.cleanup")]
    public function setEventLogCleanup(bool $enabled, int $days, #[Autowire] LightApp $app): bool
    {
        $db = $app->getDatabase();

        // Persist the retention days in config
        if (!$config = Config::Get(["name" => "eventlog_cleanup_days"])) {
            $config = Config::Create(["name" => "eventlog_cleanup_days"]);
        }
        $config->value = $days;
        $config->save();

        $db->query("DROP EVENT IF EXISTS `light_eventlog_cleanup`")->execute();

        if ($enabled) {
            $db->query(
                "CREATE EVENT `light_eventlog_cleanup`
                 ON SCHEDULE EVERY 1 DAY
                 STARTS TIMESTAMP(DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY))
                 DO DELETE FROM `EventLog` WHERE `created_time` < DATE_SUB(NOW(), INTERVAL $days DAY)"
            )->execute();
        }

        return true;
    }

    #[Query]
    #[Logged]
    #[Right("system.eventlog.cleanup")]
    public function getUserLogCleanup(#[Autowire] LightApp $app): LogCleanup
    {
        $db = $app->getDatabase();
        $result = $db->query(
            "SELECT COUNT(*) as cnt FROM information_schema.EVENTS WHERE EVENT_SCHEMA = DATABASE() AND EVENT_NAME = 'light_userlog_cleanup'"
        )->execute()->current();

        $enabled = (int)$result['cnt'] > 0;

        $config = Config::Get(["name" => "userlog_cleanup_days"]);
        $days = $config ? (int)$config->value : 30;

        return new LogCleanup($enabled, $days);
    }

    #[Mutation]
    #[Logged]
    #[Right("system.eventlog.cleanup")]
    public function setUserLogCleanup(bool $enabled, int $days, #[Autowire] LightApp $app): bool
    {
        $db = $app->getDatabase();

        if (!$config = Config::Get(["name" => "userlog_cleanup_days"])) {
            $config = Config::Create(["name" => "userlog_cleanup_days"]);
        }
        $config->value = $days;
        $config->save();

        $db->query("DROP EVENT IF EXISTS `light_userlog_cleanup`")->execute();

        if ($enabled) {
            $db->query(
                "CREATE EVENT `light_userlog_cleanup`
                 ON SCHEDULE EVERY 1 DAY
                 STARTS TIMESTAMP(DATE_ADD(CURRENT_DATE(), INTERVAL 1 DAY))
                 DO DELETE FROM `UserLog` WHERE `login_dt` < DATE_SUB(NOW(), INTERVAL $days DAY)"
            )->execute();
        }

        return true;
    }
}
