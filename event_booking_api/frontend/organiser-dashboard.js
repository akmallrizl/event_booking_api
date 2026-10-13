const API_BASE = "http://localhost/event_booking_api/api";

// ==============================
// CHECK LOGIN
// ==============================
const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
}

// Only organiser can access
if (user && user.role !== "organiser") {
    alert("Access denied. Organiser only.");
    window.location.href = "login.html";
}

// Display organiser name
document.getElementById("organiserName").textContent = user.name;


// ==============================
// LOGOUT
// ==============================
document.getElementById("logoutBtn").addEventListener("click", function () {

    const confirmLogout = confirm("Are you sure you want to logout?");

    if (confirmLogout) {
        localStorage.removeItem("user");
        window.location.href = "login.html";
    }

});


// ==============================
// LOAD DASHBOARD
// ==============================
async function loadDashboard() {

    try {

        // Get organiser events
        const eventResponse = await fetch(
            `${API_BASE}/events.php?organiser_id=${user.user_id}`
        );

        const eventResult = await eventResponse.json();

        let events = [];

        if (eventResult.success && Array.isArray(eventResult.data)) {
            events = eventResult.data;
        }

        document.getElementById("totalEvents").textContent = events.length;

        displayRecentEvents(events);


        // Get all bookings
        const bookingResponse = await fetch(
            `${API_BASE}/bookings.php`
        );

        const bookingResult = await bookingResponse.json();

        let bookings = [];

        if (bookingResult.success && Array.isArray(bookingResult.data)) {
            bookings = bookingResult.data;
        }


        // Get IDs for organiser's events
        const organiserEventIds = events.map(event =>
            Number(event.event_id)
        );


        // Only bookings belonging to organiser's events
        const organiserBookings = bookings.filter(booking =>
            organiserEventIds.includes(Number(booking.event_id))
        );


        // Total bookings
        document.getElementById("totalBookings").textContent =
            organiserBookings.length;


        // Confirmed bookings
        const confirmedBookings = organiserBookings.filter(
            booking =>
                booking.booking_status &&
                booking.booking_status.toLowerCase() === "confirmed"
        );

        document.getElementById("confirmedBookings").textContent =
            confirmedBookings.length;


        // Revenue only from confirmed bookings
        const totalRevenue = confirmedBookings.reduce(
            (total, booking) => {

                const amount = parseFloat(booking.total_amount) || 0;

                return total + amount;

            },
            0
        );

        document.getElementById("totalRevenue").textContent =
            `RM ${totalRevenue.toFixed(2)}`;

    } catch (error) {

        console.error("Dashboard error:", error);

        document.getElementById("recentEvents").innerHTML =
            "<p>Unable to load dashboard data.</p>";
    }

}


// ==============================
// DISPLAY RECENT EVENTS
// ==============================
function displayRecentEvents(events) {

    const container = document.getElementById("recentEvents");

    if (events.length === 0) {

        container.innerHTML = `
            <p>No events found.</p>
        `;

        return;
    }


    const recentEvents = events.slice(0, 3);

    container.innerHTML = recentEvents.map(event => `

        <div class="event-card">

            <div>

                <span class="status-badge status-${event.status}">
                    ${event.status.toUpperCase()}
                </span>

                <h3>${event.event_name}</h3>

                <p>
                    <strong>Date:</strong>
                    ${event.event_date}
                </p>

                <p>
                    <strong>Venue:</strong>
                    ${event.venue_name || "-"}
                </p>

                <p>
                    <strong>Available Tickets:</strong>
                    ${event.available_tickets}
                </p>

            </div>

        </div>

    `).join("");

}


// ==============================
// START
// ==============================
loadDashboard();