<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "school";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Sample student data (in real case, this would come from a form)
$name = "John Doe";
$index_number = "STU12345";
$gender = "Male";
$password_plain = "mypassword";

// Hash the password before storing
$hashed_password = password_hash($password_plain, PASSWORD_DEFAULT);

// Insert data
$sql = "INSERT INTO student (name, index_number, gender, password) 
        VALUES ('$name', '$index_number', '$gender', '$hashed_password')";

if ($conn->query($sql) === TRUE) {
    echo "New student record created successfully";
} else {
    echo "Error: " . $sql . "<br>" . $conn->error;
}

$conn->close();
?>
