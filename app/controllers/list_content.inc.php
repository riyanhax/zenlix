<?php
session_start();
require_once "/home/admin/web/alex.example.com/public_html/functions.inc.php";

if ($CONF_HD['debug_mode'] == true) {
    error_reporting(E_ALL ^ E_NOTICE);
    error_reporting(0);
}

if (isset($_POST['menu'])) {
    /* get ticket`s types */
    $stmt = $dbConnection->prepare('SELECT id, name FROM subj');
    $stmt->execute();
    $ticketTypes = $stmt->fetchAll();

    $types = f3pick($ticketTypes,'id'); // got all tickets types

    if ($_SESSION['hd.no_display'] === 'no_long_term') {
        unset($types[array_search(19,$types)]);
    }

    $types = implode(',', $types);

    if ($_POST['menu'] == 'out') {
        $UserHelper = new UserHelper($_SESSION['helpdesk_user_id'], $dbConnection);

        $user = $UserHelper->getUserData('department:extended');

        $collegues = f3pick($user['collegues'], 'uid');
        $collegues = implode(',', $collegues);

        $page      = ($_POST['page']);
        $perpage   = '10';

        if (isset($_SESSION['hd.rustem_list_out'])) {
            $perpage = $_SESSION['hd.rustem_list_out'];
        }

        $start_pos = ($page - 1) * $perpage;
        $uid = $_SESSION['helpdesk_user_id'];
        $ps  = priv_status($uid);

        //TODO: receive all users who works with current user, I mean departments
        
        /*
        2 boss (all created tickets)
        0 head (user deps created tickets)
        1 user (only his tickets)
        */
        
        $order_l = "id desc";
        $order_l_var = "";
        if (isset($_SESSION['zenlix_list_out_sort'])) {
            switch ($_SESSION['zenlix_list_out_sort']) {
                case 'id':
                    $order_l = "id";
                    break;

                case 'prio':
                    $order_l = "prio";
                    break;

                case 'subj':
                    $order_l = "subj";
                    break;

                case 'client_id':
                    $order_l = "client_id";
                    break;

                case 'date_create':
                    $order_l = "date_create";
                    break;
                case 'user_init_id':
                    $order_l = "user_init_id";
                    break;

                default:
                    $order_l = "id desc";
            }
        }
        
        if (isset($_SESSION['zenlix_list_out_sort_var'])) {
            switch ($_SESSION['zenlix_list_out_sort_var']) {
                case 'asc':
                    $order_l_var = "asc";
                    break;

                case 'desc':
                    $order_l_var = "desc";
                    break;
            }
        }
        
        $order_l = $order_l . " " . $order_l_var;

        $noRules = false; // with no checking user rules

        if ($_SESSION['hd.rustem_sort_out'] === 'activity_24_hours') {
            try {
                $noRules = true;
                $stmt = $dbConnection->prepare(
                    "SELECT ticket_id FROM ticket_log WHERE (UNIX_TIMESTAMP(date_op) + 86400) > UNIX_TIMESTAMP(NOW()) AND init_user_id IN ($collegues) GROUP BY ticket_id"
                );
                $stmt->execute();
                $idts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $idts = f3pick($idts,'ticket_id');

                if ($idts) {
                    $idts  = implode(',', $idts);
                    $stmt  = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t
                        WHERE t.id IN ($idts) AND status <> 3"
                    );

                    $stmt->execute();
                }
            } catch (Exception $e) {}
        }

        /* WARNING: if rules no matter for user, `aha` variable should be 1 */
        if ($_SESSION['hd.rustem_sort_out'] === 'personal') { // for personal mode
            $noRules = true;
            //var_dump(' --------- personal');
            $stmt = $dbConnection->prepare(
                "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                WHERE arch = :n AND status = :nu AND user_to_id = :uid AND s.id IN ($types) 
                LIMIT :start_pos, :perpage");
            /*and (lock_by<>:lb and lock_by<>0) and (status=0)*/
            $stmt->execute(array(
                ':n'         => 0,
                ':nu'        => 0,
                ':uid'       => $uid,
                ':start_pos' => $start_pos,
                ':perpage'   => $perpage
            ));
        }

        if ($ps == "2" && $noRules === false) {
            if (isset($_SESSION['hd.rustem_sort_out'])) {
                if ($_SESSION['hd.rustem_sort_out'] == "ok") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n AND status=:s AND s.id IN ($types)
                             LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':s' => '1',
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "free") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                                  WHERE arch=:n AND lock_by=:lb AND status=:s AND s.id IN ($types)
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':lb' => '0',
                        ':s' => '0',
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "ilock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE arch=:n AND lock_by=:lb AND s.id IN ($types) AND status = :status
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':lb'        => $uid,
                        ':n'         => '0',
                        ':status'    => 0,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "lock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE arch=:n AND (lock_by<>:lb and lock_by<>0) AND (status=0) AND s.id IN ($types) 
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':lb' => $uid,
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                }
            }

            /**
             * All status in the list with out canceled tickets
             * status 3 == canceled tickets
             */
            else if (!isset($_SESSION['hd.rustem_sort_out'])) {
                $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name  
                    WHERE arch=:n AND s.id IN ($types) AND status <> 3
                    ORDER BY $order_l 
                    LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
            }
        } else if ($ps == "0" && $noRules === false) {
            if (isset($_SESSION['hd.rustem_sort_out'])) {
                if ($_SESSION['hd.rustem_sort_out'] == "ok") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                         WHERE user_init_id IN ($collegues) AND arch=:arch AND status=:status AND s.id IN ($types)
                         LIMIT :start_pos, :perpage"
                    );
                    $stmt->execute([
                        ':status'    => '1',
                        ':arch'      => '0',
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ]);
                } else if ($_SESSION['hd.rustem_sort_out'] == "free") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id IN ($collegues) AND arch=:arch AND lock_by=:lock AND status=:status AND s.id IN ($types)
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute([
                        ':arch'      => '0',
                        ':lock'      => '0',
                        ':status'    => '0',
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ]);
                } else if ($_SESSION['hd.rustem_sort_out'] == "ilock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id IN ($collegues) AND arch=:arch AND lock_by=:lock AND s.id IN ($types) AND status = :status
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute([
                        ':arch'      => '0',
                        ':lock'      => $uid,
                        ':status'    => 0,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ]);
                } else if ($_SESSION['hd.rustem_sort_out'] == "lock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id IN ($collegues) AND arch=:arch AND (lock_by<>:lock AND lock_by<>0) AND (status=0) AND s.id IN ($types) 
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute([
                        ':arch'      => '0',
                        ':lock'      => $uid,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ]);
                }
            } elseif (!isset($_SESSION['hd.rustem_sort_out'])) {
                $stmt = $dbConnection->prepare(
                    "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name  
                    WHERE user_init_id IN ($collegues) AND arch=:arch AND s.id IN ($types) AND status = :status
                    ORDER BY $order_l LIMIT :start_pos, :perpage");
                $stmt->execute([
                    ':arch'      => '0',
                    ':status'    => 0,
                    ':start_pos' => $start_pos,
                    ':perpage'   => $perpage
                ]);
            }
        } else if ($ps == "1" && $noRules === false) {
            if (isset($_SESSION['hd.rustem_sort_out'])) {
                if ($_SESSION['hd.rustem_sort_out'] == "ok") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id=:user_id AND arch=:n AND status=:s AND s.id IN ($types)
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':user_id' => $uid,
                        ':s' => '1',
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "free") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id=:user_id AND arch=:n AND lock_by=:lb AND status=:s AND s.id IN ($types)
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':user_id' => $uid,
                        ':lb' => '0',
                        ':s' => '0',
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "ilock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                                  WHERE user_init_id=:user_id AND arch=:n AND lock_by=:lb AND s.id IN ($types) AND status = :status
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':user_id'   => $uid,
                        ':lb'        => $uid,
                        ':n'         => '0',
                        ':status'    => 0,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_out'] == "lock") {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                                  WHERE user_init_id=:user_id AND arch=:n AND (lock_by<>:lb AND lock_by<>0) AND (status=0) AND s.id IN ($types) 
                                  LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':user_id' => $uid,
                        ':lb' => $uid,
                        ':n' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                }
            }

            /**
             * All status in the list with out canceled tickets
             * status 3 == canceled tickets
             */
            else if (!isset($_SESSION['hd.rustem_sort_out'])) {
                $collegues = array_push($collegues, $user['uid']);

//                $stmt = $dbConnection->prepare(
//                    "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
//                              WHERE user_init_id IN (:init_users) AND arch=:n AND s.id IN ($types) AND status <> 3
//                              ORDER BY $order_l
//                              LIMIT :start_pos, :perpage");
//                $stmt->execute(array(
//                    ':init_users' => implode(',', $collegues),
//                    ':n'          => '0',
//                    ':start_pos'  => $start_pos,
//                    ':perpage'    => $perpage
//                ));
            }
        }
        
        //or id in (LIST TICKET_ID)
        
        $res1 = $stmt->fetchAll();
        
        $aha = get_total_pages('out', $uid);
        if (isset($noRules) && $noRules === true) {
            $aha = 1;
        }

        if (!isset($_SESSION['hd.rustem_sort_out'])) {
            if (isset($_SESSION['zenlix_list_out_sort'])) {
                
                if (isset($_SESSION['zenlix_list_out_sort_var'])) {
                    
                    if ($_SESSION['zenlix_list_out_sort_var'] == "asc") {
                        $r = " <i class='fa fa-sort-asc'></i>";
                    }
                    if ($_SESSION['zenlix_list_out_sort_var'] == "desc") {
                        $r = " <i class='fa fa-sort-desc'></i>";
                    }
                }
                
                switch ($_SESSION['zenlix_list_out_sort']) {
                    case 'id':
                        $sort_type_start['id'] = "<mark>";
                        $sort_type_stop['id'] = $r . "</mark>";
                        break;

                    case 'prio':
                        $sort_type_start['prio'] = "<mark>";
                        $sort_type_stop['prio'] = $r . "</mark>";
                        break;

                    case 'subj':
                        $sort_type_start['subj'] = "<mark>";
                        $sort_type_stop['subj'] = $r . "</mark>";
                        break;

                    case 'client_id':
                        $sort_type_start['client_id'] = "<mark>";
                        $sort_type_stop['client_id'] = $r . "</mark>";
                        break;

                    case 'date_create':
                        $sort_type_start['date_create'] = "<mark>";
                        $sort_type_stop['date_create'] = $r . "</mark>";
                        break;

                    case 'user_init_id':
                        $sort_type_start['user_init_id'] = "<mark>";
                        $sort_type_stop['user_init_id'] = $r . "</mark>";
                        break;
                }
            }
        }
        
        $ar_res = array();
        
        foreach ($res1 as $row) {
            $lb = $row['lock_by'];
            $ob = $row['ok_by'];
            
            ////////////////////////////Раскрашивает и подписывает кнопки/////////////////////////////////////////////////////////////////
            if ($row['is_read'] == "0") {
                $style = "bold_for_new";
            }
            if ($row['is_read'] <> "0") {
                $style = "";
            }
            if ($row['status'] == "1") {
                $ob_text = "<i class=\"fa fa-check-circle-o\"></i>";
                $ob_status = "unok";
                $ob_tooltip = lang('t_list_a_nook');
                $style = "success";
                
                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                }
                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }
            
            if ($row['status'] == "0") {
                $ob_text = "<i class=\"fa fa-circle-o\"></i>";
                $ob_status = "ok";
                $ob_tooltip = lang('t_list_a_ok');
                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                    if ($lb == $uid) {
                        $style = "warning";
                    }
                    if ($lb <> $uid) {
                        $style = "active";
                    }
                }
                
                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает приоритет//////////////////////////////////////////////////////////////
            $prio = "<span class=\"label label-info\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_norm') . "\"><i class=\"fa fa-exclamation\"></i></span>";
            
            if ($row['prio'] == "0") {
                $prio = "<span class=\"label label-primary\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_low') . "\"><i class=\"fa fa-ban\"></i></span>";
            }
            
            if ($row['prio'] == "2") {
                $prio = "<span class=\"label label-danger\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_high') . "\"><i class=\"fa fa-bolt\"></i></span>";
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает labels//////////////////////////////////////////////////////////////
            if ($row['status'] == 1) {
                $st = "<span class=\"label label-success\"><i class=\"fa fa-check-circle\"></i> " . lang('t_list_a_oko') . " " . nameshort(name_of_user_ret_nolink($ob)) . "</span>";
                $t_ago = get_date_ok($row['date_create'], $row['id']);
				$t_from  = $row['date_create'];
				$t_ago = floor((((strtotime($t_ago) - strtotime($t_from))  )));
            }
            if ($row['status'] == 0) {
                $t_ago = $row['date_create'];
				$t_ago = floor((((time() - strtotime($t_ago))  )));
                if ($lb <> 0) {
                    
                    if ($lb == $uid) {
                        $st = "<span class=\"label label-warning\"><i class=\"fa fa-gavel\"></i> " . lang('t_list_a_lock_i') . "</span>";
                    }
                    
                    if ($lb <> $uid) {
                        $st = "<span class=\"label label-default\"><i class=\"fa fa-gavel\"></i> " . lang('t_list_a_lock_u') . " " . nameshort(name_of_user_ret_nolink($lb)) . "</span>";
                    }
                }
                if ($lb == 0) {
                    $st = "<span class=\"label label-primary\"><i class=\"fa fa-clock-o\"></i> " . lang('t_list_a_hold') . "</span>";
                }
            }
            
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает кому/////////////////////////////////////////////////////////////////
            if ($row['user_to_id'] <> 0) {
                $to_text = "<div class=''>" . nameshort(name_of_user_ret($row['user_to_id'])) . "</div>";
            }
            if ($row['user_to_id'] == 0) {
                $to_text = "<strong data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . view_array(get_unit_name_return($row['unit_id'])) . "\">" . lang('t_list_a_all') . "</strong>";
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ob_start();
            
            //Start output buffer
            cutstr(make_html($row['subj'], 'no'));
            $cut_subj = ob_get_contents();
            
            //Grab output
            ob_end_clean();
            
            array_push($ar_res, array(
                'id' => $row['id'],
                'style' => $style,
                'prio' => $prio,
                //'muclass' => $muclass,
                'subj' => make_html($row['subj'], 'no') ,
				'sabj_pl' => $row['sabj_pl'],
				'comment' => getLastComment($row['id']),
				'msg1' => $row['msg'],
                'msg' => str_replace('"', "", cutstr_help_ret(make_html(strip_tags($row['msg'])) , 'no')) ,
                'hashname' => $row['hash_name'],
                'cut_subj' => $cut_subj,
                'get_user_hash_by_id_client' => get_user_hash_by_id($row['client_id']) ,
                'client' => get_user_val_by_id($row['client_id'], 'fio') ,
                'date_create' => $row['date_create'],
                't_ago' => $t_ago,
                'get_deadline_label' => get_deadline_label($row['id']) ,
                'name_of_user_ret' => nameshort(name_of_user_ret($row['user_init_id'])) ,
                'to_text' => $to_text,
                'st' => $st,
				'ok_date' => $row['ok_date']
				
            ));
        }
        $basedir = dirname(dirname(__FILE__));
        
        ////////////
        try {
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($basedir . '/views');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $basedir . '/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('list_content_out.view.tmpl');
            
            // передаём в шаблон переменные и значения
            // выводим сформированное содержание
            echo $template->render(array(
                'get_total_pages_out' => get_total_pages('out', $uid) ,
                'sort_type_start_id' => $sort_type_start['id'],
                'sort_type_stop_id' => $sort_type_stop['id'],
                'sort_type_start_prio' => $sort_type_start['prio'],
                't_LIST_prio' => lang('t_LIST_prio') ,
                'sort_type_stop_prio' => $sort_type_stop['prio'],
                'sort_type_start_subj' => $sort_type_start['subj'],
                't_LIST_subj' => lang('t_LIST_subj') ,
                'sort_type_stop_subj' => $sort_type_stop['subj'],
                'sort_type_start_client_id' => $sort_type_start['client_id'],
                't_LIST_worker' => lang('t_LIST_worker') ,
                'sort_type_stop_client_id' => $sort_type_stop['client_id'],
                'sort_type_start_date_create' => $sort_type_start['date_create'],
                't_LIST_create' => lang('t_LIST_create') ,
                'sort_type_stop_date_create' => $sort_type_stop['date_create'],
                't_LIST_ago' => lang('t_LIST_ago') ,
                'sort_type_start_user_init_id' => $sort_type_start['user_init_id'],
                't_LIST_init' => lang('t_LIST_init') ,
                'sort_type_stop_user_init_id' => $sort_type_stop['user_init_id'],
                't_LIST_to' => lang('t_LIST_to') ,
				't_LIST_placer' => lang('t_LIST_placer') ,
				't_LIST_txtmg' => lang('t_LIST_txtmg') ,
				't_LIST_compl' => lang('t_LIST_compl') ,
				't_LIST_zatr' => lang('t_LIST_zatr') ,
				't_LIST_commen' => lang('t_LIST_commen') ,
				't_LIST_ispo' => lang('t_LIST_ispo') ,
                't_LIST_status' => lang('t_LIST_status') ,
                'ar_res' => $ar_res,
                'aha' => $aha,
                'MSG_no_records' => lang('MSG_no_records')
            ));
        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
    }

    if ($_POST['menu'] == 'in') {
        $page = ($_POST['page']);

        $UserHelper = new UserHelper($_SESSION['helpdesk_user_id'], $dbConnection);

        $user  = $UserHelper->getUserData('department:extended');
        $units = $user['user']['unit'];

        $ar_res = array();

        $perpage = '10';

        if (isset($_SESSION['hd.rustem_list_in'])) {
            $perpage = $_SESSION['hd.rustem_list_in'];
        }

        $start_pos = ($page - 1) * $perpage;
        //$user_id = $_SESSION['helpdesk_user_id'];
        $uid = $_SESSION['helpdesk_user_id'];

        $unit_user = unit_of_user($uid);

        $priv_val  = priv_status($uid);

        //$unit_user = 1,2,3
        //$units = explode(",", $unit_user);

        //$units = array[1,2,3]
        //$units = implode("', '", $units);

        $ee = explode(",", $unit_user);

        foreach ($ee as $key => $value) {
            $in_query = $in_query . ' :val_' . $key . ', ';
        }

        $in_query = substr($in_query, 0, -2);

        foreach ($ee as $key => $value) {
            $vv[":val_" . $key] = $value;
        }

        $order_l = "ok_by asc, prio desc, id desc";
        $order_l_var = "";
        if (isset($_SESSION['zenlix_list_in_sort'])) {
            switch ($_SESSION['zenlix_list_in_sort']) {
                case 'id':
                    $order_l = "id";
                    break;
                case 'prio':
                    $order_l = "prio";
                    break;
                case 'subj':
                    $order_l = "subj";
                    break;
                case 'client_id':
                    $order_l = "client_id";
                    break;
                case 'date_create':
                    $order_l = "date_create";
                    break;
                case 'user_init_id':
                    $order_l = "user_init_id";
                    break;
                default:
                    $order_l = "ok_by asc, prio desc, id desc";
            }
        }

        if (isset($_SESSION['zenlix_list_in_sort_var'])) {
            switch ($_SESSION['zenlix_list_in_sort_var']) {
                case 'asc':
                    $order_l_var = "asc";
                    break;

                case 'desc':
                    $order_l_var = "desc";
                    break;
            }
        }

        $order_l = $order_l . " " . $order_l_var;

        $noRules = false; // with no checking user rules
        if ($_SESSION['hd.rustem_sort_in'] === 'activity_24_hours') {
            try {
                $noRules = true;
                $stmt    = $dbConnection->prepare(
                    "SELECT ticket_id FROM ticket_log WHERE (UNIX_TIMESTAMP(date_op) + 86400 > UNIX_TIMESTAMP(NOW())) AND (to_user_id = :uid OR to_unit_id IN ($units)) GROUP BY ticket_id"
                );
                $stmt->execute([':uid' => $uid]);
                $idts = $stmt->fetchAll(PDO::FETCH_ASSOC); // get tickets ids with activity of last 24 hours
                $idts = f3pick($idts,'ticket_id'); // get array with tickets numbers
                $idts = implode(',', $idts);

                if ($idts) {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t
                                    WHERE t.id IN ($idts) AND status <> 3"
                    );
                    $stmt->execute();
                }

                funkit_setlog('idts', $idts);
            } catch (Exception $e) {
                funkit_setlog('error', $e->getMessage());
            }
        }
        /* WARNING: if rules no matter for user, `aha` variable should be 1 */
        if ($_SESSION['hd.rustem_sort_in'] === 'personal') { // for personal mode
            $noRules = true;
            $stmt = $dbConnection->prepare(
                "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name 
                 WHERE arch = :n AND status = :nu AND user_to_id = :uid AND s.id IN ($types)
                 LIMIT :start_pos, :perpage");
            /*and (lock_by<>:lb and lock_by<>0) and (status=0)*/
            $stmt->execute(array(
                ':n'         => 0,
                ':nu'        => 0,
                ':uid'       => $uid,
                ':start_pos' => $start_pos,
                ':perpage'   => $perpage
            ));
        }

        if ($priv_val == 0) {
            if (isset($_SESSION['hd.rustem_sort_in'])) {
                if ($_SESSION['hd.rustem_sort_in'] == "ok") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            where unit_id IN ($in_query) AND arch=:n AND status=:s AND s.id IN ($types)
                            limit :start_pos, :perpage");
                    $paramss = array(
                        ':n' => '0',
                        ':s' => '1',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] == "free") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            where unit_id IN ($in_query) AND arch=:n AND status=:s AND lock_by=:lb AND s.id IN ($types)
                            limit :start_pos, :perpage");
                    $paramss = array(
                        ':n' => '0',
                        ':s' => '0',
                        ':lb' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] === 'ilock') {
                    $stmt = $dbConnection->prepare(
                        "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                                    WHERE unit_id IN ($in_query) AND arch=:n AND status = :status AND lock_by=:lb AND s.id IN ($types)
                                    LIMIT :start_pos, :perpage");
                    $paramss = array(
                        ':n'         => '0',
                        ':status'    => 0,
                        ':lb'        => $uid,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] == "lock") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            where t.unit_id IN ($in_query) AND arch=:n AND (lock_by<>:lb AND lock_by<>0) AND (status=0) AND s.id IN ($types)
                            limit :start_pos, :perpage");

                    $paramss = array(
                        ':n' => '0',
                        ':lb' => $uid,
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
            }

            /**
             * All status in the list with out canceled tickets
             * status 3 == canceled tickets
             */
            if (!isset($_SESSION['hd.rustem_sort_in'])) {
                $stmt = $dbConnection->prepare(
                    "SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                              WHERE unit_id IN ($in_query) AND t.arch=:n AND s.id IN ($types) AND status <> 3
                              ORDER BY t.$order_l
                              LIMIT :start_pos, :perpage");
                $paramss = array(
                    ':n' => '0',
                    ':start_pos' => $start_pos,
                    ':perpage' => $perpage
                );
                $stmt->execute(array_merge($vv, $paramss));
            }
        } else if ($priv_val == 1) {
            if (isset($_SESSION['hd.rustem_sort_in'])) {
                if ($_SESSION['hd.rustem_sort_in'] == "ok") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            where ((find_in_set(:user_id,user_to_id) and arch=:n) or
                            (find_in_set(:n1,user_to_id) and unit_id IN ($in_query) and arch=:n2)) and status=:s AND s.id IN ($types)
                            limit :start_pos, :perpage");
                    $paramss = array(
                        ':user_id' => $uid,
                        ':s' => '1',
                        ':n' => '0',
                        ':n1' => '0',
                        ':n2' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] == "free") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            where ((find_in_set(:user_id,user_to_id) and arch=:n) or
                            (find_in_set(:n1,user_to_id) and unit_id IN ($in_query) and arch=:n2)) and lock_by=:lb and status=:s AND s.id IN ($types)
                            limit :start_pos, :perpage");
                    $paramss = array(
                        ':user_id' => $uid,
                        ':lb' => '0',
                        ':s' => '0',
                        ':n' => '0',
                        ':n1' => '0',
                        ':n2' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] == "ilock") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE ((find_in_set(:user_id,user_to_id) AND arch=:n) OR
                            (find_in_set(:n1,user_to_id) AND unit_id IN ($in_query) AND arch=:n2)) AND lock_by=:lb AND s.id IN ($types) AND status = :status
                            LIMIT :start_pos, :perpage");
                    $paramss = array(
                        ':user_id'   => $uid,
                        ':lb'        => $uid,
                        ':n'         => '0',
                        ':n1'        => '0',
                        ':n2'        => '0',
                        ':status'    => 0,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
                else if ($_SESSION['hd.rustem_sort_in'] == "lock") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE ((find_in_set(:user_id,user_to_id) AND arch=:n) or
                            (find_in_set(:n1,user_to_id) AND unit_id IN ($in_query) AND arch=:n2)) AND (lock_by<>:lb AND lock_by<>0) AND (status=0) AND s.id IN ($types)
                            LIMIT :start_pos, :perpage");
                    $paramss = array(
                        ':user_id' => $uid,
                        ':lb' => $uid,
                        ':n' => '0',
                        ':n1' => '0',
                        ':n2' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    );
                    $stmt->execute(array_merge($vv, $paramss));
                }
            }

			 /**
             * All status in the list with out canceled tickets
             * status 3 == canceled tickets
             */
            if (!isset($_SESSION['hd.rustem_sort_in'])) {

                $stmt = $dbConnection->prepare(
				"SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE ((find_in_set(:user_id,user_to_id) and arch=:n) OR
                            (find_in_set(:n1,user_to_id) AND unit_id IN ($in_query) AND arch=:n2)) AND s.id IN ($types) AND status <> 3
                            ORDER BY $order_l
                            LIMIT :start_pos, :perpage");
                $paramss = array(
                    ':user_id' => $uid,
                    ':n' => '0',
                    ':n1' => '0',
                    ':n2' => '0',
                    ':start_pos' => $start_pos,
                    ':perpage' => $perpage
                );
                $stmt->execute(array_merge($vv, $paramss));
            }
        } else if ($priv_val == 2) {
            //Главный начальник
            if (isset($_SESSION['hd.rustem_sort_in'])) {
                if ($_SESSION['hd.rustem_sort_in'] == "ok") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n
                            AND status=:s AND s.id IN ($types)
                            LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':n' => '0',
                        ':s' => '1',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_in'] == "free") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n
                            AND lock_by=:lb AND status=:s AND s.id IN ($types)
                            LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':n' => '0',
                        ':s' => '0',
                        ':lb' => '0',
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_in'] == "ilock") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n
                            AND lock_by=:lb AND s.id IN ($types) AND status = :status
                            LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':n'         => '0',
                        ':lb'        => $uid,
                        ':status'    => 0,
                        ':start_pos' => $start_pos,
                        ':perpage'   => $perpage
                    ));
                } else if ($_SESSION['hd.rustem_sort_in'] == "lock") {
                    $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n
                            AND (lock_by<>:lb AND lock_by<>0) AND (status=0) AND s.id IN ($types)
                            LIMIT :start_pos, :perpage");
                    $stmt->execute(array(
                        ':n' => '0',
                        ':lb' => $uid,
                        ':start_pos' => $start_pos,
                        ':perpage' => $perpage
                    ));
                }
            }

            /**
             * All status in the list with out canceled tickets
             * status 3 == canceled tickets
             */
            if (!isset($_SESSION['hd.rustem_sort_in'])) {
                $stmt = $dbConnection->prepare("SELECT t.* FROM tickets AS t LEFT JOIN subj AS s ON t.subj=s.name
                            WHERE arch=:n AND s.id IN ($types) AND status <> 3
                            ORDER BY $order_l
                            LIMIT :start_pos, :perpage");
                $stmt->execute(array(
                    ':n' => '0',
                    ':start_pos' => $start_pos,
                    ':perpage' => $perpage
                ));
            }
        }

        $res1 = $stmt->fetchAll();

        $aha = get_total_pages('in', $uid);
