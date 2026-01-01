<?php
namespace Simcify\Controllers;
use Simcify\Database;
use Simcify\Auth;

class Overview{

    /**
     * Get a sample view or redirect to it
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $stats = array();
        $title = __('pages.sections.overview');
        $user = Auth::user();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $categories = Database::table('categories')->where('user',$user->id)->where('type','expense')->orderBy("id", false)->get();
        $incomecategories = Database::table('categories')->where('user',$user->id)->where('type','income')->orderBy("id", false)->get();
        $account = new \StdClass();
        $total_balance = 0;
        $total_credit = 0;
        $credit_limit = 0;
        $credit_owed = 0;
        $total_transactions = 0;
        foreach ($accounts as $account) {
            $incomeTransactions = Database::table('income')->where('account', $account->id)->count('id','total')[0]->total;
            $expenseTransactions = Database::table('expenses')->where('account', $account->id)->count('id','total')[0]->total;
            $account->transactions = $incomeTransactions + $expenseTransactions;
            if ($account->type == "Credit") {
                $total_credit += $account->balance;
                $credit_limit += $account->credit_limit;
            } else {
                $total_balance += $account->balance;
            }

            $total_transactions += $account->transactions;
        }

        $stats['total_balance'] = $total_balance;
        $stats['total_credit'] = $total_credit;
        $stats['credit_limit'] = $credit_limit;
        $stats['credit_owed'] = $credit_limit - $total_credit;
        $stats['total_transactions'] = $total_transactions;
        $stats['spent'] = Database::table('expenses')->where('user', $user->id)->where('MONTH(`expense_date`)', date("m"))->where('YEAR(`expense_date`)', date("Y"))->sum('amount','total')[0]->total;
        if ($user->monthly_spending > 0) {
          $stats['percentage'] = round(($stats['spent'] / $user->monthly_spending) * 100);
        }else{
          $stats['percentage'] = 0;
        }

        $stats['income'] = Database::table('income')->where('user', $user->id)->where('MONTH(`income_date`)', date("m"))->where('YEAR(`income_date`)', date("Y"))->sum('amount','total')[0]->total;
        $stats['expenses'] = Database::table('expenses')->where('user', $user->id)->where('MONTH(`expense_date`)', date("m"))->where('YEAR(`expense_date`)', date("Y"))->sum('amount','total')[0]->total;
        if ($stats['expenses'] > $stats['income']) {
            $stats['savings'] = 0;
        }else{
            $stats['savings'] = $stats['income'] - $stats['expenses'];
        }
        $stats['incomeTransactions'] = Database::table('income')->where('user', $user->id)->where('MONTH(`income_date`)', date("m"))->where('YEAR(`income_date`)', date("Y"))->count('id','total')[0]->total;
        $stats['expenseTransactions'] = Database::table('expenses')->where('user', $user->id)->where('MONTH(`expense_date`)', date("m"))->where('YEAR(`expense_date`)', date("Y"))->count('id','total')[0]->total;
        $totalTransactions = $stats['incomeTransactions'] + $stats['expenseTransactions'];
        if ($totalTransactions > 0) {
          $stats['incomePercentage'] = round(($stats['incomeTransactions'] / $totalTransactions) * 100);
          $stats['expensePercentage'] = round(($stats['expenseTransactions'] / $totalTransactions) * 100);
        }else{
          $stats['incomePercentage'] = 0;
          $stats['expensePercentage'] = 0;
        }
        // $reports = self::reports(date('Y-m-d', strtotime('today - 30 days')).' 23:59:59', date('Y-m-d').' 00:00:00');
        $reports = self::reports(date('Y-m-01'), date('Y-m-t'));

        $allIncome = Database::table('income')->where('user', $user->id)->orderBy('income_date', false)->get();
        $allExpenses = Database::table('expenses')->where('user', $user->id)->orderBy('expense_date', false)->get();

        $financeArr = array();

        foreach ($allIncome as $income) {
            $month = date('F', strtotime($income->income_date));
            $year = date('Y', strtotime($income->income_date));
            $financeArr[$year][$month]['income'] += $income->amount;
        }

        foreach ($allExpenses as $expense) {
            $month = date('F', strtotime($expense->expense_date));
            $year = date('Y', strtotime($expense->expense_date));
            $financeArr[$year][$month]['expenses'] += $expense->amount;
        }

        foreach ($financeArr as $year => $months) {
            foreach ($months as $month => $values) {
                $financeArr[$year][$month]['savings'] = $financeArr[$year][$month]['income'] - $financeArr[$year][$month]['expenses'];
            }
        }
 
        $stats['totalIncome'] = 0;
        $stats['totalExpenses'] = 0;
        $stats['totalSavings'] = 0;
        $stats['incomeYearly'] = 0;
        $stats['expensesYearly'] = 0;
        $stats['savingsYearly'] = 0;
        $stats['previousYearsIncome'][] = 0;
        $stats['previousYearsExpenses'][] = 0;
        $stats['previousYearsSavings'][] = 0;

        foreach ($financeArr as $year => $months) {
            foreach ($months as $month => $values) {
                $stats['totalIncome'] += $values['income'];
                $stats['totalExpenses'] += $values['expenses'];
            }

            if ($year == date("Y")) {
                foreach ($months as $month => $values) {
                    $stats['incomeYearly'] += $values['income'];
                    $stats['expensesYearly'] += $values['expenses'];
                }
                $stats['savingsYearly'] = $stats['incomeYearly'] - $stats['expensesYearly'];
            }

            if ($year != date("Y")) {
                $yearlyIncome = 0;
                $yearlyExpenses = 0;
                foreach ($months as $month => $values) {
                    $yearlyIncome += $values['income'];
                    $yearlyExpenses += $values['expenses'];
                }
                $yearlySavings = $yearlyIncome - $yearlyExpenses;
                $stats['previousYearsIncome'][$year] = $yearlyIncome;
                $stats['previousYearsExpenses'][$year] = $yearlyExpenses;
                $stats['previousYearsSavings'][$year] = $yearlySavings;
            }
        }

        // sort previous years by year descending
        krsort($stats['previousYearsIncome']);
        krsort($stats['previousYearsExpenses']);

        $stats['totalSavings'] = $stats['totalIncome'] - $stats['totalExpenses'];

        // $loanList = Database::table('loan_list')->where('userId', $user->id)->leftJoin('accounts', 'loan_list.account', 'accounts.id')->leftJoin('categories', 'loan_list.category', 'categories.id')->orderBy('loan_list.id', false)->get("`loan_list.id`", "`loan_list.title`", "`loan_list.amount`", "`loan_list.loan_date`", "`loan_list.date_to_be_paid`", "`loan_list.notes`", "`loan_list.paid_status`", "`accounts.name` as accountName", "`categories.name` as categoryName");

        $loanList = Database::table('loan_list')->where('userId', $user->id)->leftJoin('accounts', 'loan_list.account', 'accounts.id')->orderBy('loan_list.id', false)->get("`loan_list.id`", "`loan_list.title`", "`loan_list.amount`", "`loan_list.loan_date`", "`loan_list.date_to_be_paid`", "`loan_list.notes`", "`loan_list.paid_status`", "`accounts.name` as accountName");

        $stats['totalLoanAmount'] = Database::table('loan_list')->where('userId', $user->id)->where('paid_status', 1)->sum('amount', 'total')[0]->total;

        return view('overview',compact("user","accounts","categories","incomecategories","title","stats","reports", "financeArr", "loanList"));
    }

    /**
     * Create account
     * 
     * @return Json
     */
    public function createaccount(){
        $credit_limit = input('credit_limit') ? input('credit_limit') : 0;
        $data = array(
            'name'=>input('name'),
            'user'=>Auth::user()->id,
            'balance'=>input('balance'),
            'credit_limit'=> $credit_limit,
            'type'=>input('type'),
            'status'=>input('status')
        );
        Database::table('accounts')->insert($data);
        return response()->json(responder("success", __('pages.messages.alright'), __('overview.messages.add-success'), "reload()"));
    }

