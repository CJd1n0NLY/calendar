<?php
function build_calendar($month, $year) {
    $mysqli = new mysqli('localhost', 'root', '', 'bookingsystem');
    
    // Define processing, preparation, and buffer time
    $processTime = 14;
    $prepTime = 3;     
    $bufferTime = 3;    

    // Fetch all bookings regardless of the month
    $stmt = $mysqli->prepare("SELECT * FROM bookings_record");
    $bookings = array();
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bookings[] = $row['DATE'];
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

    // Array to hold unavailable date ranges dynamically
    $unavailableDates = array();

    // Loop through each booking and calculate unavailable date ranges
    foreach ($bookings as $bookedDate) {
        $bookedDateObj = new DateTime($bookedDate);

        // Calculate the unavailable date range for this booking
        $prepStartDate = clone $bookedDateObj;
        $prepStartDate->sub(new DateInterval("P{$prepTime}D"));

        $processEndDate = clone $bookedDateObj;
        $processEndDate->add(new DateInterval("P{$processTime}D"));

        $bufferEndDate = clone $processEndDate;
        $bufferEndDate->add(new DateInterval("P{$bufferTime}D"));

        // Add the entire range of unavailable dates into the unavailableDates array
        $currentDate = $prepStartDate;
        while ($currentDate <= $bufferEndDate) {
            $unavailableDates[] = $currentDate->format('Y-m-d');
            $currentDate->add(new DateInterval('P1D'));
        }
    }

    // Remove duplicates from the unavailableDates array (to save memory)
    $unavailableDates = array_unique($unavailableDates);

    // Generate the calendar for the current month
    $calendar = "<table class='table table-bordered'>";
    $calendar .= "<center><h2>$monthName $year</h2>";
    $calendar .= "<a class='btn btn-xs btn-success' href='?month=" . date('m', mktime(0, 0, 0, $month - 1, 1, $year)) . "&year=" . date('Y', mktime(0, 0, 0, $month - 1, 1, $year)) . "'>Previous Month</a> ";
    $calendar .= " <a class='btn btn-xs btn-danger' href='?month=" . date('m') . "&year=" . date('Y') . "'>Current Month</a> ";
    $calendar .= "<a class='btn btn-xs btn-primary' href='?month=" . date('m', mktime(0, 0, 0, $month + 1, 1, $year)) . "&year=" . date('Y', mktime(0, 0, 0, $month + 1, 1, $year)) . "'>Next Month</a></center><br>";

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

        $isUnavailable = in_array($date, $unavailableDates);

        $today = ($date == date('Y-m-d')) ? "today" : "";
        if ($date < date('Y-m-d')) {
            $calendar .= "<td><h4>$currentDay</h4> <button class='btn btn-danger btn-xs' disabled>N/A</button>";
        } elseif ($isUnavailable) {
            // Mark unavailable dates with a specific reason
            $calendar .= "<td class='$today'><h4>$currentDay</h4> 
            <button class='btn btn-danger btn-xs' title='Unavailable due to processing, preparation or buffer time'>
                <span class='glyphicon glyphicon-lock'></span> Unavailable
            </button>";
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
            <li><span class='glyphicon glyphicon-lock' style='color: red;'></span> Unavailable due to processing, preparation or buffer time</li>
            <li><button class='btn btn-danger btn-xs' disabled>N/A</button> Past date (not available for booking)</li>
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
            <div class="alert alert-danger" style="background:#2ecc71;border:none;color:#fff;">
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
