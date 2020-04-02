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

            switch ($user['user']['priv']) {
                case 0:
                    $condition = "AND unit_id IN (".$user['user']['unit'].")";
                    break;
                case 2://everywhere
                    $condition = "";
                    break;
            }

            $stmt = $dbConnection->prepare(
                "SELECT t_id FROM comments LEFT JOIN tickets ON comments.t_id=tickets.id WHERE comments.comment_text LIKE :a OR tickets.subj LIKE :b"
            );

            $stmt->execute([
                ':a' => $_GET['input'],
                ':b' => $_GET['input'],
            ]);

            $idts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($idts) {
                $idts = implode(',', f3pick($idts, 't_id'));

                $stmt = $dbConnection->prepare(
                    "SELECT id, user_init_id, user_to_id, date_create, subj, sabj_pl, msg, client_id, unit_id, status, hash_name, comment, is_read, lock_by, ok_by, ok_date FROM tickets 
                            WHERE id IN ($idts) AND arch = :archive $condition ORDER BY id DESC"
                );

                $stmt->execute([
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
            }

            $template = $twig->loadTemplate('/tickets/arch.view.tmpl');
            break;

        case 'out':
            $user = new UserHelper($_SESSION['helpdesk_user_id'], $dbConnection);
            $user = $user->getUserData('department:extended');

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
                "SELECT id, user_init_id, user_to_id, date_create, subj, sabj_pl, msg, client_id, unit_id, status, hash_name, comment, is_read, lock_by, ok_by, ok_date FROM tickets
                            WHERE arch != :archive AND (msg LIKE :a OR subj LIKE :b) $condition ORDER BY id DESC"
            );

            $stmt->execute([
                ':archive' => 1,
                ':a'       => $_GET['input'],
                ':b'       => $_GET['input']
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
