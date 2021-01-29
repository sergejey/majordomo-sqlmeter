<?php
/*
* @version 0.1 (wizard)
*/
 global $session;
  if ($this->owner->name=='panel') {
   $out['CONTROLPANEL']=1;
  }
  $qry="1";
  // search filters
  // QUERY READY
  global $save_qry;
  if ($save_qry) {
   $qry=$session->data['sqllogs_qry'];
  } else {
   $session->data['sqllogs_qry']=$qry;
  }
  if (!$qry) $qry="1";
  $sortby_sqllogs="ID DESC";
  $out['SORTBY']=$sortby_sqllogs;
  // SEARCH RESULTS
  $res=SQLSelect("SELECT * FROM sqllogs WHERE $qry ORDER BY ".$sortby_sqllogs);
  if ($res[0]['ID']) {
   //paging($res, 100, $out); // search result paging
   $total=count($res);
   for($i=0;$i<$total;$i++) {
    // some action for every record if required
    if ($res[$i]['QUERIES_TOTAL']) {
     $tm_start=strtotime($res[$i]['STARTED']);
     $tm_finish=strtotime($res[$i]['FINISHED']);
     $diff=$tm_finish-$tm_start;
     if ($diff>0) {
      $res[$i]['QUERIES_TOTAL'].=' ('.round($res[$i]['QUERIES_TOTAL']/$diff).' / sec)';
     }
    }
   }
   $out['RESULT']=$res;
  }
