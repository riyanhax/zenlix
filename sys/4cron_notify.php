<?php

ini_set('max_execution_time', 300);

$base = dirname(dirname(__FILE__));
include ($base . "/conf.php");
include ($base . "/arava_tools.php");

//include_once($base ."/functions.inc.php");
date_default_timezone_set('Europe/Kiev');
include ($base . '/library/PHPMailer/class.phpmailer.php');

include ($base . '/library/PushBullet/PushBullet.class.php');

include_once $base . '/lang/lang.ua.php';
include_once $base . '/lang/lang.ru.php';
include_once $base . '/lang/lang.en.php';



$dbConnection = new PDO('mysql:host=' . $CONF_DB['host'] . ';dbname=' . $CONF_DB['db_name'], $CONF_DB['username'], $CONF_DB['password'], array(
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
));
$dbConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$dbConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

include ($base . '/library/smsc/smsc_smpp.php');

require_once $base.'/library/Twig/Autoloader.php';
Twig_Autoloader::register();



/*
кому?

получатель будет получать весь лог только тому кому надо



номер заявки
действие
кто инициатор фам и7о7



*/

function make_device_push($type_op, $usr_lang, $usr_id, $ticket_id) {
    global $dbConnection, $base, $CONF;
    
    $th = get_ticket_hash_by_id($ticket_id);
    $cat_ticket = "in";
    
    $lang = $usr_lang;
    
    $MAIL_new = lang($lang, 'MAIL_new');
    $MAIL_refer = lang($lang, 'mail_msg_ticket_refer');
    $MAIL_refer_ext = lang($lang, 'mail_msg_ticket_refer_ext');
    $MAIL_to_w = lang($lang, 'mail_msg_ticket_to_ext');
    
    $MAIL_msg_comment = lang($lang, 'mail_msg_ticket_comment');
    $MAIL_msg_comment_ext = lang($lang, 'mail_msg_ticket_comment_ext');
    
    $MAIL_msg_lock = lang($lang, 'mail_msg_ticket_lock');
    $MAIL_msg_lock_ext = lang($lang, 'mail_msg_ticket_lock_ext');
    $MAIL_msg_unlock = lang($lang, 'mail_msg_ticket_unlock');
    $MAIL_msg_unlock_ext = lang($lang, 'mail_msg_ticket_unlock_ext');
    $MAIL_msg_ok = lang($lang, 'mail_msg_ticket_ok');
    $MAIL_msg_ok_ext = lang($lang, 'mail_msg_ticket_ok_ext');
    $MAIL_msg_no_ok = lang($lang, 'mail_msg_ticket_no_ok');
    $MAIL_msg_no_ok_ext = lang($lang, 'mail_msg_ticket_no_ok_ext');
    
    $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
    $stmt->execute(array(
        ':tid' => $ticket_id
    ));
    $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $h = $ticket_res['hash_name'];
    $user_init_id = $ticket_res['user_init_id'];
    
    $uss = nameshort(name_of_user_ret($user_init_id));
    
    $uin = name_of_user_ret($user_init_id);
     //????? IF CLIENT /////
    
    $nou = name_of_client_ret($ticket_res['client_id']);
    $to_id = $ticket_res['user_to_id'];
    $s = $ticket_res['subj'];
    $m = $ticket_res['msg'];

$m=preg_replace('/\[file:([^\]]*)\]/', '', $m);

    $unit_id = $ticket_res['unit_id'];
    
    //кому?
    if ($ticket_res['user_to_id'] <> 0) {
        $to_text = "" . name_of_user_ret($to_id) . "";
    } 
    else if ($ticket_res['user_to_id'] == 0) {
        $to_text = view_array(get_unit_name_return($unit_id));
    }
    
    if ($user_init_id == $usr_id) {
        $cat_ticket = "out";
    }
    
    $stmt = $dbConnection->prepare('SELECT device_token from user_devices where user_id=:uid');
    $stmt->execute(array(
        ':uid' => $usr_id
    ));
    $res1 = $stmt->fetchAll();
    
    foreach ($res1 as $value) {
        // code...
        unset($content);
        $content = array();
        if ($type_op == "ticket_create") {
            
            $msg = $uss . " " . lang($lang, 'NEWS_action_create') . " #" . $ticket_id . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            
            /*
            $msg=lang($lang,'MAIL_new').' #'.$ticket_id."\r\n";
            $msg.=lang($lang,'MAIL_subj').": ".$s."\r\n";
            $msg.=lang($lang,'MAIL_created').": ".$uin."\r\n";
            */
            
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        } 
        else if ($type_op == "ticket_refer") {
            
            $msg = $uss . " " . lang($lang, 'NEWS_action_refer') . " #" . $ticket_id . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        }
        
        /*
        else if ($type_op == "ticket_comment") {
        $msg=$MAIL_msg_comment.' #'.$ticket_id."\r\n";
        $msg.=lang($lang,'MAIL_subj').": ".$s."\r\n";
        $msg.=lang($lang,'MAIL_created').": ".$uin."\r\n";
        $content = array(
        'device_token'=>$value['device_token'],
        'msg'=>$msg,
        'th'=>$th,
        'cat'=>$cat_ticket,
        'ticket_id'=>$ticket_id,
        'ticket_action'=>$type_op,
        'ticket_user_init'=>$uss
        );
        }
        */
        else if ($type_op == "ticket_lock") {
            
            $msg = $uss . " " . lang($lang, 'NEWS_action_lock') . " #" . $ticket_id . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        } 
        else if ($type_op == "ticket_unlock") {
            $msg = $uss . " " . lang($lang, 'NEWS_action_unlock') . " #" . $ticket_id . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        } 
        else if ($type_op == "ticket_ok") {
            $msg = $uss . " " . lang($lang, 'NEWS_action_ok') . " #" . $ticket_id . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        } 
        else if ($type_op == "ticket_no_ok") {
            $msg = $uss . " " . lang($lang, 'NEWS_action_no_ok') . " #" . $ticket_id . " " . lang($lang, 'NEWS_action_no_ok') . "\r\n";
            $msg.= lang($lang, 'NEW_subj') . ": " . $s . "\r\n";
            $content = array(
                'device_token' => $value['device_token'],
                'msg' => $msg,
                'th' => $th,
                'cat' => get_ticket_action_priv_api_arr($ticket_id, $usr_id)
            );
        }
        
        /*
        $content[] = array(
        
        );
        */
        
        //print_r($content);
        
        $url = "http://api.zenlix.com/api.php";
        
        //print_r($content);
        $ch = curl_init($url);
        // Setup request to send json via POST.
        $payload = json_encode($content);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type:application/json'
        ));
        // Return response instead of printing.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Send request.
        $result = curl_exec($ch);
        curl_close($ch);
        
        //API Url
        
        //$content = $results;
        
        
    }
}

