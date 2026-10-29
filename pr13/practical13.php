
<?php
header("Content-Type: text/html; charset=UTF-8");

$conn = new mysqli("localhost", "root", "", "college1");

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL
)");

// Handle API requests
if (isset($_GET['api'])) {
    header("Content-Type: application/json; charset=UTF-8");

    $action = $_GET['api'];

    // Display students
    if ($action == "list") {
        $result = $conn->query("SELECT * FROM students");

        if (!$result) {
            http_response_code(500);
            echo json_encode(["message" => "Unable to load students"]);
            exit;
        }

        $data = [];

        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        echo json_encode($data);
        exit;
    }

    // Read JSON input
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid JSON data"]);
        exit;
    }

    // Add student
    if ($action == "add") {
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($name == "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Enter a name and valid email"]);
            exit;
        }

        $stmt = $conn->prepare(
            "INSERT INTO students (name, email) VALUES (?, ?)"
        );
        $stmt->bind_param("ss", $name, $email);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Student added successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to add student"]);
        }
        exit;
    }

    // Update student
    if ($action == "update") {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');

        if ($id <= 0 || $name == "" ||
            !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["message" => "Enter valid student details"]);
            exit;
        }

        $stmt = $conn->prepare(
            "UPDATE students SET name = ?, email = ? WHERE id = ?"
        );
        $stmt->bind_param("ssi", $name, $email, $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Student updated successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to update student"]);
        }
        exit;
    }

    // Delete student
    if ($action == "delete") {
        $id = (int)($input['id'] ?? 0);

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(["message" => "Invalid student ID"]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(["message" => "Student deleted successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["message" => "Failed to delete student"]);
        }
        exit;
    }

    http_response_code(400);
    echo json_encode(["message" => "Invalid API request"]);
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Management</title>
</head>
<body>

<h2>Student Management</h2>

<form id="studentForm">
    <input type="hidden" id="id">

    Name:
    <input type="text" id="name" required>

    Email:
    <input type="email" id="email" required>

    <button type="submit">Save Student</button>
</form>

<p id="message"></p>

<h3>Student List</h3>
<p id="loading"></p>

<table border="1" cellpadding="8">
    <thead>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="studentList"></tbody>
</table>

<script>
const form = document.getElementById("studentForm");
const message = document.getElementById("message");
const loading = document.getElementById("loading");

// Handle JSON responses safely
async function apiRequest(url, options = {}) {
    const response = await fetch(url, options);
    const text = await response.text();

    let data;

    try {
        data = JSON.parse(text);
    } catch {
        throw new Error(
            "Invalid server response. Check the PHP filename and errors."
        );
    }

    if (!response.ok) {
        throw new Error(data.message || "Request failed");
    }

    return data;
}

// Display students
async function loadStudents() {
    loading.innerText = "Loading...";

    try {
        const data = await apiRequest("practical13.php?api=list");
        let rows = "";

        data.forEach(student => {
            rows += `
                <tr>
                    <td>${student.id}</td>
                    <td>${escapeHTML(student.name)}</td>
                    <td>${escapeHTML(student.email)}</td>
                    <td>
                        <button type="button"
                            onclick="editStudent(${student.id})">
                            Edit
                        </button>
                        <button type="button"
                            onclick="deleteStudent(${student.id})">
                            Delete
                        </button>
                    </td>
                </tr>`;
        });

        document.getElementById("studentList").innerHTML =
            rows || "<tr><td colspan='4'>No students found</td></tr>";

        loading.innerText = "";
    } catch (error) {
        loading.innerText = error.message;
    }
}

// Prevent student data from being treated as HTML
function escapeHTML(value) {
    return String(value).replace(/[&<>"']/g, char => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
    })[char]);
}

// Add or update student
form.addEventListener("submit", async function(event) {
    event.preventDefault();

    const id = document.getElementById("id").value;

    const student = {
        name: document.getElementById("name").value,
        email: document.getElementById("email").value
    };

    const action = id ? "update" : "add";

    if (id) {
        student.id = Number(id);
    }

    message.innerText = "Saving...";

    try {
        const data = await apiRequest(
            "practical13.php?api=" + action,
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(student)
            }
        );

        message.innerText = data.message;
        form.reset();
        document.getElementById("id").value = "";

        await loadStudents();
    } catch (error) {
        message.innerText = error.message;
    }
});

// Fill form with selected student details
async function editStudent(id) {
    try {
        const students = await apiRequest("practical13.php?api=list");
        const student = students.find(s => Number(s.id) === id);

        if (!student) {
            message.innerText = "Student not found";
            return;
        }

        document.getElementById("id").value = student.id;
        document.getElementById("name").value = student.name;
        document.getElementById("email").value = student.email;

        message.innerText = "Edit student details and click Save Student";
    } catch (error) {
        message.innerText = error.message;
    }
}

// Delete student
async function deleteStudent(id) {
    if (!confirm("Do you want to delete this student?")) {
        return;
    }

    try {
        const data = await apiRequest(
            "practical13.php?api=delete",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({id: id})
            }
        );

        message.innerText = data.message;
        await loadStudents();
    } catch (error) {
        message.innerText = error.message;
    }
}

// Load students when the page opens
loadStudents();
</script>

</body>
</html>
