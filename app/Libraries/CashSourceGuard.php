<?php
namespace App\Libraries;
use CodeIgniter\Database\BaseConnection;
final class CashSourceGuard
{
    /** Call while holding the expense row lock when mutating the source. */
    public static function expenseLinked(BaseConnection $db, int $expense): bool
    {
        return $db->tableExists('expense_settlements') && $db->table('expense_settlements')->where('expense_id', $expense)->countAllResults() > 0;
    }
}