function send_smsc($type_op, $lang, $user_mail, $ticket_id) {
    global $dbConnection, $base, $CONF;
    
    $MAIL_new = lang($lang, 'MAIL_new');
    $MAIL_refer = lang($lang, 'mail_msg_ticket_refer');
    $MAIL_refer_ext = lang($lang, 'mail_msg_ticket_refer_ext');
    $MAIL_to_w = lang($lang, 'mail_msg_ticket_to_ext');
    
    $MAIL_msg_comment = lang($lang, 'mail_msg_ticket_comment');
    $MAIL_msg_comment_ext = lang($lang, 'mail_msg_ticket_comment_ext');
    
    $MAIL_msg_lock = lang($lang, 'mail_msg_ticket_lock');
    $MAIL_msg_lock_ext = lang($lang, 'mail_msg_ticket_lock_ext');
    $MAIL_msg_unlock = lang($lang, 'mail_msg_ticket_unlock');
    $MAIL_msg_unlock_ext = lang($lang, 'mail_msg_ticket_unlock_ext');
    $MAIL_msg_ok = lang($lang, 'mail_msg_ticket_ok');
    $MAIL_msg_ok_ext = lang($lang, 'mail_msg_ticket_ok_ext');
    $MAIL_msg_no_ok = lang($lang, 'mail_msg_ticket_no_ok');
    $MAIL_msg_no_ok_ext = lang($lang, 'mail_msg_ticket_no_ok_ext');
    
    $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
    $stmt->execute(array(
        ':tid' => $ticket_id
    ));
    $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $h = $ticket_res['hash_name'];
    $user_init_id = $ticket_res['user_init_id'];
    $uin = name_of_user_ret($user_init_id);
     //????? IF CLIENT /////
    
    $nou = name_of_client_ret($ticket_res['client_id']);
    $to_id = $ticket_res['user_to_id'];
    $s = $ticket_res['subj'];
    $m = $ticket_res['msg'];

    $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);

    $unit_id = $ticket_res['unit_id'];
    
    //кому?
    if ($ticket_res['user_to_id'] <> 0) {
        $to_text = "" . name_of_user_ret($to_id) . "";
    } 
    else if ($ticket_res['user_to_id'] == 0) {
        $to_text = view_array(get_unit_name_return($unit_id));
    }
    
    if ($type_op == "ticket_create") {
        $msg = lang($lang, 'MAIL_new') . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_refer") {
        
        $msg = $MAIL_refer . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_comment") {
        
        $msg = $MAIL_msg_comment . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_lock") {
        
        $msg = $MAIL_msg_lock . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_unlock") {
        
        $msg = $MAIL_msg_unlock . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_ok") {
        
        $msg = $MAIL_msg_ok . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    } 
    else if ($type_op == "ticket_no_ok") {
        
        $msg = $MAIL_msg_no_ok . ' #' . $ticket_id . "\r\n";
        $msg.= lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        
        $ar_list = explode(",", get_conf_param('smsc_list_action'));
        if (in_array($type_op, $ar_list)) {
            if (check_notify_sms_user($type_op, $user_mail)) {
                send_sms($user_mail, $msg, 1);
            }
        }
    }
}

function get_ticket_val_by_hash($what, $in) {
    global $CONF;
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT ' . $what . ' FROM tickets where hash_name=:in');
    $stmt->execute(array(
        ':in' => $in
    ));
    
    $fior = $stmt->fetch(PDO::FETCH_NUM);
    
    return $fior[0];
}

function get_user_val_by_id($id, $in) {
    
    //val.id
    global $CONF;
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT ' . $in . ' FROM users where id=:id');
    $stmt->execute(array(
        ':id' => $id
    ));
    
    $fior = $stmt->fetch(PDO::FETCH_NUM);
    
    return $fior[0];
}

