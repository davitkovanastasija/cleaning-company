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

        $csv_data = [];
        while ($current_date <= $end_date) {
            $day = date('w', $current_date);

            $activity = [];
            $total_time = 0;

            // Check if current day is Tuesday or Thursday
            if ($day == 2 || $day == 4) {
                $activity[] = [config('constants.cleaning_types.vacuuming.display_name'), config('constants.cleaning_types.vacuuming.duration')];
            }

            // Check if current day is last working day of the month
            if (date('t', $current_date) == date('d', $current_date)) {
                $activity[] = [config('constants.cleaning_types.window_cleaning.display_name'), config('constants.cleaning_types.window_cleaning.duration')];
            }

            // Check if it's first vacuuming day of the month
            if (date('j', $current_date) <= 7 && $day == 2) {
                $activity[] = [config('constants.cleaning_types.refrigerator_cleaning.display_name'), config('constants.cleaning_types.refrigerator_cleaning.duration')];
            }

            if (count($activity) > 0) {
                foreach ($activity as list($desc, $time)) {
                    $total_time += $time;
                }

                $csv_data[] = [
                    date('Y-m-d', $current_date),
                    implode(', ', array_column($activity, 0)),
                    sprintf('%02d:%02d', floor($total_time / 60), $total_time % 60)
                ];
            }

            $current_date = strtotime('+1 day', $current_date);
        }

        // write all rows to a CSV file
        $file = fopen(storage_path('app/schedule.csv'), 'w');
        fputcsv($file, ['Date', 'Activity', 'Total Time (HH:mm)']);
        
        foreach ($csv_data as $data) {
            fputcsv($file, $data);
        }

        fclose($file);

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

}
