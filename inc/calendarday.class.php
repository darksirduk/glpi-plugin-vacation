<?php

use Glpi\Toolbox\HttpClient;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Производственный календарь: хранятся только дни-исключения.
 * Остальные дни считаются по умолчанию: пн–пт рабочие, сб–вс выходные.
 */
class PluginVacationCalendarDay extends CommonDBTM
{
    public static string $rightname = PLUGIN_VACATION_CALENDAR_RIGHT;

    public const TYPE_HOLIDAY  = 1; // нерабочий праздничный день (ст. 112 ТК РФ), не входит в отпуск
    public const TYPE_DAYOFF   = 2; // дополнительный выходной (перенос)
    public const TYPE_WORKDAY  = 3; // рабочий день, перенесённый на субботу/воскресенье
    public const TYPE_SHORTDAY = 4; // предпраздничный сокращённый рабочий день

    public const XMLCALENDAR_URL = 'https://xmlcalendar.ru/data/ru/%d/calendar.json';

    private static $cache = [];

    public static function getTypeName($nb = 0)
    {
        return __('Производственный календарь', 'vacation');
    }

    public static function getIcon()
    {
        return 'ti ti-calendar-event';
    }

    public static function getTypes(): array
    {
        return [
            self::TYPE_HOLIDAY  => __('Праздничный день', 'vacation'),
            self::TYPE_DAYOFF   => __('Выходной (перенос)', 'vacation'),
            self::TYPE_WORKDAY  => __('Рабочий день (перенос)', 'vacation'),
            self::TYPE_SHORTDAY => __('Сокращённый предпраздничный день', 'vacation'),
        ];
    }

    public static function getMonthNames(): array
    {
        return [
            1 => 'Январь', 2 => 'Февраль', 3 => 'Март', 4 => 'Апрель', 5 => 'Май', 6 => 'Июнь',
            7 => 'Июль', 8 => 'Август', 9 => 'Сентябрь', 10 => 'Октябрь', 11 => 'Ноябрь', 12 => 'Декабрь',
        ];
    }

    /**
     * Нерабочие праздничные дни по ст. 112 ТК РФ.
     */
    public static function getStandardHolidays(int $year): array
    {
        $list = [];
        foreach ([1, 2, 3, 4, 5, 6, 8] as $day) {
            $list[sprintf('%04d-01-%02d', $year, $day)] = 'Новогодние каникулы';
        }
        $list[sprintf('%04d-01-07', $year)] = 'Рождество Христово';
        $list[sprintf('%04d-02-23', $year)] = 'День защитника Отечества';
        $list[sprintf('%04d-03-08', $year)] = 'Международный женский день';
        $list[sprintf('%04d-05-01', $year)] = 'Праздник Весны и Труда';
        $list[sprintf('%04d-05-09', $year)] = 'День Победы';
        $list[sprintf('%04d-06-12', $year)] = 'День России';
        $list[sprintf('%04d-11-04', $year)] = 'День народного единства';
        ksort($list);

        return $list;
    }

    public static function isValidDate($date): bool
    {
        return is_string($date)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /**
     * @return array date => ['type' => int, 'name' => string]
     */
    public static function getYearDays(int $year): array
    {
        global $DB;

        if (!isset(self::$cache[$year])) {
            self::$cache[$year] = [];
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => [
                    ['date' => ['>=', sprintf('%04d-01-01', $year)]],
                    ['date' => ['<=', sprintf('%04d-12-31', $year)]],
                ],
                'ORDER' => 'date',
            ]);
            foreach ($iterator as $row) {
                self::$cache[$year][$row['date']] = [
                    'type' => (int) $row['type'],
                    'name' => (string) $row['name'],
                ];
            }
        }