    /**
     * Update account view
     * 
     * @return Json
     */
    public function updateaccountview() {
        $account = Database::table('accounts')->where('id', input("accountid"))->first();
        return view('includes/ajax/account',compact('account'));
    }
    /**
     * View account history
     * 
     * @return Json
     */
    public function history(){
        $user = Auth::user();
        $account = Database::table('accounts')->where('id', input("accountid"))->first();
        $history = Database::table('history')->where('accountId', $account->id)->where('userId', $user->id)->orderBy('date_added', false)->limit(10)->get();

        return view("includes/ajax/viewTransactions", compact('account','history'));
    }

    /**
     * Update account
     * 
     * @return Json
     */
    public function updateaccount(){
        $user = Auth::user();
        $accounts = Database::table('accounts')->where('id', input('accountid'))->first();
        $credit_limit = input('credit_limit') ? input('credit_limit') : 0;
        $data = array(
            'name'=>input('name'),
            'balance'=>input('balance'),
            'credit_limit'=> $credit_limit,
            'type'=>input('type'),
            'status'=>input('status')
        );

        $history = array(
            'userId' => $user->id,
            'accountId' => $accounts->id,
            'fromAmount' => $accounts->balance,
            'toAmount' => input('balance'),
            'type' => '5',
            'date_added' => date('Y-m-d H:i:s')
        );

        Database::table('accounts')->where('id',$accounts->id)->update($data);
        Database::table('history')->insert($history);
        return response()->json(responder("success", __('pages.messages.alright'), __('overview.messages.edit-success'), "reload()"));
    }