function get_ticket_action_priv_api_arr($ticked_id, $user_id) {
    global $CONF, $dbConnection;
    
    $priv_res_arr = array();
    
    $ticket_hash = get_ticket_hash_by_id($ticked_id);
    
    $ticket['arch'] = get_ticket_val_by_hash('arch', $ticket_hash);
    $ticket['status'] = get_ticket_val_by_hash('status', $ticket_hash);
    $ticket['ok_by'] = get_ticket_val_by_hash('ok_by', $ticket_hash);
    $ticket['lock_by'] = get_ticket_val_by_hash('lock_by', $ticket_hash);
    $ticket['user_init_id'] = get_ticket_val_by_hash('user_init_id', $ticket_hash);
    $ticket['user_to_id'] = get_ticket_val_by_hash('user_to_id', $ticket_hash);
    $ticket['user_units'] = get_ticket_val_by_hash('unit_id', $ticket_hash);
    
    $user['priv'] = get_user_val_by_id($user_id, 'priv');
    $user['units'] = get_user_val_by_id($user_id, 'unit');
    
    $haystack = explode(",", $ticket['user_to_id']);
    $haystack_units = explode(",", $user['units']);
    
    if ($ticket['arch'] == 1) {
        $st = 'arch';
    } 
    else if ($ticket['arch'] == 0) {
        if ($ticket['status'] == 1) {
            
            //$st = 'ok';
            if ($ticket['ok_by'] == $user_id) {
                $st = "ok_by_me";
            }
            
            if ($ticket['ok_by'] <> $user_id) {
                $st = "ok_by_other";
            }
        }
        if ($ticket['status'] == 0) {
            if ($ticket['lock_by'] <> 0) {
                
                if ($ticket['lock_by'] == $user_id) {
                    $st = "lock_by_me";
                }
                
                if ($ticket['lock_by'] <> $user_id) {
                    $st = "lock_by_other";
                }
            } 
            else if ($ticket['lock_by'] == 0) {
                $st = "free";
            }
        }
    }
    
    //echo $st;
    
    $tpriv = false;
    
    $priv_res = "no_priv";
    
    //Если я инициатор
    //Если заявка мне
    //Если заявка моему отделу
    
    if ($user['priv'] == 1) {
        
        if ($ticket['user_init_id'] == $user_id) {
            $tpriv = true;
        }
        if (in_array($ticket['user_units'], $haystack_units)) {
            if ($ticket['user_to_id'] == 0) {
                $tpriv = true;
            } 
            else if ($ticket['user_to_id'] != 0) {
                if (in_array($user_id, $haystack)) {
                    $tpriv = true;
                }
            }
        }
        
        if ($tpriv == true) {
            switch ($st) {
                case 'arch':
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    // code...
                    break;

                case 'free':
                    $priv_res = "ref,lock";
                    
                    //array_push($priv_res_arr, "ref");
                    array_push($priv_res_arr, "lock");
                    // code...
                    break;

                case 'ok_by_me':
                    $priv_res = "un_ok";
                    array_push($priv_res_arr, "un_ok");
                    // code...
                    break;

                case 'ok_by_other':
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    // code...
                    break;

                case 'lock_by_me':
                    $priv_res = "ref,unlock,ok";
                    
                    //array_push($priv_res_arr, "ref");
                    array_push($priv_res_arr, "unlock");
                    array_push($priv_res_arr, "ok");
                    // code...
                    break;

                case 'lock_by_other':
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    // code...
                    break;

                default:
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    break;
            }
        } 
        else if ($tpriv == true) {
            $priv_res = "no_priv";
            array_push($priv_res_arr, "no_priv");
        }
    }
    
    if ($user['priv'] == 0) {
        
        if ($ticket['user_init_id'] == $user_id) {
            $tpriv = true;
        }
        if (in_array($ticket['user_units'], $haystack_units)) {
            $tpriv = true;
        }
        
        if ($tpriv == true) {
            switch ($st) {
                case 'arch':
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    // code...
                    break;

                case 'free':
                    $priv_res = "ref,lock";
                    
                    //array_push($priv_res_arr, "ref");
                    array_push($priv_res_arr, "lock");
                    // code...
                    break;

                case 'ok_by_me':
                    $priv_res = "un_ok";
                    array_push($priv_res_arr, "un_ok");
                    // code...
                    break;

                case 'ok_by_other':
                    $priv_res = "un_ok";
                    array_push($priv_res_arr, "un_ok");
                    // code...
                    break;

                case 'lock_by_me':
                    $priv_res = "ref,unlock,ok";
                    
                    //array_push($priv_res_arr, "ref");
                    array_push($priv_res_arr, "unlock");
                    array_push($priv_res_arr, "ok");
                    // code...
                    break;

                case 'lock_by_other':
                    $priv_res = "unlock";
                    array_push($priv_res_arr, "unlock");
                    // code...
                    break;

                default:
                    $priv_res = "no_priv";
                    array_push($priv_res_arr, "no_priv");
                    break;
            }
        } 
        else if ($tpriv == true) {
            $priv_res = "no_priv";
            array_push($priv_res_arr, "no_priv");
        }
    }
    
    if ($user['priv'] == 2) {
        
        switch ($st) {
            case 'arch':
                $priv_res = "no_priv";
                array_push($priv_res_arr, "no_priv");
                // code...
                break;

            case 'free':
                $priv_res = "ref,lock";
                
                //array_push($priv_res_arr, "ref");
                array_push($priv_res_arr, "lock");
                // code...
                break;

            case 'ok_by_me':
                $priv_res = "un_ok";
                array_push($priv_res_arr, "un_ok");
                // code...
                break;

            case 'ok_by_other':
                $priv_res = "un_ok";
                array_push($priv_res_arr, "un_ok");
                // code...
                break;

            case 'lock_by_me':
                $priv_res = "ref,unlock,ok";
                
                //array_push($priv_res_arr, "ref");
                array_push($priv_res_arr, "unlock");
                array_push($priv_res_arr, "ok");
                // code...
                break;

            case 'lock_by_other':
                $priv_res = "unlock";
                array_push($priv_res_arr, "unlock");
                // code...
                break;

            default:
                $priv_res = "no_priv";
                array_push($priv_res_arr, "no_priv");
                break;
        }
    }
    
    /*
    foreach ($priv_res_arr as $key => $value) {
    # code...
    $ares[$value]=$value;
    }
    */
    
    $ares = implode(",", $priv_res_arr);
    
    return $ares;
    
    //echo $lo;
    
}

