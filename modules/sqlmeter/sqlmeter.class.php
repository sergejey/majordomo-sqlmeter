<?php
/**
 * SQLmeter
 * @package project
 * @author Wizard <sergejey@gmail.com>
 * @copyright http://majordomo.smartliving.ru/ (c)
 * @version 0.1 (wizard, 20:01:17 [Jan 22, 2021])
 */
//
//
class sqlmeter extends module
{
    /**
     * sqlmeter
     *
     * Module class constructor
     *
     * @access private
     */
    function __construct()
    {
        $this->name = "sqlmeter";
        $this->title = "SQLmeter";
        $this->module_category = "<#LANG_SECTION_SYSTEM#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 1)
    {
        $p = array();
        if (IsSet($this->id)) {
            $p["id"] = $this->id;
        }
        if (IsSet($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (IsSet($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (IsSet($this->tab)) {
            $p["tab"] = $this->tab;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $view_mode;
        global $edit_mode;
        global $tab;
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
    }

    /**
     * Run
     *
     * Description
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        if (IsSet($this->owner->action)) {
            $out['PARENT_ACTION'] = $this->owner->action;
        }
        if (IsSet($this->owner->name)) {
            $out['PARENT_NAME'] = $this->owner->name;
        }
        $out['VIEW_MODE'] = $this->view_mode;
        $out['EDIT_MODE'] = $this->edit_mode;
        $out['MODE'] = $this->mode;
        $out['ACTION'] = $this->action;
        $out['TAB'] = $this->tab;
        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * Module backend
     *
     * @access public
     */
    function admin(&$out)
    {
        if (isset($this->data_source) && !$_GET['data_source'] && !$_POST['data_source']) {
            $out['SET_DATASOURCE'] = 1;
        }
        if ($this->data_source == 'sqllogs' || $this->data_source == '') {

            if ($this->mode=='upload') {
                SQLExec("SET global general_log = 0;");
                global $file;
                global $file_name;
                if (is_file($file) && $file_name!='') {
                    if (!is_dir(ROOT.'cms/sqlmeter')) {
                        umask(0);
                        mkdir(ROOT.'cms/sqlmeter',0777);
                    }
                    move_uploaded_file($file,ROOT.'cms/sqlmeter/'.$file_name);
                    $rec=array();
                    $rec['TITLE']=date('Y-m-d H:i:s');
                    $rec['TITLE']=$file_name;
                    $rec['FILENAME']=$file_name;
                    $rec['STATUS']=1;
                    $rec['ID']=SQLInsert('sqllogs',$rec);
                    $this->redirect("?view_mode=edit_sqllogs&id=".$rec['ID']); //."&mode=analyze"
                }
            }

            if ($this->mode=='start') {
                if (!is_dir(ROOT.'cms/sqlmeter')) {
                    umask(0);
                    mkdir(ROOT.'cms/sqlmeter',0777);
                }
                $filename = date('Y-m-d_H-i-s').'.log';
                SaveFile(ROOT.'cms/sqlmeter/'.$filename,'');
                $rec=array();
                $rec['TITLE']=date('Y-m-d H:i:s');
                $rec['STARTED']=date('Y-m-d H:i:s');
                $rec['FILENAME']=$filename;
                SQLInsert('sqllogs',$rec);

                SQLExec("SET global log_output = 'FILE';");
                SQLExec("SET global general_log_file='".str_replace('\\','/',dirname(__FILE__))."/../../cms/sqlmeter/".$filename."';");
                SQLExec("SET global general_log = 1;");
            }

            if ($this->mode=='stop') {
                SQLExec("SET global general_log = 0;");
                $logs=SQLSelect("SELECT * FROM sqllogs WHERE STATUS=0 ORDER BY STARTED");
                $total = count($logs);
                for($i=0;$i<$total;$i++) {
                    $logs[$i]['FINISHED']=date('Y-m-d H:i:s');
                    $logs[$i]['STATUS']=1;
                    SQLUpdate('sqllogs',$logs[$i]);
                    $latest_id=$logs[$i]['ID'];
                }
                if ($latest_id) {
                    $this->redirect("?view_mode=edit_sqllogs&id=".$latest_id); //."&mode=analyze"
                } else {
                    $this->redirect("?");
                }
            }

            if ($this->view_mode == '' || $this->view_mode == 'search_sqllogs') {
                $this->search_sqllogs($out);
            }
            if ($this->view_mode == 'edit_sqllogs') {
                $this->edit_sqllogs($out, $this->id);
            }
            if ($this->view_mode == 'delete_sqllogs') {
                $this->delete_sqllogs($this->id);
                $this->redirect("?");
            }
        }
    }

    /**
     * FrontEnd
     *
     * Module frontend
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * sqllogs search
     *
     * @access public
     */
    function search_sqllogs(&$out)
    {
        require(dirname(__FILE__) . '/sqllogs_search.inc.php');
    }

    /**
     * sqllogs edit/add
     *
     * @access public
     */
    function edit_sqllogs(&$out, $id)
    {
        require(dirname(__FILE__) . '/sqllogs_edit.inc.php');
    }

    /**
     * sqllogs delete record
     *
     * @access public
     */
    function delete_sqllogs($id)
    {
        $rec = SQLSelectOne("SELECT * FROM sqllogs WHERE ID='$id'");
        // some action for related tables
        if ($rec['FILENAME']!='') {
            @unlink(ROOT.'cms/sqlmeter/'.$rec['FILENAME']);
        }
        SQLExec("DELETE FROM sqlqueries_meta WHERE LOG_ID=".$rec['ID']);
        SQLExec("DELETE FROM sqlqueries WHERE LOG_ID=".$rec['ID']);
        SQLExec("DELETE FROM sqllogs WHERE ID='" . $rec['ID'] . "'");
    }

    /**
     * Install
     *
     * Module installation routine
     *
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }

    /**
     * Uninstall
     *
     * Module uninstall routine
     *
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS sqllogs');
        parent::uninstall();
    }

    /**
     * dbInstall
     *
     * Database installation routine
     *
     * @access private
     */
    function dbInstall($data)
    {
        /*
        sqllogs -
        */
        $data = <<<EOD
 sqllogs: ID int(10) unsigned NOT NULL auto_increment
 sqllogs: TITLE varchar(100) NOT NULL DEFAULT ''
 sqllogs: FILENAME varchar(255) NOT NULL DEFAULT ''
 sqllogs: STATUS int(3) NOT NULL DEFAULT '0'
 sqllogs: STARTED datetime
 sqllogs: FINISHED datetime
 sqllogs: QUERIES_TOTAL int(40) NOT NULL DEFAULT '0'
 
 sqlqueries: ID int(10) unsigned NOT NULL auto_increment
 sqlqueries: QUERY varchar(1024) NOT NULL DEFAULT ''
 sqlqueries: META_ID int(10) unsigned NOT NULL DEFAULT '0'
 sqlqueries: LOG_ID int(10) unsigned NOT NULL DEFAULT '0'
 sqlqueries: ADDED datetime
 
 sqlqueries_meta: ID int(10) unsigned NOT NULL auto_increment
 sqlqueries_meta: TITLE varchar(1024) NOT NULL DEFAULT ''
 sqlqueries_meta: LOG_ID int(10) unsigned NOT NULL DEFAULT '0'
 sqlqueries_meta: QUERIES_NUM int(10) unsigned NOT NULL DEFAULT '0'
 sqlqueries_meta: QUERY_TYPE int(3) unsigned NOT NULL DEFAULT '0'
 
EOD;
        parent::dbInstall($data);

        SQLExec("CREATE TABLE IF NOT EXISTS mysql.general_log(
event_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP ,
user_host MEDIUMTEXT NOT NULL ,
thread_id INT( 11 ) NOT NULL ,
server_id INT( 10 ) UNSIGNED NOT NULL ,
command_type VARCHAR( 64 ) NOT NULL ,
argument MEDIUMTEXT NOT NULL
) ENGINE = CSV DEFAULT CHARSET = utf8 COMMENT = 'General log'");

    }
// --------------------------------------------------------------------
}
/*
*
* TW9kdWxlIGNyZWF0ZWQgSmFuIDIyLCAyMDIxIHVzaW5nIFNlcmdlIEouIHdpemFyZCAoQWN0aXZlVW5pdCBJbmMgd3d3LmFjdGl2ZXVuaXQuY29tKQ==
*
*/
