<?php
/**
 * Created by PhpStorm.
 * User: arava (newbie.jedicoder@gmail.com)
 * Date: 06.03.19
 * Time: 16:50
 */
$base = dirname(dirname(dirname(__FILE__)));

include ($base . "/arava_tools.php");

class EventsReport
{
    protected $events =
        array(
            0 => array(
                'event' => 'ok',
                'desc'  => 'заявка выполнена'
            ),
            1 => array(
                'event' => 'comment',
                'desc'  => 'прокоментирована'
            ),
            2 => array(
                'event' => 'lock',
                'desc'  => 'в работе'
            ),
            3 => array(
                'event' => 'create',
                'desc'  => 'новая заявка'
            ),
            4 => array(
                'event' => 'refer',
                'desc'  => 'переадресована'
            ),
            5 => array(
                'event' => 'canceled',
                'desc'  => 'отклонена'
            ),
            6 => array(
                'event' => 'no_ok',
                'desc'  => 'не выполнена'
            ),
        );
    protected $db;
    protected $excel;
    protected $writer;
    protected $xlsPath;

    public function __construct($xlsPath)
    {
        GLOBAL $dbConnection;
        $this->db = $dbConnection;

        $this->excel   = new PHPExcel();
        $this->writer  = new PHPExcel_Writer_Excel5($this->excel);
        $this->xlsPath = $xlsPath;
    }

    public function getDepartments()
    {
        $stmt = $this->db->prepare('SELECT * FROM deps WHERE status = :one');
        $stmt->execute(array(':one' => 1));

        return $stmt->fetchAll();
    }

    public function getUsers()
    {
        $stmt = $this->db->prepare('SELECT id,login FROM users WHERE status = :one AND login <> :null ');
        $stmt->execute(array(':one' => 1,':null' => ''));

        return $stmt->fetchAll();
    }