    /**
     * Delete account
     * 
     * @return Json
     */
    public function deleteaccount(){
        Database::table('accounts')->where('id',input('accountid'))->delete();
        return response()->json(responder("success", __('pages.messages.alright'), __('overview.messages.delete-success'), "reload()"));
    }

    /**
     * Report
     * 
     * @return array
     */
    public function reports($from, $to){
        $reports = array();
        $user = Auth::user();
        $range = $from."' AND '".$to;
        $reports['income']['total'] = money(Database::table('income')->where("user", $user->id)->where('income_date','BETWEEN',$range)->sum("amount", "total")[0]->total);
        $reports['expenses']['total'] = money(Database::table('expenses')->where("user", $user->id)->where('expense_date','BETWEEN',$range)->sum("amount", "total")[0]->total);
        $reports['income']['count'] = Database::table('income')->where("user", $user->id)->where('income_date','BETWEEN',$range)->count("amount", "total")[0]->total;
        $reports['expenses']['count'] = Database::table('expenses')->where("user", $user->id)->where('expense_date','BETWEEN',$range)->count("amount", "total")[0]->total;
        $reports['expenses']['top'] = Database::table('expenses')->where("user", $user->id)->where('expense_date','BETWEEN',$range)->limit(3)->orderBy("amount", false)->get();


        if (!empty($reports['expenses']['top'])){
            foreach($reports['expenses']['top'] as $topExpense){
                $topExpense->amount = money($topExpense->amount);
            }
        }
 
        $begin = new \DateTime($from);
        $end = new \DateTime($to);
        $interval = new \DateInterval('P1D');
        $daterange = new \DatePeriod($begin, $interval ,$end);
        foreach ( $daterange as $dt ){
            $range = $dt->format( "Y-m-d" )." 00:00:00' AND '".$dt->format( "Y-m-d" )." 23:59:59";
            $reports['chart']['label'][] = $dt->format( "d F" );
            $reports['chart']['income'][] = Database::table('income')->where("user", $user->id)->where('income_date','BETWEEN',$range)->sum("amount", "total")[0]->total;
            $reports['chart']['expenses'][] = (Database::table('expenses')->where("user", $user->id)->where('expense_date','BETWEEN',$range)->sum("amount", "total")[0]->total * -1);
        }

        return $reports;
    }

    /**
     * Get Report
     * 
     * @return array
     */
    public function getreports(){
        $reports = self::reports(input("from").' 00:00:00', input("to").' 23:59:59');
        return response()->json(responder("success", "", "", "reports(".json_encode($reports).")", false));
    }

    /**
     * Add Loan
     * 
     * @return Json
     */
    public function addLoanList(){
        $data = array(
            'title'=>escape(input('title')),
            'userId'=>Auth::user()->id,
            'amount'=>input('amount'),
            'account'=>input('account'),
            'loan_date'=>date('Y-m-d',strtotime(input('loan_date'))),
            'date_to_be_paid'=>date('Y-m-d',strtotime(input('date_to_be_paid'))),
            'notes'=>escape(input('notes')),
            'paid_status'=>input('status')
        );

        Database::table('loan_list')->insert($data);
        return response()->json(responder("success", __('pages.messages.alright'), __('overview.messages.add-loan-success'), "reload()"));
    }

    /**
     * Account balance
     * 
     * @return true
     */
    public function balance($accountId, $amount, $action) {
        $account = Database::table('accounts')->where('id', $accountId)->first();
        if ($action == "plus") {
          $balance = $account->balance + $amount;
        }elseif ($action == "minus") {
          $balance = $account->balance - $amount;
        }
        Database::table('accounts')->where('id', $accountId)->update(array("balance" => $balance));
        return true;
    }
}
