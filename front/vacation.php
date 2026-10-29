<?php

Session::checkRight(PLUGIN_VACATION_RIGHT, READ);

Html::header(
    PluginVacationVacation::getTypeName(Session::getPluralNumber()),
    $_SERVER['PHP_SELF'],
    'tools',
    'pluginvacationmenu',
    'vacation'
);

Search::show('PluginVacationVacation');

Html::footer();
