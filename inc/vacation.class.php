<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class PluginVacationVacation extends CommonDBTM
{
    public static string $rightname = PLUGIN_VACATION_RIGHT;

    public bool $dohistory = true;

    public const STATUS_PLANNED  = 1;
    public const STATUS_APPROVED = 2;
    public const STATUS_REJECTED = 3;

    public static function getTypeName($nb = 0)
    {
        return _n('Отпуск', 'Отпуска', $nb, 'vacation');
    }

    public static function getIcon()
    {
        return 'ti ti-beach';
    }

    public static function getStatuses(): array
    {
        return [
            self::STATUS_PLANNED  => __('Запланирован', 'vacation'),
            self::STATUS_APPROVED => __('Согласован', 'vacation'),
            self::STATUS_REJECTED => __('Отклонён', 'vacation'),
        ];
    }

    /**
     * Экранированный вывод значения из БД (с GLPI 11 текст хранится как есть).
     */
    public static function text($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public function getName($options = [])
    {
        if (empty($this->fields['id'])) {
            return '';
        }

        return sprintf(
            '%s: %s – %s',
            getUserName($this->fields['users_id']),
            Html::convDate($this->fields['date_begin']),
            Html::convDate($this->fields['date_end'])
        );
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab('Log', $tabs, $options);

        return $tabs;
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof User && self::canView()) {
            $count = 0;
            if ($_SESSION['glpishow_count_on_tabs']) {
                $count = countElementsInTable(self::getTable(), ['users_id' => $item->getID()]);
            }
            return self::createTabEntry(self::getTypeName(Session::getPluralNumber()), $count);
        }

        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if ($item instanceof User) {
            self::showForUser($item);
        }

        return true;
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(1),
        ];
        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('ID'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => 2,
            'table'    => 'glpi_users',
            'field'    => 'name',
            'name'     => __('Сотрудник', 'vacation'),
            'datatype' => 'dropdown',
            'right'    => 'all',
        ];
        $tab[] = [
            'id'       => 3,
            'table'    => $this->getTable(),
            'field'    => 'date_begin',
            'name'     => __('Начало', 'vacation'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'date_end',
            'name'     => __('Окончание', 'vacation'),
            'datatype' => 'date',
        ];
        $tab[] = [
            'id'            => 5,
            'table'         => $this->getTable(),
            'field'         => 'days_charged',
            'name'          => __('Дней отпуска', 'vacation'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 6,
            'table'         => $this->getTable(),
            'field'         => 'days_working',
            'name'          => __('Рабочих дней', 'vacation'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'         => 7,
            'table'      => $this->getTable(),
            'field'      => 'status',
            'name'       => __('Статус'),
            'datatype'   => 'specific',
            'searchtype' => ['equals', 'notequals'],
        ];
        $tab[] = [
            'id'            => 8,
            'table'         => $this->getTable(),
            'field'         => 'days_holiday',
            'name'          => __('Праздничных дней', 'vacation'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 9,
            'table'         => $this->getTable(),
            'field'         => 'days_calendar',
            'name'          => __('Календарных дней', 'vacation'),
            'datatype'      => 'integer',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'       => 16,
            'table'    => $this->getTable(),
            'field'    => 'comment',
            'name'     => __('Comments'),
            'datatype' => 'text',
        ];
        $tab[] = [
            'id'            => 19,
            'table'         => $this->getTable(),
            'field'         => 'date_mod',
            'name'          => __('Last update'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 80,
            'table'         => 'glpi_entities',
            'field'         => 'completename',
            'name'          => Entity::getTypeName(1),
            'datatype'      => 'dropdown',
            'massiveaction' => false,
        ];
        $tab[] = [
            'id'            => 121,
            'table'         => $this->getTable(),
            'field'         => 'date_creation',
            'name'          => __('Creation date'),
            'datatype'      => 'datetime',
            'massiveaction' => false,
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            return self::text(self::getStatuses()[(int) ($values[$field] ?? 0)] ?? '');
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        if ($field === 'status') {
            $options['display'] = false;
            $options['value']   = $values[$field];
            return Dropdown::showFromArray($name, self::getStatuses(), $options);
        }

        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    public function prepareInputForAdd($input)
    {
        if (!isset($input['entities_id'])) {
            $input['entities_id'] = $_SESSION['glpiactive_entity'];
        }
        $input['users_id_creator'] = Session::getLoginUserID();

        return $this->checkInput($input, false);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->checkInput($input, true);
    }

    /**
     * Проверка периода и расчёт дней по производственному календарю.
     *
     * @return array|false
     */
    private function checkInput(array $input, bool $is_update)
    {
        global $DB;

        // При добавлении $this->fields может остаться от ранее загруженной записи — не используем его
        $current  = $is_update ? $this->fields : [];
        $id       = $is_update ? (int) ($input['id'] ?? $current['id'] ?? 0) : 0;
        $users_id = (int) ($input['users_id'] ?? $current['users_id'] ?? 0);
        $begin    = $input['date_begin'] ?? $current['date_begin'] ?? '';
        $end      = $input['date_end'] ?? $current['date_end'] ?? '';
        $status   = (int) ($input['status'] ?? $current['status'] ?? self::STATUS_PLANNED);
        $days     = (int) ($input['days'] ?? 0);
        unset($input['days']);

        if ($users_id <= 0) {
            Session::addMessageAfterRedirect(__('Не выбран сотрудник', 'vacation'), false, ERROR);
            return false;
        }
        if (!PluginVacationCalendarDay::isValidDate($begin)) {
            Session::addMessageAfterRedirect(__('Укажите дату начала отпуска', 'vacation'), false, ERROR);
            return false;
        }
        // Длительность задана количеством дней — дату окончания считаем сами
        if (!PluginVacationCalendarDay::isValidDate($end) && $days > 0) {
            $end = PluginVacationCalendarDay::computeEndDate($begin, $days);
        }
        if (!PluginVacationCalendarDay::isValidDate($end)) {
            Session::addMessageAfterRedirect(
                __('Укажите дату окончания или количество дней отпуска', 'vacation'),
                false,
                ERROR
            );
            return false;
        }
        if ($end < $begin) {
            Session::addMessageAfterRedirect(__('Дата окончания раньше даты начала', 'vacation'), false, ERROR);
            return false;
        }
        if ((new DateTime($begin))->diff(new DateTime($end))->days > 366) {
            Session::addMessageAfterRedirect(__('Период отпуска больше года', 'vacation'), false, ERROR);
            return false;
        }

        if ($status !== self::STATUS_REJECTED) {
            $where = [
                'users_id'   => $users_id,
                'status'     => ['<>', self::STATUS_REJECTED],
                'date_begin' => ['<=', $end],
                'date_end'   => ['>=', $begin],
            ];
            if ($id > 0) {
                $where['id'] = ['<>', $id];
            }
            foreach ($DB->request(['FROM' => self::getTable(), 'WHERE' => $where, 'LIMIT' => 1]) as $row) {
                Session::addMessageAfterRedirect(sprintf(
                    __('У сотрудника уже есть отпуск, пересекающийся с этим периодом: %s – %s', 'vacation'),
                    Html::convDate($row['date_begin']),
                    Html::convDate($row['date_end'])
                ), false, ERROR);
                return false;
            }
        }

        for ($year = (int) substr($begin, 0, 4); $year <= (int) substr($end, 0, 4); $year++) {
            if (!PluginVacationCalendarDay::hasYear($year)) {
                Session::addMessageAfterRedirect(sprintf(
                    __('Производственный календарь на %d год не заполнен — праздничные дни не учтены', 'vacation'),
                    $year
                ), false, WARNING);
            }
        }

        $count = PluginVacationCalendarDay::countPeriod($begin, $end);
        $input['users_id']      = $users_id;
        $input['date_begin']    = $begin;
        $input['date_end']      = $end;
        $input['days_calendar'] = $count['calendar'];
        $input['days_holiday']  = $count['holidays'];
        $input['days_charged']  = $count['charged'];
        $input['days_working']  = $count['working'];

        return $input;
    }

    /**
     * Пересчитать сохранённые количества дней после изменения календаря.
     */
    public static function recomputeYear(int $year): int
    {
        global $DB;

        PluginVacationCalendarDay::clearCache();
        $updated  = 0;
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                ['date_begin' => ['<=', sprintf('%04d-12-31', $year)]],
                ['date_end'   => ['>=', sprintf('%04d-01-01', $year)]],
            ],
        ]);
        foreach ($iterator as $row) {
            $count  = PluginVacationCalendarDay::countPeriod($row['date_begin'], $row['date_end']);
            $values = [
                'days_calendar' => $count['calendar'],
                'days_holiday'  => $count['holidays'],
                'days_charged'  => $count['charged'],
                'days_working'  => $count['working'],
            ];
            foreach ($values as $field => $value) {
                if ((int) $row[$field] !== $value) {
                    $DB->update(self::getTable(), $values, ['id' => $row['id']]);
                    $updated++;
                    break;
                }
            }
        }

        return $updated;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);

        if ($this->isNewID($ID)) {
            if (!empty($options['users_id'])) {
                $this->fields['users_id'] = (int) $options['users_id'];
            }
            if (PluginVacationCalendarDay::isValidDate($options['date_begin'] ?? null)) {
                $this->fields['date_begin'] = $options['date_begin'];
            }
            $this->fields['status'] = $this->fields['status'] ?: self::STATUS_PLANNED;
        }

        $this->showFormHeader($options);
        $rand = mt_rand();

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Сотрудник', 'vacation') . " <span class='red'>*</span></td><td>";
        User::dropdown([
            'name'   => 'users_id',
            'value'  => $this->fields['users_id'],
            'right'  => 'all',
            'entity' => $_SESSION['glpiactiveentities'],
        ]);
        echo "</td>";
        echo "<td>" . __('Статус') . "</td><td>";
        Dropdown::showFromArray('status', self::getStatuses(), ['value' => $this->fields['status']]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Начало', 'vacation') . " <span class='red'>*</span></td><td>";
        Html::showDateField('date_begin', ['value' => $this->fields['date_begin'], 'maybeempty' => false]);
        echo "</td>";
        echo "<td>" . __('Окончание', 'vacation') . "</td><td>";
        Html::showDateField('date_end', ['value' => $this->fields['date_end']]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Количество дней', 'vacation')
            . "<br><small class='text-muted'>календарных, без праздников</small></td><td>";
        echo "<input type='number' name='days' min='1' max='366' class='form-control' style='max-width:120px'"
            . " value='" . ((int) $this->fields['days_charged'] ?: '') . "'>";
        echo "</td>";
        echo "<td>" . __('Расчёт', 'vacation') . "</td>";
        echo "<td><span id='vac-preview-$rand' class='vac-preview'></span></td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Comments') . "</td>";
        echo "<td colspan='3'><textarea name='comment' rows='3' class='form-control'>"
            . self::text($this->fields['comment']) . "</textarea></td></tr>";

        if (!$this->isNewID($ID)) {
            echo "<tr class='tab_bg_1'><td>" . __('Автор', 'vacation') . "</td><td>"
                . self::text(getUserName($this->fields['users_id_creator'])) . "</td>";
            echo "<td>" . __('Creation date') . "</td><td>" . Html::convDateTime($this->fields['date_creation']) . "</td></tr>";
        }

        $this->showFormButtons($options);

        // Живой расчёт дней в браузере по тем же правилам, что и на сервере
        $year  = (int) date('Y');
        if (PluginVacationCalendarDay::isValidDate($this->fields['date_begin'])) {
            $year = (int) substr($this->fields['date_begin'], 0, 4);
        }
        $years   = [$year - 1, $year, $year + 1, $year + 2];
        $missing = array_values(array_filter($years, function ($y) {
            return !PluginVacationCalendarDay::hasYear($y);
        }));
        $cal = json_encode((object) PluginVacationCalendarDay::getJsData($years));
        $mis = json_encode($missing);

        echo Html::scriptBlock(<<<JS
(function () {
    var cal = $cal, missing = $mis;
    var preview = document.getElementById('vac-preview-$rand');
    if (!preview) { return; }
    var form = preview.closest('form');
    var inBegin = form.querySelector('[name="date_begin"]');
    var inEnd = form.querySelector('[name="date_end"]');
    var inDays = form.querySelector('[name="days"]');

    function parse(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(s || '');
        return m ? new Date(Date.UTC(+m[1], +m[2] - 1, +m[3])) : null;
    }
    function iso(d) { return d.toISOString().slice(0, 10); }
    function next(d) { return new Date(d.getTime() + 86400000); }
    function type(d) { return cal[iso(d)] || 0; }
    function isHoliday(d) { return type(d) === 1; }
    function isNonWorking(d) {
        var t = type(d);
        if (t === 1 || t === 2) { return true; }
        if (t === 3 || t === 4) { return false; }
        return d.getUTCDay() === 0 || d.getUTCDay() === 6;
    }
    function setDate(input, value) {
        var host = input.closest('.flatpickr');
        var fp = (host && host._flatpickr) || input._flatpickr;
        if (fp) { fp.setDate(value, false); } else { input.value = value; }
    }
    function update() {
        var b = parse(inBegin.value), e = parse(inEnd.value);
        if (!b || !e || e < b) { preview.textContent = ''; return; }
        var total = 0, hol = 0, work = 0, warn = false;
        for (var d = b; d <= e && total < 1000; d = next(d)) {
            total++;
            if (isHoliday(d)) { hol++; }
            if (!isNonWorking(d)) { work++; }
            if (missing.indexOf(d.getUTCFullYear()) !== -1) { warn = true; }
        }
        inDays.value = total - hol;
        preview.textContent = 'календарных: ' + total + ', праздничных: ' + hol
            + ' → дней отпуска: ' + (total - hol) + ', рабочих дней: ' + work
            + (warn ? ' (календарь на год не заполнен!)' : '');
    }
    function fromDays() {
        var b = parse(inBegin.value), n = parseInt(inDays.value, 10);
        if (!b || !(n > 0)) { update(); return; }
        var d = b, c = 0;
        for (var i = 0; i < 1000; i++) {
            if (!isHoliday(d)) { c++; if (c >= n) { break; } }
            d = next(d);
        }
        setDate(inEnd, iso(d));
        update();
    }
    inBegin.addEventListener('change', function () { if (parseInt(inDays.value, 10) > 0) { fromDays(); } else { update(); } });
    inEnd.addEventListener('change', update);
    inDays.addEventListener('change', fromDays);
    update();
})();
JS);

        return true;
    }

    /**
     * Вкладка «Отпуска» в карточке пользователя.
     */
    public static function showForUser(User $user): void
    {
        global $DB;

        $users_id = $user->getID();
        $statuses = self::getStatuses();
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['users_id' => $users_id] + getEntitiesRestrictCriteria(self::getTable()),
            'ORDER' => ['date_begin DESC'],
        ]);

        echo "<div class='vac-page'>";
        if (self::canCreate()) {
            echo "<p><a class='btn btn-primary' href='" . self::getFormURL() . "?users_id=$users_id'>"
                . "<i class='ti ti-plus'></i> " . __('Добавить отпуск', 'vacation') . "</a> ";
            echo "<a class='btn btn-outline-secondary' href='" . PluginVacationMenu::getUrl('schedule.php') . "'>"
                . "<i class='ti ti-chart-bar'></i> " . __('График отпусков', 'vacation') . "</a></p>";
        }

        if (count($iterator) === 0) {
            echo "<p>" . __('Отпусков нет', 'vacation') . "</p></div>";
            return;
        }

        $by_year = [];
        foreach ($iterator as $row) {
            $by_year[(int) substr($row['date_begin'], 0, 4)][] = $row;
        }

        echo "<table class='table table-sm table-hover'>";
        echo "<thead><tr><th>Период</th><th>Календарных</th><th>Праздничных</th><th>Дней отпуска</th>"
            . "<th>Рабочих</th><th>Статус</th><th>Комментарий</th></tr></thead>";
        foreach ($by_year as $year => $rows) {
            $total = 0;
            echo "<tbody><tr class='table-active'><th colspan='7'>$year</th></tr>";
            foreach ($rows as $row) {
                if ((int) $row['status'] !== self::STATUS_REJECTED) {
                    $total += (int) $row['days_charged'];
                }
                $class = (int) $row['status'] === self::STATUS_REJECTED ? " class='text-muted text-decoration-line-through'" : '';
                echo "<tr$class>";
                echo "<td><a href='" . self::getFormURLWithID($row['id']) . "'>"
                    . Html::convDate($row['date_begin']) . " – " . Html::convDate($row['date_end']) . "</a></td>";
                echo "<td>{$row['days_calendar']}</td><td>{$row['days_holiday']}</td>";
                echo "<td><b>{$row['days_charged']}</b></td><td>{$row['days_working']}</td>";
                echo "<td><span class='vac-sw vac-st{$row['status']}'></span>" . ($statuses[$row['status']] ?? '') . "</td>";
                echo "<td>" . self::text($row['comment']) . "</td>";
                echo "</tr>";
            }
            echo "<tr><td colspan='3' class='text-end'>Итого за $year (без отклонённых):</td>"
                . "<td colspan='4'><b>$total</b></td></tr></tbody>";
        }
        echo "</table></div>";
    }
}
