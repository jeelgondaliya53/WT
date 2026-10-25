<?php

$conn = mysqli_connect("localhost", "root", "", "college1");

if (!$conn) {
    die("Database connection failed");
}

$msg = "";

/* ADD / UPDATE */
if (isset($_POST['save'])) {

    $name = $_POST['name'];
    $email = $_POST['email'];
    $course = $_POST['course'];
    $id = $_POST['id'];

    if ($id == "") {

        $sql = "INSERT INTO students(name,email,course)
                VALUES('$name','$email','$course')";

        if (mysqli_query($conn, $sql))
            $msg = "Student added successfully!";
        else
            $msg = "Failed to add student.";

    } else {

        $sql = "UPDATE students
                SET name='$name', email='$email', course='$course'
                WHERE id=$id";

        if (mysqli_query($conn, $sql))
            $msg = "Student updated successfully!";
        else
            $msg = "Failed to update student.";
    }
}


/* DELETE */
if (isset($_GET['delete'])) {

    $id = $_GET['delete'];

    if (mysqli_query($conn, "DELETE FROM students WHERE id=$id"))
        $msg = "Student deleted successfully!";
    else
        $msg = "Failed to delete student.";
}


/* EDIT - GET OLD DATA */
$edit = null;

if (isset($_GET['edit'])) {

    $id = $_GET['edit'];

    $result = mysqli_query($conn,
        "SELECT * FROM students WHERE id=$id");

    $edit = mysqli_fetch_assoc($result);
}


/* SEARCH AND FILTER */

$search = isset($_GET['search']) ? $_GET['search'] : "";
$course = isset($_GET['course']) ? $_GET['course'] : "";

$sql = "SELECT * FROM students WHERE
        (name LIKE '%$search%'
        OR email LIKE '%$search%')";

if ($course != "") {
    $sql .= " AND course='$course'";
}

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Student Management</title>
</head>

<body>

<h2>Student Management System</h2>

<p><b><?php echo $msg; ?></b></p>


<!-- ADD / EDIT FORM -->

<h3><?php echo $edit ? "Edit Student" : "Add Student"; ?></h3>

<form method="post">

    <input type="hidden" name="id"
           value="<?php echo $edit['id'] ?? ''; ?>">

    Name:
    <input type="text" name="name"
           value="<?php echo $edit['name'] ?? ''; ?>"
           required>

    Email:
    <input type="email" name="email"
           value="<?php echo $edit['email'] ?? ''; ?>"
           required>

    Course:
    <select name="course">

        <option value="BCA"
        <?php if (($edit['course'] ?? '') == "BCA") echo "selected"; ?>>
        BCA
        </option>

        <option value="BSc IT"
        <?php if (($edit['course'] ?? '') == "BSc IT") echo "selected"; ?>>
        BSc IT
        </option>

    </select>

    <input type="submit" name="save"
           value="<?php echo $edit ? 'Update' : 'Add'; ?>">

</form>


<hr>


<!-- SEARCH AND FILTER -->

<h3>Search / Filter</h3>

<form method="get">

    <input type="text"
           name="search"
           placeholder="Name or Email"
           value="<?php echo $search; ?>">

    <select name="course">

        <option value="">All Courses</option>

        <option value="BCA"
        <?php if ($course == "BCA") echo "selected"; ?>>
        BCA
        </option>

        <option value="BSc IT"
        <?php if ($course == "BSc IT") echo "selected"; ?>>
        BSc IT
        </option>

    </select>

    <input type="submit" value="Search">

    <a href="student.php">Show All</a>

</form>


<br>


<!-- STUDENT TABLE -->

<table border="1" cellpadding="8">

<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Email</th>
    <th>Course</th>
    <th>Action</th>
</tr>

<?php

while ($row = mysqli_fetch_assoc($result)) {

?>

<tr>

    <td><?php echo $row['id']; ?></td>

    <td><?php echo $row['name']; ?></td>

    <td><?php echo $row['email']; ?></td>

    <td><?php echo $row['course']; ?></td>

    <td>

        <a href="student.php?edit=<?php echo $row['id']; ?>">
            Edit
        </a>

        |

        <a href="student.php?delete=<?php echo $row['id']; ?>"
           onclick="return confirm('Are you sure you want to delete this student?');">
            Delete
        </a>

    </td>

</tr>

<?php
}
?>

</table>

</body>
</html>