function send_pushbullet($type_op, $lang, $user_mail, $ticket_id) {
    global $dbConnection, $base, $CONF;
    
    $MAIL_new = lang($lang, 'MAIL_new');
    $MAIL_refer = lang($lang, 'mail_msg_ticket_refer');
    $MAIL_refer_ext = lang($lang, 'mail_msg_ticket_refer_ext');
    $MAIL_to_w = lang($lang, 'mail_msg_ticket_to_ext');
    
    $MAIL_msg_comment = lang($lang, 'mail_msg_ticket_comment');
    $MAIL_msg_comment_ext = lang($lang, 'mail_msg_ticket_comment_ext');
    
    $MAIL_msg_lock = lang($lang, 'mail_msg_ticket_lock');
    $MAIL_msg_lock_ext = lang($lang, 'mail_msg_ticket_lock_ext');
    $MAIL_msg_unlock = lang($lang, 'mail_msg_ticket_unlock');
    $MAIL_msg_unlock_ext = lang($lang, 'mail_msg_ticket_unlock_ext');
    $MAIL_msg_ok = lang($lang, 'mail_msg_ticket_ok');
    $MAIL_msg_ok_ext = lang($lang, 'mail_msg_ticket_ok_ext');
    $MAIL_msg_no_ok = lang($lang, 'mail_msg_ticket_no_ok');
    $MAIL_msg_no_ok_ext = lang($lang, 'mail_msg_ticket_no_ok_ext');
    
    $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
    $stmt->execute(array(
        ':tid' => $ticket_id
    ));
    $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $h = $ticket_res['hash_name'];
    $user_init_id = $ticket_res['user_init_id'];
    $uin = name_of_user_ret($user_init_id);
     //????? IF CLIENT /////
    
    $nou = name_of_client_ret($ticket_res['client_id']);
    $to_id = $ticket_res['user_to_id'];
    $s = $ticket_res['subj'];
    $m = $ticket_res['msg'];
    $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
    $unit_id = $ticket_res['unit_id'];
    
    //кому?
    if ($ticket_res['user_to_id'] <> 0) {
        $to_text = "" . name_of_user_ret($to_id) . "";
    } 
    else if ($ticket_res['user_to_id'] == 0) {
        $to_text = view_array(get_unit_name_return($unit_id));
    }
    
    if ($type_op == "ticket_create") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_new . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
            try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $response = $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            $response = $e->getMessage();
        } finally {
                funkit_setlog('ticket:create', $response);
            }
    } 
    else if ($type_op == "ticket_refer") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_refer . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            die($e->getMessage());
        }
    } 
    else if ($type_op == "ticket_comment") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_msg_comment . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $response = $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            $response = $e->getMessage();
        } finally {
            funkit_setlog('ticket:comment', $response);
        }
    } 
    else if ($type_op == "ticket_lock") {
        
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_msg_lock . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            die($e->getMessage());
        }
    } 
    else if ($type_op == "ticket_unlock") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_msg_unlock . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            die($e->getMessage());
        }
    } 
    else if ($type_op == "ticket_ok") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_msg_ok . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            die($e->getMessage());
        }
    } 
    else if ($type_op == "ticket_no_ok") {
        $tn = lang($lang, 'TICKET_name') . ' #' . $ticket_id . " (" . $MAIL_msg_no_ok . ")";
        $msg = lang($lang, 'MAIL_subj') . ": " . $s . "\r\n";
        $msg.= lang($lang, 'MAIL_created') . ": " . $uin . "\r\n";
        $msg.= lang($lang, 'MAIL_to') . ": " . $to_text . "\r\n";
        $msg.= lang($lang, 'MAIL_worker') . ": " . $nou . "\r\n";
        $msg.= lang($lang, 'MAIL_msg') . ": " . $m . "\r\n";
        
        try {
            $p = new PushBullet(get_conf_param('pb_api'));
            
            //email, title, msg
            $p->pushNote($user_mail, $tn, $msg);
        }
        catch(PushBulletException $e) {
            die($e->getMessage());
        }
    }
}

function check_user_devices($id) {
    global $dbConnection, $CONF;
    
    $stmt = $dbConnection->prepare('SELECT device_token from user_devices where user_id=:uid');
    $stmt->execute(array(
        ':uid' => $id
    ));
    $res1 = $stmt->fetchAll();
    
    if (!empty($res1)) {
        return true;
    }
    if (empty($res1)) {
        return false;
    }
    
    //foreach ($res1 as $row) {}
    
    
}

function nameshort($name) {
    $nameshort = preg_replace('/(\w+) (\w)\w+ (\w)\w+/iu', '$1 $2.$3.', $name);
    return $nameshort;
}

function get_conf_param($in) {
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT value FROM perf where param=:in');
    $stmt->execute(array(
        ':in' => $in
    ));
    $fio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $fio['value'];
}
function get_unit_name_return($input) {
    global $dbConnection;
    
    $u = explode(",", $input);
    $res = array();
    foreach ($u as $val) {
        
        $stmt = $dbConnection->prepare('SELECT name FROM deps where id=:val');
        $stmt->execute(array(
            ':val' => $val
        ));
        $dep = $stmt->fetch(PDO::FETCH_ASSOC);
        
        array_push($res, $dep['name']);
        
        //$res.=$dep['name'];
        //$res.="<br>";
        
    }
    
    return $res;
}

$def_timezone = get_conf_param('time_zone');

date_default_timezone_set($def_timezone);
$date_tz = new DateTime();
$date_tz->setTimezone(new DateTimeZone($def_timezone));
$now_date_time = $date_tz->format('Y-m-d H:i:s');

$CONF = array(
    'title_header' => get_conf_param('title_header') ,
    'hostname' => 'http://' . get_conf_param('hostname') ,
    'real_hostname' => 'http://' . get_conf_param('hostname')."/" ,
    'mail' => get_conf_param('mail') ,
    'days2arch' => get_conf_param('days2arch') ,
    'name_of_firm' => get_conf_param('name_of_firm') ,
    'fix_subj' => get_conf_param('fix_subj') ,
    'first_login' => get_conf_param('first_login') ,
    'file_uploads' => get_conf_param('file_uploads') ,
    'file_types' => '(' . get_conf_param('file_types') . ')',
    'file_size' => get_conf_param('file_size') ,
    'now_dt' => $now_date_time
);
$CONF_MAIL = array(
    'active' => get_conf_param('mail_active') ,
    'host' => get_conf_param('mail_host') ,
    'port' => get_conf_param('mail_port') ,
    'auth' => get_conf_param('mail_auth') ,
    'auth_type' => get_conf_param('mail_auth_type') ,
    'username' => get_conf_param('mail_username') ,
    'password' => get_conf_param('mail_password') ,
    'from' => get_conf_param('mail_from') ,
    'debug' => 'false'
);

function check_notify_mail_user($action, $mail) {
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT id from users where email=:uto');
    $stmt->execute(array(
        ':uto' => $mail
    ));
    $tt = $stmt->fetch(PDO::FETCH_ASSOC);
    $uid = $tt['id'];
    
    $stmt2 = $dbConnection->prepare('SELECT mail from users_notify where user_id=:uto');
    $stmt2->execute(array(
        ':uto' => $uid
    ));
    $tt2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($tt2['mail']) {
        
        $p_str = explode(",", $tt2['mail']);
        
        if (in_array($action, $p_str)) {
            $res = true;
        }
        if (!in_array($action, $p_str)) {
            $res = false;
        }
    } 
    else if (!$tt2['mail']) {
        $res = true;
    }
    
    return $res;
}

