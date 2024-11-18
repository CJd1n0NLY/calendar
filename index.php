<?php
function build_calendar($month, $year) {
    $mysqli = new mysqli('localhost', 'root', '', 'bookingsystem');
    
    // Define processing, preparation, and buffer time
    $processTime = 5;
    $prepTime = 3;       
    $bufferTime = 2;  

    // Fetch all bookings from the database
    $stmt = $mysqli->prepare("
        SELECT 
            b.DATE,
            s.processing_time,
            s.preparation_time,
            s.buffer_time
        FROM 
            bookings_record b
        JOIN 
            services s 
        ON 
            b.SERVICE_ID = s.id
    ");

    $bookings = array();
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row;
        }
        $stmt->close();
    }

    // Set up day names and first day of the month
    $daysOfWeek = array('Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday');
    $firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
    $numberDays = date('t', $firstDayOfMonth);
    $dateComponents = getdate($firstDayOfMonth);
    $monthName = $dateComponents['month'];
    $dayOfWeek = $dateComponents['wday'];

    // Array to hold unavailable date ranges dynamically with types of unavailability
    $unavailableDates = array();

    // Loop through each booking and calculate unavailable date ranges with types
    foreach ($bookings as $booked) {
        $bookedDateObj = new DateTime($booked['DATE']);
        $prepTime = $booked['preparation_time'];
        $processTime = $booked['processing_time'];
        $bufferTime = $booked['buffer_time']-1;
    
        // Calculate date ranges
        $prepStartDate = clone $bookedDateObj;
        $prepStartDate->sub(new DateInterval("P{$prepTime}D"));
    
        $processEndDate = clone $bookedDateObj;
        $processEndDate->add(new DateInterval("P{$processTime}D"));
    
        $bufferEndDate = clone $processEndDate;
        $bufferEndDate->add(new DateInterval("P{$bufferTime}D"));
    
        // Add unavailable dates with reasons
        $currentDate = $prepStartDate;
        while ($currentDate <= $bufferEndDate) {
            if ($currentDate < $bookedDateObj) {
                $unavailableDates[$currentDate->format('Y-m-d')] = 'Preparation';
            } elseif ($currentDate >= $bookedDateObj && $currentDate < $processEndDate) {
                $unavailableDates[$currentDate->format('Y-m-d')] = 'Processing';
            } else {
                $unavailableDates[$currentDate->format('Y-m-d')] = 'Buffer';
            }
            $currentDate->add(new DateInterval('P1D'));
        }
    }
    // Generate the calendar for the current month
    $calendar = "<table class='table table-bordered'>";

    $prevMonth = new DateTime("$year-$month-01");
    $prevMonth->modify('-1 month');
    $nextMonth = new DateTime("$year-$month-01");
    $nextMonth->modify('+1 month');

    $calendar .= "<center><h2>$monthName $year</h2>";
    $calendar .= "<div class='nav-container'><a class='btn btn-xs prev' href='?month=" . $prevMonth->format('m') . "&year=" . $prevMonth->format('Y') . "'>Prev</a>";
    $calendar .= " <a class='btn btn-xs btn-primary' href='?month=" . date('m') . "&year=" . date('Y') . "'>Current Month</a> ";
    
    $calendar .= "<a class='btn btn-xs next' href='?month=" . $nextMonth->format('m') . "&year=" . $nextMonth->format('Y') . "'>Next</a></div>";


    // Day names row
    $calendar .= "<tr>";
    foreach ($daysOfWeek as $day) {
        $calendar .= "<th class='header'>$day</th>";
    }

    $currentDay = 1;
    $calendar .= "</tr><tr>";

    // Fill the empty cells for the first week
    if ($dayOfWeek > 0) {
        for ($k = 0; $k < $dayOfWeek; $k++) {
            $calendar .= "<td class='empty'></td>";
        }
    }

    // Loop through each day of the month
    while ($currentDay <= $numberDays) {
        if ($dayOfWeek == 7) {
            $dayOfWeek = 0;
            $calendar .= "</tr><tr>";
        }

        $currentDayRel = str_pad($currentDay, 2, "0", STR_PAD_LEFT);
        $date = "$year-$month-$currentDayRel";

        $isUnavailable = isset($unavailableDates[$date]);
        $unavailabilityReason = $isUnavailable ? $unavailableDates[$date] : null;

        $today = ($date == date('Y-m-d')) ? "today" : "";
        if ($date < date('Y-m-d')) {
            $calendar .= "<td><h4>$currentDay</h4> <button class='btn btn-danger btn-xs' disabled><span class='glyphicon glyphicon-remove'></span> N/A</button>";
        } elseif ($isUnavailable) {
            // Different buttons based on unavailability reason
            $calendar .= "<td class='$today'><h4>$currentDay</h4>";
            if ($unavailabilityReason == 'Preparation') {
                $calendar .= "<button class='btn btn-warning btn-xs' disabled><span class='glyphicon glyphicon-time'></span> Preparation</button>";
            } elseif ($unavailabilityReason == 'Processing') {
                $calendar .= "<button class='btn btn-danger btn-xs' disabled><span class='glyphicon glyphicon-lock'></span> Processing</button>";
            } else {
                $calendar .= "<button class='btn btn-info btn-xs' disabled><span class='glyphicon glyphicon-refresh'></span> Buffer</button>";
            }
        } else {
            $calendar .= "<td class='$today'><h4>$currentDay</h4> 
            <a href='book.php?date=" . $date . "' class='btn btn-success btn-xs'> 
                <span class='glyphicon glyphicon-ok'></span> Book Now
            </a>";
        }

        $calendar .= "</td>";
        $currentDay++;
        $dayOfWeek++;
    }

    // Fill the empty cells for the last week
    if ($dayOfWeek != 7) {
        $remainingDays = 7 - $dayOfWeek;
        for ($l = 0; $l < $remainingDays; $l++) {
            $calendar .= "<td class='empty'></td>";
        }
    }

    $calendar .= "</tr>";
    $calendar .= "</table>";

    // Add a legend for the unavailable dates
    $calendar .= "<div class='legend'>
        <h4>Unavailable Date Legend:</h4>
        <ul>
            <li><button class='btn btn-warning btn-xs' disabled><span class='glyphicon glyphicon-time'></span> Preparation</button> Unavailable due to preparation time</li>
            <li><button class='btn btn-danger btn-xs' disabled><span class='glyphicon glyphicon-lock'></span> Processing</button> Unavailable due to processing time</li>
            <li><button class='btn btn-info btn-xs' disabled><span class='glyphicon glyphicon-refresh'></span> Buffer</button> Unavailable due to buffer time</li>
            <li><button class='btn btn-danger btn-xs' disabled><span class='glyphicon glyphicon-remove'></span> N/A</button> Past date (not available for booking)</li>
        </ul>
    </div>";

    echo $calendar;
}
?>

<html lang="en">
  <head>
    <title>Online Booking System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css" />
    <link rel="stylesheet" href="index.css">
    <style>
      /* Custom styles for the calendar and legend */
      .legend {
        margin-top: 20px;
        padding: 10px;
        border: 1px solid #ccc;
        background-color: #f9f9f9;
      }
      .legend ul {
        list-style-type: none;
        padding-left: 0;
      }
      .legend li {
        margin-bottom: 5px;
      }
    </style>
  </head>
  <body>
      <div class="container">
        <div class="row">
          <div class="col-md-12">
            <div class="text-center alert alert-danger" style="background:#1f242d;border:none;color:#fff;">
                <h1>Booking Calendar</h1>
            </div>
                <?php echo isset($message) ? $message : ''; ?>
                <?php 
                    $dateComponents = getdate();
                    if(isset($_GET['month']) && isset($_GET['year'])){
                        $month = $_GET['month'];
                        $year = $_GET['year'];
                    } else {
                        $month = $dateComponents['mon'];
                        $year = $dateComponents['year'];
                    }
                    echo build_calendar($month, $year);
                ?>
          </div>
        </div>
      </div>
  </body>
</html>
