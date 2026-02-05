<?php
/*
This PHP script parses an IceCast2 radio log and outputs the number of radio listeners to GoogleCharts.
Parameters required are date and minimum required listening time in seconds.

*/


 $file_path = "access.log";
    
$handle = fopen($file_path, "r") or die("Unable to open " . $filepath);

//test pattern - get both radiowaterloo and stream2
//$pattern = "/(GET \/radiowaterloo)|(GET \/stream2)/";  //works - parentheses optional for readabilty
 
$pattern1 = "/GET \/radiowaterloo/";   //parse out only radiowaterloo
$pattern2 = "/GET \/stream2/"; //parse out only stream2
       
$pattern3 = '/^(\\S+) (\\S+) (\\S+) \\[(\\d{2}\\/[a-zA-Z]{3}\\/\\d{4}:\\d{2}:\\d{2}:\\d{2} [^\\s]+)\\] "(.*)" (\\S+) (\\S+) "(.*)" "(.*)" (\\d+)$/';   //pattern designed to place the date as the fifth part in an array
$date_format = 'd/M/Y:H:i:s T';  //date format from access.log 

$pattern4 = "/02\/Dec/";   //for filtering by day



$array_radiowat_datetimes = [];     //will store the radio waterloo datetimes
$array_libre_datetimes = [];  //will store the libre datetimes

$array_radiowat_intervalcounts = [];  //stores the number of libre datetime occurences in a 15 minute interval
$array_libre_intervalcounts = []; //stores the number of radiowaterloo datetime occurences in a 15 minute interval

if ($handle) {
    // Loop through the file line by line until the end of the file (feof)
    while (($line = fgets($handle)) !== false) {
        // Process each line here
      //if (preg_match($pattern4, $line)) { //filter by day first     
       
        if (preg_match($pattern4, $line)) {

        if (preg_match($pattern1, $line)) {
            //echo $line . "<br>"; 
            //fwrite($newfile1, $line);                   
            $data_array=getDateTime($pattern3, $line, $date_format);
            //filter only durations that are 30 or greater
            if ($data_array[1] >= 30)
                {
                //TRACE
                //echo $data_array[0]->format('l, F j, Y H:i:s') . "--dur--" . $data_array[1] . "<br>";
                array_push($array_radiowat_datetimes, $data_array);
                }
            }
        else if (preg_match($pattern2, $line)) {
            //echo $line . "<br>"; 
            //fwrite($newfile2, $line); 
            $data_array=getDateTime($pattern3, $line, $date_format);

            //filter only durations that are 60 or greater
            if ($data_array[1] >= 60)
                array_push($array_libre_datetimes, $data_array);
            
        } 
      }
    }

   

    //echo "finished reading " . $file_path . "<br>";
    // Close the file handles
    fclose($handle);


    //var_dump($array_radiowat_datetimes);      //works
    //var_dump($array_libre_datetimes);     //works

    //echo "generating radiowaterloo interval counts<br>";
    $array_radiowat_intervalcounts = generate_intervalcounts($array_radiowat_datetimes);
    //print_r($array_radiowat_intervalcounts);  //works

    //echo "generating libre interval counts<br>";
    $array_libre_intervalcounts = generate_intervalcounts($array_libre_datetimes);
    //print_r($array_libre_intervalcounts);  //works

    //convert associative interval arrays to 2 dimensional arrays usable for Google Charts
    $twoDimArray_radioWat = [];
    $twoDimArray_radioWat[] = array('DateTime','Count per 15 min');     //headers is first subarray

    $twoDimArray_libre = [];
    $twoDimArray_libre[] = array('DateTime','Count per 15 min');     


    foreach ($array_radiowat_intervalcounts as $key => $value) {
         $twoDimArray_radioWat[] = [
         $key,
         $value
        ];
    }

    foreach ($array_libre_intervalcounts as $key => $value) {
         $twoDimArray_libre[] = [
         $key,
         $value
        ];
    }

    //print_r($twoDimArray_radioWat);    //works

    // Encode the interval arrays into JSON format
    $json_data_radioWat = json_encode($twoDimArray_radioWat);
    $json_data_libre = json_encode($twoDimArray_libre);

    



} else {
    echo "Error: Could not open the file '$file_path'.";
}
    
  
//extracts datetime, duration in seconds, description, ip from line and returns in array 
function getDateTime($pattern, $line, $date_format) {
    
    if (preg_match($pattern, $line, $matches)) {
                // $matches[4] contains the raw timestamp string with brackets, e.g., "[08/May/2018:11:43:38 +0200]"
                // Extract the date string without the brackets
                 $raw_date_string = trim($matches[4], '[]');
                 $duration = $matches[10];
                 $description = $matches[9];
                 $ip = $matches[1];
                 //echo $duration . ":";

                 // Create a DateTime object
                 $date_time_obj = DateTime::createFromFormat($date_format, $raw_date_string);
                 return [$date_time_obj,$duration,$description, $ip];
            }
            else 
                echo "Can't parse date for . $line<BR>";

}


