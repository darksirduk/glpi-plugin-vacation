<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginVacationProfile extends CommonGLPI
{
    public static string $rightname = 'profile';

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => 'PluginVacationVacation',
                'label'    => PluginVacationVacation::getTypeName(Session::getPluralNumber()),
                'field'    => PLUGIN_VACATION_RIGHT,
                'rights'   => [
                    READ   => __('Read'),
                    CREATE => __('Create'),
                    UPDATE => __('Update'),
                    PURGE  => ['short' => __('Purge'), 'long' => _x('button', 'Delete permanently')],
                ],
            ],
            [
                'itemtype' => 'PluginVacationCalendarDay',
                'label'    => PluginVacationCalendarDay::getTypeName(1),
                'field'    => PLUGIN_VACATION_CALENDAR_RIGHT,
                'rights'   => [
                    READ   => __('Read'),
                    UPDATE => __('Update'),
                ],
            ],
        ];
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Profile && $item->getField('interface') !== 'helpdesk') {
            return self::createTabEntry(__('График отпусков', 'vacation'));
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof Profile) {
            self::showRightsForm($item->getID());
        }

        return true;
    }

    public static function showRightsForm(int $profiles_id): void
    {
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        echo "<div class='firstbloc'>";
        if ($canedit) {
            echo "<form method='post' action='" . $profile->getFormURL() . "'>";
        }
        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => __('График отпусков', 'vacation'),
        ]);
        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $profiles_id]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
            echo "</div>";
            Html::closeForm();
        }
        echo "</div>";
    }
}
