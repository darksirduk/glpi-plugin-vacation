<?php

Session::checkRight(PLUGIN_VACATION_RIGHT, READ);

$params = PluginVacationSchedule::getParams($_GET);

if (($_GET['export'] ?? '') === 'csv') {
    PluginVacationSchedule::exportCsv($params);
    return;
}
if (($_GET['export'] ?? '') === 'xlsx') {
    PluginVacationSchedule::exportXlsx($params);
    return;
}

Html::header(
    PluginVacationSchedule::getTypeName(),
    $_SERVER['PHP_SELF'],
    'tools',
    'pluginvacationmenu',
    'schedule'
);

PluginVacationSchedule::show($params);

Html::footer();