        return self::$cache[$year];
    }

    public static function hasYear(int $year): bool
    {
        return count(self::getYearDays($year)) > 0;
    }

    public static function getDayInfo(string $date): array
    {
        $days = self::getYearDays((int) substr($date, 0, 4));

        return $days[$date] ?? ['type' => 0, 'name' => ''];
    }

    public static function isWeekend(string $date): bool
    {
        return (int) date('N', strtotime($date)) >= 6;
    }

    public static function isHoliday(string $date): bool
    {
        return self::getDayInfo($date)['type'] === self::TYPE_HOLIDAY;
    }

    public static function isNonWorking(string $date): bool
    {
        switch (self::getDayInfo($date)['type']) {
            case self::TYPE_HOLIDAY:
            case self::TYPE_DAYOFF:
                return true;
            case self::TYPE_WORKDAY:
            case self::TYPE_SHORTDAY:
                return false;
        }

        return self::isWeekend($date);
    }

    public static function getCssClass(string $date): string
    {
        switch (self::getDayInfo($date)['type']) {
            case self::TYPE_HOLIDAY:
                return 'vac-holiday';
            case self::TYPE_DAYOFF:
                return 'vac-dayoff';
            case self::TYPE_WORKDAY:
                return 'vac-workday';
            case self::TYPE_SHORTDAY:
                return 'vac-short';
        }

        return self::isWeekend($date) ? 'vac-weekend' : '';
    }

    /**
     * Подсчёт дней периода (включительно).
     *
     * @return array ['calendar', 'holidays', 'charged', 'working']
     *   charged — календарные дни без нерабочих праздничных (ст. 120 ТК РФ)
     */
    public static function countPeriod(string $begin, string $end): array
    {
        $result = ['calendar' => 0, 'holidays' => 0, 'charged' => 0, 'working' => 0];
        $day  = new DateTime($begin);
        $last = new DateTime($end);
        while ($day <= $last) {
            $date = $day->format('Y-m-d');
            $result['calendar']++;
            if (self::isHoliday($date)) {
                $result['holidays']++;
            }
            if (!self::isNonWorking($date)) {
                $result['working']++;
            }
            $day->modify('+1 day');
        }
        $result['charged'] = $result['calendar'] - $result['holidays'];

        return $result;
    }

    /**
     * Дата окончания отпуска длительностью $days календарных дней,
     * с продлением на праздничные дни, попавшие в период.
     */
    public static function computeEndDate(string $begin, int $days): string
    {
        $day   = new DateTime($begin);
        $count = 0;
        for ($i = 0; $i < 1000; $i++) {
            if (!self::isHoliday($day->format('Y-m-d'))) {
                $count++;
                if ($count >= $days) {
                    break;
                }
            }
            $day->modify('+1 day');
        }

        return $day->format('Y-m-d');
    }

    /**
     * Типы дней-исключений для расчётов в браузере.
     */
    public static function getJsData(array $years): array
    {
        $data = [];
        foreach ($years as $year) {
            foreach (self::getYearDays((int) $year) as $date => $info) {
                $data[$date] = $info['type'];
            }
        }

        return $data;
    }

    public static function getYearStats(int $year): array
    {
        $stats = ['calendar' => 0, 'working' => 0, 'nonworking' => 0, 'holidays' => 0, 'short' => 0];
        $day   = new DateTime(sprintf('%04d-01-01', $year));
        while ((int) $day->format('Y') === $year) {
            $date = $day->format('Y-m-d');
            $stats['calendar']++;
            if (self::isNonWorking($date)) {
                $stats['nonworking']++;
            } else {
                $stats['working']++;
            }
            if (self::isHoliday($date)) {
                $stats['holidays']++;
            }
            if (self::getDayInfo($date)['type'] === self::TYPE_SHORTDAY) {
                $stats['short']++;
            }
            $day->modify('+1 day');
        }
        // Норма часов: сокращённый день короче на 1 час
        $stats['hours40'] = $stats['working'] * 8 - $stats['short'];
        $stats['hours36'] = $stats['working'] * 7.2 - $stats['short'];
        $stats['hours24'] = $stats['working'] * 4.8 - $stats['short'];

        return $stats;
    }

    /**
     * Сохранить исключение для дня. $type = 0 — удалить исключение.
     * $name — неэкранированная строка.
     */
    public static function saveDay(string $date, int $type, string $name = ''): bool
    {
        global $DB;

        if (!self::isValidDate($date) || ($type !== 0 && !isset(self::getTypes()[$type]))) {
            return false;
        }
        $DB->delete(self::getTable(), ['date' => $date]);
        self::clearCache();
        if ($type === 0) {
            return true;
        }

        return (bool) $DB->insert(self::getTable(), [
            'date'     => $date,
            'type'     => $type,
            'name'     => mb_substr($name, 0, 255),
            'date_mod' => $_SESSION['glpi_currenttime'],
        ]);
    }

    public static function clearYear(int $year): void
    {
        global $DB;

        $DB->delete(self::getTable(), [
            ['date' => ['>=', sprintf('%04d-01-01', $year)]],
            ['date' => ['<=', sprintf('%04d-12-31', $year)]],
        ]);
        self::clearCache();
    }

    /**
     * Добавить праздники ст. 112 ТК РФ (существующие записи не трогаются).
     */
    public static function fillStandardHolidays(int $year): int
    {
        $existing = self::getYearDays($year);
        $count    = 0;
        foreach (self::getStandardHolidays($year) as $date => $name) {
            if (!isset($existing[$date]) && self::saveDay($date, self::TYPE_HOLIDAY, $name)) {
                $count++;
            }
        }

        return $count;
    }

    public static function fetchRemote(int $year, ?string &$error = null): ?string
    {
        $url = sprintf(self::XMLCALENDAR_URL, $year);
        try {
            // Учитываются настройки прокси GLPI
            $content = (new HttpClient(null, ['timeout' => 20]))->get($url)->getContent();
        } catch (\Throwable $e) {
            $error = sprintf('Не удалось загрузить %s: %s', $url, $e->getMessage());
            return null;
        }
        if ($content === '') {
            $error = sprintf('Не удалось загрузить %s: пустой ответ', $url);
            return null;
        }

        return $content;
    }

    /**
     * Импорт календаря в формате xmlcalendar.ru (calendar.json):
     * {"year":2026,"months":[{"month":1,"days":"1,2,3,...,9*"}, ...]}
     * В "days" перечислены нерабочие дни; "*" — сокращённый рабочий день, "+" — перенесённый выходной.
     * Данные года полностью заменяются.
     */
    public static function importJson(int $year, string $json, ?string &$error = null): bool
    {
        $data = json_decode(trim($json), true);
        if (!is_array($data) || !isset($data['months']) || !is_array($data['months'])) {
            $error = 'Неверный формат данных: ожидается JSON xmlcalendar.ru с ключом "months"';
            return false;
        }
        if (isset($data['year']) && (int) $data['year'] !== $year) {
            $error = sprintf('Данные относятся к %d году, а выбран %d', (int) $data['year'], $year);
            return false;
        }

        $listed = [];
        foreach ($data['months'] as $month_data) {
            $month = (int) ($month_data['month'] ?? 0);
            if ($month < 1 || $month > 12) {
                continue;
            }
            foreach (explode(',', (string) ($month_data['days'] ?? '')) as $token) {
                if (!preg_match('/^(\d{1,2})([*+]?)$/', trim($token), $m) || !checkdate($month, (int) $m[1], $year)) {
                    continue;
                }
                $listed[sprintf('%04d-%02d-%02d', $year, $month, (int) $m[1])] = $m[2];
            }
        }
        if (count($listed) < 52) {
            $error = sprintf('В данных всего %d нерабочих дней — похоже, они неполные', count($listed));
            return false;
        }

        $standard = self::getStandardHolidays($year);
        $rows     = [];
        $day      = new DateTime(sprintf('%04d-01-01', $year));
        while ((int) $day->format('Y') === $year) {
            $date    = $day->format('Y-m-d');
            $weekend = self::isWeekend($date);
            if (isset($listed[$date])) {
                if ($listed[$date] === '*') {
                    $rows[$date] = [self::TYPE_SHORTDAY, 'Предпраздничный день'];
                } elseif (isset($standard[$date])) {
                    $rows[$date] = [self::TYPE_HOLIDAY, $standard[$date]];
                } elseif (!$weekend) {
                    $rows[$date] = [self::TYPE_DAYOFF, 'Перенесённый выходной день'];
                }
            } elseif ($weekend) {
                $rows[$date] = [self::TYPE_WORKDAY, 'Рабочий день (перенос выходного)'];
            }
            $day->modify('+1 day');
        }

        self::clearYear($year);
        foreach ($rows as $date => [$type, $name]) {
            self::saveDay($date, $type, $name);
        }
        self::clearCache();

        return true;
    }

    /**
     * Страница производственного календаря на год.
     */
    public static function showYear(int $year, bool $canedit): void
    {
        $url   = PluginVacationMenu::getUrl('calendar.php');
        $types = self::getTypes();
        $days  = self::getYearDays($year);

        echo "<div class='vac-page'>";

        // Навигация по годам
        echo "<div class='vac-yearnav'>";
        echo "<a class='btn btn-outline-secondary' href='$url?year=" . ($year - 1) . "'>&laquo; " . ($year - 1) . "</a>";
        echo "<h2>" . sprintf('Производственный календарь на %d год', $year) . "</h2>";
        echo "<a class='btn btn-outline-secondary' href='$url?year=" . ($year + 1) . "'>" . ($year + 1) . " &raquo;</a>";
        echo "</div>";

        if (count($days) === 0) {
            echo "<div class='alert alert-warning'>"
                . "Календарь на этот год не заполнен: праздничные дни и переносы не учитываются, "
                . "выходными считаются только суббота и воскресенье. "
                . ($canedit ? "Загрузите данные с xmlcalendar.ru или заполните праздники по ст. 112 ТК РФ." : '')
                . "</div>";
        }

        $stats = self::getYearStats($year);
        echo "<div class='vac-stats'>";
        echo "<span>Календарных дней: <b>{$stats['calendar']}</b></span>";
        echo "<span>Рабочих: <b>{$stats['working']}</b></span>";
        echo "<span>Выходных и праздничных: <b>{$stats['nonworking']}</b></span>";
        echo "<span>Праздничных (ст. 112): <b>{$stats['holidays']}</b></span>";
        echo "<span>Сокращённых: <b>{$stats['short']}</b></span>";
        echo "<span>Норма часов 40 / 36 / 24 ч: <b>{$stats['hours40']} / "
            . round($stats['hours36'], 1) . " / " . round($stats['hours24'], 1) . "</b></span>";
        echo "</div>";

        if ($canedit) {
            echo "<form method='post' action='$url' class='vac-actions'>";
            echo Html::hidden('year', ['value' => $year]);
            echo "<button type='submit' name='import_remote' value='1' class='btn btn-primary'>"
                . "<i class='ti ti-download'></i> Загрузить с xmlcalendar.ru</button> ";
            echo "<button type='submit' name='fill_standard' value='1' class='btn btn-secondary'>"
                . "<i class='ti ti-calendar-plus'></i> Добавить праздники ст. 112 ТК РФ</button> ";
            echo "<button type='submit' name='clear_year' value='1' class='btn btn-outline-danger' "
                . "onclick=\"return confirm('Удалить все исключения за $year год?');\">"
                . "<i class='ti ti-trash'></i> Очистить год</button>";
            echo "<details class='vac-json'><summary>Импорт из JSON вручную (если сервер GLPI без доступа в интернет)</summary>";
            echo "<p>Скачайте файл <code>" . sprintf(self::XMLCALENDAR_URL, $year) . "</code> и вставьте его содержимое:</p>";
            echo "<textarea name='json' rows='6' class='form-control'></textarea>";
            echo "<button type='submit' name='import_json' value='1' class='btn btn-primary mt-2'>Импортировать</button>";
            echo "</details>";
            Html::closeForm();
        }

        self::showLegend(false);

        // Сетка месяцев
        $weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
        echo "<div class='vac-months'>";
        foreach (self::getMonthNames() as $month => $month_name) {
            $first  = mktime(0, 0, 0, $month, 1, $year);
            $offset = (int) date('N', $first) - 1;
            $dim    = (int) date('t', $first);
            $work   = 0;
            for ($d = 1; $d <= $dim; $d++) {
                if (!self::isNonWorking(sprintf('%04d-%02d-%02d', $year, $month, $d))) {
                    $work++;
                }
            }

            echo "<div class='vac-month'>";
            echo "<div class='vac-month-title'>$month_name <small>раб. дней: $work</small></div>";
            echo "<table class='vac-cal'><tr>";
            foreach ($weekdays as $wd) {
                echo "<th>$wd</th>";
            }
            echo "</tr><tr>";
            echo str_repeat('<td></td>', $offset);
            for ($d = 1; $d <= $dim; $d++) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
                $info = self::getDayInfo($date);
                $name = htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8');
                echo "<td class='" . self::getCssClass($date) . "' data-date='$date' data-type='{$info['type']}'"
                    . " data-name='$name' title='$name'>$d</td>";
                if (($offset + $d) % 7 === 0 && $d < $dim) {
                    echo "</tr><tr>";
                }
            }
            $tail = (7 - ($offset + $dim) % 7) % 7;
            echo str_repeat('<td></td>', $tail);
            echo "</tr></table></div>";
        }
        echo "</div>";

        if ($canedit) {
            echo "<h3 class='mt-4'>Изменить день</h3>";
            echo "<p class='text-muted'>Щёлкните по дню в календаре, чтобы заполнить форму.</p>";
            echo "<form method='post' action='$url' class='vac-dayform'>";
            echo Html::hidden('year', ['value' => $year]);
            echo "<label>Дата <input type='date' name='date' id='vac-day-date' class='form-control' required"
                . " min='$year-01-01' max='$year-12-31'></label>";
            echo "<label>Тип <select name='type' id='vac-day-type' class='form-select'>";
            echo "<option value='0'>Обычный день (по умолчанию)</option>";
            foreach ($types as $id => $label) {
                echo "<option value='$id'>$label</option>";
            }
            echo "</select></label>";
            echo "<label>Название <input type='text' name='name' id='vac-day-name' class='form-control' maxlength='255'></label>";
            echo "<button type='submit' name='save_day' value='1' class='btn btn-primary'>Сохранить</button>";
            Html::closeForm();

            echo Html::scriptBlock("
                document.querySelectorAll('.vac-cal td[data-date]').forEach(function (td) {
                    td.addEventListener('click', function () {
                        document.getElementById('vac-day-date').value = td.dataset.date;
                        document.getElementById('vac-day-type').value = td.dataset.type;
                        document.getElementById('vac-day-name').value = td.dataset.name || '';
                        document.getElementById('vac-day-date').scrollIntoView({behavior: 'smooth', block: 'center'});
                    });
                });
            ");
        }

        // Список исключений
        echo "<h3 class='mt-4'>Праздники и переносы</h3>";
        if (count($days) === 0) {
            echo "<p>Нет данных.</p>";
        } else {
            $weekday_names = [1 => 'пн', 'вт', 'ср', 'чт', 'пт', 'сб', 'вс'];
            if ($canedit) {
                echo "<form method='post' action='$url'>";
                echo Html::hidden('year', ['value' => $year]);
            }
            echo "<table class='table table-sm table-striped vac-exceptions'>";
            echo "<thead><tr>" . ($canedit ? "<th></th>" : '') . "<th>Дата</th><th>Тип</th><th>Название</th></tr></thead><tbody>";
            foreach ($days as $date => $info) {
                echo "<tr>";
                if ($canedit) {
                    echo "<td><input type='checkbox' name='dates[]' value='$date' class='form-check-input'></td>";
                }
                echo "<td><span class='vac-sw " . self::getCssClass($date) . "'></span>"
                    . Html::convDate($date) . ", " . $weekday_names[(int) date('N', strtotime($date))] . "</td>";
                echo "<td>" . ($types[$info['type']] ?? $info['type']) . "</td>";
                echo "<td>" . htmlspecialchars($info['name'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            if ($canedit) {
                echo "<button type='submit' name='delete_days' value='1' class='btn btn-outline-danger'>Удалить выбранные</button>";
                Html::closeForm();
            }
        }

        echo "</div>";
    }

    public static function showLegend(bool $with_vacations): void
    {
        $items = [
            'vac-weekend' => 'Выходной',
            'vac-holiday' => 'Праздничный (не входит в отпуск)',
            'vac-dayoff'  => 'Выходной (перенос)',
            'vac-workday' => 'Рабочий (перенос)',
            'vac-short'   => 'Сокращённый',
        ];
        if ($with_vacations) {
            $items['vac-st' . PluginVacationVacation::STATUS_PLANNED]  = 'Отпуск: запланирован';
            $items['vac-st' . PluginVacationVacation::STATUS_APPROVED] = 'Отпуск: согласован';
            $items['vac-st' . PluginVacationVacation::STATUS_REJECTED] = 'Отпуск: отклонён';
        }
        echo "<div class='vac-legend'>";
        foreach ($items as $class => $label) {
            echo "<span><span class='vac-sw $class'></span>$label</span>";
        }
        echo "</div>";
    }
}
