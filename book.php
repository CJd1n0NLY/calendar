<?php
$errors = []; // Initialize an array to hold errors

if (isset($_GET['date'])){
    $date = $_GET['date'];
}

$conn = new mysqli('localhost', 'root', '', 'bookingsystem');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$servicesQuery = "SELECT * FROM services";
$servicesResult = $conn->query($servicesQuery);
if (!$servicesResult) {
    die("Error fetching services: " . $conn->error);
}else {
    function formatTime($days) {
        $weeks = floor($days / 7);
        $remainingDays = $days % 7;
        return $weeks > 0 ? $weeks . " week(s) " . $remainingDays . " day(s)" : $remainingDays . " day(s)";
    }
}

if (isset($_POST['submit'])) {
    // Extracting form fields
    $fname = $_POST['FIRSTNAME'];
    $mname = $_POST['MIDDLENAME'];
    $lname = $_POST['LASTNAME'];
    $phone = $_POST['PHONE'];
    $email = $_POST['EMAIL'];
    $serviceId = $_POST['SERVICE_ID'];

    // Basic validation
    if (empty($fname)) $errors['FIRSTNAME'] = 'First name is required';
    if (empty($mname)) $errors['MIDDLENAME'] = 'Middle name is required';
    if (empty($lname)) $errors['LASTNAME'] = 'Last name is required';
    if (empty($phone)) $errors['PHONE'] = 'Phone number is required';
    if (empty($email)) $errors['EMAIL'] = 'Email is required';
    if (empty($serviceId)) $errors['SERVICE_ID'] = 'Service selection is required';

    if (empty($errors)) {
        // Fetch service details
        $serviceQuery = $conn->prepare("SELECT processing_time, preparation_time, buffer_time FROM services WHERE id = ?");
        $serviceQuery->bind_param("i", $serviceId);
        $serviceQuery->execute();
        $serviceResult = $serviceQuery->get_result();

        if ($serviceResult->num_rows > 0) {
            $service = $serviceResult->fetch_assoc();

            $prepTime = $service['preparation_time'];
            $processTime = $service['processing_time'];
            $bufferTime = $service['buffer_time'];

            // Calculate unavailable date range for new booking
            $bookingDate = new DateTime($date);
            $prepStartDate = clone $bookingDate;
            $prepStartDate->sub(new DateInterval("P{$prepTime}D"));
            $processEndDate = clone $bookingDate;
            $processEndDate->add(new DateInterval("P{$processTime}D"));
            $bufferEndDate = clone $processEndDate;
            $bufferEndDate->add(new DateInterval("P{$bufferTime}D"));

            // Check for conflicting bookings
            $overlapQuery = $conn->prepare("
                SELECT bookings_record.DATE, services.processing_time, services.preparation_time, services.buffer_time
                FROM bookings_record
                JOIN services ON bookings_record.SERVICE_ID = services.id
                WHERE (
                    bookings_record.DATE BETWEEN ? AND ?
                    OR DATE_ADD(bookings_record.DATE, INTERVAL services.processing_time DAY) BETWEEN ? AND ?
                    OR DATE_ADD(bookings_record.DATE, INTERVAL services.buffer_time DAY) BETWEEN ? AND ?
                )
            ");
            $start_date = $prepStartDate->format('Y-m-d');
            $end_date = $bufferEndDate->format('Y-m-d');

            $overlapQuery->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
            $overlapQuery->execute();
            $overlapResult = $overlapQuery->get_result();

            if ($overlapResult->num_rows > 0) {
                $conflictingDates = [];
                while ($row = $overlapResult->fetch_assoc()) {
                    $conflictingDates[] = $row['DATE'];
                }
                $errors['conflict'] = "Booking conflicts with existing schedule on the following dates: " . implode(', ', $conflictingDates) . " </br>Please try booking on another date.";
            } else {
                // Proceed with booking if no conflicts
                $stmt = $conn->prepare("
                    INSERT INTO bookings_record (FIRSTNAME, MIDDLENAME, LASTNAME, PHONE, EMAIL, DATE, SERVICE_ID) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssssssi", $fname, $mname, $lname, $phone, $email, $date, $serviceId);

                if ($stmt->execute()) {
                    echo "<script>
                        alert('Booking successful!');
                        window.location.href = 'index.php';
                    </script>";
                } else {
                    $errors['booking'] = 'Booking failed, please try again.';
                }
                
                $stmt->close();
            }
            $overlapQuery->close();
        } else {
            $errors['service'] = 'Invalid service selected.';
        }

        $serviceQuery->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Form</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css"/>
</head>
<body>
    <div class="container">
        <h1 class="text-center alert alert-danger" style="background:#1f242d;border:none;color:#fff;">Book for Date: <?php echo $date ?></h1>
        <div class="row">
            <div class="col-md-12">
                <form action="" method="POST" autocomplete="off">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul>
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for=""> FIRST NAME</label>
                        <input type="text" class="form-control" name="FIRSTNAME">
                        <?php if (isset($errors['FIRSTNAME'])): ?>
                            <div class="text-danger"><?php echo $errors['FIRSTNAME']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for=""> MIDDLE NAME</label>
                        <input type="text" class="form-control" name="MIDDLENAME">
                        <?php if (isset($errors['MIDDLENAME'])): ?>
                            <div class="text-danger"><?php echo $errors['MIDDLENAME']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for=""> LAST NAME</label>
                        <input type="text" class="form-control" name="LASTNAME">
                        <?php if (isset($errors['LASTNAME'])): ?>
                            <div class="text-danger"><?php echo $errors['LASTNAME']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for=""> PHONE NUMBER</label>
                        <input type="number" class="form-control" name="PHONE" >
                        <?php if (isset($errors['PHONE'])): ?>
                            <div class="text-danger"><?php echo $errors['PHONE']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for=""> EMAIL</label>
                        <input type="email" class="form-control" name="EMAIL" >
                        <?php if (isset($errors['EMAIL'])): ?>
                            <div class="text-danger"><?php echo $errors['EMAIL']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="SERVICE_ID">Select Service</label>
                        <select name="SERVICE_ID" class="form-control" id="serviceSelect" required>\
                            <option value="">--Select a Service--</option>
                            <?php while ($service = $servicesResult->fetch_assoc()): ?>
                                <?php 
                                    $formattedPrepTime = formatTime($service['preparation_time']);
                                    $formattedProcessTime = formatTime($service['processing_time']);
                                    $formattedBufferTime = formatTime($service['buffer_time']);
                                ?>
                                <option value="<?php echo $service['id']; ?>" 
                                        data-price="<?php echo $service['price']; ?>" 
                                        data-description="<?php echo $service['description']; ?>"
                                        data-formatted-processing-time="<?php echo $formattedProcessTime; ?>"
                                        data-formatted-preparation-time="<?php echo $formattedPrepTime; ?>"
                                        data-formatted-buffer-time="<?php echo $formattedBufferTime; ?>">
                                    <?php echo $service['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <?php if (isset($errors['SERVICE_ID'])): ?>
                            <div class="text-danger"><?php echo $errors['SERVICE_ID']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div id="serviceDetails" style="display: none;">
                        <p><strong>Price:</strong> <span id="servicePrice"></span></p>
                        <p><strong>Description:</strong> <span id="serviceDescription"></span></p>
                        <p><strong>Processing Time:</strong> <span id="serviceProcessingTime"></span></p>
                        <p><strong>Preparation Time:</strong> <span id="servicePreparationTime"></span></p>
                        <p><strong>Post-Process Time:</strong> <span id="serviceBufferTime"></span></p>
                    </div>


                    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                    <a href="index.php" class="btn btn-success">Back</a>
                </form>
            </div>
        </div>
    </div>


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#serviceSelect').change(function() {
                var selectedOption = $(this).find('option:selected');
                var price = selectedOption.data('price');
                var description = selectedOption.data('description');
                
                // Add data attributes for the formatted times
                var formattedProcessingTime = selectedOption.data('formatted-processing-time');  // Formatted processing time
                var formattedPreparationTime = selectedOption.data('formatted-preparation-time');  // Formatted preparation time
                var formattedBufferTime = selectedOption.data('formatted-buffer-time');  // Formatted buffer time

                // Display price, description, and formatted times
                if (price && description) {
                    $('#servicePrice').text(price);
                    $('#serviceDescription').text(description);
                    $('#serviceProcessingTime').text(formattedProcessingTime);
                    $('#servicePreparationTime').text(formattedPreparationTime);
                    $('#serviceBufferTime').text(formattedBufferTime);
                    $('#serviceDetails').show(); // Show the details
                } else {
                    $('#serviceDetails').hide(); // Hide if no price or description
                }
            });

            // Trigger change event when page loads to show the initial service details (if any)
            $(window).on('load', function() {
                $('#serviceSelect').trigger('change');
            });
        });


    </script>


    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</body>
</html>