function check_notify_sms_user($action, $mail) {
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT id from users where mob=:uto');
    $stmt->execute(array(
        ':uto' => $mail
    ));
    $tt = $stmt->fetch(PDO::FETCH_ASSOC);
    $uid = $tt['id'];
    
    $stmt2 = $dbConnection->prepare('SELECT sms from users_notify where user_id=:uto');
    $stmt2->execute(array(
        ':uto' => $uid
    ));
    $tt2 = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    if ($tt2['sms']) {
        
        $p_str = explode(",", $tt2['sms']);
        
        if (in_array($action, $p_str)) {
            $res = true;
        }
        if (!in_array($action, $p_str)) {
            $res = false;
        }
    } 
    else if (!$tt2['sms']) {
        $res = false;
    }
    
    return $res;
}

function get_ticket_hash_by_id($in) {
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('select hash_name from tickets where id=:in');
    $stmt->execute(array(
        ':in' => $in
    ));
    $total_ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $tt = $total_ticket['hash_name'];
    return $tt;
}

function send_mail($to, $subj, $msg, $msg_id) {
    global $CONF, $CONF_MAIL, $dbConnection;
    
    $v = parse_url("http://" . get_conf_param('hostname'));
    
    if (!isset($msg_id)) {
        $msg_id = md5(time());
    }
    
    //$msg_r="------------------- PLEASE DO NOT REMOVE THIS LINE ---------------------\n\r";
    //$msg_r.="UNIQ_TICKET_CODE:";
    //$msg=$msg_r.$msg;
    
    //echo "helo";
    if (get_conf_param('mail_type') == "sendmail") {
        
        $mail = new PHPMailer();
        
        //$mail->SMTPDebug = 1;
        $mail->CharSet = 'UTF-8';
        $mail->IsSendmail();
        
        $mail->AddReplyTo($CONF_MAIL['from'], $CONF['name_of_firm']);
        $mail->AddAddress($to, $to);
        $mail->MessageID = $msg_id . "@" . $v['host'];
        $mail->SetFrom($CONF_MAIL['from'], $CONF['name_of_firm']);
        $mail->Subject = $subj;
        $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
        $mail->MsgHTML($msg);
        $mail->Send();
    } 
    else if (get_conf_param('mail_type') == "SMTP") {
        
        $mail = new PHPMailer();
        $mail->CharSet = 'UTF-8';
        $mail->IsSMTP();
        $mail->SMTPAuth = $CONF_MAIL['auth'];
         // enable SMTP authentication
        if (get_conf_param('mail_auth_type') != "none") {
            $mail->SMTPSecure = $CONF_MAIL['auth_type'];
        }
        $mail->Host = $CONF_MAIL['host'];
        $mail->Port = $CONF_MAIL['port'];
        $mail->Username = $CONF_MAIL['username'];
        $mail->Password = $CONF_MAIL['password'];
        
        //$mail->set('Message-ID', '008');
        //$mail->addCustomHeader("Message-ID: 008");
        $mail->MessageID = $msg_id . "@" . $v['host'];
        
        $mail->AddReplyTo($CONF_MAIL['from'], $CONF['name_of_firm']);
        $mail->AddAddress($to, $to);
        $mail->SetFrom($CONF_MAIL['from'], $CONF['name_of_firm']);
        $mail->Subject = $subj;
        $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!';
         // optional - MsgHTML will create an alternate automatically
        $mail->MsgHTML($msg);
        $mail->Send();
    }
}

function name_of_user_ret($input) {
    global $dbConnection;
    
    $u = explode(",", $input);
    $u_count = count($u);
    
    if ($u_count > 1) {
        $res = "";
        foreach ($u as $val) {
            $stmt = $dbConnection->prepare('SELECT fio FROM users where id=:input');
            $stmt->execute(array(
                ':input' => $val
            ));
            $fio = $stmt->fetch(PDO::FETCH_ASSOC);
            $res.= $fio['fio'] . ", ";
        }
        $res = substr($res, 0, -2);
    } 
    else if ($u_count <= 1) {
        $stmt = $dbConnection->prepare('SELECT fio FROM users where id=:input');
        $stmt->execute(array(
            ':input' => $input
        ));
        $fio = $stmt->fetch(PDO::FETCH_ASSOC);
        $res = $fio['fio'];
    }
    return ($res);
}

function lang($lang, $in = 'ru') {
    
    switch ($lang) {
        case 'ua':
            $res = lang_ua($in);
            break;

        case 'ru':
            $res = lang_ru($in);
            break;

        case 'en':
            $res = lang_en($in);
            break;

        default:
            $res = lang_en($in);
    }
    
    return $res;
}

function view_array($in) {
    $end_element = array_pop($in);
    $res = "";
    foreach ($in as $value) {
        
        // делаем что-либо с каждым элементом
        $res.= $value;
        $res.= "<br>";
    }
    $res.= $end_element;
    
    // делаем что-либо с последним элементом $end_element
    
    return $res;
}
function name_of_client_ret($input) {
    global $dbConnection;
    
    $stmt = $dbConnection->prepare('SELECT fio FROM users where id=:input');
    $stmt->execute(array(
        ':input' => $input
    ));
    $fio = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $fio['fio'];
}

