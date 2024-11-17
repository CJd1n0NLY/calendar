<?php 

if (isset($_GET['date'])){
    $date = $_GET['date'];
}

if (isset($_POST['submit'])) {
    $fname = $_POST['FIRSTNAME'];
    $mname = $_POST['MIDDLENAME'];
    $lname = $_POST['LASTNAME'];
    $phone = $_POST['PHONE'];
    $email = $_POST['EMAIL'];

    $conn = new mysqli('localhost', 'root', '', 'bookingsystem');
    
    // Prepare SQL statement
    $stmt = $conn->prepare("INSERT INTO bookings_record (FIRSTNAME, MIDDLENAME, LASTNAME, PHONE, EMAIL, DATE) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $fname, $mname, $lname, $phone, $email, $date);
    
    // Execute the statement
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Booking Successful</div>";
        header("Location: index.php");
        exit();
    } else {
        $message = "<div class='alert alert-danger'>Booking was not Successful</div>";
    }

    // Close the statement and connection
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Form</title>
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet"href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.0/css/bootstrap.min.css"/>
</head>
<body>
    <div class="container">
        <h1 class="text-center alert alert-danger" style="background:#2ecc71;border:none;color:#fff;">Book for Date: </h1>
        <div class="row">
            <div class="col-md-12">
                <form action="" method="POST" autocomplete="off">
                    <div class="form-group">
                        <label for=""> FIRST NAME</label>
                        <input type="text" class="form-control" name="FIRSTNAME" required>
                    </div>

                    <div class="form-group">
                        <label for=""> MIDDLE NAME</label>
                        <input type="text" class="form-control" name="MIDDLENAME" required>
                    </div>

                    <div class="form-group">
                        <label for=""> LAST NAME</label>
                        <input type="text" class="form-control" name="LASTNAME" required>
                    </div>

                    <div class="form-group">
                        <label for=""> PHONE NUMBER</label>
                        <input type="number" class="form-control" name="PHONE" required>
                    </div>

                    <div class="form-group">
                        <label for=""> EMAIL</label>
                        <input type="email" class="form-control" name="EMAIL" required>
                    </div>

                    <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                    <a href="index.php" class="btn btn-success">Back</a>
                </form>
            </div>
        </div>
    </div>


    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

</body>
</html>