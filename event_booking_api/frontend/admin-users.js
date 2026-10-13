const API_URL = "http://localhost/event_booking_api/api/users.php";

let editingUserId = null;


// =====================================================
// CHECK ADMIN LOGIN
// =====================================================

document.addEventListener("DOMContentLoaded", () => {

    const user = JSON.parse(localStorage.getItem("user"));

    if (!user) {
        window.location.href = "login.html";
        return;
    }

    if (user.role !== "admin") {
        window.location.href = "events.html";
        return;
    }

    const adminName = document.getElementById("adminName");
    const adminRole = document.getElementById("adminRole");

    if (adminName) {
        adminName.textContent = user.name || "System Administrator";
    }

    if (adminRole) {
        adminRole.textContent = "Admin";
    }

    loadUsers();
});


// =====================================================
// LOAD ALL USERS
// =====================================================

async function loadUsers() {

    const usersGrid = document.getElementById("usersGrid");

    usersGrid.innerHTML = "<p>Loading users...</p>";

    try {

        const response = await fetch(API_URL);

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || "Failed to load users");
        }

        const users = result.data || [];

        if (users.length === 0) {

            usersGrid.innerHTML = `
                <p>No users found.</p>
            `;

            return;
        }

        usersGrid.innerHTML = "";

        users.forEach(user => {

            const card = document.createElement("div");

            card.className = "user-card";

            const role = String(user.role || "").toLowerCase();

            let roleClass = "role-customer";

            if (role === "admin") {
                roleClass = "role-admin";
            }

            if (role === "organiser") {
                roleClass = "role-organiser";
            }

            card.innerHTML = `

                <div class="user-card-header">

                    <span class="role-badge ${roleClass}">
                        ${escapeHTML(role)}
                    </span>

                    <small>
                        User #${user.user_id}
                    </small>

                </div>


                <h3>
                    ${escapeHTML(user.name)}
                </h3>


                <div class="user-info">

                    <small>Email</small>

                    <strong>
                        ${escapeHTML(user.email)}
                    </strong>

                </div>


                <div class="user-info">

                    <small>Role</small>

                    <strong>
                        ${escapeHTML(role)}
                    </strong>

                </div>


                <div class="user-info">

                    <small>Created At</small>

                    <strong>
                        ${escapeHTML(user.created_at || "-")}
                    </strong>

                </div>


                <div class="user-actions">

                    <button
                        class="edit-btn"
                        onclick="editUser(${user.user_id})">

                        Edit

                    </button>


                    <button
                        class="delete-btn"
                        onclick="deleteUser(${user.user_id})">

                        Delete

                    </button>

                </div>
            `;

            usersGrid.appendChild(card);
        });

    } catch (error) {

        console.error(error);

        usersGrid.innerHTML = `
            <p style="color:red;">
                ${escapeHTML(error.message)}
            </p>
        `;
    }
}


// =====================================================
// SHOW ADD USER FORM
// =====================================================

function showAddUserForm() {

    editingUserId = null;

    document.getElementById("formTitle").textContent =
        "Add New User";

    document.getElementById("userId").value = "";

    document.getElementById("name").value = "";

    document.getElementById("email").value = "";

    document.getElementById("password").value = "";

    document.getElementById("role").value = "";

    document.getElementById("passwordGroup").style.display =
        "block";

    document.getElementById("password").required = true;

    document.getElementById("userFormSection").style.display =
        "block";

    document.getElementById("userFormSection")
        .scrollIntoView({
            behavior: "smooth"
        });
}


// =====================================================
// CLOSE FORM
// =====================================================

function closeUserForm() {

    editingUserId = null;

    document.getElementById("userForm").reset();

    document.getElementById("userId").value = "";

    document.getElementById("userFormSection").style.display =
        "none";
}


// =====================================================
// ADD / UPDATE USER
// =====================================================

