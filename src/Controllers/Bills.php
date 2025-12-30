<?php
namespace Simcify\Controllers;
use Simcify\Auth;
use Simcify\Database;

class Bills {

    /**
     * Get a sample view or redirect to it
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $stats = array();
        $title = __('pages.sections.bills');
        $user = Auth::user();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $categories = Database::table('categories')->where('user', $user->id)->where('type','expense')->orderBy("id", false)->get();
        $incomecategories = Database::table('categories')->where('user',$user->id)->where('type','Income')->orderBy("id", false)->get();
        $bills = Database::table("bills")->where("bills`.`user", $user->id)->leftJoin("accounts", "bills.account","accounts.id")->leftJoin("categories", "bills.category","categories.id")->orderBy("bills.id", false)->get("`bills.next_payment`","`bills.type`","`bills.id`", "`bills.title`", "`bills.amount`", "`bills.deadline`", "`bills.reminder_day`", "`bills.date_added`", "`bills.date_updated`", "`bills.status`", "`accounts.name` as accountname", "`categories.name` as categoryname");
        $monthly_bill_payment_count = Database::table('bill_payment')->where('user', $user->id)->where('MONTH(date_paid)', date('m'))->where('YEAR(date_paid)', date('Y'))->where('bill_type', '1')->count('id','total')[0]->total;
        // $yearly_bill_payment_count = Database::table('bill_payment')->where('user', $user->id)->where('YEAR(date_paid)', date('Y') - 1)->where('bill_type', '2')->count('id','total')[0]->total;
        $bill_count = Database::table('bills')->where('user', $user->id)->where('status', '1')->count('id','total')[0]->total;
        $bill = new \StdClass();

        $yearly_bill_payment_count = 0;
        $bill_amount_paid = 0;
        $bill_amount_total = 0;

        $billArr = [];

        foreach ($bills as $bill){
            $bill_status = 0;
            
            if ($bill->status == 1){
                $bill_amount_total += $bill->amount;

                if ($bill->type == 1){
                    $bill_payment = Database::table('bill_payment')->where('bill_id', $bill->id)->where('MONTH(date_paid)', date('m'))->where('YEAR(date_paid)', date('Y'))->where('user', $user->id)->where('bill_type', '1')->first();
                    if (!empty($bill_payment)) {
                        $bill_status = 1;
                        $bill_amount_paid += $bill->amount;
                    }
                } else {
                    $bill_payment = Database::table('bill_payment')->where('bill_id', $bill->id)->where('YEAR(date_paid)', date('Y') - 1)->where('user', $user->id)->where('bill_type', '2')->first();
                    if (!empty($bill_payment)) {
                        $bill_status = 1;
                        $yearly_bill_payment_count++;
                        $bill_amount_paid += $bill->amount;
                    } else {
                        $bill_payment = Database::table('bill_payment')->where('bill_id', $bill->id)->where('YEAR(date_paid)', date('Y'))->where('user', $user->id)->where('bill_type', '2')->first();
                        if (!empty($bill_payment)) {
                            $bill_status = 1;
                            $yearly_bill_payment_count++;
                            $bill_amount_paid += $bill->amount;
                        }
                    }

                }                
            }

            $bill_payment_count = $monthly_bill_payment_count + $yearly_bill_payment_count;

            $billArr[] = array(
                'id' => $bill->id,
                'title' => $bill->title,
                'amount' => $bill->amount,
                'deadline' => $bill->deadline,
                'reminder_day' => $bill->reminder_day,
                'date_added' => $bill->date_added,
                'date_updated' => $bill->date_updated,
                'status' => $bill->status,
                'accountname' => $bill->accountname,
                'categoryname' => $bill->categoryname,
                'bill_status' => $bill_status,
            );
        }

        $stats['bill_payment_count'] = $bill_payment_count;
        $stats['bill_count'] = $bill_count;
        $stats['bill_amount_paid'] = money($bill_amount_paid);
        $stats['bill_amount_total'] = money($bill_amount_total);
        $stats['percentage'] = $bill_count > 0 ? round(($bill_payment_count / $bill_count) * 100) : 0;

        return view('bill',compact("user","title","accounts","categories", "bills", "billArr", "stats", "incomecategories"));
    }

    /**
     * Add Loan
     * 
     * @return Json
     */
    public function add() {
        $user = Auth::user();

        $data = array(
            'title'             =>  escape(input('title')),
            'user'              =>  $user->id,
            'amount'            =>  input('amount'),
            'account'           =>  input('account'),
            'category'          =>  input('category'),
            'date_added'        =>  date('Y-m-d H:i:s'),
            'date_updated'      =>  '0000-00-00 00:00:00',
            'deadline'          =>  floor(input('deadline')),
            'status'            =>  input('bill_status'),
            'type'              =>  input('payment_type'),
            'reminder_day'      =>  floor(input('reminder_day')),
            'next_payment'      =>  date('Y-m-d'),
        );

        Database::table('bills')->insert($data);

        return response()->json(responder("success", __('pages.messages.alright'), __('bills.messages.add-success'), "reload()"));
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

        return true;
    }