    public function eventRequests($array = array(),$sort,$col)
    {
        if ($array['col'] === 'msg') {
            $order_by = "ORDER BY tl.$col $sort";
        } else {
            if ($array['col'] === 'date_op') {
                $order_by = "ORDER BY tl.$col $sort";
            } else {
                $order_by = "ORDER BY t.$col $sort";
            }

        }
        echo json_encode($order_by);
        if ($array['uid'] >= 0) {
            if ($array['uid'] == 0) { // if ALL selected
                $stmt = $this->db->prepare(
                    "SELECT t.id,t.user_init_id,t.msg AS t_msg,t.ok_date,t.date_create,t.lock_by,tl.msg,tl.date_op AS date_op,t.ok_by,t.subj,t.sabj_pl FROM
                              ticket_log AS tl LEFT JOIN tickets AS t ON t.id=tl.ticket_id
                              WHERE tl.msg IN ('create','comment','lock','unlock','refer','canceled','no_ok','ok') AND date_op BETWEEN :start AND :end $order_by");
                $stmt->execute(array(
                        ':start' => $array['start'],
                        ':end'   => $array['end'],
                    )
                );
            } elseif ($array['uid'] > 0) {
                $stmt = $this->db->prepare(
                    "SELECT t.id,t.user_init_id,t.msg AS t_msg,t.ok_date,t.date_create,t.lock_by,tl.msg,tl.date_op AS date_op,t.ok_by,t.subj,t.sabj_pl FROM
                              ticket_log AS tl LEFT JOIN tickets AS t ON t.id=tl.ticket_id
                              WHERE user_init_id = :uid AND date_op BETWEEN :start AND :end 
                              AND tl.msg IN ('create','comment','lock','unlock','refer','canceled','no_ok','ok') $order_by");
                $stmt->execute(array(
                        ':uid'   => $array['uid'],
                        ':start' => $array['start'],
                        ':end'   => $array['end'],
                    )
                );
            }
        } elseif ($array['department'] >= 0) {
            if ($array['department'] == 0) {
                $stmt = $this->db->prepare(
                    "SELECT t.id,t.user_init_id,t.msg AS t_msg,t.ok_date,t.date_create,t.lock_by,tl.msg,tl.date_op AS date_op,t.ok_by,t.subj,t.sabj_pl FROM
                              ticket_log AS tl LEFT JOIN tickets AS t ON t.id=tl.ticket_id
                              WHERE tl.msg IN ('create','comment','lock','unlock','refer','canceled','no_ok','ok') 
                              AND date_op BETWEEN :start AND :end $order_by");
                $stmt->execute(array(
                        ':start' => $array['start'],
                        ':end'   => $array['end'],
                    )
                );
            } elseif ($array['department'] > 0) {
                $stmt = $this->db->prepare(
                    "SELECT t.id,t.user_init_id,t.msg AS t_msg,t.ok_date,t.date_create,t.lock_by,tl.msg,tl.date_op AS date_op,t.ok_by,t.subj,t.sabj_pl FROM
                              ticket_log AS tl LEFT JOIN tickets AS t ON t.id=tl.ticket_id
                              WHERE tl.msg IN ('create','comment','lock','unlock','refer','canceled','no_ok','ok') 
                              AND to_unit_id = :did AND date_op BETWEEN :start AND :end $order_by");
                $stmt->execute(array(
                        ':did'   => $array['department'],
                        ':start' => $array['start'],
                        ':end'   => $array['end'],
                    )
                );
            }
        }
        if (isset($stmt)) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return false;
    }

    public function getTicketsData($idts,$sort,$col)
    {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE id IN ($idts) ORDER BY $col $sort");
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function displayReport($data)
    {
        //$array  = array();
        //foreach ($this->events as $key => $event) {
            $eventsData = $this->eventRequests($data,$data['sort'],$data['col']);

            //echo json_encode($eventsData, JSON_UNESCAPED_UNICODE);
            //exit;
            //$idts      = f3pick($eventsData,'ticket_id');// idts = array()
            //$eventName = f3pick($eventsData,'msg');
            //if ($idts) {
            //    $array[] = $this->getTicketsData(implode(',',$idts),$data['sort'],$data['col']);
            //} else {
            //    $array[] = null;
            //}
        //}

        $this->excel->setActiveSheetIndex(0);

        $sheet = $this->excel->getActiveSheet();
        $sheet->setTitle('отчет по событиям');
        $sheet->setCellValue('A1','№')->getColumnDimension('A')->setAutoSize(true);
        $sheet->setCellValue('B1','Дата')->getColumnDimension('B')->setAutoSize(true);
        $sheet->setCellValue('C1','События')->getColumnDimension('C')->setAutoSize(true);
        $sheet->setCellValue('D1','№ заявки')->getColumnDimension('D')->setAutoSize(true);
        $sheet->setCellValue('E1','Сообщение')->getColumnDimension('E')->setWidth(20);
        $sheet->setCellValue('F1','Зона работ')->getColumnDimension('F')->setAutoSize(true);
        $sheet->setCellValue('G1','Тип работ')->getColumnDimension('G')->setAutoSize(true);
        $sheet->setCellValue('H1','Создал')->getColumnDimension('H')->setAutoSize(true);
        $sheet->setCellValue('I1','Статус')->getColumnDimension('I')->setAutoSize(true);
        $sheet->setCellValue('J1','Выполнил')->getColumnDimension('J')->setAutoSize(true);
        $sheet->setCellValue('K1','Затрачено')->getColumnDimension('k')->setAutoSize(true);

        $view = "<table class=\"table table-bordered table-hover btn-group btn-group-xs\">
                    <tr>
                        <td>№</td>
                        <td><button id='events_sort' value='date_op' class=\"btn-primary btn-xs\">Дата</button></td>
                        <td><button id='events_sort' value='msg' class=\"btn-primary btn-xs\">События</button></td>
                        <td><button id='events_sort' value='id' class=\"btn-primary btn-xs\">№ заявки</button></td>
                        <td><button id='' value='' class=\"btn-primary btn-xs\" disabled>Сообщение</button></td>
                        <td><button id='events_sort' value='sabj_pl' class=\"btn-primary btn-xs\">Зона работ</button></td>
                        <td><button id='events_sort' value='subj' class=\"btn-primary btn-xs\">Тип работ</button></td>
                        <td><button id='events_sort' value='user_init_id' class=\"btn-primary btn-xs\">Создал</button></td>
                        <td><button id='events_sort' value='status' class=\"btn-primary btn-xs\">Статус</button></td>
                        <td><button id='events_sort' value='ok_by' class=\"btn-primary btn-xs\">Выполнил</button></td>
                        <td><button id='events_sort' value='last_update' class=\"btn-primary btn-xs\">Затрачено</button></td>
                    </tr>";
        $i = 1;
        $ecount = 2;
        //foreach ($array as $key => $value) {
            $view .= "<tr style='background-color: #3c8dbc;height: 15px'>
                        <td colspan='11'></td>
                      </tr>";
            foreach ($eventsData as $item) {
                if ($item['msg'] === 'comment') {
                    // get last comment from table.comments
                    $stmt = $this->db->prepare('SELECT comment_text FROM comments WHERE t_id = :idt ORDER BY id DESC LIMIT :one');
                    $stmt->execute(array(
                        ':idt' => $item['id'],
                        ':one' => 1,
                    ));
                    $comment = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($comment) {
                        $text = $comment['comment_text'];
                    }
                } else {
                    $text = $item['t_msg'];
                }
                if ($item['msg'] === 'lock') {
                    // who is working with current ticket
                    // get user by id
                    $state = 'работает '.$this->getLoginById($item['lock_by']);
                } else {
                    $state = $this->convertTicketEvent($item['msg']);
                }
                if ($item['ok_date'] !== null) {
                    $passedTime = $this->secondsToTime(strtotime($item['ok_date']) - strtotime($item['date_create']));
                } else {
                    $passedTime = null;
                }

                $sheet->setCellValue("A$ecount",$i);
                $sheet->setCellValue("B$ecount",$item['date_op']);
                $sheet->setCellValue("C$ecount",$this->convertTicketEvent($item['msg']));
                $sheet->setCellValue("D$ecount",$item['id']);
                $sheet->setCellValue("E$ecount",$text);
                $sheet->setCellValue("F$ecount",$item['sabj_pl']);
                $sheet->setCellValue("G$ecount",$item['subj']);
                $sheet->setCellValue("H$ecount",$this->getLoginById($item['user_init_id']));
                $sheet->setCellValue("I$ecount",$state); // status, value msg from ticket_log table
                $sheet->setCellValue("J$ecount",$this->getLoginById($item['ok_by']));
                $sheet->setCellValue("K$ecount",$passedTime);
                $view .= "<tr>
                        <td>$i</td>
                        <td>".$item['date_op']."</td>
                        <td>".$this->convertTicketEvent($item['msg'])."</td>
                        <td>".$item['id']."</td>
                        <td>".$text."</td>
                        <td>".$item['sabj_pl']."</td>
                        <td>".$item['subj']."</td>
                        <td>".$this->getLoginById($item['user_init_id'])."</td>
                        <td>".$state."</td>
                        <td>".$this->getLoginById($item['ok_by'])."</td>
                        <td>".$passedTime."</td>
                      </tr>";
                $i++;
                $ecount++;
            }
        //}
        $view .= "</table>";
        $this->writer->save($this->xlsPath);

        return $view;
    }

    public function getDaysMinutes($unix)
    {
        if ($unix > 0) {
            $days  = floor($unix/86400);
            $hours = floor(round($unix - $days * 86400)/(3600));
            $min   = floor(round($unix - $hours)/(3600)/60);

            return "$days дн. $hours час. $min мин";
        }
        return false;
    }

    public function secondsToTime($seconds) { // returns days/hours/minutes/seconds
        $dtF = new DateTime("@0");
        $dtT = new DateTime("@$seconds");
        $timing = explode(' ',$dtF->diff($dtT)->format('%a %h %i'));
        $date = null;
        if ($timing[0] != 0) {
            $date .= "$timing[0] дн ";
        }
        if ($timing[1] != 0) {
            $date .= "$timing[1] час ";
        }
        if ($timing[2] != 0) {
            $date .= "$timing[2] мин ";
        }
        return $date;
    }

    public function getLoginById($uid)
    {
        $stmt = $this->db->prepare('SELECT login FROM users where id = :uid LIMIT :one');
        $stmt->execute(array(
            ':uid' => $uid,
            ':one' => 1,
        ));
        $login = $stmt->fetch(PDO::FETCH_ASSOC);

        return $login['login'];
    }

    public function getTicketData($idt)
    {
        $stmt = $this->db->prepare('SELECT status FROM tickets WHERE id = :idt');
        $stmt->execute(array(
            ':idt' => $idt,
        ));
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function convertTicketStatus($status)
    {
        switch ($status) {
            case 0:
                $status = 'свободен';
                break;
            case 1:
                $status = 'выполнен';
                break;
            case 3:
                $status = 'отменен';
                break;
            default:
                $status = 'не определен';
        }
        return $status;
    }

    public function convertTicketEvent($event)
    {
        switch ($event) {
            case 'ok':
                $event = 'выполнено';
                break;
            case 'lock':
                $event = 'в работе';
                break;
            case 'unlock':
                $event = 'свободно';
                break;
            case 'create':
                $event = 'создано';
                break;
            case 'canceled':
                $event = 'отменено';
                break;
            case 'comment':
                $event = 'прокоментировано';
                break;
            case 'refer':
                $event = 'переадресовано';
                break;
            case 'no_ok':
                $event = 'не выполнено';
                break;
            default:
                $event = 'не определено';
        }
        return $event;
    }

    public function setSort($column,$sort)
    {
        $stmt = $this->db->prepare('INSERT INTO events_sort (col, sort) VALUES (:col,:sort)');
        $stmt->execute(array(':col' => $column,':sort' => $sort));
    }

    public function getSort($column)
    {
        $stmt = $this->db->prepare('SELECT * FROM events_sort WHERE `col` = :col ORDER BY id DESC LIMIT 1');
        $stmt->execute(array(':col' => $column));

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function checkSort()
    {
        $stmt = $this->db->prepare('SELECT * FROM events_sort');
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get users by department
     * WARNING: find_in_set is case-insensitive. If we need case sensitive, add binary modifier (FIND_IN_SET(binary 'unit',`unit)
     * @param $department_id
     * @return array|bool
     */
    public function getUsersByDepartment($department_id)
    {
        $stmt = $this->db->prepare('SELECT id,login FROM users WHERE FIND_IN_SET(:did,`unit`)');
        $stmt->execute(array(':did' => $department_id));

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class UserHelper
{
    protected $uid;
    protected $dbConnection;

    public function __construct(int $uid, $dbConnection = [])
    {
        $this->uid = $uid;
        $this->dbConnection = $dbConnection;
    }

    public function getUserData(string $dataType) : array
    {
        switch ($dataType) {
            case 'department':
                $stmt = $this->dbConnection->prepare(
                    'SELECT id, fio, status, priv, unit FROM users WHERE id = :uid'
                );
                $stmt->execute([':uid' => $this->uid]);

                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            case 'department:extended':
                $stmt = $this->dbConnection->prepare(
                    'SELECT id as uid, unit, priv FROM users WHERE id = :uid'
                );
                $stmt->execute([':uid' => $this->uid]);

                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    $units = explode(',', $user['unit']);

                    foreach ($units as $unit) {
                        $stmt = $this->dbConnection->prepare(
                            'SELECT id as uid, unit FROM users WHERE unit IN (:unit)'
                        );

                        $stmt->execute([
                            ':unit' => $unit
                        ]);

                        $colleagues = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($colleagues as $colleague) {
                            $response['collegues'][] = $colleague;
                        }
                    }
                    
                    $response['user'] = $user;
                }

                return $response ?? [];
        }

        return [];
    }
}

class UserObserver
{
    protected $dbConnection;

    public function __construct($dbConnection)
    {
        $this->dbConnection = $dbConnection;
    }

    public  function getUserData($uid)
    {
        $stmt = $this->dbConnection->prepare(
            'SELECT id, fio, login, status, priv, unit, uniq_id FROM users WHERE id = :uid'
        );
        $stmt->execute([':uid' => $uid]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC)[0];
    }
}