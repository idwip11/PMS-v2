<?php
/** Confirmed owner allocation and frozen snapshots on the real CF schema clone. */
ob_start();$root=dirname(__DIR__,2);define('ENVIRONMENT','development');define('FCPATH',$root.'/public/');chdir($root);require $root.'/app/Config/Paths.php';$paths=new Config\Paths();require $paths->systemDirectory.'/Boot.php';CodeIgniter\Boot::bootConsole($paths);
use App\Libraries\CompanyEntitlementService;use App\Libraries\FinanceOwnerSetupService;
$c=config('Database');$name=$argv[1]??'';if(!preg_match('/^homiq_pms_mitra_stage6_test_\d{8}_[a-z0-9]+$/D',$name)||!in_array($c->default['hostname'],['localhost','127.0.0.1'],true))throw new RuntimeException('Guarded local clone required.');$c->default['database']=$name;$db=db_connect();if($db->table('companies')->countAllResults()!==0){
 if($db->table('companies')->countAllResults()!==1||$db->table('companies')->where('id',1)->where('code','STAGE6')->countAllResults()!==1)throw new RuntimeException('Only the owned CF fixture may be reset.');
 foreach(['company_finance_snapshots','cash_payment_receipts','cash_entries','cash_opening_balances']as$t)$db->table($t)->where('company_id',1)->delete();
 $db->table('cash_transactions')->where('company_id',1)->where('reverses_id IS NOT NULL',null,false)->delete();
 foreach(['cash_transactions','cash_accounts','company_user_roles','company_properties']as$t)$db->table($t)->where('company_id',1)->delete();
 $db->table('companies')->where('id',1)->delete();
}
$at='2026-10-01 00:00:00';$n=0;$check=function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException('FAIL: '.$label);$n++;};
$db->table('companies')->insert(['id'=>1,'code'=>'STAGE6','name'=>'Holding Sintetis','is_active'=>1,'created_at'=>$at]);$db->table('company_user_roles')->insert(['company_id'=>1,'user_id'=>1001,'role'=>'Company Viewer','created_at'=>$at]);foreach([1,2,3]as$p)$db->table('company_properties')->insert(['company_id'=>1,'property_id'=>$p]);
$db->table('cash_accounts')->insert(['id'=>1,'company_id'=>1,'code'=>'S6BANK','name'=>'Bank Fixture','account_type'=>'bank','created_by'=>1001,'created_at'=>$at]);
$db->table('cash_opening_balances')->insert(['company_id'=>1,'account_id'=>1,'effective_at'=>$at,'amount'=>0,'verified_by'=>1001,'reason'=>'Fixture zero opening','created_at'=>$at]);
foreach([[401,2,201,650,50],[402,3,203,600,0]]as[$tx,$prop,$payment,$net,$fee]){
 $db->table('cash_transactions')->insert(['id'=>$tx,'company_id'=>1,'property_id'=>$prop,'event_key'=>'S6-'.$tx,'payload_hash'=>hash('sha256','S6-'.$tx),'kind'=>'receipt','classification'=>'operating','effective_at'=>'2026-10-05 12:00:00','description'=>'Confirmed fixture receipt','source_type'=>'guest_payment','source_reference'=>(string)$payment,'posted_by'=>1001,'created_at'=>$at]);
 $db->table('cash_entries')->insert(['company_id'=>1,'transaction_id'=>$tx,'account_id'=>1,'amount'=>$net]);$db->table('cash_payment_receipts')->insert(['company_id'=>1,'transaction_id'=>$tx,'payment_id'=>$payment,'amount'=>$net,'withheld_fee'=>$fee,'source_amount'=>$net+$fee,'created_at'=>$at]);
}
$service=new CompanyEntitlementService($db);$r=$service->monthlyReport(1001,1,'2026-10');
$check($r['bridge']['total_confirmed_gross']==='1300.00'&&$r['bridge']['total_confirmed_net']==='1250.00','Holding gross includes withheld while net follows actual ledger receipts');
$check($r['bridge']['total_owner_share']==='399.96'&&$r['bridge']['total_company_fee']==='800.04'&&$r['bridge']['total_direct_services']==='100.00','Holding applies one shared percentage to eligible confirmed revenue');
$check($r['cards']['current_cash_total']==='1250.00'&&$r['cards']['cash_in_month']==='1250.00','Cash balance and external cash flow reconcile to posted entries');
$rows=array_column($r['properties'],null,'property_id');$check($rows[2]['owner_share_base']==='600.00'&&$rows[3]['owner_share_base']==='600.00','Both source bases include Extra Bed; only confirmed DP fraction enters Homestay base');
$check($rows[2]['owner_share']==='199.98'&&$rows[3]['owner_share']==='199.98','Source owner allocations sum exactly to combined entitlement');
$check($service->monthlyReport(1001,1,'2026-10',3)['properties'][0]===$rows[3],'Holding source filter preserves the precomputed allocation');
$db->table('cash_transactions')->insert(['id'=>403,'company_id'=>1,'property_id'=>2,'event_key'=>'S6-403','payload_hash'=>hash('sha256','S6-403'),'kind'=>'reversal','classification'=>'operating','effective_at'=>'2026-10-06 12:00:00','description'=>'Fixture reversal','source_type'=>'reversal','source_reference'=>'401','reverses_id'=>401,'posted_by'=>1001,'created_at'=>$at]);$db->table('cash_entries')->insert(['company_id'=>1,'transaction_id'=>403,'account_id'=>1,'amount'=>-650]);
$rev=$service->monthlyReport(1001,1,'2026-10');$check($rev['bridge']['total_confirmed_gross']==='600.00'&&$rev['bridge']['total_owner_share']==='199.98'&&$rev['bridge']['total_direct_services']==='0.00','Reversed receipt no longer contributes any owner basis or direct service');$check($rev['cards']['current_cash_total']==='600.00'&&$rev['cards']['net_cash_flow']==='600.00','Reversal reduces actual cash balance and net flow consistently');
$db->table('cash_entries')->where('transaction_id',403)->delete();$db->table('cash_transactions')->where('id',403)->delete();
$payload=json_encode(['fixture'=>'frozen prior period']);$hash=hash('sha256',$payload);
$db->table('company_finance_snapshots')->insert(['company_id'=>1,'month'=>'2026-08','version'=>1,'snapshot_data'=>$payload,'snapshot_hash'=>$hash,'closed_by'=>1001,'created_at'=>$at]);
$snapshotBefore=$db->table('company_finance_snapshots')->get()->getResultArray();$service->monthlyReport(1001,1,'2026-08');$check($db->table('company_finance_snapshots')->get()->getResultArray()===$snapshotBefore,'Recalculation never rewrites a frozen prior-period snapshot');
ob_end_clean();echo "Stage 6 holding: {$n} checks passed on actual CF schema. Fixture retained for UI.\n";file_put_contents('/tmp/mitra-stage6-holding.json',json_encode(['checks'=>$n,'bridge'=>$r['bridge'],'cards'=>$r['cards']],JSON_PRETTY_PRINT));