    /**
     * Update Loan modal
     * 
     * @return \Pecee\Http\Response
     */
    public function payView() {
        $user = Auth::user();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $categories = Database::table('categories')->where('user', $user->id)->orderBy("id", false)->get();
        $bills = Database::table('bills')->where('id', input("billid"))->first();
        $bill_payments = Database::table('bill_payment')->where('bill_id', input("billid"))->orderBy("id", false)->get();
        $total_paid = Database::table('bill_payment')->where('bill_id', input("billid"))->sum('amount','total')[0]->total;
        $include = (input('type') == 0) ? "includes/ajax/bills" : "includes/ajax/payBills";
        $advance = input('advance');

        return view($include, compact("bills","accounts", "categories", "bill_payments", "total_paid", "advance"));
    }

    /**
     * Update Loan modal
     * 
     * @return \Pecee\Http\Response
     */
    public function view() {
        $user = Auth::user();
        $bills = Database::table('bills')->where('id', input("billid"))->first();
        $bill_payments = Database::table('bill_payment')->where('bill_id', input("billid"))->orderBy("id", false)->get();
        $total_paid = Database::table('bill_payment')->where('bill_id', input("billid"))->sum('amount','total')[0]->total;

        return view('includes/ajax/viewHistory', compact("bills","bill_payments","total_paid"));
    }

    /**
     * Update Loan
     * 
     * @return Json
     */
    public function update(){
        $bills = Database::table('bills')->where('id', input("billid"))->first();
        $account = Database::table('accounts')->where('id', input('account'))->first();
        $user = Auth::user();
        $type = input('type');

        if ($type == 0){
            $data = array(
                'title'             =>  escape(input('title')),
                'user'              =>  $user->id,
                'amount'            =>  input('amount'),
                'account'           =>  input('account'),
                'category'          =>  input('category'),
                'date_updated'      =>  date('Y-m-d H:i:s'),
                'deadline'          =>  floor(input('deadline')),
                'status'            =>  input('bill_status'),
                'type'              =>  input('payment_type'),
                'reminder_day'      =>  floor(input('reminder_day')),
            );
            
            Database::table('bills')->where('id',input('billid'))->update($data);

        } else {
            $advance = input('advance');
            $amount = $bills->amount;
            $nextPayment = ($bills->next_payment == "0000-00-00 00:00:00") ? date('Y-m-d H:i:s') : $bills->next_payment;
            $dateToday = date('Y-m-d H:i:s');
            $paymentDay = ($advance == 1) ? date('Y-m-1 H:i:s', strtotime($nextPayment)) : $dateToday;
            $payment_Arr = array(
                'bill_id'           =>  input('billid'),
                'bill_type'         =>  $bills->type,
                'user'              =>  $user->id,
                'amount'            =>  $amount,
                'date_paid'         =>  $paymentDay,
            );

            $expenseArr = array(
                'title'             =>  $bills->title,
                'user'              =>  $user->id,
                'amount'            =>  $amount,
                'account'           =>  $bills->account,
                'category'          =>  $bills->category,
                'expense_date'      =>  date('Y-m-d H:i:s'),
            );

            $paymentType = ($bills->type == 1) ? "+ 1 month" : "+ 1 year";

            $next_payment_date = ($advance == 1) ? date('Y-m-1', strtotime($nextPayment . $paymentType)) : date('Y-m-d', strtotime($nextPayment . $paymentType));

            self::balance($bills->account, $amount, "minus");

            $history = array(
                'userId' => $user->id,
                'accountId' => $bills->account,
                'fromAmount' => $account->balance,
                'toAmount' => $account->balance - $amount,
                'type' => '4',
                'date_added' => date('Y-m-d H:i:s')
            );

            Database::table('history')->insert($history);
            Database::table('bill_payment')->insert($payment_Arr);
            Database::table('expenses')->insert($expenseArr);
            Database::table('bills')->where('id',input('billid'))->update(array("next_payment" => $next_payment_date));
        }

        return response()->json(responder("success", __('pages.messages.alright'), __('bills.messages.edit-success'), "reload()"));
    }

    /**
     * Delete Loan record
     * 
     * @return Json
     */
    public function delete(){
        Database::table('bills')->where('id',input('billid'))->delete();
        Database::table('bill_payment')->where('bill_id',input('billid'))->delete();

        return response()->json(responder("success", __('pages.messages.alright'), __('bills.messages.delete-success'), "reload()"));
    }

}
