<?php

Session::checkRight(PLUGIN_VACATION_RIGHT, READ);

$vacation = new PluginVacationVacation();

if (isset($_POST['add'])) {
    $vacation->check(-1, CREATE, $_POST);
    if ($vacation->add($_POST) && $_SESSION['glpibackcreated']) {
        Html::redirect($vacation->getLinkURL());
    }
    Html::back();
} elseif (isset($_POST['update'])) {
    $vacation->check($_POST['id'], UPDATE);
    $vacation->update($_POST);
    Html::back();
} elseif (isset($_POST['purge'])) {
    $vacation->check($_POST['id'], PURGE);
    $vacation->delete($_POST, 1);
    $vacation->redirectToList();
} else {
    Html::header(
        PluginVacationVacation::getTypeName(Session::getPluralNumber()),
        $_SERVER['PHP_SELF'],
        'tools',
        'pluginvacationmenu',
        'vacation'
    );

    $id = (int) ($_GET['id'] ?? 0);
    if ($id > 0) {
        $vacation->display(['id' => $id]);
    } else {
        $vacation->check(-1, CREATE);
        $vacation->showForm(0, [
            'users_id'   => (int) ($_GET['users_id'] ?? 0),
            'date_begin' => $_GET['date_begin'] ?? '',
        ]);
    }

    Html::footer();
}
