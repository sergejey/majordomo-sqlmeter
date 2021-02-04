<?php
/*
* @version 0.1 (wizard)
*/
if ($this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}
$table_name = 'sqllogs';
$rec = SQLSelectOne("SELECT * FROM $table_name WHERE ID='$id'");

$tm_start=strtotime($rec['STARTED']);
$tm_finish=strtotime($rec['FINISHED']);
$diff=$tm_finish-$tm_start;

$out['DIFF']=$diff;

if ($this->mode=='download') {

    $file = ROOT.'cms/sqlmeter/'.$rec['FILENAME'];
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

$explain_id = gr('explain_id','int');
if ($explain_id) {
    $query = SQLSelectOne("SELECT * FROM sqlqueries WHERE ID=".$explain_id);
    $sql = $query['QUERY'];
    $tmp = SQLSelect("EXPLAIN ".$sql);

    echo "<b>".htmlspecialchars('EXPLAIN '.$sql)."</b>\n";

    $total = count($tmp);
    for($i=0;$i<$total;$i++) {
        echo "<pre>";
        foreach($tmp[$i] as $k=>$v) {
            if ($v!='') {
                echo "$k: $v\n";
            }
        }
        echo "</pre>";
    }
    exit;
}

$meta_id=gr('meta_id');
if ($meta_id) {
    echo "<br/>";
    $queries=SQLSelect("SELECT `QUERY`, ID, COUNT(*) as TOTAL FROM sqlqueries WHERE META_ID=".(int)$meta_id." GROUP BY `QUERY` ORDER BY TOTAL DESC");
    $total = count($queries);
    echo '<table class="table">';
    for($i=0;$i<$total;$i++) {
        echo '<tr>';
        echo '<td>'.$queries[$i]['QUERY'].'</td>';
        echo '<td>'.$queries[$i]['TOTAL'].'</td>';
        echo '<td><a href="#" onclick="return explainQuery('.$queries[$i]['ID'].');">Explain</a></td>';
        if ($diff>0) {
            echo '<td nowrap>'.round($queries[$i]['TOTAL']/$diff,1).' / sec</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    exit;
}

if ($this->mode == 'analyze') {
    require dirname(__FILE__).'/analyze.php';
    $this->redirect("?view_mode=".$this->view_mode."&id=".$rec['ID']);
}

if ($this->mode == 'update') {
    $ok = 1;
    //updating '<%LANG_TITLE%>' (varchar, required)
    $rec['TITLE'] = gr('title');
    if ($rec['TITLE'] == '') {
        $out['ERR_TITLE'] = 1;
        $ok = 0;
    }
    //updating 'FILENAME' (varchar)
    $rec['FILENAME'] = gr('filename');
    //updating 'STARTED' (varchar)
    $rec['STARTED'] = gr('started');
    //updating 'FINISHED' (varchar)
    $rec['FINISHED'] = gr('finished');
    //updating 'QUERIES_TOTAL' (varchar)
    $rec['QUERIES_TOTAL'] = gr('queries_total');
    //UPDATING RECORD
    if ($ok) {
        if ($rec['ID']) {
            SQLUpdate($table_name, $rec); // update
        } else {
            $new_rec = 1;
            $rec['ID'] = SQLInsert($table_name, $rec); // adding new record
        }
        $out['OK'] = 1;
    } else {
        $out['ERR'] = 1;
    }
}


if ($diff>0) {
    $out['LOAD']=round($rec['QUERIES_TOTAL']/$diff,2);
}

$qry="1";
$search = gr('search');
if ($search!='') {
    $out['SEARCH']=htmlspecialchars($search);
    $qry.=" AND TITLE LIKE '%".DBSafe($search)."%'";
}
$types=gr('types');
if (is_array($types)) {
    $qry.=" AND QUERY_TYPE IN (".implode(',',$types).")";
    foreach($types as $type) {
        $out['TYPE'.$type]=1;
    }
}

$metas = SQLSelect("SELECT * FROM sqlqueries_meta WHERE LOG_ID=".$rec['ID']." AND $qry ORDER BY QUERIES_NUM DESC");
$total = count($metas);
for($i=0;$i<$total;$i++) {
    if ($diff>0) {
        $metas[$i]['SPEED']=round($metas[$i]['QUERIES_NUM']/$diff,1);
    }
}
$out['METAS']=$metas;

if (is_array($rec)) {
    foreach ($rec as $k => $v) {
        if (!is_array($v)) {
            $rec[$k] = htmlspecialchars($v);
        }
    }
}
outHash($rec, $out);