function make_mail($type_op, $lang, $user_mail, $ticket_id) {
    global $dbConnection, $base, $CONF;
    
    /*
    ticket_create
    ticket_refer
    ticket_comment
    ticket_lock
    ticket_unlock
    ticket_ok
    ticket_no_ok
    */
    $MAIL_new = lang($lang, 'MAIL_new');
    $MAIL_code = lang($lang, 'MAIL_code');
    $MAIL_2link = lang($lang, 'MAIL_2link');
    $MAIL_info = lang($lang, 'MAIL_info');
    $MAIL_created = lang($lang, 'MAIL_created');
    $MAIL_to = lang($lang, 'MAIL_to');
    $MAIL_prio = lang($lang, 'MAIL_prio');
    $MAIL_worker = lang($lang, 'MAIL_worker');
    $MAIL_msg = lang($lang, 'MAIL_msg');
    $MAIL_subj = lang($lang, 'MAIL_subj');
    $MAIL_text = lang($lang, 'MAIL_text');
    
    $MAIL_refer = lang($lang, 'mail_msg_ticket_refer');
    $MAIL_refer_ext = lang($lang, 'mail_msg_ticket_refer_ext');
    $MAIL_to_w = lang($lang, 'mail_msg_ticket_to_ext');
    
    $MAIL_msg_comment = lang($lang, 'mail_msg_ticket_comment');
    $MAIL_msg_comment_ext = lang($lang, 'mail_msg_ticket_comment_ext');
    
    $MAIL_msg_lock = lang($lang, 'mail_msg_ticket_lock');
    $MAIL_msg_lock_ext = lang($lang, 'mail_msg_ticket_lock_ext');
    $MAIL_msg_unlock = lang($lang, 'mail_msg_ticket_unlock');
    $MAIL_msg_unlock_ext = lang($lang, 'mail_msg_ticket_unlock_ext');
    $MAIL_msg_ok = lang($lang, 'mail_msg_ticket_ok');
    $MAIL_msg_ok_ext = lang($lang, 'mail_msg_ticket_ok_ext');
    $MAIL_msg_no_ok = lang($lang, 'mail_msg_ticket_no_ok');
    $MAIL_msg_no_ok_ext = lang($lang, 'mail_msg_ticket_no_ok_ext');
    
    if ($type_op == "mailers") {
        
        $stmt22 = $dbConnection->prepare('SELECT value FROM perf where param=:tid');
        $stmt22->execute(array(
            ':tid' => 'mailers_text'
        ));
        $mm = $stmt22->fetch(PDO::FETCH_ASSOC);
        $mmm = $mm['value'];
        
        $subject = get_conf_param('mailers_subj');
        $message = $mmm;
        
        send_mail($user_mail, $subject, $message);
    }
    
    if ($type_op == "portal_post_new") {
        
        $stmt = $dbConnection->prepare('SELECT subj, author_id, uniq_id, msg FROM portal_posts where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $post_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $SUBJ_POST = $post_res['subj'];
        $AUTHOR_POST = name_of_user_ret($post_res['author_id']);
        $THREAD_HASH = $post_res['uniq_id'];
        
        $POST_COMMENT = $post_res['msg'];
        
        $subject = $SUBJ_POST . ' - ' . lang($lang, 'POST_MAIL_POST_NEW');




        try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('portal_post_new.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),


'PORTAL_post_comment'=>lang($lang, 'POST_MAIL_POST_NEW') . ' ' . get_conf_param('name_of_firm'),
'MAIL_info'=>lang($lang, 'MAIL_info'),
'POST_created_author'=>lang($lang, 'POST_created_author'),
'POST_MAIL_subj'=>lang($lang, 'POST_MAIL_subj'),
'PORTAL_post_comment_ext'=>lang($lang, 'PORTAL_post_NEWM_ext'),
'MAIL_2link'=>lang($lang, 'PORTAL_post_MAIL_2link'),
'uin'=>$AUTHOR_POST,
'to_text'=>$SUBJ_POST,
'who_init'=>$AUTHOR_POST,
'comment'=>strip_tags($POST_COMMENT),
'h'=>$THREAD_HASH,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_NO')

));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }














        send_mail($user_mail, $subject, $message, $post_res['uniq_id']);
    } 
    else if ($type_op == "portal_post_comment") {
        
        $stmt = $dbConnection->prepare('SELECT subj, author_id, uniq_id FROM portal_posts where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $post_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $SUBJ_POST = $post_res['subj'];
        $AUTHOR_POST = name_of_user_ret($post_res['author_id']);
        $THREAD_HASH = $post_res['uniq_id'];
        
        $stmt2 = $dbConnection->prepare('SELECT * FROM post_comments where p_id=:tid ORDER BY id DESC LIMIT 1');
        $stmt2->execute(array(
            ':tid' => $ticket_id
        ));
        $res_post_comment = $stmt2->fetch(PDO::FETCH_ASSOC);
        
        //$user_init_comment=$res_post_comment['user_id'];
        
        $USER_AUTHOR_COMMENT = name_of_user_ret($res_post_comment['user_id']);
        $POST_COMMENT = $res_post_comment['comment_text'];
        
        $subject = $SUBJ_POST . ' - ' . lang($lang, 'POST_MAIL_COMMENT');
        
     





 try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('portal_post_comment.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),


'PORTAL_post_comment'=>lang($lang, 'POST_MAIL_COMMENT') . ' ' . get_conf_param('name_of_firm'),
'MAIL_info'=>lang($lang, 'MAIL_info'),
'POST_created_author'=>lang($lang, 'POST_created_author'),
'POST_MAIL_subj'=>lang($lang, 'POST_MAIL_subj'),
'PORTAL_post_comment_ext'=>lang($lang, 'PORTAL_post_comment_ext'),
'MAIL_2link'=>lang($lang, 'PORTAL_post_MAIL_2link'),
'uin'=>$AUTHOR_POST,
'to_text'=>$SUBJ_POST,
'who_init'=>$AUTHOR_POST,
'comment'=>strip_tags($POST_COMMENT),
'h'=>$THREAD_HASH,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_NO')

));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }









        send_mail($user_mail, $subject, $message, $post_res['uniq_id']);
    } 
    else if ($type_op == "ticket_create") {
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_new;
        
        

try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('new_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),


'MAIL_new_ext'=>lang('mail_msg_ticket_new'),
'MAIL_new'=>$MAIL_new,
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')

));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }

        
        if (check_notify_mail_user($type_op, $user_mail)) {
            do {
                try {
                    print "Sending email to $user_mail\n";
                    send_mail($user_mail, $subject, $message, $h);

                    $error = false;
                } catch (Exception $e) {
                    $error = true;
                    print $e->getMessage(). "\n";
                }
            } while ($error === true);
        }
        
        /*
        
        ticket_create:true,
        ticket_refer:true,
        ticket_comment:true,
        ticket_lock:true,
        ticket_unlock:true,
        ticket_ok:true,
        ticket_no_ok:true
        
        
        
        
        if (check_notify_mail_user($type_op, $user_mail))
        {
        send_mail($user_mail,$subject,$message,$h);
        }
        */
    } 
    else if ($type_op == "ticket_refer") {
        
        /*
        Тема: Заявка # переадресована
        Текст: ФИО, Вы получили это сообщение, потому что заявка была переадресована.
        send_mail($to,$subj,$msg);
        */
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'refer'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_refer;
        
       


try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('refer_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),

'MAIL_refer'=>$MAIL_refer,
'MAIL_refer_ext'=>$MAIL_refer_ext,
'who_init'=>$who_init,
'MAIL_to_w'=>$MAIL_to_w,
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')   ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }


        if (check_notify_mail_user($type_op, $user_mail)) {
            send_mail($user_mail, $subject, $message, $h);
        }
    } 
    else if ($type_op == "ticket_comment") {
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'comment'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        $wuid = $ticket_log_res['init_user_id'];
        
        $stmt_com = $dbConnection->prepare('SELECT comment_text FROM comments where t_id=:tid and user_id=:uid order by ID desc limit 1');
        $stmt_com->execute(array(
            ':tid' => $ticket_id,
            ':uid' => $wuid
        ));
        $ticket_com_res = $stmt_com->fetch(PDO::FETCH_ASSOC);
        $comment = $ticket_com_res['comment_text'];
        $comment=preg_replace('/\[file:([^\]]*)\]/', '', $comment);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0 || $ticket_res['user_to_id'] == '') {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_msg_comment;



//echo $base."/library/Twig/Autoloader.php";
        try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('comment_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),
'MAIL_msg_comment'=>$MAIL_msg_comment,
'MAIL_msg_comment_ext'=>$MAIL_msg_comment_ext,
'who_init'=>$who_init,
'comment'=>strip_tags($comment),
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')
   
    ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }


        $message = str_replace("{MAIL_msg_comment}", $MAIL_msg_comment, $message);
        $message = str_replace("{MAIL_msg_comment_ext}", $MAIL_msg_comment_ext, $message);
        $message = str_replace("{who_init}", $who_init, $message);
        $message = str_replace("{comment}", $comment, $message);
        
        $message = str_replace("{MAIL_code}", $MAIL_code, $message);
        $message = str_replace("{ticket_id}", $ticket_id, $message);
        
        $message = str_replace("{MAIL_2link}", $MAIL_2link, $message);
        $message = str_replace("{MAIL_info}", $MAIL_info, $message);
        
        $message = str_replace("{MAIL_created}", $MAIL_created, $message);
        $message = str_replace("{uin}", $uin, $message);
        
        $message = str_replace("{MAIL_to}", $MAIL_to, $message);
        $message = str_replace("{to_text}", $to_text, $message);
        
        $message = str_replace("{MAIL_prio}", $MAIL_prio, $message);
        $message = str_replace("{prio}", $prio, $message);
        $message = str_replace("{MAIL_worker}", $MAIL_worker, $message);
        $message = str_replace("{nou}", $nou, $message);
        
        $message = str_replace("{MAIL_msg}", $MAIL_msg, $message);
        $message = str_replace("{MAIL_subj}", $MAIL_subj, $message);
        $message = str_replace("{s}", $s, $message);
        $message = str_replace("{MAIL_text}", $MAIL_text, $message);
        $message = str_replace("{m}", $m, $message);
        
        $message = str_replace("{h}", $h, $message);

        if (check_notify_mail_user($type_op, $user_mail)) {
            do {
                try {
                    print "Sending email to $user_mail\n";
                    send_mail($user_mail, $subject, $message, $h);

                    $error = false;
                } catch (Exception $e) {
                    $error = true;
                    print $e->getMessage(). "\n";
                }
            } while ($error === true);
        }
    } 
    else if ($type_op == "ticket_lock") {
        
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'lock'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_msg_lock;
        
      


 try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('lock_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),
'MAIL_msg_lock' =>$MAIL_msg_lock,
'MAIL_msg_lock_ext' =>$MAIL_msg_lock_ext,
'who_init' =>$who_init,
'MAIL_code' =>$MAIL_code,
'ticket_id' =>$ticket_id,
'MAIL_2link' =>$MAIL_2link,
'MAIL_info' =>$MAIL_info,
'MAIL_created' =>$MAIL_created,
'uin' =>$uin,
'MAIL_to' =>$MAIL_to,
'to_text' =>$to_text,
'MAIL_prio' =>$MAIL_prio,
'prio' =>$prio,
'MAIL_worker' =>$MAIL_worker,
'nou' =>$nou,
'MAIL_msg' =>$MAIL_msg,
'MAIL_subj' =>$MAIL_subj,
's' =>$s,
'MAIL_text' =>strip_tags($MAIL_text),
'm' =>strip_tags($m),
'h' =>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')
   
    ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }






        if (check_notify_mail_user($type_op, $user_mail)) {
            send_mail($user_mail, $subject, $message, $h);
        }
    } 
    else if ($type_op == "ticket_unlock") {
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'unlock'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_msg_unlock;



try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('unlock_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),


'MAIL_msg_unlock'=>$MAIL_msg_unlock,
'MAIL_msg_unlock_ext'=>$MAIL_msg_unlock_ext,
'who_init'=>$who_init,
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')

    ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }




        if (check_notify_mail_user($type_op, $user_mail)) {
            send_mail($user_mail, $subject, $message, $h);
        }
    } 
    else if ($type_op == "ticket_ok") {
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'ok'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_msg_ok;
        
       


try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('ok_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),

'MAIL_msg_ok'=>$MAIL_msg_ok,
'MAIL_msg_ok_ext'=>$MAIL_msg_ok_ext,
'who_init'=>$who_init,
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')
    ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }





        if (check_notify_mail_user($type_op, $user_mail)) {
            send_mail($user_mail, $subject, $message, $h);
        }
    } 
    else if ($type_op == "ticket_no_ok") {
        $stmt = $dbConnection->prepare('SELECT user_init_id,user_to_id,date_create,subj,msg, client_id, unit_id, status, hash_name, prio,last_update FROM tickets where id=:tid');
        $stmt->execute(array(
            ':tid' => $ticket_id
        ));
        $ticket_res = $stmt->fetch(PDO::FETCH_ASSOC);
        
        //Узнать кем?
        $stmt_log = $dbConnection->prepare('SELECT init_user_id FROM ticket_log where ticket_id=:tid and msg=:msg order by ID desc limit 1');
        $stmt_log->execute(array(
            ':tid' => $ticket_id,
            ':msg' => 'no_ok'
        ));
        $ticket_log_res = $stmt_log->fetch(PDO::FETCH_ASSOC);
        $who_init = name_of_user_ret($ticket_log_res['init_user_id']);
        
        $h = $ticket_res['hash_name'];
        $user_init_id = $ticket_res['user_init_id'];
        $uin = name_of_user_ret($user_init_id);
         //????? IF CLIENT /////
        
        $prio = lang($lang, 't_list_a_p_norm');
        if ($ticket_res['prio'] == "0") {
            $prio = lang($lang, 't_list_a_p_low');
        } 
        else if ($ticket_res['prio'] == "2") {
            $prio = lang($lang, 't_list_a_p_high');
        }
        $nou = name_of_client_ret($ticket_res['client_id']);
        $to_id = $ticket_res['user_to_id'];
        $s = $ticket_res['subj'];
        $m = $ticket_res['msg'];
        $m=preg_replace('/\[file:([^\]]*)\]/', '', $m);
        $unit_id = $ticket_res['unit_id'];
        
        //кому?
        if ($ticket_res['user_to_id'] <> 0) {
            $to_text = "" . name_of_user_ret($to_id) . "";
        } 
        else if ($ticket_res['user_to_id'] == 0) {
            $to_text = view_array(get_unit_name_return($unit_id));
        }
        
        $subject = lang($lang, 'TICKET_name') . ' #' . $ticket_id . ' - ' . $MAIL_msg_no_ok;
        

try {
            
            // указывае где хранятся шаблоны
            $loader = new Twig_Loader_Filesystem($base.'/app/mail_tmpl');
            
            // инициализируем Twig
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $base . '/app/cache',
                ));
            } 
            else {
                $twig = new Twig_Environment($loader);
            }
            
            // подгружаем шаблон
            $template = $twig->loadTemplate('refer_ticket.tpl');

