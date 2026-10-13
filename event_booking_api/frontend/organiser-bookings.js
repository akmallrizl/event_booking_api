const API_BASE = "http://localhost/event_booking_api/api";

let currentUser = null;
let myEvents = [];
let allBookings = [];


// =====================================================
// START PAGE
// =====================================================

document.addEventListener("DOMContentLoaded", async () => {

    const userData = localStorage.getItem("user");

    if (!userData) {
        window.location.href = "login.html";
        return;
    }

    try {

        currentUser = JSON.parse(userData);

    } catch (error) {

        localStorage.removeItem("user");
        window.location.href = "login.html";
        return;
    }


    // Only organiser
    if (currentUser.role !== "organiser") {

        alert("Access denied.");

        window.location.href = "login.html";
        return;
    }


    document.getElementById("organiserName").textContent =
        currentUser.name;


    document.getElementById("logoutBtn")
        .addEventListener("click", logout);


    document.getElementById("eventFilter")
        .addEventListener("change", displayBookings);


    document.getElementById("statusFilter")
        .addEventListener("change", displayBookings);


    await loadOrganiserEvents();

});


// =====================================================
// LOAD ORGANISER EVENTS
// =====================================================

async function loadOrganiserEvents() {

    try {

        const response = await fetch(
            `${API_BASE}/events.php?organiser_id=${currentUser.user_id}`
        );

        const result = await response.json();


        if (!result.success) {

            showMessage(
                result.message || "Failed to load events.",
                "error"
            );

            return;
        }


        myEvents = result.data || [];


        populateEventFilter();


        await loadBookings();


    } catch (error) {

        console.error(error);

        showMessage(
            "Unable to connect to the server.",
            "error"
        );
    }
}


// =====================================================
// EVENT FILTER
// =====================================================

function populateEventFilter() {

    const select =
        document.getElementById("eventFilter");


    select.innerHTML =
        `<option value="">All My Events</option>`;


    myEvents.forEach(event => {

        const option =
            document.createElement("option");


        option.value = event.event_id;

        option.textContent =
            `${event.event_name} (Event #${event.event_id})`;


        select.appendChild(option);
    });
}


// =====================================================
// LOAD ALL BOOKINGS
// =====================================================

async function loadBookings() {

    try {

        const response =
            await fetch(`${API_BASE}/bookings.php`);


        const result =
            await response.json();


        if (!result.success) {

            showMessage(
                result.message || "Failed to load bookings.",
                "error"
            );

            return;
        }


        const bookings =
            result.data || [];


        // Get IDs of events belonging to this organiser
        const myEventIds =
            myEvents.map(event =>
                Number(event.event_id)
            );


        // Only bookings for organiser's events
        allBookings =
            bookings.filter(booking =>
                myEventIds.includes(
                    Number(booking.event_id)
                )
            );


        displayBookings();


    } catch (error) {

        console.error(error);

        showMessage(
            "Unable to load bookings.",
            "error"
        );
    }
}


// =====================================================
// DISPLAY BOOKINGS
// =====================================================

function displayBookings() {

    const container =
        document.getElementById("bookingsContainer");


    const eventId =
        document.getElementById("eventFilter").value;


    const status =
        document.getElementById("statusFilter").value;


    let filteredBookings =
        [...allBookings];


    // Filter event
    if (eventId) {

        filteredBookings =
            filteredBookings.filter(booking =>
                Number(booking.event_id) ===
                Number(eventId)
            );
    }


    // Filter status
    if (status) {

        filteredBookings =
            filteredBookings.filter(booking =>
                booking.booking_status === status
            );
    }


    updateStats(filteredBookings);


    if (filteredBookings.length === 0) {

        container.innerHTML = `
            <div class="empty-bookings">
                <h3>No Bookings Found</h3>
                <p>
                    There are currently no customer bookings
                    matching this filter.
                </p>
            </div>
        `;

        return;
    }


    container.innerHTML =
        filteredBookings
            .map(createBookingCard)
            .join("");
}


// =====================================================
// CREATE BOOKING CARD
// =====================================================

function createBookingCard(booking) {

    const statusClass =
        booking.booking_status === "confirmed"
            ? "confirmed"
            : booking.booking_status === "cancelled"
                ? "cancelled"
                : "pending";


    return `

        <div class="organiser-booking-card">

            <div class="booking-card-top">

                <span class="booking-status ${statusClass}">
                    ${escapeHtml(
                        booking.booking_status.toUpperCase()
                    )}
                </span>

                <span class="booking-number">
                    Booking #${booking.booking_id}
                </span>

            </div>


            <h3>
                ${escapeHtml(booking.event_name)}
            </h3>


            <div class="booking-customer">

                <strong>
                    ${escapeHtml(booking.customer_name)}
                </strong>

                <span>
                    ${escapeHtml(booking.customer_email)}
                </span>

            </div>


            <div class="booking-info-grid">

                <div>
                    <span>Event Date</span>

                    <strong>
                        ${escapeHtml(booking.event_date)}
                    </strong>
                </div>


                <div>
                    <span>Event Time</span>

                    <strong>
                        ${escapeHtml(booking.event_time)}
                    </strong>
                </div>


                <div>
                    <span>Quantity</span>

                    <strong>
                        ${booking.quantity} Ticket(s)
                    </strong>
                </div>


                <div>
                    <span>Total Amount</span>

                    <strong>
                        RM ${Number(
                            booking.total_amount
                        ).toFixed(2)}
                    </strong>
                </div>

            </div>


            <div class="booking-date">

                Booking Date:
                <strong>
                    ${escapeHtml(booking.booking_date)}
                </strong>

            </div>

        </div>

    `;
}


// =====================================================
// UPDATE STATISTICS
// =====================================================

function updateStats(bookings) {

    const total =
        bookings.length;


    const confirmed =
        bookings.filter(booking =>
            booking.booking_status === "confirmed"
        );


    const cancelled =
        bookings.filter(booking =>
            booking.booking_status === "cancelled"
        );


    const revenue =
        confirmed.reduce(
            (total, booking) =>
                total +
                Number(booking.total_amount),
            0
        );


    document.getElementById("totalBookings")
        .textContent = total;


    document.getElementById("confirmedBookings")
        .textContent = confirmed.length;


    document.getElementById("cancelledBookings")
        .textContent = cancelled.length;


    document.getElementById("totalRevenue")
        .textContent =
        `RM ${revenue.toFixed(2)}`;
}


// =====================================================
// MESSAGE
// =====================================================

function showMessage(message, type = "success") {

    const box =
        document.getElementById("messageBox");


    box.innerHTML = `
        <div class="message ${type}">
            ${escapeHtml(message)}
        </div>
    `;


    setTimeout(() => {

        box.innerHTML = "";

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
// SECURITY
// =====================================================

function escapeHtml(value) {

    if (value === null || value === undefined) {
        return "";
    }


    return String(value)

        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}