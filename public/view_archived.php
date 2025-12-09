<?php include "header.php"; ?>

<div class="container">
    <h2>Archived Schedules</h2>

    <div id="archivedList" class="schedule-grid"></div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", loadArchived);

    async function loadArchived() {
        const list = document.getElementById("archivedList");
        list.innerHTML = "<p>Loading...</p>";

        const data = await fetch("backend/get_archived.php").then(r => r.json());

        if (data.length === 0) {
            list.innerHTML = "<p>No archived schedules found.</p>";
            return;
        }

        list.innerHTML = "";
        data.forEach(item => {
            list.appendChild(createCard(item));
        });
    }

    function createCard(item) {
        const div = document.createElement("div");
        div.className = "schedule-card";
        div.dataset.id = item.id;

        div.innerHTML = `
        <img src="${item.instructor_image ? item.instructor_image : 'assets/img/default.png'}" class="card-img">
        
        <h3>${item.class_name}</h3>
        <p><strong>Instructor:</strong> ${item.instructor_name}</p>
        <p><strong>Email:</strong> ${item.instructor_email}</p>
        <p><strong>Course Code:</strong> ${item.course_code}</p>

        <span class="status archived">Archived</span>

        <div class="card-actions">
            <button class="btn-restore" onclick="restore(${item.id})">Restore</button>
        </div>
    `;

        return div;
    }

    async function restore(id) {
        const res = await fetch("backend/update_status.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                id,
                status: "active"
            }),
        }).then(r => r.json());

        alert(res.message);
        if (res.success) location.reload();
    }
</script>

<style>
    .schedule-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
        gap: 15px;
    }

    .schedule-card {
        background: #fff;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        text-align: center;
    }

    .schedule-card img {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        object-fit: cover;
    }

    .status.archived {
        background: #888;
        color: white;
        padding: 5px 10px;
        border-radius: 5px;
    }

    .card-actions {
        margin-top: 10px;
    }

    .btn-restore {
        background: #4CAF50;
        color: white;
        padding: 8px 12px;
        border-radius: 5px;
    }
</style>