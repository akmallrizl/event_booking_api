const API_URL = "http://localhost/event_booking_api/api/bookings.php";

const bookingsContainer = document.getElementById("bookingsContainer");
const messageBox = document.getElementById("messageBox");
const statusFilter = document.getElementById("statusFilter");
const refreshBtn = document.getElementById("refreshBtn");
const logoutBtn = document.getElementById("logoutBtn");

let allBookings = [];


/* =========================
   LOAD BOOKINGS
========================= */

async function loadBookings() {
    bookingsContainer.innerHTML = "<p>Loading bookings...</p>";

    try {
        const response = await fetch(API_URL);
        const result = await response.json();

        if (!response.ok || result.success === false) {
            throw new Error(result.message || "Failed to load bookings.");
        }

        if (Array.isArray(result)) {
            allBookings = result;
        } else if (Array.isArray(result.data)) {
            allBookings = result.data;
        } else {
            allBookings = [];
        }

        displayBookings();

    } catch (error) {
        console.error(error);

        bookingsContainer.innerHTML = `
            <div class="empty-message">
                Failed to load bookings.
            </div>
        `;
    }
}


/* =========================
   DISPLAY BOOKINGS
========================= */

function displayBookings() {

    const selectedStatus = statusFilter.value.toLowerCase();

    let filteredBookings = allBookings;

    if (selectedStatus !== "") {
        filteredBookings = allBookings.filter(booking =>
            String(booking.booking_status || "")
                .toLowerCase() === selectedStatus
        );
    }

    if (filteredBookings.length === 0) {
        bookingsContainer.innerHTML = `
            <div class="empty-message">
                No bookings found.
            </div>
        `;
        return;
    }

    bookingsContainer.innerHTML = filteredBookings.map(booking => {

        const bookingId =
            booking.booking_id ?? "-";

        const customerName =
            booking.customer_name ??
            booking.name ??
            `Customer #${booking.customer_id ?? "-"}`;

        const customerEmail =
            booking.customer_email ??
            booking.email ??
            "-";

        const eventName =
            booking.event_name ??
            `Event #${booking.event_id ?? "-"}`;

        const quantity =
            booking.quantity ?? 0;

        const ticketPrice =
            Number(booking.ticket_price ?? 0);

        const totalAmount =
            Number(
                booking.total_amount ??
                (ticketPrice * Number(quantity))
            );

        const status =
            booking.booking_status ?? "unknown";

        const createdAt =
            booking.created_at ??
            booking.booking_date ??
            "-";

        return `
            <div class="booking-card">

                <div class="booking-card-top">

                    <span class="status-badge ${getStatusClass(status)}">
                        ${escapeHTML(status.toUpperCase())}
                    </span>

                    <span class="booking-id">
                        Booking #${escapeHTML(String(bookingId))}
                    </span>

                </div>


                <h3>${escapeHTML(eventName)}</h3>

                <p class="booking-customer">
                    Booked by <strong>${escapeHTML(customerName)}</strong>
                </p>


                <div class="booking-details">

                    <div class="booking-detail">
                        <span>Customer Email</span>
                        <strong>${escapeHTML(customerEmail)}</strong>
                    </div>

                    <div class="booking-detail">
                        <span>Customer ID</span>
                        <strong>${escapeHTML(String(booking.customer_id ?? "-"))}</strong>
                    </div>

                    <div class="booking-detail">
                        <span>Event ID</span>
                        <strong>${escapeHTML(String(booking.event_id ?? "-"))}</strong>
                    </div>

                    <div class="booking-detail">
                        <span>Quantity</span>
                        <strong>${escapeHTML(String(quantity))}</strong>
                    </div>

                    <div class="booking-detail">
                        <span>Ticket Price</span>
                        <strong>RM ${ticketPrice.toFixed(2)}</strong>
                    </div>

                    <div class="booking-detail">
                        <span>Total Amount</span>
                        <strong>RM ${totalAmount.toFixed(2)}</strong>
                    </div>

                    <div class="booking-detail booking-detail-full">
                        <span>Booking Date</span>
                        <strong>${escapeHTML(String(createdAt))}</strong>
                    </div>

                </div>


                <div class="booking-actions">

                    ${
                        status.toLowerCase() !== "cancelled"
                        ? `
                        <button
                            class="cancel-booking-btn"
                            onclick="cancelBooking(${Number(bookingId)}, ${Number(quantity)})"
                        >
                            Cancel Booking
                        </button>
                        `
                        : `
                        <button
                            class="cancel-booking-btn"
                            disabled
                        >
                            Cancelled
                        </button>
                        `
                    }

                </div>

            </div>
        `;

    }).join("");
}


/* =========================
   CANCEL BOOKING
========================= */

async function cancelBooking(bookingId, quantity) {

    if (!Number.isInteger(bookingId) || bookingId <= 0) {
        showMessage("Invalid booking ID.", "error");
        return;
    }

    const confirmCancel = confirm(
        "Are you sure you want to cancel this booking?"
    );

    if (!confirmCancel) {
        return;
    }

    try {

        const response = await fetch(
            `${API_URL}?id=${bookingId}`,
            {
                method: "PUT",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({
                    quantity: quantity,
                    booking_status: "cancelled"
                })
            }
        );

        const result = await response.json();

        if (!response.ok || result.success === false) {
            throw new Error(
                result.message || "Failed to cancel booking."
            );
        }

        showMessage(
            "Booking cancelled successfully.",
            "success"
        );

        await loadBookings();

    } catch (error) {

        console.error(error);

        showMessage(
            error.message || "Failed to cancel booking.",
            "error"
        );
    }
}


/* =========================
   STATUS CLASS
========================= */

function getStatusClass(status) {

    status = String(status).toLowerCase();

    if (status === "confirmed") {
        return "status-confirmed";
    }

    if (status === "pending") {
        return "status-pending";
    }

    if (status === "cancelled") {
        return "status-cancelled";
    }

    return "status-default";
}


/* =========================
   MESSAGE
========================= */

function showMessage(message, type) {

    messageBox.innerHTML = `
        <div class="admin-message ${type}">
            ${escapeHTML(message)}
        </div>
    `;

    window.scrollTo({
        top: 0,
        behavior: "smooth"
    });

    setTimeout(() => {
        messageBox.innerHTML = "";
    }, 4000);
}


/* =========================
   ESCAPE HTML
========================= */

function escapeHTML(value) {

    const div = document.createElement("div");

    div.textContent = value ?? "";

    return div.innerHTML;
}


/* =========================
   FILTER
========================= */

statusFilter.addEventListener("change", () => {
    displayBookings();
});


/* =========================
   REFRESH
========================= */

refreshBtn.addEventListener("click", () => {
    loadBookings();
});


/* =========================
   LOGOUT
========================= */

logoutBtn.addEventListener("click", () => {

    const confirmLogout = confirm(
        "Are you sure you want to logout?"
    );

    if (!confirmLogout) {
        return;
    }

    localStorage.removeItem("user");
    localStorage.removeItem("loggedInUser");

    window.location.href = "login.html";
});


/* =========================
   START PAGE
========================= */

document.addEventListener("DOMContentLoaded", () => {
    loadBookings();
});