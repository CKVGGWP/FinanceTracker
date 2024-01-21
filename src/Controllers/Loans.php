<?php
namespace Simcify\Controllers;
use Simcify\Auth;
use Simcify\Database;

class Loans{

    /**
     * Get a sample view or redirect to it
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $stats = array();
        $title = __('pages.sections.loans');
        $user = Auth::user();
        $categories = Database::table('categories')->where('user',$user->id)->where('type','expense')->orderBy("id", false)->get();
        $incomecategories = Database::table('categories')->where('user',$user->id)->where('type','Income')->orderBy("id", false)->get();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $loans = Database::table("loans")->where("loans`.`user", $user->id)->leftJoin("accounts", "loans.account","accounts.id")->orderBy("loans.id", false)->get("`loans.id`", "`loans.title`", "`loans.amount`", "`loans.payment_terms`", "`loans.amount_remaining`", "`loans.start_date`", "`loans.end_date`", "`loans.deadline`", "`loans.paid`", "`loans.reminder_day`", "`loans.latest_paid_date`", "`accounts.name` as accountname");
        $loan = new \StdClass();
        $total_loan_amount = 0;
        $total_loan_balance = 0;
        $total_partially_paid = 0;
        $total_full_paid = 0;

        foreach ($loans as $loan){
            $total_loan_amount += $loan->amount;
            $total_loan_balance += $loan->amount_remaining;
            if ($loan->paid == 1) {
                $total_partially_paid += 1;
            }else if ($loan->paid == 2){
                $total_full_paid += 1;
            }
        }

        $stats['total_loan_amount'] = $total_loan_amount;
        $stats['total_loan_balance'] = $total_loan_balance;
        $stats['total_partially_paid'] = $total_partially_paid;
        $stats['total_full_paid'] = $total_full_paid;
        $stats['loan_count'] = Database::table('loans')->where('user', $user->id)->count('id','total')[0]->total;
        $stats['percentage'] = $stats['loan_count'] > 0 ? round(($stats['total_full_paid'] / $stats['loan_count']) * 100) : 0;

        return view('loan',compact("user","title","accounts","loans","stats", "categories", "incomecategories"));
    }

    /**
     * Add Loan
     * 
     * @return Json
     */
    public function add() {
        $user = Auth::user();
        // make the deadline a whole number

        $data = array(
            'title'             =>  escape(input('title')),
            'user'              =>  $user->id,
            'amount'            =>  input('amount'),
            'account'           =>  input('account'),
            'payment_terms'     =>  floor(input('payment_terms')),
            'amount_remaining'  =>  input('amount') * input('payment_terms'),
            'start_date'        =>  date('Y-m-d',strtotime(input('start_date'))),
            'end_date'          =>  date('Y-m-d',strtotime(input('end_date'))),
            'deadline'          =>  floor(input('deadline')),
            'paid'              =>  0,
            'reminder_day'      =>  floor(input('reminder_day')),
            'latest_paid_date'  =>  '0000-00-00'
        );

        Database::table('loans')->insert($data);

        // if (input('account') != "00") {
        //     self::balance(input('account'), input('amount'), "minus");
        // }

        return response()->json(responder("success", __('pages.messages.alright'), __('loans.messages.add-success'), "reload()"));
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

    /**
     * View Payment History
     * 
     * @return \Pecee\Http\Response
     */
    public function view() {
        $user = Auth::user();
        $loans = Database::table('loans')->where('id', input("loanid"))->first();
        $loanPayment = Database::table('expenses')->where('title', $loans->title)->where('user', $loans->user)->orderBy("id", false)->get();
        $total_paid = Database::table('expenses')->where('title', $loans->title)->where('user', $loans->user)->sum('amount','total')[0]->total;

        return view('includes/ajax/viewLoanHistory', compact("loans","loanPayment","total_paid"));
    }

    /**
     * Update Loan modal
     * 
     * @return \Pecee\Http\Response
     */
    public function payView() {
        $user = Auth::user();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $loans = Database::table('loans')->where('id', input("loanid"))->first();
        $include = (input('type') == 0) ? "includes/ajax/loans" : "includes/ajax/payLoan";
        return view($include, compact("loans","accounts"));
    }

    /**
     * Update Loan
     * 
     * @return Json
     */
    public function update(){
        $loans = Database::table('loans')->where('id', input("loanid"))->first();
        $expenses = Database::table('expenses')->where('title', $loans->title)->where('user', $loans->user)->get();
        $user = Auth::user();
        $type = input('type');

        $total_paid = 0;

        foreach ($expenses as $expense) {
            $total_paid += $expense->amount;
        }

        if ($type == 0){
            $payment_terms = floor(input('payment_terms'));
            $start_date = date('Y-m-d',strtotime(input('start_date')));
            $amountRemaining = input('amount') * input('payment_terms') - $total_paid;
            
            if ($total_paid == 0){
                $paid = 0;
                $latest_paid_date = '0000-00-00';
            } else if ($total_paid < input('amount') * input('payment_terms')){
                $paid = 1;
                $latest_paid_date = $loans->latest_paid_date;
            } else {
                $paid = 2;
                $latest_paid_date = $loans->latest_paid_date;
            }
            
            $data = array(
                'title'             =>  escape(input('title')),
                'user'              =>  $user->id,
                'amount'            =>  input('amount'),
                'account'           =>  input('account'),
                'payment_terms'     =>  $payment_terms,
                'amount_remaining'  =>  $amountRemaining,
                'start_date'        =>  $start_date,
                'end_date'          =>  date('Y-m-d',strtotime($start_date . " + " . $payment_terms . " months")),
                'deadline'          =>  floor(input('deadline')),
                'paid'              =>  $paid,
                'reminder_day'      =>  floor(input('reminder_day')),
                'latest_paid_date'  =>  $latest_paid_date
            );
        } else {
            $choose_payment = input('choose_payment');

            if ($choose_payment == 0){
                $amount = ($loans->amount_remaining - input('amount') >= 0) ? input('amount') : $loans->amount_remaining;
                $amountRemaining = ($loans->amount_remaining - $amount >= 0) ? ($loans->amount_remaining - $amount) : 0;
            } else {
                $amountRemaining = 0;
                $amount = $loans->amount_remaining;
            }

            $data = array(
                'user'              =>  $user->id,
                'amount_remaining'  =>  $amountRemaining,
                'paid'              =>  ($amountRemaining <= 0) ? 2 : 1,
                'latest_paid_date'  =>  date('Y-m-d', strtotime(input('date'))),
            );

            if ($loans->account > 0) {
                self::balance($loans->account, $amount, "minus");
            }

            $expenseArr = array(
                'user'              =>  $user->id,
                'title'             =>  escape($loans->title),
                'amount'            =>  $amount,
                'account'           =>  $loans->account,
                'category'          =>  0,
                'expense_date'      =>  date('Y-m-d', strtotime(input('date'))),
                'updated_at'        =>  date('Y-m-d H:i:s'),
            );

            Database::table('expenses')->insert($expenseArr);
        }

        Database::table('loans')->where('id',input('loanid'))->update($data);
        return response()->json(responder("success", __('pages.messages.alright'), __('loans.messages.edit-success'), "reload()"));
    }

    /**
     * Delete Loan record
     * 
     * @return Json
     */
    public function delete(){
        $loans = Database::table('loans')->where('id', input("loanid"))->first();
        
        if (!empty($loans->account)) {
            self::balance($loans->account, $loans->amount_remaining, "plus");
            Database::table('expenses')->where('title', $loans->title)->where('user', $loans->user)->delete();
        }

        Database::table('loans')->where('id',input('loanid'))->delete();
        return response()->json(responder("success", __('pages.messages.alright'), __('loans.messages.delete-success'), "reload()"));
    }

}
