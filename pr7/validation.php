<?php

$nameErr = "";
$emailErr = "";
$mobileErr = "";
$passwordErr = "";

if (isset($_POST['submit'])) {

    // Name Validation
    if (empty($_POST["name"])) {
        $nameErr = "Name is required";
    }
    else {
        $name = filterdata($_POST["name"]);

        if (!preg_match("/^[a-zA-Z-' ]*$/", $name)) {
            $nameErr = "Only letters and white space allowed";
        }
    }


    // Email Validation
    if (empty($_POST["email"])) {
        $emailErr = "Email is required";
    }
    else {
        $email = filterdata($_POST["email"]);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $emailErr = "Invalid Email Format";
        }
    }


    // Mobile Validation
    if (empty($_POST["phone"])) {
        $mobileErr = "Mobile is required";
    }
    else {
        $mobile = filterdata($_POST["phone"]);

        if (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $mobileErr = "Invalid Mobile Format";
        }
    }


    // Password Validation
    if (empty($_POST["password"])) {
        $passwordErr = "Password is required";
    }
    else {
        $password = filterdata($_POST["password"]);

        if (!preg_match(
            "/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[@#$%^&*!]).{8,}$/",
            $password
        )) {
            $passwordErr =
            "Password must contain 8 characters, uppercase, lowercase, number and special character";
        }
    }
}


function filterdata($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);

    return $data;
}

?>

<html>

<head>
    <title>Form Validation</title>
</head>

<body>

    <h2>Registration Form</h2>

    <form method="post">

        Name:
        <input type="text"
               name="name"
               placeholder="Enter your name">

        <span><?php echo $nameErr; ?></span>

        <br><br>


        Email:
        <input type="email"
               name="email"
               placeholder="Enter your email">

        <span><?php echo $emailErr; ?></span>

        <br><br>


        Mobile:
        <input type="text"
               name="phone"
               placeholder="Enter your mobile number">

        <span><?php echo $mobileErr; ?></span>

        <br><br>


        Password:
        <input type="password"
               name="password"
               placeholder="Enter your password">

        <span><?php echo $passwordErr; ?></span>

        <br><br>


        <input type="submit"
               name="submit"
               value="SUBMIT">

    </form>

</body>

</html>