//        if (isset($noRules) && $noRules === true) {
//            $aha = 1;
//        }

        if (!isset($_SESSION['hd.rustem_sort_in'])) {
            if (isset($_SESSION['zenlix_list_in_sort'])) {

                if (isset($_SESSION['zenlix_list_in_sort_var'])) {

                    if ($_SESSION['zenlix_list_in_sort_var'] == "asc") {
                        $r = " <i class='fa fa-sort-asc'></i>";
                    }
                    if ($_SESSION['zenlix_list_in_sort_var'] == "desc") {
                        $r = " <i class='fa fa-sort-desc'></i>";
                    }
                }

                switch ($_SESSION['zenlix_list_in_sort']) {
                    case 'id':
                        $sort_type_start['id'] = "<mark>";
                        $sort_type_stop['id'] = $r . "</mark>";
                        break;

                    case 'prio':
                        $sort_type_start['prio'] = "<mark>";
                        $sort_type_stop['prio'] = $r . "</mark>";
                        break;

                    case 'subj':
                        $sort_type_start['subj'] = "<mark>";
                        $sort_type_stop['subj'] = $r . "</mark>";
                        break;

                    case 'client_id':
                        $sort_type_start['client_id'] = "<mark>";
                        $sort_type_stop['client_id'] = $r . "</mark>";
                        break;

                    case 'date_create':
                        $sort_type_start['date_create'] = "<mark>";
                        $sort_type_stop['date_create'] = $r . "</mark>";
                        break;

                    case 'user_init_id':
                        $sort_type_start['user_init_id'] = "<mark>";
                        $sort_type_stop['user_init_id'] = $r . "</mark>";
                        break;
                }
            }
        }

        foreach ($res1 as $row) {
            $lb = $row['lock_by'];
            $ob = $row['ok_by'];

            $user_id_z = $uid;//$_SESSION['helpdesk_user_id'];
            $unit_user_z = unit_of_user($user_id_z);
            $status_ok_z = $row['status'];
            $ok_by_z = $row['ok_by'];
            $lock_by_z = $row['lock_by'];

            ////////////////////////////Раскрашивает и подписывает кнопки/////////////////////////////////////////////////////////////////
            if ($row['is_read'] == "0") {
                $style = "bold_for_new";
            }
            if ($row['is_read'] <> "0") {
                $style = "";
            }

            if ($row['status'] == "1") {
                $ob_text = "<i class=\"fa fa-check-circle-o\"></i>";
                $ob_status = "unok";
                $ob_tooltip = lang('t_list_a_nook');
                $style = "success";

                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                }
                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }

            if ($row['status'] == "0") {
                $ob_text = "<i class=\"fa fa-circle-o\"></i>";
                $ob_status = "ok";
                $ob_tooltip = lang('t_list_a_ok');
                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                    if ($lb == $uid) {
                        $style = "warning";
                    }
                    if ($lb <> $uid) {
                        $style = "active";
                    }
                }

                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }

            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            if ($row['status'] == "1") {
                $status_ok_status = "ok";
            }

            if ($row['status'] == "0") {
                $status_ok_status = "no_ok";
            }

            ////////////////////////////Показывает кому/////////////////////////////////////////////////////////////////
            if ($row['user_to_id'] <> 0) {
                $to_text = "<div class=''>" . nameshort(name_of_user_ret($row['user_to_id'])) . "</div>";
            }
            if ($row['user_to_id'] == 0) {
                $to_text = "<strong data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . view_array(get_unit_name_return($row['unit_id'])) . "\">" . lang('t_list_a_all') . "</strong>";
            }

            ////////////////////////////////////////////////////////////////////////////////////////////////////////////

            ////////////////////////////Показывает приоритет//////////////////////////////////////////////////////////////
            $prio = "<span class=\"label label-info\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_norm') . "\"><i class=\"fa fa-exclamation\"></i></span>";

            if ($row['prio'] == "0") {
                $prio = "<span class=\"label label-primary\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_low') . "\"><i class=\"fa fa-ban\"></i></span>";
            }

            if ($row['prio'] == "2") {
                $prio = "<span class=\"label label-danger\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_high') . "\"><i class=\"fa fa-bolt\"></i></span>";
            }

            ////////////////////////////////////////////////////////////////////////////////////////////////////////////

            ////////////////////////////Показывает labels//////////////////////////////////////////////////////////////
            if ($row['status'] == 1) {
                $st = "<span class=\"label label-success\"><i class=\"fa fa-check-circle\"></i> " . lang('t_list_a_oko') . " " . nameshort(name_of_user_ret_nolink($ob)) . "</span>";
                $t_ago = get_date_ok($row['date_create'], $row['id']);
                $t_from  = $row['date_create'];
                $t_ago = floor((((strtotime($t_ago) - strtotime($t_from)) )));
            }
            if ($row['status'] == 0) {
                $t_ago=$row['date_create'];
                $t_ago = floor((((time() - strtotime($t_ago))  )));
                if ($lb <> 0) {

                    if ($lb == $uid) {$st=  "<span class=\"label label-warning\"><i class=\"fa fa-gavel\"></i> ".lang('t_list_a_lock_i')."</span>";}

                    if ($lb <> $uid) {$st=  "<span class=\"label label-default\"><i class=\"fa fa-gavel\"></i> ".lang('t_list_a_lock_u')." ".nameshort(name_of_user_ret($lb))."</span>";}

                }
                if ($lb == 0) {$st=  "<span class=\"label label-primary\"><i class=\"fa fa-clock-o\"></i> ".lang('t_list_a_hold')."</span>";}
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

            /////////если пользователь///////////////////////////////////////////////////////////////////////////////////////////
            if ($priv_val == 1) {

                //ЗАявка не выполнена ИЛИ выполнена мной
                //ЗАявка не заблокирована ИЛИ заблокирована мной
                $lo = "no";

                if ($row['user_init_id'] == $user_id_z) {

                    $lo = "yes";
                }

                if ($row['user_init_id'] <> $user_id_z) {

                    if (($status_ok_z == 0) || (($status_ok_z == 1) && ($ok_by_z == $user_id_z))) {
                        if (($lock_by_z == 0) || ($lock_by_z == $user_id_z)) {
                            $lo = "yes";
                        }
                    }
                }

                if ($lo == "yes") {
                    $lock_st = "";
                    $muclass = "";
                }
                else if ($lo == "no") {
                    $lock_st = "disabled=\"disabled\"";
                    $muclass = "text-muted";
                }
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

            /////////если нач отдела/////////////////////////////////////////////////////////////////////////////////////////////
            else if ($priv_val == 0) {
                $lock_st = "";
                $muclass = "";
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

            //////////главный админ/////////////////////////////////////////////////////////////////////////////////////////////
            else if ($priv_val == 2) {
                $lock_st = "";
                $muclass = "";
            }

            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            ob_start();

            //Start output buffer
            cutstr(make_html($row['subj'], 'no'));
            $cut_subj = ob_get_contents();

            //Grab output
            ob_end_clean();

            array_push($ar_res, array(

                'id' => $row['id'],
                'style' => $style,
                'prio' => $prio,
                'muclass' => $muclass,
                'subj' => make_html($row['subj'], 'no') ,
                'sabj_pl' => $row['sabj_pl'],
                'comment' => getLastComment($row['id']),
                'msg1' => $row['msg'],
                'msg' => str_replace('"', "", cutstr_help_ret(make_html(strip_tags($row['msg'])) , 'no')) ,
                'hashname' => $row['hash_name'],
                'cut_subj' => $cut_subj,
                'get_user_hash_by_id_client' => get_user_hash_by_id($row['client_id']) ,
                'client' => get_user_val_by_id($row['client_id'], 'fio') ,
                'date_create' => $row['date_create'],
                't_ago' => $t_ago,
                'get_deadline_label' => get_deadline_label($row['id']) ,
                'name_of_user_ret' => nameshort(name_of_user_ret($row['user_init_id'])) ,

                'init_hash' => get_user_hash_by_id($row['user_init_id']) ,
                'init_fio' => nameshort(name_of_user_ret($row['user_init_id'])) ,
                'to_text' => $to_text,
                'st' => $st,

                'get_b_lb' => get_button_act_status(get_ticket_action_priv($row['id']) , $lb_status) ,
                'lb_tooltip' => $lb_tooltip,
                'user_id' => $uid,
                'lb_status' => $lb_status,
                'lb_text' => $lb_text,

                'get_b_ob' => get_button_act_status(get_ticket_action_priv($row['id']) , $status_ok_status) ,
                'ob_tooltip' => $ob_tooltip,
                'ob_status' => $ob_status,
                'ob_text' => $ob_text,
                'ok_date' => $row['ok_date']
            ));
        }
        $basedir = dirname(dirname(__FILE__));

        ////////////
        try {

            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($basedir . '/views');

            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $basedir . '/cache',
                ));
            }
            else {
                $twig = new Twig_Environment($loader);
            }

            // подгружаем шаблон
            $template = $twig->loadTemplate('list_content_in.view.tmpl');

            // передаём в шаблон переменные и значения
            // выводим сформированное содержание

            echo $template->render(array(
                'get_total_pages_in' => get_total_pages('in', $uid),
                'user_id' => $uid,
                'helpdesk_sort_id' => $_SESSION['helpdesk_sort_id'],
                'sort_type_start_id' => $sort_type_start['id'],
                'sort_type_stop_id' => $sort_type_stop['id'],
                'id_icon' => $id_icon,
                'field_name' => $rown['field_name'],
                'field_val' => $rown['field_val'],
                'helpdesk_sort_prio' => $_SESSION['helpdesk_sort_prio'],
                'tdata_arr' => $tdata_arr,
                'sort_type_start_prio' => $sort_type_start['prio'],
                't_LIST_prio' => lang('t_LIST_prio') ,
                't_LIST_placer' => lang('t_LIST_placer') ,
                't_LIST_txtmg' => lang('t_LIST_txtmg') ,
                't_LIST_compl' => lang('t_LIST_compl') ,
                't_LIST_zatr' => lang('t_LIST_zatr') ,
                't_LIST_commen' => lang('t_LIST_commen') ,
                't_LIST_ispo' => lang('t_LIST_ispo') ,
                'sort_type_stop_prio' => $sort_type_stop['prio'],
                'prio_icon' => $prio_icon,

                'helpdesk_sort_subj' => $_SESSION['helpdesk_sort_subj'],

                'sort_type_start_subj' => $sort_type_start['subj'],
                't_LIST_subj' => lang('t_LIST_subj') ,
                'sort_type_stop_subj' => $sort_type_stop['subj'],
                'subj_icon' => $subj_icon,

                'helpdesk_sort_clientid' => $_SESSION['helpdesk_sort_clientid'],

                'sort_type_start_client_id' => $sort_type_start['client_id'],
                't_LIST_worker' => lang('t_LIST_worker') ,
                'sort_type_stop_client_id' => $sort_type_stop['client_id'],
                'cli_icon' => $cli_icon,

                'sort_type_start_date_create' => $sort_type_start['date_create'],
                't_LIST_create' => lang('t_LIST_create') ,
                'sort_type_stop_date_create' => $sort_type_stop['date_create'],

                't_LIST_ago' => lang('t_LIST_ago') ,

                'helpdesk_sort_userinitid' => $_SESSION['helpdesk_sort_userinitid'],

                'sort_type_start_user_init_id' => $sort_type_start['user_init_id'],
                't_LIST_init' => lang('t_LIST_init') ,
                'sort_type_stop_user_init_id' => $sort_type_stop['user_init_id'],
                'init_icon' => $init_icon,
                't_LIST_to' => lang('t_LIST_to') ,
                't_LIST_status' => lang('t_LIST_status') ,
				't_LIST_deadline' => lang('t_LIST_deadline') ,
                'ar_res' => $ar_res,
                'aha' => $aha,
                'MSG_no_records' => lang('MSG_no_records'),
                't_LIST_action' => lang('t_LIST_action'),
                'ticket_cancel' => 'x',
            ));
        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
    }

    if ($_POST['menu'] == 'find') {
        $z = ($_GET['t']);

        $uid = $_SESSION['helpdesk_user_id'];
        $unit_user = unit_of_user($uid);
        $priv_val = priv_status($uid);
        
        $units = explode(",", $unit_user);
        $units = implode("', '", $units);
        
        $ee = explode(",", $unit_user);
        foreach ($ee as $key => $value) {
            $in_query = $in_query . ' :val_' . $key . ', ';
        }
        $in_query = substr($in_query, 0, -2);
        foreach ($ee as $key => $value) {
            $vv[":val_" . $key] = $value;
        }
        
        if ($priv_val == 0) {
            $stmt = $dbConnection->prepare('SELECT
                a.id, a.user_init_id, a.user_to_id, a.date_create, a.subj, a.sabj_pl, a.msg, a.client_id, a.unit_id, a.status, a.hash_name, 
                a.is_read, a.lock_by, a.ok_by, a.ok_date, a.prio, a.last_update, a.arch, b.comment_text, b.t_id
                FROM tickets as a LEFT JOIN comments AS b ON a.id = b.t_id
                WHERE ((a.unit_id IN (' . $in_query . ') AND a.arch=:n) or (a.user_init_id=:user_id)) AND
                (a.id=:z or a.subj LIKE :z1 OR a.msg LIKE :z2 OR b.comment_text LIKE :z3) GROUP BY a.id');

                $paramss = array(
                    ':n' => '0',
                    ':user_id' => $uid,
                    ':z' => $z,
                    ':z1' => '%' . $z . '%',
                    ':z2' => '%' . $z . '%',
                    ':z3' => '%' . $z . '%'
                );
                $stmt->execute(array_merge($vv, $paramss));
                $res1 = $stmt->fetchAll();
        } 
        else if ($priv_val == 1) {
            $stmt = $dbConnection->prepare('SELECT
            a.id, a.user_init_id, a.user_to_id, a.date_create, a.subj, a.sabj_pl, a.msg, a.client_id, a.unit_id, a.status, a.hash_name, 
            a.is_read, a.lock_by, a.ok_by, a.ok_date, a.prio, a.last_update, a.arch, b.comment_text, b.t_id
              FROM tickets AS a LEFT JOIN comments AS b ON a.id = b.t_id
            WHERE (((find_in_set(:user_id,a.user_to_id) ) OR
            (find_in_set(:n,a.user_to_id) AND a.unit_id IN (' . $in_query . ') )) OR a.user_init_id=:user_id2) AND 
            (a.id=:z OR a.subj LIKE :z1 OR a.msg LIKE :z2 OR b.comment_text LIKE :z3) GROUP BY a.id');

            $paramss = array(
                ':n' => '0',
                ':user_id' => $uid,
                ':z' => $z,
                ':z1' => '%' . $z . '%',
                ':z2' => '%' . $z . '%',
                ':z3' => '%' . $z . '%',
                ':user_id2' => $uid
            );
            $stmt->execute(array_merge($vv, $paramss));
            $res1 = $stmt->fetchAll();
        } 
        else if ($priv_val == 2) {
            
            $stmt = $dbConnection->prepare('SELECT
            a.id, a.user_init_id, a.user_to_id, a.date_create, a.subj, a.sabj_pl, a.msg, a.client_id, a.unit_id, a.status, a.hash_name, 
            a.is_read, a.lock_by, a.ok_by, a.ok_date, a.prio, a.last_update, a.arch, b.comment_text, b.t_id
            FROM tickets AS a LEFT JOIN  comments AS b ON a.id = b.t_id
                WHERE a.id=:z OR a.subj LIKE :z1 OR a.msg LIKE :z2 OR b.comment_text LIKE :z3 GROUP BY a.id');

            $stmt->execute(array(
                ':z' => $z,
                ':z1' => '%' . $z . '%',
                ':z2' => '%' . $z . '%',
                ':z3' => '%' . $z . '%',
            ));
            $res1 = $stmt->fetchAll();
        }
        
        $ar_res = array();
        
        if (empty($res1)) {
            $aha = "0";
        } else if (!empty($res1)) {
            $aha = "1";
        }

        $count = count($res1);
        foreach ($res1 as $row) {
            $lb = $row['lock_by'];
            $ob = $row['ok_by'];
            $arch = $row['arch'];
            
            $user_id_z = $_SESSION['helpdesk_user_id'];
            $unit_user_z = unit_of_user($user_id_z);
            $status_ok_z = $row['status'];
            $ok_by_z = $row['ok_by'];
            $lock_by_z = $row['lock_by'];
            
            ////////////////////////////Раскрашивает и подписывает кнопки/////////////////////////////////////////////////////////////////
            if ($row['is_read'] == "0") {
                $style = "bold_for_new";
            }
            if ($row['is_read'] <> "0") {
                $style = "";
            }
            if ($row['status'] == "1") {
                $ob_text = "<i class=\"fa fa-check-circle-o\"></i>";
                $ob_status = "unok";
                $ob_tooltip = lang('t_list_a_nook');
                $style = "success";
                
                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                }
                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }
            
            if ($row['status'] == "0") {
                $ob_text = "<i class=\"fa fa-circle-o\"></i>";
                $ob_status = "ok";
                $ob_tooltip = lang('t_list_a_ok');
                if ($lb <> "0") {
                    $lb_text = "<i class=\"fa fa-lock\"></i>";
                    $lb_status = "unlock";
                    $lb_tooltip = lang('t_list_a_unlock');
                    if ($lb == $uid) {
                        $style = "warning";
                    }
                    if ($lb <> $uid) {
                        $style = "active";
                    }
                }
                
                if ($lb == "0") {
                    $lb_text = "<i class=\"fa fa-unlock\"></i>";
                    $lb_status = "lock";
                    $lb_tooltip = lang('t_list_a_lock');
                }
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает кому/////////////////////////////////////////////////////////////////
            if ($row['user_to_id'] <> 0) {
                $to_text = "<div class=''>" . nameshort(name_of_user_ret($row['user_to_id'])) . "</div>";
            }
            if ($row['user_to_id'] == 0) {
                $to_text = "<strong data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . view_array(get_unit_name_return($row['unit_id'])) . "\">" . lang('t_list_a_all') . "</strong>";
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает приоритет//////////////////////////////////////////////////////////////
            $prio = "<span class=\"label label-info\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_norm') . "\"><i class=\"fa fa-exclamation\"></i></span>";
            
            if ($row['prio'] == "0") {
                $prio = "<span class=\"label label-primary\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_low') . "\"><i class=\"fa fa-ban\"></i></span>";
            }
            
            if ($row['prio'] == "2") {
                $prio = "<span class=\"label label-danger\" data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . lang('t_list_a_p_high') . "\"><i class=\"fa fa-bolt\"></i></span>";
            }
            
            ////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            ////////////////////////////Показывает labels//////////////////////////////////////////////////////////////
            if ($row['status'] == 1) {
                $st = "<span class=\"label label-success\"><i class=\"fa fa-check-circle\"></i> " . lang('t_list_a_oko') . " " . nameshort(name_of_user_ret_nolink($ob)) . "</span>";
                $t_ago = get_date_ok($row['date_create'], $row['id']);
				$t_from  = $row['date_create'];
				$t_ago = floor((((strtotime($t_ago) - strtotime($t_from))  )));
			
            }
            if ($row['status'] == 0) {
                $t_ago = $row['date_create'];
				$t_ago = floor((((time() - strtotime($t_ago))  )));
				
                if ($lb <> 0) {
                    
                    if ($lb == $uid) {
                        $st = "<span class=\"label label-warning\"><i class=\"fa fa-gavel\"></i> " . lang('t_list_a_lock_i') . "</span>";
                    }
                    
                    if ($lb <> $uid) {
                        $st = "<span class=\"label label-default\"><i class=\"fa fa-gavel\"></i> " . lang('t_list_a_lock_u') . " " . nameshort(name_of_user_ret_nolink($lb)) . "</span>";
                    }
                }
                if ($lb == 0) {
                    $st = "<span class=\"label label-primary\"><i class=\"fa fa-clock-o\"></i> " . lang('t_list_a_hold') . "</span>";
                }
            }
            
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            /////////если пользователь///////////////////////////////////////////////////////////////////////////////////////////
            if ($priv_val == 1) {
                
                //ЗАявка не выполнена ИЛИ выполнена мной
                //ЗАявка не заблокирована ИЛИ заблокирована мной
                $lo == "no";
                if (($status_ok_z == 0) || (($status_ok_z == 1) && ($ok_by_z == $user_id_z))) {
                    if (($lock_by_z == 0) || ($lock_by_z == $user_id_z)) {
                        $lo == "yes";
                    }
                }
                if ($lo == "yes") {
                    $lock_st = "";
                    $muclass = "";
                } 
                else if ($lo == "no") {
                    $lock_st = "disabled=\"disabled\"";
                    $muclass = "text-muted";
                }
            }
            
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            /////////если нач отдела/////////////////////////////////////////////////////////////////////////////////////////////
            else if ($priv_val == 0) {
                $lock_st = "";
                $muclass = "";
            }
            
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            //////////главный админ//////////////////////////////////////////////////////////////////////////////////////////////
            else if ($priv_val == 2) {
                $lock_st = "";
                $muclass = "";
            }
            
            /////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
            
            if ($arch == "1") {
                $st = "<span class=\"label label-default\">" . lang('t_list_a_arch') . " </span>";
            }
            if ($arch == "0") {
                if ($row['status'] == 1) {
                    $st = "<span class=\"label label-success\"><i class=\"fa fa-check-circle\"></i> " . lang('t_list_a_oko') . " " . nameshort(name_of_user_ret_nolink($ob)) . "</span>";
                }
                if ($row['status'] == 0) {
                    if ($lb <> 0) {
                        $st = "<span class=\"label label-warning\"><i class=\"fa fa-gavel\"></i> " . lang('t_list_a_lock_u') . " " . nameshort(name_of_user_ret_nolink($lb)) . "</span>";
                    }
                    if ($lb == 0) {
                        $st = "<span class=\"label label-primary\"><i class=\"fa fa-clock-o\"></i> " . lang('t_list_a_hold') . "</span>";
                    }
                }
            }
            if ($row['status'] == 1) {
                $t_ago = get_date_ok($row['date_create'], $row['id']);
				$t_from  = $row['date_create'];
				$t_ago = floor((((strtotime($t_ago) - strtotime($t_from)) )));
            }
            if ($row['status'] == 0) {
                $t_ago = $row['date_create'];
				$t_ago = floor((((time() - strtotime($t_ago)) )));
            }
            
            ob_start();
            
            //Start output buffer
            cutstr(make_html($row['subj'], 'no'));
            $cut_subj = ob_get_contents();
            
            //Grab output
            ob_end_clean();
            
            array_push($ar_res, array(
                
                'id' => $row['id'],
                'style' => $style,
                'prio' => $prio,
                'muclass' => $muclass,
                'subj' => make_html($row['subj'], 'no') ,
				'sabj_pl' => $row['sabj_pl'],
				'comment' => getLastComment($row['id']),
				'msg1' => $row['msg'],
                'msg' => str_replace('"', "", cutstr_help_ret(make_html(strip_tags($row['msg'])) , 'no')) ,
                'hashname' => $row['hash_name'],
                'cut_subj' => $cut_subj,
                'get_user_hash_by_id_client' => get_user_hash_by_id($row['client_id']) ,
                'client' => get_user_val_by_id($row['client_id'], 'fio') ,
                'date_create' => $row['date_create'],
                't_ago' => $t_ago,
                'get_deadline_label' => get_deadline_label($row['id']) ,
                'name_of_user_ret' => nameshort(name_of_user_ret($row['user_init_id'])) ,
                'to_text' => $to_text,
                'st' => $st,
				'ok_date' => $row['ok_date']
            ));
        }
        
        $basedir = dirname(dirname(__FILE__));
        
        ////////////
        try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($basedir . '/views');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $basedir . '/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('list_content_find.view.tmpl');
            
            // передаём в шаблон переменные и значения
            // выводим сформированное содержание
            echo $template->render(array(
                'results' => lang('results')." $count" ,
                't_LIST_prio' => lang('t_LIST_prio') ,
                't_LIST_subj' => lang('t_LIST_subj') ,
                't_LIST_worker' => lang('t_LIST_worker') ,
                't_LIST_create' => lang('t_LIST_create') ,
                't_LIST_ago' => lang('t_LIST_ago') ,
                't_LIST_init' => lang('t_LIST_init') ,
                't_LIST_to' => lang('t_LIST_to') ,
                't_LIST_status' => lang('t_LIST_status') ,
				't_LIST_placer' => lang('t_LIST_placer') ,
				't_LIST_txtmg' => lang('t_LIST_txtmg') ,
				't_LIST_compl' => lang('t_LIST_compl') ,
				't_LIST_zatr' => lang('t_LIST_zatr') ,
				't_LIST_commen' => lang('t_LIST_commen') ,
				't_LIST_ispo' => lang('t_LIST_ispo') ,
                'ar_res' => $ar_res,
                'aha' => $aha,
                'MSG_no_records' => lang('MSG_no_records')
            ));
        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
    }

    if ($_POST['menu'] == 'arch') {
        
        $page = ($_POST['page']);
        $perpage = '10';
        if (isset($_SESSION['hd.rustem_list_arch'])) {
            $perpage = $_SESSION['hd.rustem_list_arch'];
        }
        $start_pos = ($page - 1) * $perpage;
        
        $uid = $_SESSION['helpdesk_user_id'];
        $unit_user = unit_of_user($uid);
        $units = explode(",", $unit_user);
        $units = implode("', '", $units);
        $priv_val = priv_status($uid);
        
        $ee = explode(",", $unit_user);
        $s = 1;
        foreach ($ee as $key => $value) {
            $in_query = $in_query . ' :val_' . $key . ', ';
            $s++;
        }
        $c = ($s - 1);
        foreach ($ee as $key => $value) {
            $in_query2 = $in_query2 . ' :val_' . ($c + $key) . ', ';
        }
        $in_query = substr($in_query, 0, -2);
        $in_query2 = substr($in_query2, 0, -2);
        foreach ($ee as $key => $value) {
            $vv[":val_" . $key] = $value;
        }
        foreach ($ee as $key => $value) {
            $vv2[":val_" . ($c + $key) ] = $value;
        }
        
        //$pp2=array_merge($vv,$vv2);
        
        if ($priv_val == 0) {
            
            $stmt = $dbConnection->prepare('SELECT 
                            id, user_init_id, user_to_id, date_create, subj, sabj_pl, msg, client_id, unit_id, status, hash_name, comment, is_read, lock_by, ok_by, ok_date
                            from tickets
                            where (unit_id IN (' . $in_query . ') or user_init_id=:user_id) and arch=:n
                            order by id DESC
                            limit :start_pos, :perpage');
            
            $paramss = array(
                ':n' => '1',
                ':user_id' => $uid,
                ':start_pos' => $start_pos,
                ':perpage' => $perpage
            );
            $stmt->execute(array_merge($vv, $paramss));
            $res1 = $stmt->fetchAll();
        } 
        else if ($priv_val == 1) {
            
            $stmt = $dbConnection->prepare('
            SELECT 
                            id, user_init_id, user_to_id, date_create, subj, sabj_pl, msg, client_id, unit_id, status, hash_name, comment, is_read, lock_by, ok_by, ok_date
                            from tickets
                            where (
                            (find_in_set(:user_id,user_to_id) and unit_id IN (' . $in_query . ') and arch=:n) or
                            (find_in_set(:n1,user_to_id) and unit_id IN (' . $in_query2 . ') and arch=:n2)
                            ) or (user_init_id=:user_id2 and arch=:n3)
                            order by id DESC
                            limit :start_pos, :perpage');
            
            $paramss = array(
                ':n' => '1',
                ':n1' => '0',
                ':n2' => '1',
                ':n3' => '1',
                ':user_id' => $uid,
                ':user_id2' => $uid,
                ':start_pos' => $start_pos,
                ':perpage' => $perpage
            );
            
            $stmt->execute(array_merge($vv, $vv2, $paramss));
            $res1 = $stmt->fetchAll();
        } 
        else if ($priv_val == 2) {
            
            $stmt = $dbConnection->prepare('SELECT 
                            id, user_init_id, user_to_id, date_create, subj, sabj_pl, msg, client_id, unit_id, status, hash_name, comment, is_read, lock_by, ok_by, ok_date
                            from tickets
                            where arch=:n
                            order by id DESC
                            limit :start_pos, :perpage');
            
            $stmt->execute(array(
                ':n' => '1',
                ':start_pos' => $start_pos,
                ':perpage' => $perpage
            ));
            $res1 = $stmt->fetchAll();
        }
        
        $aha = get_total_pages('arch', $uid);
        
        $ar_res = array();
        
        foreach ($res1 as $row) {
            
            if ($row['user_to_id'] <> 0) {
                $to_text = "<div class=''>" . nameshort(name_of_user_ret($row['user_to_id'])) . "</div>";
            }
            if ($row['user_to_id'] == 0) {
                $to_text = "<strong data-toggle=\"tooltip\" data-placement=\"bottom\" title=\"" . view_array(get_unit_name_return($row['unit_id'])) . "\">" . lang('t_list_a_all') . "</strong>";
            }
            
            ob_start();
        $t_ago = get_date_ok($row['date_create'], $row['id']);
		$t_from  = $row['date_create'];
		$t_ago = floor((((strtotime($t_ago) - strtotime($t_from))  )));    
            //Start output buffer
            cutstr(make_html($row['subj'], 'no'));
            $cut_subj = ob_get_contents();
            
            //Grab output
            ob_end_clean();
            
            array_push($ar_res, array(
                
                'id' => $row['id'],
                'muclass' => $muclass,
                'subj' => make_html($row['subj'], 'no') ,
				'sabj_pl' => $row['sabj_pl'],
				'comment' => getLastComment($row['id']),
				'msg1' => $row['msg'],
                'msg' => str_replace('"', "", cutstr_help_ret(make_html(strip_tags($row['msg'])) , 'no')) ,
                'hashname' => $row['hash_name'],
                
                'cut_subj' => $cut_subj,
                'get_user_hash_by_id_client' => get_user_hash_by_id($row['client_id']) ,
                'client' => get_user_val_by_id($row['client_id'], 'fio') ,
                'date_create' => $row['date_create'],
                't_ago' => $t_ago,
                'get_deadline_label' => get_deadline_label($row['id']) ,
                'name_of_user_ret' => nameshort(name_of_user_ret($row['user_init_id'])) ,
                
                'init_hash' => get_user_hash_by_id($row['user_init_id']) ,
                'init_fio' => nameshort(name_of_user_ret($row['user_init_id'])) ,
                'to_text' => $to_text,
                'ok_by' => nameshort(name_of_user_ret($row['ok_by'])) ,
                'ok_date' => $row['ok_date'],
            ));
        }
        
        $basedir = dirname(dirname(__FILE__));
        
        ////////////
        try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($basedir . '/views');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $basedir . '/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('list_content_arch.view.tmpl');
            
            // передаём в шаблон переменные и значения
            // выводим сформированное содержание
            echo $template->render(array(
                'get_total_pages_arch' => get_total_pages('arch', $uid) ,
                'user_id' => $uid,
                't_LIST_subj' => lang('t_LIST_subj') ,
                't_LIST_worker' => lang('t_LIST_worker') ,
                't_LIST_create' => lang('t_LIST_create') ,
                't_LIST_init' => lang('t_LIST_init') ,
                't_LIST_to' => lang('t_LIST_to') ,
                'ar_res' => $ar_res,
                'aha' => $aha,
                'MSG_no_records' => lang('MSG_no_records') ,
				't_LIST_placer' => lang('t_LIST_placer') ,
				't_LIST_txtmg' => lang('t_LIST_txtmg') ,
				't_LIST_compl' => lang('t_LIST_compl') ,
				't_LIST_zatr' => lang('t_LIST_zatr') ,
				't_LIST_commen' => lang('t_LIST_commen') ,
				't_LIST_ispo' => lang('t_LIST_ispo') ,
                't_LIST_status' => lang('t_LIST_status') ,
                't_list_a_user_ok' => lang('t_list_a_user_ok') ,
                't_list_a_date_ok' => lang('t_list_a_date_ok')
            ));
        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
    }
}
?>
