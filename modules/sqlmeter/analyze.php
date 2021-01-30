<?php


$filename = ROOT.'cms/sqlmeter/'.$rec['FILENAME'];
if (!file_exists($filename)) return;

$data = LoadFile($filename);

$lines=explode("\n",$data);


$total = count($lines);
$tm = 0;
$query='';
$queries = array();
$tm_started=0;
for($i=3;$i<$total;$i++) {
    $line = $lines[$i];
    if (preg_match('/^(\d+\-\d+\-\d+.+)Z/',$line,$m)) {
        $tm=strtotime($m[1]);
    }
    //dprint($line);
    if (preg_match('/^(\d+)\s+(\d+\:\d+\:\d+)/',$line,$m)) {
        $dt = '20'.substr($m[1],0,2).'-'.substr($m[1],2,2).'-'.substr($m[1],4,2);
        $tm = strtotime($dt.' '.$m[2]);
    }
    if (!$tm) continue;

    if (!$tm_started) {
        $tm_started=$tm;
    }
    $tm_ended=$tm;

    if (preg_match('/\d+ Query\t(.+)/',$line,$m) || preg_match('/\d+ Change user/',$line,$m)) {
        if ($query!='') {
            $query = str_replace("\t",' ',$query);
            $query = str_replace("\n",' ',$query);
            $query = preg_replace("/\s+/",' ',$query);
            $query = trim($query);
            $queries[]=array('TM'=>$tm,'SQL'=>$query);
        }
        $query=$m[1];
    } else {
        $query.=$line;
    }
}

$rec['QUERIES_TOTAL']=count($queries);

if ($tm_started && $tm_ended) {
    $rec['STARTED']=date('Y-m-d H:i:s',$tm_started);
    $rec['FINISHED']=date('Y-m-d H:i:s',$tm_ended);
}

SQLUpdate('sqllogs',$rec);

SQLExec("DELETE FROM sqlqueries WHERE LOG_ID=".$rec['ID']);

$total = count($queries);
for($i=0;$i<$total;$i++) {
    $q=array();
    $q['LOG_ID']=$rec['ID'];
    $q['ADDED']=date('Y-m-d H:i:s',$queries[$i]['TM']);
    $q['QUERY']=$queries[$i]['SQL'];
    SQLInsert('sqlqueries',$q);
}

unset($queries);


function processValues($sql) {
    if (preg_match_all('/\'([^\']*?)\'/',$sql,$m)) {
        $total = count($m[0]);
        for($i=0;$i<$total;$i++) {
            $new = '';
            if ($m[1][$i]!='') {
                $new = '?';
            }
            $sql = str_replace($m[0][$i],"'".$new."'",$sql);
        }
    }
    return $sql;
}

function processSet($sql) {
    $sql=str_replace("\\'",'',$sql);
    if (preg_match_all('/`?(\w+)`\s*?=\s*?\'?([^\']*)\'/is',$sql,$m)) {
        $total = count($m[0]);
        for($i=0;$i<$total;$i++) {
            $new = '';
            if ($m[2][$i]!='') {
                $new = '?';
            }
            $sql = str_replace($m[0][$i],$m[1][$i]."='".$new."'",$sql);
        }
    }
    return $sql;
}

function processWhere($sql) {
    if (preg_match_all('/\'([^\']*?)\'/',$sql,$m)) {
        $total = count($m[0]);
        for($i=0;$i<$total;$i++) {
            $new = '';
            if ($m[1][$i]!='') {
                $new = '?';
            }
            $sql = str_replace($m[0][$i],"'".$new."'",$sql);
        }
    }
    if (preg_match_all('/([><=])\s?(\d+)/',$sql,$m)) {
        $total = count($m[0]);
        for($i=0;$i<$total;$i++) {
            $new = '?';
            $sql = str_replace($m[0][$i], $m[1][$i].$new, $sql);
        }
    }
    return $sql;
}

SQLExec("DELETE FROM sqlqueries_meta WHERE LOG_ID=".$rec['ID']);
$queries=SQLSelect("SELECT * FROM sqlqueries WHERE LOG_ID=".$rec['ID']);
$total = count($queries);

$seen_meta=array();

for($i=0;$i<$total;$i++) {
    $sql=$queries[$i]['QUERY'];
    $meta_query = '';
    $query_type=0;
    if (preg_match('/^SELECT/is',$sql)) {
        $query_type=1;
        if (preg_match('/ WHERE (.+)$/is',$sql,$m)) {
            $where_part = $m[1];
            $where_part = preg_replace('/ ORDER BY .+/','',$where_part);
            $new_where = processWhere($where_part);
            $sql = str_replace($where_part,$new_where,$sql);
        }
        $meta_query = $sql;
    } elseif (preg_match('/^UPDATE/is',$sql)) {
        $query_type=2;
        if (preg_match('/ WHERE (.+)$/is',$sql,$m)) {
            $where_part = $m[1];
            $new_where = processWhere($where_part);
            $sql = str_replace($where_part,$new_where,$sql);
        }
        if (preg_match('/ SET (.+) WHERE/is',$sql,$m)) {
            $set_part = $m[1];
            $new_set = processSet($set_part);
            $sql = str_replace($set_part,$new_set,$sql);
        }
        $meta_query = $sql;
    } elseif (preg_match('/^REPLACE/is',$sql)) {
        $query_type=3;
        if (preg_match('/ VALUES\s?\((.+)\)$/is',$sql,$m)) {
            $where_part = $m[1];
            $new_where = processValues($where_part);
            $sql = str_replace($where_part,$new_where,$sql);
        }
        $meta_query = $sql;
    } elseif (preg_match('/^INSERT/is',$sql)) {
        $query_type=4;
        if (preg_match('/ VALUES\s?\((.+)\)$/is',$sql,$m)) {
            $where_part = $m[1];
            $new_where = processValues($where_part);
            $sql = str_replace($where_part,$new_where,$sql);
        }
        $meta_query = $sql;
    } elseif (preg_match('/^DELETE/is',$sql)) {
        $query_type=5;
        if (preg_match('/ WHERE (.+)$/is',$sql,$m)) {
            $where_part = $m[1];
            $new_where = processWhere($where_part);
            $sql = str_replace($where_part,$new_where,$sql);
        }
        $meta_query = $sql;
    } elseif (preg_match('/^SET/is',$sql)) {
        $query_type=6;
        //dprint($sql,false);
        $meta_query = $sql;
    } else {
        $query_type=100;
        $meta_query='Other';
    }

    if (!$seen_meta[$meta_query]) {
        $meta=array('LOG_ID'=>$rec['ID'],'TITLE'=>$meta_query,'QUERY_TYPE'=>$query_type);
        $seen_meta[$meta_query] = SQLInsert('sqlqueries_meta',$meta);
    }
    $queries[$i]['META_ID']=$seen_meta[$meta_query];
    SQLUpdate('sqlqueries',$queries[$i]);
}

$metas=SQLSelect("SELECT * FROM sqlqueries_meta WHERE LOG_ID=".$rec['ID']);
$total = count($metas);
for($i=0;$i<$total;$i++) {
    $tmp=SQLSelectOne("SELECT COUNT(*) as TOTAL FROM sqlqueries WHERE META_ID=".$metas[$i]['ID']);
    $metas[$i]['QUERIES_NUM']=(int)$tmp['TOTAL'];
    SQLUpdate('sqlqueries_meta',$metas[$i]);
}
//dprint($metas);
//dprint($queries);