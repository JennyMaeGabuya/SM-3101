# Teacher Dashboard (LearnHub)

This folder contains a simple Teacher Admin Dashboard skeleton for managing quizzes, tasks, activities, learning materials, submissions, and messages.

Quick setup:

1. Import your existing database tables (you indicated they already exist).
2. Place the `teacher` folder inside your project root (it is already added).
3. Ensure the DB connection in `connection/dbsConnection.php` is correct.
4. Visit `http://localhost/LearnHub/teacher/index.php` and log in as a teacher (this skeleton uses `$_SESSION['teacher_id']`).

Notes:
- All DB operations use prepared statements.
- Expand the actions and pages to cover editing/deleting resources and file uploads.
