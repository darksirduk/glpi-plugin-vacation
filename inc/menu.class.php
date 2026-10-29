<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginVacationMenu extends CommonGLPI
{
    public static string $rightname = PLUGIN_VACATION_RIGHT;

    public static function getMenuName()
    {
        return __('График отпусков', 'vacation');
    }

    public static function getIcon()
    {
        return 'ti ti-beach';
    }

    /**
     * URL страницы из каталога front плагина (построен так же, как URL ядра для itemtype).
     */
    public static function getUrl(string $page, bool $full = true): string
    {
        return dirname(PluginVacationVacation::getSearchURL($full)) . '/' . $page;
    }

    public static function getMenuContent()
    {
        $can_vacation = Session::haveRight(PLUGIN_VACATION_RIGHT, READ);
        $can_calendar = Session::haveRight(PLUGIN_VACATION_CALENDAR_RIGHT, READ);
        if (!$can_vacation && !$can_calendar) {
            return false;
        }

        $menu = [
            'title' => self::getMenuName(),
            'page'  => self::getUrl($can_vacation ? 'schedule.php' : 'calendar.php', false),
            'icon'  => self::getIcon(),
        ];

        if ($can_vacation) {
            $links = ['search' => PluginVacationVacation::getSearchURL(false)];
            if (PluginVacationVacation::canCreate()) {
                $links['add'] = PluginVacationVacation::getFormURL(false);
            }
            $menu['links'] = $links;

            $menu['options']['schedule'] = [
                'title' => __('График', 'vacation'),
                'page'  => self::getUrl('schedule.php', false),
                'icon'  => 'ti ti-chart-bar',
                'links' => $links,
            ];
            $menu['options']['vacation'] = [
                'title' => PluginVacationVacation::getTypeName(Session::getPluralNumber()),
                'page'  => PluginVacationVacation::getSearchURL(false),
                'icon'  => PluginVacationVacation::getIcon(),
                'links' => $links,
            ];
        }

        if ($can_calendar) {
            $menu['options']['calendar'] = [
                'title' => PluginVacationCalendarDay::getTypeName(1),
                'page'  => self::getUrl('calendar.php', false),
                'icon'  => PluginVacationCalendarDay::getIcon(),
            ];
        }

        return $menu;
    }
}
