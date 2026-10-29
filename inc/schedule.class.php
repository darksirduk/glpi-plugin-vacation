<?php

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Сводный график отпусков: строки — сотрудники, столбцы — дни периода.
 */
class PluginVacationSchedule extends CommonGLPI
{
    public static string $rightname = PLUGIN_VACATION_RIGHT;

    public static function getTypeName($nb = 0)
    {
        return __('График отпусков', 'vacation');
    }

    public static function getParams(array $request): array
    {
        $year = (int) ($request['year'] ?? date('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) date('Y');
        }
        $month_from = max(1, min(12, (int) ($request['month_from'] ?? 1)));
        $month_to   = max($month_from, min(12, (int) ($request['month_to'] ?? 12)));

        return [
            'year'          => $year,
            'month_from'    => $month_from,
            'month_to'      => $month_to,
            'groups_id'     => max(0, (int) ($request['groups_id'] ?? 0)),
            'show_rejected' => !empty($request['show_rejected']),
            'max_absent'    => max(0, (int) ($request['max_absent'] ?? 0)),
        ];
    }

    public static function getPeriod(array $params): array
    {
        return [
            sprintf('%04d-%02d-01', $params['year'], $params['month_from']),
            date('Y-m-t', mktime(0, 0, 0, $params['month_to'], 1, $params['year'])),
        ];
    }

    /**
     * @return array ['users' => [id => ФИО], 'vacations' => [users_id => [rows]]]
     */
    public static function getData(array $params): array
    {
        global $DB;

        [$begin, $end] = self::getPeriod($params);
        $table = PluginVacationVacation::getTable();

        $where = [
            ['date_begin' => ['<=', $end]],
            ['date_end'   => ['>=', $begin]],
        ];
        if (!$params['show_rejected']) {
            $where[] = ['status' => ['<>', PluginVacationVacation::STATUS_REJECTED]];
        }
        $where = array_merge($where, getEntitiesRestrictCriteria($table));

        // Участники группы показываются, даже если отпусков у них ещё нет
        $user_ids = [];
        if ($params['groups_id'] > 0) {
            $iterator = $DB->request([
                'SELECT' => ['glpi_groups_users.users_id'],
                'FROM'   => 'glpi_groups_users',
                'INNER JOIN' => [
                    'glpi_users' => ['ON' => ['glpi_groups_users' => 'users_id', 'glpi_users' => 'id']],
                ],
                'WHERE'  => [
                    'glpi_groups_users.groups_id' => $params['groups_id'],
                    'glpi_users.is_active'        => 1,
                    'glpi_users.is_deleted'       => 0,
                ],
            ]);
            foreach ($iterator as $row) {
                $user_ids[] = (int) $row['users_id'];
            }
            $where[] = ['users_id' => count($user_ids) > 0 ? $user_ids : [0]];
        }

        $vacations = [];
        foreach ($DB->request(['FROM' => $table, 'WHERE' => $where, 'ORDER' => ['date_begin']]) as $row) {
            $vacations[(int) $row['users_id']][] = $row;
            $user_ids[] = (int) $row['users_id'];
        }
        $user_ids = array_values(array_unique($user_ids));

        $users = [];
        if (count($user_ids) > 0) {
            $iterator = $DB->request([
                'SELECT' => ['id', 'name', 'realname', 'firstname'],
                'FROM'   => 'glpi_users',
                'WHERE'  => ['id' => $user_ids],
            ]);
            foreach ($iterator as $row) {
                $users[(int) $row['id']] = formatUserName(
                    (int) $row['id'],
                    $row['name'],
                    $row['realname'],
                    $row['firstname']
                );
            }
            asort($users, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return ['begin' => $begin, 'end' => $end, 'users' => $users, 'vacations' => $vacations];
    }

    public static function show(array $params): void
    {
        $url      = PluginVacationMenu::getUrl('schedule.php');
        $form_url = PluginVacationVacation::getFormURL();
        $months   = PluginVacationCalendarDay::getMonthNames();
        $statuses = PluginVacationVacation::getStatuses();
        $cancreate = PluginVacationVacation::canCreate();

        echo "<div class='vac-page'>";

        // Фильтр
        echo "<form method='get' action='$url' class='vac-filter'>";
        echo "<label>Год ";
        Dropdown::showNumber('year', [
            'value' => $params['year'],
            'min'   => (int) date('Y') - 5,
            'max'   => (int) date('Y') + 5,
        ]);
        echo "</label>";
        echo "<label>С ";
        Dropdown::showFromArray('month_from', $months, ['value' => $params['month_from']]);
        echo "</label>";
        echo "<label>по ";
        Dropdown::showFromArray('month_to', $months, ['value' => $params['month_to']]);
        echo "</label>";
        echo "<label>Группа ";
        Group::dropdown([
            'name'      => 'groups_id',
            'value'     => $params['groups_id'],
            'entity'    => $_SESSION['glpiactiveentities'],
            'condition' => ['is_usergroup' => 1],
        ]);
        echo "</label>";
        echo "<label title='Подсвечивать дни, когда отсутствует больше указанного числа сотрудников'>Порог отсутствующих ";
        echo "<input type='number' name='max_absent' min='0' max='999' class='form-control' style='width:80px'"
            . " value='" . ($params['max_absent'] ?: '') . "'></label>";
        echo "<label class='form-check'><input type='checkbox' class='form-check-input' name='show_rejected' value='1'"
            . ($params['show_rejected'] ? ' checked' : '') . "> отклонённые</label>";
        echo "<button type='submit' class='btn btn-primary'><i class='ti ti-filter'></i> Показать</button>";
        echo "<button type='submit' name='export' value='csv' class='btn btn-outline-secondary'>"
            . "<i class='ti ti-file-spreadsheet'></i> CSV</button>";
        echo "<button type='submit' name='export' value='xlsx' class='btn btn-outline-success'"
            . " title='График отпусков на весь год по форме: даты отпусков сотрудника в одной ячейке'>"
            . "<i class='ti ti-file-type-xls'></i> Excel</button>";
        if ($cancreate) {
            echo "<a class='btn btn-outline-primary' href='$form_url'><i class='ti ti-plus'></i> Добавить отпуск</a>";
        }
        echo "</form>";

        $year = $params['year'];
        if (!PluginVacationCalendarDay::hasYear($year)) {
            echo "<div class='alert alert-warning'>Производственный календарь на $year год не заполнен — "
                . "праздничные дни и переносы не учитываются. ";
            if (PluginVacationCalendarDay::canView()) {
                echo "<a href='" . PluginVacationMenu::getUrl('calendar.php') . "?year=$year'>Открыть календарь</a>";
            }
            echo "</div>";
        }

        PluginVacationCalendarDay::showLegend(true);

        $data = self::getData($params);
        if (count($data['users']) === 0) {
            echo "<div class='alert alert-info'>За выбранный период отпусков нет."
                . ($cancreate ? " Выберите группу, чтобы планировать отпуска её участникам прямо в графике." : '')
                . "</div></div>";
            return;
        }

        // Дни периода
        $days       = [];
        $month_span = [];
        $day        = new DateTime($data['begin']);
        $last       = new DateTime($data['end']);
        while ($day <= $last) {
            $date = $day->format('Y-m-d');
            $info = PluginVacationCalendarDay::getDayInfo($date);
            $days[$date] = [
                'num'   => (int) $day->format('j'),
                'class' => PluginVacationCalendarDay::getCssClass($date),
                'title' => Html::convDate($date) . ($info['name'] !== '' ? ' — ' . $info['name'] : ''),
            ];
            $month = (int) $day->format('n');
            $month_span[$month] = ($month_span[$month] ?? 0) + 1;
            $day->modify('+1 day');
        }
        $absent = array_fill_keys(array_keys($days), 0);

        echo "<div class='vac-grid-wrap'><table class='vac-grid'>";
        echo "<thead><tr><th class='vac-name' rowspan='2'>Сотрудник</th>"
            . "<th class='vac-total' rowspan='2' title='Дней отпуска в периоде (без праздников)'>Дней</th>";
        foreach ($month_span as $month => $span) {
            echo "<th colspan='$span' class='vac-mhead'>{$months[$month]}</th>";
        }
        echo "</tr><tr>";
        foreach ($days as $date => $d) {
            $first = $d['num'] === 1 ? ' vac-mstart' : '';
            echo "<th class='vac-day {$d['class']}$first' title='" . htmlspecialchars($d['title'], ENT_QUOTES) . "'>{$d['num']}</th>";
        }
        echo "</tr></thead><tbody>";

        foreach ($data['users'] as $users_id => $user_name) {
            $map   = [];
            $total = 0;
            foreach ($data['vacations'][$users_id] ?? [] as $vacation) {
                $from = max($vacation['date_begin'], $data['begin']);
                $to   = min($vacation['date_end'], $data['end']);
                if ((int) $vacation['status'] !== PluginVacationVacation::STATUS_REJECTED) {
                    $total += PluginVacationCalendarDay::countPeriod($from, $to)['charged'];
                }
                $title = sprintf(
                    '%s: %s – %s, %d дн. (%s)',
                    $user_name,
                    Html::convDate($vacation['date_begin']),
                    Html::convDate($vacation['date_end']),
                    $vacation['days_charged'],
                    $statuses[$vacation['status']] ?? ''
                );
                if ($vacation['comment'] !== null && $vacation['comment'] !== '') {
                    $title .= "\n" . $vacation['comment'];
                }
                $cursor = new DateTime($from);
                while ($cursor->format('Y-m-d') <= $to) {
                    $date = $cursor->format('Y-m-d');
                    // Отклонённый отпуск не перекрывает действующий
                    if (!isset($map[$date]) || (int) $map[$date]['status'] === PluginVacationVacation::STATUS_REJECTED) {
                        $map[$date] = ['id' => $vacation['id'], 'status' => (int) $vacation['status'], 'title' => $title];
                    }
                    $cursor->modify('+1 day');
                }
            }

            echo "<tr><td class='vac-name' title='" . htmlspecialchars($user_name, ENT_QUOTES) . "'>"
                . "<a href='" . User::getFormURLWithID($users_id) . "&forcetab=PluginVacationVacation\$1'>"
                . htmlspecialchars($user_name, ENT_QUOTES) . "</a></td>";
            echo "<td class='vac-total'>$total</td>";
            foreach ($days as $date => $d) {
                $class = 'vac-day ' . $d['class'] . ($d['num'] === 1 ? ' vac-mstart' : '');
                if (isset($map[$date])) {
                    $v = $map[$date];
                    if ($v['status'] !== PluginVacationVacation::STATUS_REJECTED) {
                        $absent[$date]++;
                    }
                    echo "<td class='$class vac-st{$v['status']}' data-v='{$v['id']}'"
                        . " title='" . htmlspecialchars($v['title'] . "\n" . $d['title'], ENT_QUOTES) . "'></td>";
                } elseif ($cancreate) {
                    echo "<td class='$class vac-free' data-u='$users_id' data-d='$date'"
                        . " title='" . htmlspecialchars($d['title'], ENT_QUOTES) . "'></td>";
                } else {
                    echo "<td class='$class'></td>";
                }
            }
            echo "</tr>";
        }
        echo "</tbody><tfoot><tr><th class='vac-name'>Отсутствуют</th><th class='vac-total'></th>";
        foreach ($days as $date => $d) {
            $count = $absent[$date];
            $over  = $params['max_absent'] > 0 && $count > $params['max_absent'] ? ' vac-over' : '';
            echo "<td class='vac-day vac-count$over'>" . ($count ?: '') . "</td>";
        }
        echo "</tr></tfoot></table></div>";

        if ($cancreate) {
            echo "<p class='text-muted mt-2'>Щёлкните по свободной клетке, чтобы запланировать отпуск сотруднику "
                . "с этой даты; по цветной — чтобы открыть отпуск.</p>";
        }

        $js_url = json_encode($form_url);
        echo Html::scriptBlock("
            document.querySelector('.vac-grid').addEventListener('click', function (e) {
                var td = e.target.closest('td');
                if (!td) { return; }
                if (td.dataset.v) {
                    window.location = $js_url + '?id=' + td.dataset.v;
                } else if (td.dataset.u) {
                    window.location = $js_url + '?users_id=' + td.dataset.u + '&date_begin=' + td.dataset.d;
                }
            });
        ");

        echo "</div>";
    }

    public static function exportCsv(array $params): void
    {
        $data     = self::getData($params);
        $statuses = PluginVacationVacation::getStatuses();

        header('Content-Type: text/csv; charset=UTF-8');
        header(sprintf(
            'Content-Disposition: attachment; filename="vacations_%d_%02d-%02d.csv"',
            $params['year'],
            $params['month_from'],
            $params['month_to']
        ));

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Сотрудник', 'Начало', 'Окончание', 'Календарных дней', 'Праздничных дней',
            'Дней отпуска', 'Рабочих дней', 'Статус', 'Комментарий',
        ], ';');
        foreach ($data['users'] as $users_id => $user_name) {
            $rows = $data['vacations'][$users_id] ?? [];
            if (count($rows) === 0) {
                fputcsv($out, [$user_name], ';');
                continue;
            }
            foreach ($rows as $row) {
                fputcsv($out, [
                    $user_name,
                    Html::convDate($row['date_begin']),
                    Html::convDate($row['date_end']),
                    $row['days_calendar'],
                    $row['days_holiday'],
                    $row['days_charged'],
                    $row['days_working'],
                    $statuses[$row['status']] ?? '',
                    (string) $row['comment'],
                ], ';');
            }
        }
        fclose($out);
    }

    /**
     * Выгрузка в Excel по форме «График отпусков»: №, сотрудник, должность,
     * все периоды отпуска за год в одной ячейке, количество календарных дней.
     * Выгружается весь год (фильтр месяцев не учитывается), отклонённые отпуска не попадают.
     * Без фильтра по группе — блоками по всем группам (см. getExportSections).
     */
    public static function exportXlsx(array $params): void
    {
        global $DB;

        $year = $params['year'];
        $data = self::getData(['month_from' => 1, 'month_to' => 12, 'show_rejected' => false] + $params);

        $sections = self::getExportSections($params, $data);
        $user_ids = array_merge([], ...array_values($sections));

        // ФИО полностью и должность
        $people = [];
        if (count($user_ids) > 0) {
            $iterator = $DB->request([
                'SELECT'    => ['glpi_users.id', 'glpi_users.name', 'glpi_users.realname', 'glpi_users.firstname', 'glpi_usertitles.name AS title'],
                'FROM'      => 'glpi_users',
                'LEFT JOIN' => [
                    'glpi_usertitles' => ['ON' => ['glpi_users' => 'usertitles_id', 'glpi_usertitles' => 'id']],
                ],
                'WHERE'     => ['glpi_users.id' => $user_ids],
            ]);
            foreach ($iterator as $row) {
                $fio = trim(($row['realname'] ?? '') . ' ' . ($row['firstname'] ?? ''));
                $people[(int) $row['id']] = [
                    'name'  => $fio !== '' ? $fio : (string) $row['name'],
                    'title' => (string) ($row['title'] ?? ''),
                ];
            }
        }

        $department = $params['groups_id'] > 0 ? (string) array_key_first($sections) : 'все отделы';

        $book  = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('График отпусков ' . $year);
        $book->getDefaultStyle()->getFont()->setName('Arial')->setSize(8);

        foreach (['A' => 8.5, 'B' => 51, 'C' => 40.7, 'D' => 47.3, 'E' => 26.3] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $border = ['borders' => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN]]];
        $wrap   = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;

        $sheet->mergeCells('A1:C1');
        $sheet->getRowDimension(1)->setRowHeight(12.75);

        // Шапка
        $sheet->fromArray([
            '№',
            'Сотрудник',
            'Должность',
            'Дата предоставления отпуска на ' . $year . ' год',
            'Количество календарных дней',
        ], null, 'A2');
        $sheet->getRowDimension(2)->setRowHeight(33);
        $sheet->getStyle('A2:E2')->applyFromArray($border + [
            'font'      => ['size' => 12],
            'alignment' => ['horizontal' => $wrap::HORIZONTAL_CENTER, 'vertical' => $wrap::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $row = 3;
        $num = 1;
        foreach ($sections as $section => $members) {
            // Строка отдела
            $sheet->setCellValueExplicit("A$row", (string) $section, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $sheet->mergeCells("A$row:C$row");
            $sheet->getStyle("A$row:E$row")->applyFromArray($border + ['font' => ['bold' => true]]);
            $sheet->getRowDimension($row)->setRowHeight(15);
            $row++;

            $members = array_values(array_filter($members, fn($id) => isset($people[$id])));
            usort($members, fn($a, $b) => strnatcasecmp($people[$a]['name'], $people[$b]['name']));
            foreach ($members as $users_id) {
                $row = self::writeExportRow($sheet, $row, $num++, $people[$users_id], $data['vacations'][$users_id] ?? [], $border);
            }
        }

        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0)
            ->setRowsToRepeatAtTopByStartAndEnd(2, 2);

        $filename = sprintf('График отпусков %d %s.xlsx', $year, preg_replace('/[\\\\\/:*?"<>|]+/u', '_', $department));
        $ascii    = sprintf('vacations_%d.xlsx', $year);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header(sprintf(
            "Content-Disposition: attachment; filename=\"%s\"; filename*=UTF-8''%s",
            $ascii,
            rawurlencode($filename)
        ));
        header('Cache-Control: max-age=0');

        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
    }

    /**
     * Блоки выгрузки: [название отдела => [users_id, ...]].
     * Без фильтра по группе — все группы пользователей, в которых за год есть отпуска;
     * сотрудник попадает один раз: в свою группу по умолчанию, иначе в первую по алфавиту.
     */
    private static function getExportSections(array $params, array $data): array
    {
        global $DB;

        if ($params['groups_id'] > 0) {
            $group = new Group();
            $name  = $group->getFromDB($params['groups_id']) ? $group->fields['name'] : 'Сотрудники';
            return [$name => array_keys($data['users'])];
        }

        $iterator = $DB->request([
            'SELECT'     => [
                'glpi_groups.id AS gid',
                'glpi_groups.completename',
                'glpi_users.id AS uid',
                'glpi_users.groups_id AS default_gid',
            ],
            'FROM'       => 'glpi_groups_users',
            'INNER JOIN' => [
                'glpi_groups' => ['ON' => ['glpi_groups_users' => 'groups_id', 'glpi_groups' => 'id']],
                'glpi_users'  => ['ON' => ['glpi_groups_users' => 'users_id', 'glpi_users' => 'id']],
            ],
            'WHERE'      => array_merge([
                'glpi_groups.is_usergroup' => 1,
                'glpi_users.is_active'     => 1,
                'glpi_users.is_deleted'    => 0,
            ], getEntitiesRestrictCriteria('glpi_groups', '', '', true)),
            'ORDER'      => ['glpi_groups.completename'],
        ]);

        $names      = [];
        $user_group = [];
        foreach ($iterator as $row) {
            $gid = (int) $row['gid'];
            $uid = (int) $row['uid'];
            $names[$gid] = $row['completename'];
            if (!isset($user_group[$uid]) || $gid === (int) $row['default_gid']) {
                $user_group[$uid] = $gid;
            }
        }

        $sections = [];
        foreach ($names as $gid => $name) {
            $members = array_keys($user_group, $gid, true);
            if (count(array_intersect($members, array_keys($data['vacations']))) > 0) {
                $sections[$name] = $members;
            }
        }
        $orphans = array_values(array_diff(array_keys($data['vacations']), array_keys($user_group)));
        if (count($orphans) > 0) {
            $sections['Без группы'] = $orphans;
        }
        return $sections;
    }

    private static function writeExportRow($sheet, int $row, int $num, array $person, array $vacations, array $border): int
    {
        $align = \PhpOffice\PhpSpreadsheet\Style\Alignment::class;
        $text  = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING;

        $periods = [];
        $total   = 0;
        foreach ($vacations as $vacation) {
            $periods[] = sprintf(
                'с %s по %s (%d дн.)',
                date('d.m.Y', strtotime($vacation['date_begin'])),
                date('d.m.Y', strtotime($vacation['date_end'])),
                $vacation['days_charged']
            );
            $total += (int) $vacation['days_charged'];
        }

        $sheet->setCellValue("A$row", $num);
        $sheet->setCellValueExplicit("B$row", $person['name'], $text);
        $sheet->setCellValueExplicit("C$row", $person['title'], $text);
        $sheet->setCellValueExplicit("D$row", implode("\n", $periods), $text);
        if ($total > 0) {
            $sheet->setCellValue("E$row", $total);
        }
        $sheet->getStyle("A$row:E$row")->applyFromArray($border + [
            'alignment' => ['vertical' => $align::VERTICAL_TOP, 'wrapText' => true],
        ]);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal($align::HORIZONTAL_RIGHT);
        $sheet->getStyle("E$row")->getAlignment()->setHorizontal($align::HORIZONTAL_CENTER);
        // Высота строки под число периодов: Excel сам её не подбирает
        $sheet->getRowDimension($row)->setRowHeight(max(22.5, 11.5 * count($periods) + 4));

        return $row + 1;
    }
}
