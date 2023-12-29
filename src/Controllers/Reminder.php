<?php

namespace Simcify\Controllers;
use Simcify\Database;
use Simcify\Mail;

class Reminder {

    public function test(){
        $user = Database::table('users')->where('status', 'Active')->get();
        
        return 1;
    }

    public function send_reminder(){
        $loan_reminder = self::send_loan_reminder();
        return $loan_reminder;
     }

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

    public function loan_reminder($user){
        $loans = Database::table('loans')->where('user', $user->id)->get();
        $typeArr = ['0', '1', '2'];

        if (empty($loans)){
            return;
        }

        foreach ($loans as $loan){
            $loanReminder = Database::table('loan_reminder')->where('loanid', $loan->id)->where('user', $user->id)->where('date', date("Y-m-d"))->first();
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

            if (!empty($loanReminder AND $loanReminder->type == $type)){
                continue;
            }

            $send = Mail::send(
                $user->email,
                env("APP_NAME")." Loan Payment Reminder for " . $loan->title,
                array(
                    "title" => "Loan Payment Reminder",
                    "subtitle" => "Click the the button below to navigate to the loan page.",
                    "buttonText" => "View Payment",
                    "buttonLink" => url("loan/update/view?loanid=" . $loan->id),
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