$message=$template->render(array(
'real_hostname'=>$CONF['real_hostname'],
'name_of_firm'=>get_conf_param('name_of_firm'),
'MAIL_msg_no_ok'=>$MAIL_msg_no_ok,
'MAIL_msg_no_ok_ext'=>$MAIL_msg_no_ok_ext,
'who_init'=>$who_init,
'MAIL_code'=>$MAIL_code,
'ticket_id'=>$ticket_id,
'MAIL_2link'=>$MAIL_2link,
'MAIL_info'=>$MAIL_info,
'MAIL_created'=>$MAIL_created,
'uin'=>$uin,
'MAIL_to'=>$MAIL_to,
'to_text'=>$to_text,
'MAIL_prio'=>$MAIL_prio,
'prio'=>$prio,
'MAIL_worker'=>$MAIL_worker,
'nou'=>$nou,
'MAIL_msg'=>$MAIL_msg,
'MAIL_subj'=>$MAIL_subj,
's'=>$s,
'MAIL_text'=>strip_tags($MAIL_text),
'm'=>strip_tags($m),
'h'=>$h,
'REPLY_INFORMATION'=>lang($lang,'REPLY_INFORMATION_YES')
   ));

        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }


        
        if (check_notify_mail_user($type_op, $user_mail)) {
            send_mail($user_mail, $subject, $message, $h);
        }
    }
}

