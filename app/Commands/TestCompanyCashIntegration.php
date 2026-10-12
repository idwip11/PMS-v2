<?php
namespace App\Commands;

use App\Libraries\CashLedgerService;
use App\Libraries\CompanyCashIntegration;
use App\Libraries\CashSourceGuard;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use RuntimeException;
use Throwable;

final class TestCompanyCashIntegration extends BaseCommand
{
    protected $group = 'Testing';
    protected $name = 'company:integration-test';
    protected $description = 'Uji CF-2 hanya di clone lokal homiq_pms_cf2_test_YYYYMMDD.';
    protected $options = ['--http-fixtures' => 'Akun uji HTTP sementara di clone.'];

    public function run(array $params)
    {
        $db = db_connect();
        if (ENVIRONMENT !== 'development' || ! in_array($db->hostname, ['localhost','127.0.0.1'], true) || preg_match('/^homiq_pms_cf2_test_[0-9]{8}$/D', $db->database) !== 1) throw new RuntimeException('Wajib clone CF-2 lokal; main/live ditolak.');
        $n = 0;
        $check = static function (bool $ok, string $name) use (&$n): void { if (! $ok) throw new RuntimeException('FAIL ' . $name); ++$n; CLI::write('PASS ' . $name); };
        $reject = static function (callable $fn, string $name) use ($check): void { $failed=false; try { $fn(); } catch (Throwable) { $failed=true; } $check($failed,$name); };
        $users=$db->table('users')->orderBy('id')->limit(2)->get()->getResultArray(); $approver=(int)$users[0]['id']; $finance=(int)$users[1]['id'];
        $db->table('companies')->insert(['code'=>'CF2_TEST','name'=>'CF2 test only','is_active'=>1,'created_at'=>gmdate('Y-m-d H:i:s')]); $company=(int)$db->insertID();
        foreach ([$approver=>'Company Approver',$finance=>'Company Finance'] as $u=>$role) $db->table('company_user_roles')->insert(['company_id'=>$company,'user_id'=>$u,'role'=>$role,'created_at'=>gmdate('Y-m-d H:i:s')]);
        $reservation=$db->table('reservations')->where('property_id',1)->get()->getRowArray();
        $db->table('company_properties')->insert(['company_id'=>$company,'property_id'=>1]);
        $ledger=new CashLedgerService($db); $cash=new CompanyCashIntegration($db);
        $a=$ledger->createAccount($approver,$company,'A','Bank test','bank'); $b=$ledger->createAccount($approver,$company,'B','Kas test','cash');
        $ledger->opening($approver,$company,$a,'2026-01-01 00:00:00','10000.00','Fixture verified'); $ledger->opening($approver,$company,$b,'2026-01-01 00:00:00','0.00','Fixture verified');
        $payment=static function (string $method, string $status='Paid', string $amount='1000.00') use ($db,$reservation): int { $db->table('payments')->insert(['reservation_id'=>$reservation['id'],'amount'=>$amount,'payment_date'=>$method==='OTA'?null:'2026-01-02','payment_method'=>$method,'status'=>$status,'type'=>'Completed']); return (int)$db->insertID(); };
        $expense=static function (string $status='Approved', string $amount='1000.00') use ($db): int { $db->table('expenses')->insert(['property_id'=>1,'description'=>'CF2 expense fixture','category'=>'Lain-lain','amount'=>$amount,'date_incurred'=>'2026-01-02','status'=>$status]); return (int)$db->insertID(); };
        $p=$payment('Transfer'); $ota=$payment('OTA'); $e=$expense(); $pending=$expense('Pending');
        $before=$ledger->balances($finance,$company,'2026-01-03 00:00:00'); $cash->preview($finance,$company);
        $check($before===$ledger->balances($finance,$company,'2026-01-03 00:00:00'),'preview and approved/unpaid expense do not move cash');
        $id=$cash->receipt($finance,$company,$p,$a,'1000.00','0.00','2026-01-03 00:00:00','receipt1','Confirmed transfer');
        $check($cash->receipt($finance,$company,$p,$a,'1000.00','0.00','2026-01-03 00:00:00','receipt1','Confirmed transfer')===$id,'receipt retry no duplicate');
        $reject(fn()=>$cash->receipt($finance,$company,$p,$a,'1.00','0.00','2026-01-03 00:00:00','overreceipt','Too much'),'source receipt limit');
        $cash->receipt($finance,$company,$ota,$a,'850.00','150.00','2026-01-03 00:00:00','ota1','Net OTA verified');
        $check($ledger->balances($finance,$company,'2026-01-04 00:00:00')[0]['balance']==='11850.00','OTA adds net 850, not gross 1000');
        $reject(fn()=>$cash->receipt($finance,$company,$ota,$a,'850.00','100.00','2026-01-03 00:00:00','ota1','Net OTA verified'),'same event with different withholding rejected');
        $check($db->table('payments')->where('id',$ota)->get()->getRowArray()['payment_date']===null,'OTA source date unchanged');
        $reject(fn()=>$cash->settleExpense($finance,$company,$pending,$a,'10.00','2026-01-03 00:00:00','pending','No approval'),'pending expense cannot be paid');
        $s=$cash->settleExpense($finance,$company,$e,$a,'400.00','2026-01-04 00:00:00','part1','Partial expense');
        $check($cash->settleExpense($finance,$company,$e,$a,'400.00','2026-01-04 00:00:00','part1','Partial expense')===$s,'partial settlement retry no duplicate');
        $cash->settleExpense($finance,$company,$e,$a,'600.00','2026-01-05 00:00:00','part2','Expense balance');
        $reject(fn()=>$cash->settleExpense($finance,$company,$e,$a,'1.00','2026-01-05 00:00:00','overpay','Too much'),'overpayment rejected');
        $check(CashSourceGuard::expenseLinked($db,$e),'paid expense source guard enabled');
        $check($db->table('expenses')->where('id',$e)->get()->getRowArray()['status']==='Approved','approval status separate from payment');
        $fail=$expense(); $count=$db->table('cash_transactions')->countAllResults();
        $db->query("CREATE TRIGGER cf2_fail_link BEFORE INSERT ON expense_settlements FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='injected settlement failure'");
        $reject(fn()=>$cash->settleExpense($finance,$company,$fail,$a,'100.00','2026-01-05 00:00:00','fail-link','Injected'),'settlement link database failure rejected');
        $db->query('DROP TRIGGER cf2_fail_link');
        $check($db->table('cash_transactions')->countAllResults()===$count,'ledger + settlement rolled back together');
        $db->table('payments')->where('id',$p)->update(['status'=>'Refund_Pending']);
        $check($ledger->balances($finance,$company,'2026-01-06 00:00:00')[0]['balance']==='10850.00','cancel/refund pending retains original receipt');
        $reject(fn()=>$cash->refund($finance,$company,$p,$a,'1.00','2026-01-06 00:00:00','unauthorized-refund','Denied'),'refund needs approver');
        $reject(fn()=>$cash->refund($approver,$company,$p,$a,'1.00','2026-01-02 00:00:00','early-refund','Too early'),'refund cannot precede confirmed receipt');
        $cash->refund($approver,$company,$p,$a,'1000.00','2026-01-06 00:00:00','refund1','Actual refund');
        $reject(fn()=>$cash->refund($approver,$company,$p,$a,'1.00','2026-01-06 00:00:00','refund-over','Too much'),'refund capped at confirmed net');
        $historic=$payment('Transfer','Refund_Pending','100.00');
        $count=$db->table('cash_transactions')->countAllResults();
        $cash->linkOpeningPayment($approver,$company,$historic,$a,'100.00','0.00','2025-12-31 00:00:00','Already included in opening');
        $check($db->table('cash_transactions')->countAllResults()===$count,'historical opening mapping does not add cash');
        $cash->refund($approver,$company,$historic,$a,'100.00','2026-01-06 00:00:00','historic-refund','Historic actual refund');
        $reject(fn()=>$cash->linkOpeningPayment($approver,$company,$historic,$a,'90.00','0.00','2025-12-31 00:00:00','Changed'),'opening mapping immutable');
        $over=$cash->createOverhead($finance,$company,'over1','Holding office cost','Office','100.00','2026-01-02');
        $check($cash->createOverhead($finance,$company,'over1','Holding office cost','Office','100.00','2026-01-02')===$over,'overhead creation retry idempotent');
        $reject(fn()=>$cash->settleExpense($finance,$company,$over,$a,'100.00','2026-01-06 00:00:00','over-pending','Pending',true),'unapproved overhead no cash out');
        $self=$cash->createOverhead($approver,$company,'over-self','Self test','Office','1.00','2026-01-02');
        $reject(fn()=>$cash->approveOverhead($approver,$company,$self),'maker cannot approve own overhead');
        $cash->approveOverhead($approver,$company,$over); $cash->settleExpense($finance,$company,$over,$a,'100.00','2026-01-06 00:00:00','over-pay','Office payment',true);
        $check($db->table('company_expenses')->where('id',$over)->get()->getRowArray()['company_id']==$company,'overhead has company scope, no fake HomiQ property');
        $cash->manual($approver,$company,'transfer',$a,$b,'100.00','2026-01-06 00:00:00','transfer1','Transfer treasury');
        $cash->manual($approver,$company,'capital',$a,null,'500.00','2026-01-06 00:00:00','capital1','Capital injection');
        $cash->manual($approver,$company,'bank_fee',$a,null,'10.00','2026-01-06 00:00:00','fee1','Bank charge');
        $cash->manual($approver,$company,'asset_purchase',$a,null,'100.00','2026-01-06 00:00:00','asset1','Purchase asset');
        $flow=$ledger->externalFlow($finance,$company,'2026-01-01 00:00:00','2026-02-01 00:00:00'); $classes=array_column($flow,null,'classification');
        $check(isset($classes['financing'],$classes['investing']) && !isset($classes['internal']),'capital/assets classified, internal transfers excluded');
        $unmapped=$payment('Transfer');
        $reject(fn()=>$cash->receipt($finance,$company,$unmapped,999999,'1.00','0.00','2026-01-06 00:00:00','foreign-account','Foreign'),'foreign account rejected');
        $reject(fn()=>$cash->receipt($finance,$company,$ota,$a,'1.00','0.00','2099-01-01 00:00:00','future','Future'),'actual confirmation cannot be future-dated');
        $ledger->setPeriod($approver,$company,'2026-01',true,'Lock');
        $reject(fn()=>$cash->settleExpense($finance,$company,$fail,$a,'10.00','2026-01-06 00:00:00','closed','Closed'),'integration honors period lock');
        $ledger->setPeriod($approver,$company,'2026-01',false,'Reopen');
        if (function_exists('pcntl_fork')) {
            $race=$expense('Approved','200.00'); $db->close(); $children=[];
            for($i=0;$i<2;$i++){ $pid=pcntl_fork(); if($pid<0)throw new RuntimeException('Fork failed'); if($pid===0){try{$c=\Config\Database::connect(config('Database')->default,false); (new CompanyCashIntegration($c))->settleExpense($finance,$company,$race,$a,'150.00','2026-01-06 00:00:00','race'.$i,'Concurrent partial');exit(0);}catch(Throwable){exit(2);}} $children[]=$pid; }
            $codes=[];foreach($children as $pid){pcntl_waitpid($pid,$status);$codes[]=pcntl_wexitstatus($status);}sort($codes);$db->initialize();
            $check($codes===[0,2],'concurrent partial payments reject overpayment race');
            $check($db->table('expense_settlements')->where('expense_id',$race)->countAllResults()===1,'race created only one settlement');
        }
        if(CLI::getOption('http-fixtures')){
            foreach(['cf2_test_finance'=>'Company Finance','cf2_test_approver'=>'Company Approver','cf2_test_property'=>null] as $username=>$role){$db->table('users')->insert(['username'=>$username,'full_name'=>'CF2 HTTP test only','password_hash'=>password_hash('CF2-test-clone-only',PASSWORD_DEFAULT),'role'=>'Finance']);$u=(int)$db->insertID();$db->table('user_property_roles')->insert(['user_id'=>$u,'property_id'=>1,'role'=>'Finance']);if($role)$db->table('company_user_roles')->insert(['company_id'=>$company,'user_id'=>$u,'role'=>$role,'created_at'=>gmdate('Y-m-d H:i:s')]);}
            CLI::write('HTTP fixtures: company='.$company.' account='.$a.' expense='.$fail.' payment='.$ota);
        }
        CLI::write('CF2 PASS: '.$n.' checks; clone only','green');
    }
}
