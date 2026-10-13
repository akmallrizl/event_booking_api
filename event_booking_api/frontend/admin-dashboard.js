// ==========================================
// ADMIN DASHBOARD
// ==========================================


// ==========================================
// GET LOGGED IN USER
// ==========================================

const user = JSON.parse(localStorage.getItem("user"));


// ==========================================
// CHECK LOGIN
// ==========================================

if (!user) {
    window.location.href = "login.html";
}


// ==========================================
// CHECK ADMIN ROLE
// ==========================================

if (user && user.role !== "admin") {
    window.location.href = "events.html";
}


// ==========================================
// DISPLAY ADMIN NAME
// ==========================================

const adminName = document.getElementById("adminName");

if (adminName && user) {
    adminName.textContent = user.name;
}


// ==========================================
// API URLS
// ==========================================

const EVENTS_API =
    "http://localhost/event_booking_api/api/events.php";

const USERS_API =
    "http://localhost/event_booking_api/api/users.php";

const BOOKINGS_API =
    "http://localhost/event_booking_api/api/bookings.php";


// ==========================================
// DISPLAY ERROR
// ==========================================

function showError(message) {

    const errorBox =
        document.getElementById("errorMessage");

    if (!errorBox) return;

    errorBox.textContent = message;
    errorBox.style.display = "block";
}


// ==========================================
// LOAD DASHBOARD
// ==========================================

async function loadDashboard() {

    try {

        // ==================================
        // GET EVENTS
        // ==================================

        const eventsResponse =
            await fetch(EVENTS_API);

        const eventsResult =
            await eventsResponse.json();


        // ==================================
        // GET USERS
        // ==================================

        const usersResponse =
            await fetch(USERS_API);

        const usersResult =
            await usersResponse.json();


        // ==================================
        // GET BOOKINGS
        // ==================================

        const bookingsResponse =
            await fetch(BOOKINGS_API);

        const bookingsResult =
            await bookingsResponse.json();


        // ==================================
        // EVENTS COUNT
        // ==================================

        let events = [];

        if (
            eventsResponse.ok &&
            eventsResult.success &&
            Array.isArray(eventsResult.data)
        ) {
            events = eventsResult.data;
        }


        // ==================================
        // USERS COUNT
        // ==================================

        let users = [];

        if (
            usersResponse.ok &&
            usersResult.success &&
            Array.isArray(usersResult.data)
        ) {
            users = usersResult.data;
        }


        // ==================================
        // BOOKINGS COUNT
        // ==================================

        let bookings = [];

        if (
            bookingsResponse.ok &&
            bookingsResult.success &&
            Array.isArray(bookingsResult.data)
        ) {
            bookings = bookingsResult.data;
        }


        // ==================================
        // CONFIRMED BOOKINGS
        // ==================================

        const confirmedBookings =
            bookings.filter(
                booking =>
                    booking.booking_status === "confirmed"
            );


        // ==================================
        // DISPLAY STATISTICS
        // ==================================

        document.getElementById("totalEvents")
            .textContent = events.length;

        document.getElementById("totalUsers")
            .textContent = users.length;

        document.getElementById("totalBookings")
            .textContent = bookings.length;

        document.getElementById("confirmedBookings")
            .textContent = confirmedBookings.length;

    }

    catch (error) {

        console.error(
            "Dashboard Error:",
            error
        );

        showError(
            "Unable to load dashboard data."
        );
    }
}


// ==========================================
// LOGOUT
// ==========================================

const logoutBtn =
    document.getElementById("logoutBtn");

if (logoutBtn) {

    logoutBtn.addEventListener(
        "click",
        function () {

            localStorage.removeItem("user");

            window.location.href =
                "login.html";
        }
    );
}


// ==========================================
// START
// ==========================================

loadDashboard();