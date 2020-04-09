<?php

global $CONF_HD;

$CONF['title_header'] = 'HD > Поиск';

require_once $CONF_HD['root'] . '/app/controllers/head.inc.php';
require_once $CONF_HD['root'] . '/app/controllers/navbar.inc.php';

try {
    require_once $CONF_HD['root'] . '/library/Twig/Autoloader.php';

    $loader = new Twig_Loader_Filesystem($CONF_HD['root'] . '/app/views');
    $twig   = new Twig_Environment($loader);
    $data   = [];

    switch ($_GET['mode']) {
        case 'archive':
            $user = new UserHelper($_SESSION['helpdesk_user_id'], $dbConnection);
            $user = $user->getUserData('department:extended');

            $input = str_replace('%', '', $_GET['input']) . '%';

            switch ($user['user']['priv']) {
                case 0:
                    $condition = "AND t.unit_id IN (".$user['user']['unit'].")";
                    break;
                case 2://everywhere
                    $condition = "";
                    break;
            }

            $stmt = $dbConnection->prepare(
                "SELECT t.id, t.user_init_id, t.user_to_id, t.date_create, t.subj, t.sabj_pl, t.msg, t.client_id, t.unit_id, t.status, t.hash_name, t.comment, t.is_read, t.lock_by, t.ok_by, t.ok_date
                            FROM tickets AS t
                            LEFT JOIN comments c ON t.id = c.t_id
                            WHERE t.arch = :archive AND t.id = :idt OR c.comment_text LIKE :a OR t.subj LIKE :b OR t.msg LIKE :msg $condition GROUP BY t.id ORDER BY t.id DESC"
            );

            $stmt->execute([
                ':idt'     => $input,
                ':a'       => $input,
                ':b'       => $input,
                ':msg'     => $input,
                ':archive' => '1',
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $userObserver = new UserObserver($dbConnection);

            foreach ($data as $k => $row) {
                $data[$k]['user_init_id'] = $userObserver->getUserData($row['user_init_id']);
                $data[$k]['user_to_id']   = $userObserver->getUserData($row['user_to_id']);
                $data[$k]['client_id']    = $userObserver->getUserData($row['client_id']);
                $data[$k]['ok_by']        = $userObserver->getUserData($row['ok_by']);
            }

            $template = $twig->loadTemplate('/tickets/arch.view.tmpl');
            break;

        case 'out':
            $user = new UserHelper($_SESSION['helpdesk_user_id'], $dbConnection);
            $user = $user->getUserData('department:extended');

            $input = str_replace('%', '', $_GET['input']) . '%';

            $collegues   = f3pick($user['collegues'], 'uid');
            $departments = array_unique(f3pick($user['collegues'], 'unit'));

            switch ($user['user']['priv']) {
                case 0:
                    $condition = "AND user_init_id IN (" . implode(',', $collegues) . ") AND unit_id IN (" . implode(',', $departments) . ")";
                    break;
                case 2://everywhere
                    $condition = "";
                    break;
            }

            $stmt = $dbConnection->prepare(
                "SELECT t.id, t.user_init_id, t.user_to_id, t.date_create, t.subj, t.sabj_pl, t.msg, t.client_id, t.unit_id, t.status, t.hash_name, t.comment, t.is_read, t.lock_by, t.ok_by, t.ok_date 
                            FROM tickets AS t
                            LEFT JOIN comments c ON t.id = c.t_id
                            WHERE arch != :archive AND t.id = :idt OR c.comment_text LIKE :a OR t.subj LIKE :b OR t.msg LIKE :msg $condition GROUP BY t.id ORDER BY t.id DESC"
            );

            $stmt->execute([
                ':archive' => 1,
                ':idt'     => $input,
                ':a'       => $input,
                ':b'       => $input,
                ':msg'     => $input,
            ]);

            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $userObserver = new UserObserver($dbConnection);

            foreach ($data as $k => $row) {
                $data[$k]['user_init_id'] = $userObserver->getUserData($row['user_init_id']);
                $data[$k]['user_to_id']   = $userObserver->getUserData($row['user_to_id']);
                $data[$k]['client_id']    = $userObserver->getUserData($row['client_id']);
                $data[$k]['ok_by']        = $userObserver->getUserData($row['ok_by']);
            }

            $template = $twig->loadTemplate('/tickets/out.view.tmpl');

            break;

        default:
            $template = $twig->loadTemplate('/client.404.view.tmpl');
    }

    echo $template->render([
        'data' => $data
    ]);

} catch (Exception $e) {
    die($e->getMessage());
}

require_once ($CONF_HD['root'] . '/app/controllers/footer.inc.php');
