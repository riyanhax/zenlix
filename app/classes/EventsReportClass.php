<?php
/**
 * Created by PhpStorm.
 * User: arava
 * Date: 06.03.19
 * Time: 16:50
 */
class EventsReport
{
    protected $events =
        array(
            'complete'     => 'заявка выполнена',
            'commented'    => 'прокоментирована',
            'work'         => 'в работе',
            'new'          => 'новая заявка',
            'redirected'   => 'переадресована',
            'canceled'     => 'отклонена',
            'not_perfomed' => 'не выполнена',
        );
    protected $db;

    public function __construct()
    {
        GLOBAL $dbConnection;
        $this->db = $dbConnection;
    }

    public function setterView()
    {

    }

    public function getDepartments()
    {
        $stmt = $this->db->prepare('SELECT * FROM deps WHERE status = :one');
        $stmt->execute(array(':one' => 1));

        return $stmt->fetchAll();
    }

    public function getUsers()
    {
        $stmt = $this->db->prepare('SELECT login FROM users WHERE status = :one AND login <> :null ');
        $stmt->execute(array(':one' => 1,':null' => ''));

        return $stmt->fetchAll();
    }
}