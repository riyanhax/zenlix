<?php
/**
 * Created by PhpStorm.
 * User: arava
 * Date: 06.03.19
 * Time: 10:56
 */
if (validate_user($_SESSION['helpdesk_user_id'], $_SESSION['code'])) {
    if ($_SESSION['helpdesk_user_id']) {
        include("head.inc.php");
        include("navbar.inc.php");

        $Events  = new EventsReport($CONF_HD['xls_report_path']);
        $basedir = dirname(dirname(__FILE__));

        try {
            $loader = new Twig_Loader_Filesystem($basedir . '/views');
            if (get_conf_param('twig_cache') == "true") {
                $twig = new Twig_Environment($loader, array(
                    'cache' => $basedir . '/cache',
                ));
            }
            else {
                $twig = new Twig_Environment($loader);
            }

            $template = $twig->loadTemplate('events_report.view.tmpl');
            $main_arr = array(
                'hostname'     => $CONF['hostname'],
                'name_of_firm' => $CONF['name_of_firm'],
                'date'         => date("Y-m-d"),
                'HELP_all'     => lang('HELP_all'),
                //'link'         => '/report.xls',
                'link' => 'getXlsReport',
                'departments'  => $Events->getDepartments(),
                'users'        => $Events->getUsers(),
            );

            echo $template->render($main_arr);
        }
        catch(Exception $e) {
            die('ERROR: ' . $e->getMessage());
        }
        include ("footer.inc.php");
    }
} else {
    var_dump('validate user failed');
}