<?php
namespace Simcify\Controllers;

use Simcify\Auth as Authenticate;
use Pecee\Http\Request;
use Simcify\Database;
use Simcify\Str;
use Simcify\Mail;


class Auth {
    
    /**
     * Get a sample view or redirect to it
     * 
     * @return \Pecee\Http\Response
     */
    public function get() {
        if (!isset($_GET['secure'])) {
            redirect(url("Auth@get")."?secure=true");
        }
        return view('auth');
    }
    
    /**
     * User signin
     * 
     * @return Json
     */
    public function signin() {
        $signin = Authenticate::login(input('email'), input('password'), array(
            "rememberme" => true,
            "redirect" => url(''),
            "status" => "Active"
        ));
        
        return response()->json($signin);
    }
    
    /**
     * Create a user account
     * 
     * @return Json
     */
    public function signup() {
        $register = Authenticate::signup(array(
            "fname" => input('fname'),
            "lname" => input('lname'),
            "email" => input('email'),
            "password" => Authenticate::password(input('password')),
            "role" => 'user'
        ), array(
            "authenticate" => true,
            "redirect" => url(""),
            "uniqueEmail" => input('email')
        ));
        
        return response()->json($register);
        
    }
    
    
    /**
     * signout User
     * 
     * @return \Pecee\Http\Response
     */
    public function signout() {
        Authenticate::deauthenticate();
        redirect(url('Auth@get'));
    }
    
    /**
     * Forgot password - send reset password email
     * 
     * @return Json
     */
    public function forgot() {
        $forgot = Authenticate::forgot(input('email'), env('APP_URL') . "/reset/[token]");
        return response()->json($forgot);
    }
    
    
    /**
     * Get reset password page
     * 
     * @return \Pecee\Http\Response
     */
    public function resetpage($token) {
        $title = __('pages.sections.reset-password');
        return view('reset', compact("token", "title"));
    }
    
    /**
     * Reset password
     * 
     * @return Json
     */
    public function reset() {
        $reset = Authenticate::reset(input('token'), input('password'));
        return response()->json($reset);
    }

    /**
     * Send loan reminder
     * 
     * @return Json
     */
    public function send_loan_reminder(){
        $user = Database::table('users')->where('status', 'Active')->get();
        $responseArr = [];

        foreach ($user as $user){
            $loanReminder = self::loan_reminder($user);

            if (!empty($loanReminder)){
                array_push($responseArr, $loanReminder);
            }
        }

        return $responseArr;
    }

    /**
     * Loan reminder
     * 
     * @return Json
     */
    public function loan_reminder($user){
        $loans = Database::table('loans')->where('user', $user->id)->get();
        $typeArr = ['0', '1', '2'];

        if (empty($loans)){
            return;
        }

        foreach ($loans as $loan){
            $message = "";
            $checkExpenses = Database::table('expenses')->where('title', $loan->title)->where('user', $user->id)->where('MONTH(expense_date)', date('m'))->where('YEAR(expense_date)', date('Y'))->first();

            if (!empty($checkExpenses)){
                continue;
            }

            $type = 3;
            
            if ($loan->paid == 2){
                continue;
            }

            $today = date("Y-m-d");
            $deadline = $loan->deadline;
            $reminder_day = $loan->reminder_day;
            $reminder_date = date("Y-m-" . $reminder_day);
            $deadline_date = date("Y-m-" . $deadline);

            if ($today == $reminder_date){
                $message = "Your loan payment for " . $loan->title . " with amount " . $loan->amount . " is due on " . date("F j, Y", strtotime($deadline_date)) . ". Please make payment to avoid penalties.";
                $type = 1;

            } else if ($today == $deadline_date){
                $message = "Your loan payment for " . $loan->title . " with amount " . $loan->amount . " is due today. Please make payment to avoid penalties.";
                $type = 0;

            } else if ($today > $deadline_date){
                $message = "Your loan payment for " . $loan->title . " with amount " . $loan->amount . " was due on " . date("F j, Y", strtotime($deadline_date)) . ". Please make payment to avoid penalties.";
                $type = 2;
            }

            if ($type != 2){
                $loanReminder = Database::table('loan_reminder')->where('loanid', $loan->id)->where('user', $user->id)->where('type', $type)->where('MONTH(date)', date('m'))->where('YEAR(date)', date('Y'))->first();
    
                if (!empty($loanReminder)){
                    continue;
                }
            }


            $send = Mail::send(
                $user->email,
                "Loan Payment Reminder for " . $loan->title,
                array(
                    "title" => "Loan Payment Reminder",
                    "subtitle" => "Click the the button below to navigate to the loan page.",
                    "buttonText" => "View Loan",
                    "buttonLink" => url("/loan"),
                    "message" => $message
                ),
                "withbutton"
            );

            if ($send) {
                $response = array(
                    "status" => "success",
                    "title" => "Email sent!",
                    "message" => "Email with loan payment reminder successfully sent!"
                );
            }else{
                $response = array(
                    "status" => "error",
                    "title" => "Failed to send",
                    "message" => $send->ErrorInfo
                );
            }

            Database::table('loan_reminder')->insert(array(
                "loanid" => $loan->id,
                "user" => $user->id,
                "type" => $type,
                "date" => date("Y-m-d")
            ));

            return $response;
        }
    }
}