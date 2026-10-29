<?php

Session::checkRight(PLUGIN_VACATION_CALENDAR_RIGHT, READ);

$year = (int) ($_REQUEST['year'] ?? date('Y'));
if ($year < 2000 || $year > 2100) {
    $year = (int) date('Y');
}
$self = PluginVacationMenu::getUrl('calendar.php') . '?year=' . $year;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkRight(PLUGIN_VACATION_CALENDAR_RIGHT, UPDATE);

    $error   = null;
    $years   = [];
    $message = '';

    if (isset($_POST['import_remote'])) {
        $json = PluginVacationCalendarDay::fetchRemote($year, $error);
        if ($json !== null && PluginVacationCalendarDay::importJson($year, $json, $error)) {
            $years[] = $year;
            $message = sprintf('Производственный календарь на %d год загружен', $year);
        }
    } elseif (isset($_POST['import_json'])) {
        $json = (string) ($_POST['json'] ?? '');
        if (PluginVacationCalendarDay::importJson($year, $json, $error)) {
            $years[] = $year;
            $message = sprintf('Производственный календарь на %d год импортирован', $year);
        }
    } elseif (isset($_POST['fill_standard'])) {
        $count   = PluginVacationCalendarDay::fillStandardHolidays($year);
        $years[] = $year;
        $message = sprintf('Добавлено праздничных дней: %d. Переносы выходных добавьте вручную или загрузите календарь.', $count);
    } elseif (isset($_POST['clear_year'])) {
        PluginVacationCalendarDay::clearYear($year);
        $years[] = $year;
        $message = sprintf('Данные за %d год удалены', $year);
    } elseif (isset($_POST['save_day'])) {
        $date = (string) ($_POST['date'] ?? '');
        $name = (string) ($_POST['name'] ?? '');
        if (PluginVacationCalendarDay::saveDay($date, (int) ($_POST['type'] ?? 0), $name)) {
            $years[] = (int) substr($date, 0, 4);
            $message = sprintf('День %s сохранён', Html::convDate($date));
        } else {
            $error = 'Неверная дата или тип дня';
        }
    } elseif (isset($_POST['delete_days'])) {
        foreach ((array) ($_POST['dates'] ?? []) as $date) {
            if (PluginVacationCalendarDay::saveDay((string) $date, 0)) {
                $years[] = (int) substr($date, 0, 4);
            }
        }
        $message = sprintf('Удалено записей: %d', count($years));
    }

    if ($error !== null) {
        Session::addMessageAfterRedirect(htmlspecialchars($error, ENT_QUOTES, 'UTF-8'), false, ERROR);
    }
    if ($message !== '') {
        Session::addMessageAfterRedirect(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    }

    // Календарь изменился — пересчитываем дни в отпусках
    $updated = 0;
    foreach (array_unique($years) as $changed_year) {
        $updated += PluginVacationVacation::recomputeYear($changed_year);
    }
    if ($updated > 0) {
        Session::addMessageAfterRedirect(sprintf('Пересчитано отпусков: %d', $updated));
    }

    Html::redirect($self);
}

Html::header(
    PluginVacationCalendarDay::getTypeName(1),
    $_SERVER['PHP_SELF'],
    'tools',
    'pluginvacationmenu',
    'calendar'
);

PluginVacationCalendarDay::showYear($year, PluginVacationCalendarDay::canUpdate());

Html::footer();
