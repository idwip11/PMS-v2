<?php
/** Money precision, grouped booking isolation and stable pagination on the actual schema. */
ob_start();$root=dirname(__DIR__,2);define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');chdir($root);require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\FinanceReportScope as Scope;use App\Libraries\FinanceReportService as Report;use App\Libraries\MitraOwnerFinanceService as Owner;
$name=$argv[1]??'';$c=config('Database');if(!preg_match('/^homiq_pms_mitra_stage6_test_\d{8}_[a-z0-9]+$/D',$name)||!in_array($c->default['hostname'],['localhost','127.0.0.1'],true))throw new RuntimeException('Guarded local clone required.');$c->default['database']=$name;$db=db_connect();if($db->table('users')->where('id',1001)->where('username','stage6_1001')->countAllResults()!==1)throw new RuntimeException('Stage-6 fixture required.');
$n=0;$check=function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException('FAIL: '.$label);$n++;};
$rooms=$db->table('rooms')->where('property_id',2)->orderBy('id')->get()->getResultArray();$homiq=$db->table('rooms')->where('property_id',1)->get()->getRowArray();
try{
 foreach([501,502,503,504]as$id){$prop=$id===504?1:2;$db->table('reservations')->insert(['id'=>$id,'property_id'=>$prop,'room_id'=>$prop===1?$homiq['id']:$rooms[$id-501]['id'],'guest_id'=>$prop,'order_id'=>'S6EDGE-'.$id,'booking_group_id'=>'S6EDGE-SHARED','check_in_date'=>'2026-09-11','check_out_date'=>'2026-09-12','total_amount'=>'0.10','status'=>'Confirmed']);}
 foreach([501,502,503]as$id)$db->table('payments')->insert(['id'=>$id+100,'reservation_id'=>$id,'order_id'=>'S6EDGE-'.$id,'amount'=>'0.10','payment_date'=>'2026-09-11','status'=>'Paid','type'=>'Completed','payment_method'=>'Cash']);
 $db->table('expenses')->insert(['id'=>701,'property_id'=>2,'amount'=>'0.10','description'=>'S6 precision expense','category'=>'Supplies','date_incurred'=>'2026-09-11','status'=>'Approved']);
 $scope=Scope::forUser(1001,2,Scope::REPORT);$r=Report::getPropertyReportData($scope,'2026-09');$check($r['summary']['revenue']===0.3&&$r['summary']['expenses']===0.1&&$r['summary']['netProfit']===0.2,'Decimal summary conserves cents');
 $check($r['cashFlow']['income'][5]===0.3&&$r['cashFlow']['expenses'][5]===0.1,'Chart decimal amounts match summary');
 $in=array_filter($r['allTransactions'],fn($t)=>$t['type']==='Income');$check(count($in)===3&&array_unique(array_column($in,'room_count'))===[3],'Shared group ID in HomiQ does not inflate Mitra room count');
 $o=(new Owner($db))->report(Scope::forUser(1002,2,Scope::OWNER_REPORT),'2026-09');$check($o['summary']['shareBase']==0.3&&$o['summary']['investorShare']==0.1&&$o['summary']['managerShare']==0.2,'Grouped booking money is recognized once from each actual payment');
 for($i=0;$i<13;$i++)$db->table('payments')->insert(['id'=>610+$i,'reservation_id'=>501,'order_id'=>'S6EDGE-501','amount'=>'0.01','payment_date'=>'2026-09-11','status'=>'Paid','type'=>'DP','payment_method'=>'Cash']);
 $a=Report::getPropertyReportData($scope,'2026-09',1);$b=Report::getPropertyReportData($scope,'2026-09',2);
 $keys=fn($rows)=>array_map(fn($t)=>$t['type'].':'.$t['sortId'],$rows);
 $check($a['pagination']['totalItems']===17&&count($a['transactions'])===10&&count($b['transactions'])===7,'Pagination spans grouped partial payments globally');
 $check(array_intersect($keys($a['transactions']),$keys($b['transactions']))===[]&&array_merge($keys($a['transactions']),$keys($b['transactions']))===$keys($a['allTransactions']),'Pages have no duplicate/missing rows and match export order');
 $check(serialize($a)===serialize(Report::getPropertyReportData($scope,'2026-09',1)),'Repeated same-date ordering is stable');
 $check($a['summary']['revenue']===0.43&&$a['summary']['netProfit']===0.33,'Many small DP amounts do not accumulate floating-point errors');
}finally{
 $db->table('payments')->whereIn('id',array_merge([601,602,603],range(610,622)))->delete();$db->table('expenses')->where('id',701)->delete();$db->table('reservations')->whereIn('id',[501,502,503,504])->delete();
}
ob_end_clean();echo "Stage 6 financial edges: {$n} checks passed. Synthetic edge rows cleaned.\n";file_put_contents('/tmp/mitra-stage6-edges.json',json_encode(['checks'=>$n]));
