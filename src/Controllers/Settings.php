<?php
namespace Simcify\Controllers;

use Simcify\File;
use Simcify\Auth;
use Simcify\Database;
use DotEnvWriter\DotEnvWriter;
use Simcify\Mail;

class Settings {
    
    /**
     * Get settings view
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        $title      = __('pages.profile-menu.settings');
        $user       = Auth::user();
        $timezones  = Database::table("timezones")->get();
        $currencies = Database::table("currencies")->get();
        $accounts = Database::table('accounts')->where('user', $user->id)->orderBy("id", false)->get();
        $categories = Database::table('categories')->where('user',$user->id)->orderBy("name", true)->get();
        $income = Database::table('income')->where('user', $user->id)->orderBy("id", false)->get();
        $expense = Database::table('expenses')->where('user', $user->id)->orderBy("id", false)->get();

        $dateArr = [];

        foreach ($income as $key => $value) {
            $date = explode('-', $value->income_date);
            $dateArr[] = $date[0] . '-' . $date[1];
        }

        foreach ($expense as $key => $value) {
            $date = explode('-', $value->expense_date);
            $dateArr[] = $date[0] . '-' . $date[1];
        }

        $dateArr = array_unique($dateArr);
        return view('settings', compact("user", "title", "timezones", "currencies","accounts","categories", "dateArr"));
    }
    
    /**
     * Update profile on settings page
     * 
     * @return Json
     */
    public function updateprofile() {
        $account = Database::table(config('auth.table'))->where("email", input("email"))->first();
        if (!empty($account) && $account->id != Auth::user()->id) {
            return response()->json(responder("error", __('pages.messages.oops'), input("email") . " " . __('settings.messages.already-exists')));
        }
        
        foreach (input()->post as $field) {
            if ($field->index == "avatar") {
                if (!empty($field->value)) {
                    $avatar = File::upload($field->value, "avatar", array(
                        "source" => "base64",
                        "extension" => "png"
                    ));
                    
                    if ($avatar['status'] == "success") {
                        if (!empty(Auth::user()->avatar)) {
                            File::delete(Auth::user()->avatar, "avatar");
                        }
                        Database::table(config('auth.table'))->where("id", Auth::user()->id)->update(array(
                            "avatar" => $avatar['info']['name']
                        ));
                    }
                }
                continue;
            }
            
            if ($field->index == "csrf-token") {
                continue;
            }
            
            Database::table(config('auth.table'))->where("id", Auth::user()->id)->update(array(
                $field->index => escape($field->value)
            ));
        }
        
        return response()->json(responder("success", __('pages.messages.alright'), __('settings.messages.profile-edit-success'), "reload()"));
    }
    
    /**
     * Update company on settings page
     * 
     * @return Json
     */
    public function updatecompany() {
        foreach (input()->post as $field) {
            if ($field->index == "csrf-token") {
                continue;
            }
            
            Database::table("companies")->where("id", Auth::user()->company)->update(array(
                $field->index => escape($field->value)
            ));
        }
        exit(json_encode(responder("success", __('pages.messages.alright'), __('settings.messages.company-edit-success'))));
    }
    
    
    /**
     * Update password on settings page
     * 
     * @return Json
     */
    public function updatepassword() {
        $user = Auth::user();
        if (hash_compare($user->password, Auth::password(input("current")))) {
            Database::table(config('auth.table'))->where("id", $user->id)->update(array(
                "password" => Auth::password(input("password"))
            ));
            return response()->json(responder("success", __('pages.messages.alright'), __('settings.messages.password-edit-success'), "reload()"));
        } else {
            return response()->json(responder("error", __('pages.messages.oops'), __('settings.messages.password-incorrect')));
        }
    }
    
    /**
     * Update system settings
     * 
     * @return Json
     */
    public function updatesystem() {
        header('Content-type: application/json');
        if (strpos(dirname(__FILE__),'\\') > 0)
		{
			$envPath = str_replace("src\Controllers", ".env", dirname(__FILE__));
		} else {
			$envPath = str_replace("src/Controllers", ".env", dirname(__FILE__));
		}
        $env = new DotEnvWriter($envPath);
        $env->castBooleans();
        $enableToggle = array(
            "PKI_STATUS",
            "CERTIFICATE_DOWNLOAD",
            "NEW_ACCOUNTS",
            "ALLOW_NON_PDF",
            "USE_CLOUD_CONVERT",
            "SHOW_SAAS"
        );
        foreach ($enableToggle as $key) {
            if (empty(input($key))) {
                $env->set($key, 'Disabled');
            }
        }
        if (empty(input("SMTP_AUTH"))) {
            $env->set("SMTP_AUTH", false);
        }
        $env->set("MAIL_SENDER", input("APP_NAME") . " <" . input("MAIL_FROM") . ">");
        foreach (input()->post as $field) {
            if ($field->index == "APP_LOGO") {
                if (!empty($field->value)) {
                    $upload = File::upload($field->value, "app", array(
                        "source" => "base64",
                        "extension" => "png"
                    ));
                    
                    if ($upload['status'] == "success") {
                        File::delete(env("APP_LOGO"), "app");
                        $env->set("APP_LOGO", $upload['info']['name']);
                        $env->save();
                    }
                }
                continue;
            }
            if ($field->index == "APP_ICON") {
                if (!empty($field->value)) {
                    $upload = File::upload($field->value, "app", array(
                        "source" => "base64",
                        "extension" => "png"
                    ));
                    
                    if ($upload['status'] == "success") {
                        File::delete(env("APP_ICON"), "app");
                        $env->set("APP_ICON", $upload['info']['name']);
                        $env->save();
                    }
                }
                continue;
            }
            
            if ($field->index == "csrf-token") {
                continue;
            }
            
            $env->set($field->index, $field->value);
            $env->save();
        }
        
        exit(json_encode(responder("success", __('pages.messages.alright'), __('settings.messages.settings-edit-success'), "reload()")));
    }
    
