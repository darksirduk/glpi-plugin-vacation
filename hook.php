<?php

function plugin_vacation_db_query(string $query): void
{
    global $DB;

    $DB->doQuery($query);
}

function plugin_vacation_install()
{
    global $DB;

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $sign      = DBConnection::getDefaultPrimaryKeySignOption();

    if (!$DB->tableExists('glpi_plugin_vacation_vacations')) {
        plugin_vacation_db_query("CREATE TABLE `glpi_plugin_vacation_vacations` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id` int {$sign} NOT NULL DEFAULT '0',
            `users_id_creator` int {$sign} NOT NULL DEFAULT '0',
            `date_begin` date DEFAULT NULL,
            `date_end` date DEFAULT NULL,
            `days_calendar` int NOT NULL DEFAULT '0',
            `days_holiday` int NOT NULL DEFAULT '0',
            `days_charged` int NOT NULL DEFAULT '0',
            `days_working` int NOT NULL DEFAULT '0',
            `status` tinyint NOT NULL DEFAULT '1',
            `comment` text,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `entities_id` (`entities_id`),
            KEY `users_id` (`users_id`),
            KEY `users_id_creator` (`users_id_creator`),
            KEY `date_begin` (`date_begin`),
            KEY `date_end` (`date_end`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    if (!$DB->tableExists('glpi_plugin_vacation_calendardays')) {
        plugin_vacation_db_query("CREATE TABLE `glpi_plugin_vacation_calendardays` (
            `id` int {$sign} NOT NULL AUTO_INCREMENT,
            `date` date NOT NULL,
            `type` tinyint NOT NULL DEFAULT '1',
            `name` varchar(255) DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `date` (`date`),
            KEY `type` (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Права: при первой установке выдаём полный доступ профилям с правом изменения настроек
    $rights = [
        PLUGIN_VACATION_RIGHT          => ALLSTANDARDRIGHT,
        PLUGIN_VACATION_CALENDAR_RIGHT => READ | UPDATE,
    ];
    $new_rights = [];
    foreach (array_keys($rights) as $name) {
        if (countElementsInTable('glpi_profilerights', ['name' => $name]) === 0) {
            $new_rights[] = $name;
        }
    }
    if (count($new_rights) > 0) {
        ProfileRight::addProfileRights($new_rights);
        $grant = array_intersect_key($rights, array_flip($new_rights));
        foreach ($DB->request(['FROM' => 'glpi_profilerights', 'WHERE' => ['name' => 'config']]) as $row) {
            if (((int) $row['rights'] & UPDATE) === UPDATE) {
                ProfileRight::updateProfileRights($row['profiles_id'], $grant);
            }
        }
        if (Session::haveRight('config', UPDATE)) {
            foreach ($grant as $name => $value) {
                $_SESSION['glpiactiveprofile'][$name] = $value;
            }
        }
    }

    // Колонки списка отпусков по умолчанию
    if (countElementsInTable('glpi_displaypreferences', ['itemtype' => 'PluginVacationVacation']) === 0) {
        $rank = 1;
        foreach ([2, 3, 4, 5, 7] as $num) {
            $DB->insert('glpi_displaypreferences', [
                'itemtype' => 'PluginVacationVacation',
                'num'      => $num,
                'rank'     => $rank++,
                'users_id' => 0,
            ]);
        }
    }

    return true;
}

function plugin_vacation_uninstall()
{
    global $DB;

    foreach (['glpi_plugin_vacation_vacations', 'glpi_plugin_vacation_calendardays'] as $table) {
        if ($DB->tableExists($table)) {
            plugin_vacation_db_query("DROP TABLE `$table`");
        }
    }

    ProfileRight::deleteProfileRights([PLUGIN_VACATION_RIGHT, PLUGIN_VACATION_CALENDAR_RIGHT]);
    foreach (['glpi_displaypreferences', 'glpi_logs'] as $table) {
        $DB->delete($table, ['itemtype' => 'PluginVacationVacation']);
    }

    unset(
        $_SESSION['glpiactiveprofile'][PLUGIN_VACATION_RIGHT],
        $_SESSION['glpiactiveprofile'][PLUGIN_VACATION_CALENDAR_RIGHT]
    );

    return true;
}