$stmt = $dbConnection->prepare('SELECT id, delivers_id,type_op,ticket_id 
from notification_pool where status=:n');
$stmt->execute(array(
    ':n' => '0'
));
$res1 = $stmt->fetchAll();

foreach ($res1 as $qrow) {
    //
    $r_id = $qrow['id'];
    $stmt_del = $dbConnection->prepare('delete from notification_pool where id=:n');
    $stmt_del->execute(array(
        ':n' => $r_id
    ));
    
    $users = explode(",", $qrow['delivers_id']);
    $type_op = $qrow['type_op'];
    $ticket_id = $qrow['ticket_id'];
    
    foreach ($users as $val) {
        
        //from users fio,lang,email where status=1
        //$val
        
        $stmt = $dbConnection->prepare('SELECT email, pb, lang, mob, id FROM users where id=:tid');
        $stmt->execute(array(
            ':tid' => $val
        ));
        $usr_info = $stmt->fetch(PDO::FETCH_ASSOC);
        $pb = $usr_info['pb'];
        $usr_mail = strtolower($usr_info['email']);
        $usr_lang = $usr_info['lang'];
        $mob = $usr_info['mob'];
        
        $usr_id = $usr_info['id'];
        
        // $lb=$fio['lock_by'];
        
        if (get_conf_param('pb_active') == "true") {
            if ($pb) {
                send_pushbullet($type_op, $usr_lang, $pb, $ticket_id);
            }
        }
        if (get_conf_param('mail_active') == "true") {
            if ($usr_mail) {
                make_mail($type_op, $usr_lang, $usr_mail, $ticket_id);
            }
        }
        
        if (check_user_devices($usr_id)) {
            
            make_device_push($type_op, $usr_lang, $usr_id, $ticket_id);
        }
        
        if (get_conf_param('smsc_active') == "true") {
            if ($mob) {
                send_smsc($type_op, $usr_lang, $mob, $ticket_id);
            }
        }
        
        //make_mail($type_op, $usr_lang, $usr_mail, $ticket_id);
        
        
    }
}

//make_mail('ticket_no_ok','ru', 'info@rustem.com.ua', '288');
//send_mail('info@rustem.com.ua','hello','eeee');
/*
  
*/
include ($base . '/sys/scheduler.php');
?>