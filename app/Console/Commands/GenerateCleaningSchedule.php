<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class GenerateCleaningSchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:schedule {email? : If provided, the csv file will be sent to the email address}';
    
    // This property will store the last working day of the month. Every new month it's value will be reset. This property will be used to schedule window cleaning on the last working day of the month
    protected $last_working_day;
    // We will use this property to schedule a refrigerator cleaning on the first vacuming day and them mark it as completed for the month. Every new month, we will reset this property to false in order for refrigerator cleaning to be scheduled again.
    protected $refrigerator_cleaned;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate cleaning schedule for next three months';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->last_working_day = $this->lastWorkingDay(strtotime(date('Y-m-d')));
        $this->refrigerator_cleaned = false; 
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $current_date = strtotime(date('Y-m-d'));
        $end_date = strtotime('+3 months', $current_date);

        // write headers to a CSV file
        $file = fopen(storage_path('app/schedule.csv'), 'w');
        fputcsv($file, ['Date', 'Activity', 'Total Time (HH:mm)']);
        
        while ($current_date <= $end_date) {
            $day = date('w', $current_date);
            $activity = [];
            $total_time = 0;

            // If current date is 1st of the month, reset refrigerator_cleaned and last_working_day values so the refrigerator and windows can be scheduled for cleaning again
            if(date('d', $current_date) == 1) {
                $this->refrigerator_cleaned = false;
                $this->last_working_day = $this->lastWorkingDay($current_date);
            }

            if($day != config('constants.days.saturday') && $day != config('constants.days.sunday')) {
                // Check if current day is Tuesday or Thursday
                if ($day == config('constants.days.tuesday') || $day == config('constants.days.thursday')) {
                    $activity[] = [config('constants.cleaning_types.vacuuming.display_name'), config('constants.cleaning_types.vacuuming.duration')];

                    // Check if it's first vacuuming day of the month
                    if(!$this->refrigerator_cleaned) {
                        $activity[] = [config('constants.cleaning_types.refrigerator_cleaning.display_name'), config('constants.cleaning_types.refrigerator_cleaning.duration')];
                        $this->refrigerator_cleaned = true;
                    }
                }

                // Check if current day is last working day of the month
                if ($this->last_working_day == date('d', $current_date)) {
                    $activity[] = [config('constants.cleaning_types.window_cleaning.display_name'), config('constants.cleaning_types.window_cleaning.duration')];
                }

                if (count($activity) > 0) {
                    foreach ($activity as list($desc, $time)) {
                        $total_time += $time;
                    }

                    $csv_data = [
                        date('Y-m-d', $current_date),
                        implode(', ', array_column($activity, 0)),
                        sprintf('%02d:%02d', floor($total_time / 60), $total_time % 60)
                    ];

                    // write the activities in the csv file
                    fputcsv($file, $csv_data);
                }
            }

            // get the next date
            $current_date = strtotime('+1 day', $current_date);
        }

        fclose($file);

        // If a valid email address is provided when running the command, attach the generated file and send the email
        if($this->argument('email') && filter_var($this->argument('email'), FILTER_VALIDATE_EMAIL)) {
            $email_data = [
                'email' => $this->argument('email'),
                'subject' => config('constants.email_subject')
            ];
            $file = storage_path('app/schedule.csv');

            Mail::send('emails.schedule', $email_data, function($message) use ($email_data, $file) {
                $message->to($email_data["email"])
                        ->subject($email_data["subject"])
                        ->attach($file);
                
            });
        }

        $this->info('Cleaning Schedule generated at schedule.csv successfully.');
    }

    private function lastWorkingDay($current_date) {
        // Get the last day of the month
        $date = date('t', $current_date);
        // Get the day of the last date of the month
        $day = date('w', strtotime(date('Y-m', $current_date) . '-' . $date));

        // If the last working day is saturday or sunday, return the date of the last friday
        if ($day == config('constants.days.saturday')) {
            return $date - 1;
        } else if ($day == config('constants.days.sunday')) {
            return $date - 2;
        }

        return $date;    
    }

}