function generate_intervalcounts($twoDArray)
{
$interval_counts=[];

//foreach ($datetimes as $date) {
for ($i = 0; $i < count($twoDArray); $i++) {
        $date=$twoDArray[$i][0];
        $duration=$twoDArray[$i][1];
        $description=$twoDArray[$i][2];
        $ip=$twoDArray[$i][3];

        //skip for iteration if out of time interval
        $datetime_start = new DateTime('2025-12-02 21:00:00',new DateTimeZone('America/New_York'));
        $datetime_end = new DateTime('2025-12-03 07:15:00',new DateTimeZone('America/New_York'));
       
        if ($date<$datetime_start)
            continue;
        //////////

        
        //TRACE
        /*
        echo "<BR>Date processing in generate_interval_counts<BR>";
        echo $date->format('l, F j, Y H:i:s') . "<BR>";
        */



        //determine how how many 15 minute intervals the duration in sections spans
        //15 minutes = 900 seconds

        $buckets15=ceil($duration/900);

        
        echo "TRACE<BR>";
        
        $datetime_start = new DateTime('2025-12-02 21:00:00',new DateTimeZone('America/New_York'));
        $datetime_end = new DateTime('2025-12-03 07:15:00',new DateTimeZone('America/New_York'));
       
        if ($date>=$datetime_start && $date<$datetime_end)
            {
                //echo $datetime_start->format('l, F j, Y H:i:s') . "<br>";
                echo $date->format('l, F j, Y H:i:s') . "-dur-" . $duration . "-bu-" . $buckets15 . "-desc-" . $description . "-ip-" . $ip . "<br>";

            }

        
        /////////////////////////

    try {

        //calculate interval_key
        
        $minutes = (int)$date->format('i');
        
        // Calculate the start of the 15-minute interval by rounding down
        $interval_start_minutes = floor($minutes / 15) * 15;
        
        // Set the minutes and seconds to the start of the interval
        $date->setTime($date->format('H'), $interval_start_minutes, 0);
        
        // Format the interval start time as a key (e.g., "2025-11-13 10:00")
        $interval_key = $date->format('Y-m-d H:i');
        

        // Increment the count for this interval

        if (!isset($interval_counts[$interval_key])) {
            $interval_counts[$interval_key] = 0;   //initialize if it doesn't exist  
        }
        $interval_counts[$interval_key]++;  //increment the count


        //also increment the previous 15 minute buckets if at least 2 buckets
                
        if ($buckets15>1)
        {
            for ($b=2; $b<=$buckets15; $b++)
               {
                    //determine the bth-1 previous bucket
                    $minutes_before=15*($b-1);
                    $date_before= clone $date;     //copy date object using clone so not copy reference
                  
                    $modify_str='-' . $minutes_before . ' minutes';
                    $date_before->modify($modify_str);    //example '-30 minutes'
                   
                    //test
                    //if ($buckets15>=900)
                      //  echo $date->format('Y-m-d H:i:s') . "duration" . $duration . "buckets" . $buckets15 . "modify" . "$modify_str" . "--->" . $date_before->format('Y-m-d H:i');
                    

                    $interval_key_before = $date_before->format('Y-m-d H:i');
                       if (!isset($interval_counts[$interval_key_before])) {
                            $interval_counts[$interval_key_before] = 0;   //initialize if it doesn't exist  
                            }
                    $interval_counts[$interval_key_before]++;  //increment the count

               }
        }
        

      

        //sort the array in order of its keys since we added new ones out of order with the buckets
        ksort($interval_counts);
       
        
        
    } catch (Exception $e) {
        // Handle potential invalid date format errors
        $dt_string = $date->format('l, F j, Y H:i');
        echo "Error processing date: $dt_string - " . $e->getMessage() . "\n";
    }
 
  

}

return $interval_counts;
}


?>


<!DOCTYPE html>
    <html>
    <head>
        <title>Radio Waterloo Charts</title>
    </head>
    <body>
           <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>

           <div id="chart_div_radioWat" style="width: 900px; height: 500px;"></div>
           <div id="chart_div_libre" style="width: 900px; height: 500px;"></div>


           <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
            // Create the data table from the JSON-encoded PHP array

            //Draw Radio Waterloo chart
            let chartTitle='Dec 2, 2025 Radio Waterloo';
            let json_data=<?php echo $json_data_radioWat; ?>;
            let chartDiv='chart_div_radioWat';
            drawActualChart(json_data,chartTitle,chartDiv);

            //Draw Libre chart
            chartTitle='Dec 2, 2025 Libre';
            json_data=<?php echo $json_data_libre; ?>;
            chartDiv='chart_div_libre';

            drawActualChart(json_data,chartTitle,chartDiv);

        
        }

        function drawActualChart(json_data,chartTitle,chartDiv) {
            var data1 = google.visualization.arrayToDataTable(json_data);

            // Set chart options
            var options1 = {
                title: chartTitle,
                curveType: 'function',
                legend: { position: 'bottom' }
            };

            // Instantiate and draw the chart
            var chart = new google.visualization.LineChart(document.getElementById(chartDiv));
            chart.draw(data1, options1);

            
        }

    </script>


    </body>

    </html>