    /**
     * Add category
     * 
     * @return Json
     */
    public function addcategory() {
        $data = array(
            'name' => input('category'),
            'type' => input('type'),
            'user' => Auth::user()->id
        );
        Database::table('categories')->insert($data);
        return response()->json(responder("success", __('pages.messages.alright'), __('settings.messages.category-add-success'), "reload()"));
    }
    
    
    /**
     * Category message
     * 
     * @return Json
     */
    public function deletecategory() {
        Database::table("categories")->where("id", input("categoryid"))->delete();
        return response()->json(responder("success", __('settings.messages.category-deleted'), __('settings.messages.category-delete-success'), "reload()"));
    }
    
    /**
     * Update category view
     * 
     * @return Json
     */
    public function updatecategoryview() {
        $category = Database::table('categories')->where('id', input("categoryid"))->first();
        return view('includes/ajax/editcategory', compact("category"));
    }
    
    /**
     * Update category
     * 
     * @return Json
     */
    public function updatecategory() {
        $data = array(
            'name' => input('category'),
            'type' => input('type')
        );
        Database::table('categories')->where('id', input("categoryid"))->update($data);
        return response()->json(responder("success", __('pages.messages.alright'), __('settings.messages.category-edit-success'), "reload()"));
    }
    
    /**
     * Download Statement
     * 
     * @return Json
     */
    public function downloadStatement(){
        $date = input('statement-date');
        $user = Auth::user();

        if (empty($date)) {
            return response()->json(responder("error", __('pages.messages.oops'), __('settings.messages.statement-date-empty')));
        }

        $dataArr = [];
        $financeArr = [];
        $user = Auth::user();

        if ($date != "all"){
            $month = explode('-', $date)[1];
            $year = explode('-', $date)[0];
        }

        $incomeData = ($date == "all") ? Database::table('income')->where('user', $user->id)->orderBy("income_date", true)->get() : Database::table('income')->where('user', $user->id)->where('MONTH(income_date)', $month)->where('YEAR(income_date)', $year)->orderBy("income_date", true)->get();
        $expenseData = ($date == "all") ? Database::table('expenses')->where('user', $user->id)->orderBy("expense_date", true)->get() : Database::table('expenses')->where('user', $user->id)->where('MONTH(expense_date)', $month)->where('YEAR(expense_date)', $year)->orderBy("expense_date", true)->get();

        foreach ($incomeData as $key => $value) {
            $dataArr[] = array(
                'date' => $value->income_date,
                'title' => $value->title,
                'amount' => $value->amount,
                'currencyAmount' => money($value->amount),
                'type' => 'Income',
            );
        }

        foreach ($expenseData as $key => $value) {
            $dataArr[] = array(
                'date' => $value->expense_date,
                'title' => $value->title,
                'amount' => $value->amount,
                'currencyAmount' => money($value->amount),
                'type' => 'Expense',
            );
        }

        usort($dataArr, function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        $total_income = $total_expense = 0;

        foreach ($dataArr as $key => $value) {
            if ($value['type'] == 'Income'){
                $total_income += $value['amount'];
            } else {
                $total_expense += $value['amount'];
            }
        }

        $total_savings = $total_income - $total_expense;

        money($total_income);
        money($total_expense);
        money($total_savings);

        $financeArr['total_income'] = $total_income;
        $financeArr['total_expense'] = $total_expense;
        $financeArr['total_savings'] = $total_savings;

        $subtitle = ($date == "all") ? "From the beginning" : date('F Y', strtotime($date)) . " only";
        $subject = "Statement of Account for " . (($date == "all") ? "All Time" : date('F Y', strtotime($date)));

        // $view_data = array_merge(array(
        //     "title" => "Statement of Account",
        //     "subtitle" => "Period Covered: " . $subtitle,
        //     "dataArr" => $dataArr,
        //     "financeArr" => $financeArr,
        // ), array(
        //     "appurl" => env('APP_URL'),
        //     "applogo" => env('APP_URL')."/uploads/app/".env('APP_LOGO'),
        //     "copyright" => "&copy; ".date("Y")." ".env("APP_NAME")." | ".__('pages.footer')
        // ));

        // $body = view("emails.html.statement", $view_data);

        // // convert the html body into a PDF file using our dompdf class
        // $dompdf = new \Dompdf\Dompdf();
        // $dompdf->loadHtml($body);
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        // $output = $dompdf->output();

        // $filename = $user->id . "_Statement of Account for " . (($date == "all") ? "All Time" : date('F Y', strtotime($date)));

        // if (!file_exists("uploads/statements/")) {
        //     mkdir("uploads/statements/", 0777, true);
        // }

        // $file = fopen("uploads/statements/" . $filename . ".pdf", "w");
        // fwrite($file, $output);
        // fclose($file);

        $send = Mail::send(
            $user->email,
            $subject,
            array(
                "title" => "Statement of Account",
                "subtitle" => "Period Covered: " . $subtitle,
                "dataArr" => $dataArr,
                "financeArr" => $financeArr,
            ),
            "statement",
            // array(
            //     "uploads/statements/" . $filename . ".pdf" => "Statement of Account.pdf"
            // )
        );

        if ($send){
            $response = array(
                "status" => "success",
                "title" => "Email sent!",
                "message" => "Statement sent to your email",
            );
        } else {
            $response = array(
                "status" => "error",
                "title" => "Oops!",
                "message" => $send->ErrorInfo
            );
        }

        return response()->json($response);
    }
}