document.getElementById("userForm").addEventListener(
    "submit",
    async function(event) {

        event.preventDefault();

        const name =
            document.getElementById("name").value.trim();

        const email =
            document.getElementById("email").value.trim();

        const password =
            document.getElementById("password").value;

        const role =
            document.getElementById("role").value;


        let method = "POST";

        let url = API_URL;

        let body = {
            name: name,
            email: email,
            role: role
        };


        // ADD USER
        if (!editingUserId) {

            body.password = password;

        }

        // EDIT USER
        else {

            method = "PUT";

            url =
                API_URL +
                "?id=" +
                encodeURIComponent(editingUserId);
        }


        try {

            const response = await fetch(url, {

                method: method,

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify(body)
            });


            const result = await response.json();


            if (!response.ok || !result.success) {

                throw new Error(
                    result.message ||
                    "Operation failed"
                );
            }


            if (editingUserId) {

                showMessage(
                    "User updated successfully.",
                    "success"
                );

            } else {

                showMessage(
                    "User created successfully.",
                    "success"
                );
            }


            closeUserForm();

            loadUsers();


        } catch (error) {

            console.error(error);

            showMessage(
                error.message,
                "error"
            );
        }
    }
);


// =====================================================
// EDIT USER
// =====================================================

async function editUser(id) {

    try {

        const response = await fetch(
            API_URL +
            "?id=" +
            encodeURIComponent(id)
        );

        const result = await response.json();


        if (!response.ok || !result.success) {

            throw new Error(
                result.message ||
                "Failed to load user"
            );
        }


        const user = result.data;


        editingUserId = user.user_id;


        document.getElementById("formTitle").textContent =
            "Edit User";


        document.getElementById("userId").value =
            user.user_id;


        document.getElementById("name").value =
            user.name;


        document.getElementById("email").value =
            user.email;


        document.getElementById("role").value =
            user.role;


        // Password tak perlu masa edit
        document.getElementById("passwordGroup").style.display =
            "none";

        document.getElementById("password").required =
            false;


        document.getElementById("userFormSection").style.display =
            "block";


        document.getElementById("userFormSection")
            .scrollIntoView({
                behavior: "smooth"
            });


    } catch (error) {

        console.error(error);

        showMessage(
            error.message,
            "error"
        );
    }
}


// =====================================================
// DELETE USER
// =====================================================

async function deleteUser(id) {

    const currentUser =
        JSON.parse(localStorage.getItem("user"));


    // Prevent admin delete account sendiri
    if (
        currentUser &&
        Number(currentUser.user_id) === Number(id)
    ) {

        alert(
            "You cannot delete your own admin account."
        );

        return;
    }


    const confirmation = confirm(
        "Are you sure you want to delete this user?"
    );


    if (!confirmation) {
        return;
    }


    try {

        const response = await fetch(
            API_URL +
            "?id=" +
            encodeURIComponent(id),
            {
                method: "DELETE"
            }
        );


        const result = await response.json();


        if (!response.ok || !result.success) {

            throw new Error(
                result.message ||
                "Failed to delete user"
            );
        }


        showMessage(
            "User deleted successfully.",
            "success"
        );


        loadUsers();


    } catch (error) {

        console.error(error);

        showMessage(
            error.message,
            "error"
        );
    }
}


// =====================================================
// MESSAGE
// =====================================================

function showMessage(message, type) {

    const box =
        document.getElementById("messageBox");


    box.style.display =
        "block";


    box.textContent =
        message;


    box.style.padding =
        "12px";


    box.style.marginBottom =
        "20px";


    box.style.borderRadius =
        "6px";


    box.style.textAlign =
        "center";


    box.style.fontWeight =
        "600";


    if (type === "success") {

        box.style.background =
            "#e2f5e8";

        box.style.color =
            "#167c35";

        box.style.border =
            "1px solid #bfe5ca";

    } else {

        box.style.background =
            "#fde8e8";

        box.style.color =
            "#d92d20";

        box.style.border =
            "1px solid #f7c7c7";
    }


    setTimeout(() => {

        box.style.display =
            "none";

    }, 4000);
}


// =====================================================
// LOGOUT
// =====================================================

function logout() {

    localStorage.removeItem("user");

    window.location.href =
        "login.html";
}


// =====================================================
// SECURITY - ESCAPE HTML
// =====================================================

function escapeHTML(value) {

    if (
        value === null ||
        value === undefined
    ) {
        return "";
    }


    return String(value)

        .replaceAll("&", "&amp;")

        .replaceAll("<", "&lt;")

        .replaceAll(">", "&gt;")

        .replaceAll('"', "&quot;")

        .replaceAll("'", "&#039;");
}