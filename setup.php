<?php

/**
 * Плагин GLPI «График отпусков».
 *
 * Планирование отпусков сотрудников с учётом производственного календаря:
 * праздничные дни (ст. 112 ТК РФ) не входят в число календарных дней отпуска,
 * выходные и переносы учитываются при подсчёте рабочих дней.
 *
 * @author  Claude (Anthropic)
 * @license GPL-3.0-or-later
 */

define('PLUGIN_VACATION_VERSION', '1.0.0');
define('PLUGIN_VACATION_MIN_GLPI', '11.0.0');
define('PLUGIN_VACATION_MAX_GLPI', '12.0.99');

// Права плагина (хранятся в glpi_profilerights)
define('PLUGIN_VACATION_RIGHT', 'plugin_vacation');
define('PLUGIN_VACATION_CALENDAR_RIGHT', 'plugin_vacation_calendar');

function plugin_init_vacation()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['vacation'] = true;

    Plugin::registerClass('PluginVacationProfile', ['addtabon' => ['Profile']]);
    Plugin::registerClass('PluginVacationVacation', ['addtabon' => ['User']]);

    if (
        Session::getLoginUserID()
        && (Session::haveRight(PLUGIN_VACATION_RIGHT, READ)
            || Session::haveRight(PLUGIN_VACATION_CALENDAR_RIGHT, READ))
    ) {
        $PLUGIN_HOOKS['menu_toadd']['vacation'] = ['tools' => 'PluginVacationMenu'];
    }

    // Файл лежит в public/css/vacation.css
    $PLUGIN_HOOKS['add_css']['vacation'] = 'css/vacation.css';
}

function plugin_version_vacation()
{
    return [
        'name'         => 'График отпусков',
        'version'      => PLUGIN_VACATION_VERSION,
        'author'       => 'Claude (Anthropic)',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://github.com/darksirduk/glpi-plugin-vacation',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_VACATION_MIN_GLPI,
                'max' => PLUGIN_VACATION_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.2',
            ],
        ],
    ];
}

function plugin_vacation_check_prerequisites()
{
    return true;
}

function plugin_vacation_check_config($verbose = false)
{
    return true;
}
