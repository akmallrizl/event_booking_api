// =====================================================
// GET LOGGED-IN USER
// =====================================================

const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
}


// =====================================================
// ELEMENTS
// =====================================================

const userInfo = document.getElementById("userInfo");
const loading = document.getElementById("bookingLoading");
const message = document.getElementById("myBookingMessage");
const container = document.getElementById("myBookingsContainer");


// =====================================================
// DISPLAY USER
// =====================================================

if (userInfo) {
    userInfo.innerHTML = `
        <strong>${user.name}</strong>
        <span>${user.role}</span>
    `;
}


// =====================================================
// LOAD CUSTOMER BOOKINGS
// =====================================================

async function loadBookings() {

    loading.style.display = "block";
    message.textContent = "";
    message.className = "";

    try {

        const response = await fetch(
            `http://localhost/event_booking_api/api/bookings.php?customer_id=${user.user_id}`
        );

        const result = await response.json();

        console.log("My Bookings API:", result);

        loading.style.display = "none";

        if (!response.ok || !result.success) {

            message.textContent =
                result.message || "Failed to load bookings.";

            message.className = "error-message";

            return;
        }

        const bookings = result.data || [];

        // =============================================
        // NO BOOKINGS
        // =============================================

        if (bookings.length === 0) {

            container.innerHTML = `
                <div class="empty-bookings">

                    <h2>No Bookings Yet</h2>

                    <p>
                        You have not booked any events yet.
                    </p>

                    <a
                        href="events.html"
                        class="browse-events-btn"
                    >
                        Browse Events
                    </a>

                </div>
            `;

            return;
        }

        container.innerHTML = "";

        // =============================================
        // DISPLAY BOOKINGS
        // =============================================

        bookings.forEach(function (booking) {

            const card = document.createElement("div");

            card.className = "my-booking-card";

            const amount = Number(
                booking.total_amount || 0
            ).toFixed(2);

            const status =
                booking.booking_status || "pending";

            // Only confirmed/pending bookings can cancel
            let cancelButton = "";

            if (status.toLowerCase() !== "cancelled") {

                cancelButton = `
                    <button
                        class="cancel-booking-btn"
                        onclick="cancelBooking(
                            ${booking.booking_id},
                            ${booking.quantity}
                        )"
                    >
                        Cancel Booking
                    </button>
                `;
            }

            card.innerHTML = `

                <div class="booking-card-top">

                    <span
                        class="booking-status ${status.toLowerCase()}"
                    >
                        ${status}
                    </span>

                    <span class="booking-id">
                        Booking #${booking.booking_id}
                    </span>

                </div>


                <h2>
                    ${booking.event_name}
                </h2>


                <div class="booking-info-grid">

                    <div>
                        <span>Date</span>

                        <strong>
                            ${booking.event_date}
                        </strong>
                    </div>


                    <div>
                        <span>Time</span>

                        <strong>
                            ${booking.event_time}
                        </strong>
                    </div>


                    <div>
                        <span>Tickets</span>

                        <strong>
                            ${booking.quantity}
                        </strong>
                    </div>


                    <div>
                        <span>Total Amount</span>

                        <strong>
                            RM ${amount}
                        </strong>
                    </div>

                </div>


                <div class="booking-date">
                    Booked on:
                    ${booking.booking_date || "-"}
                </div>


                ${cancelButton}

            `;

            container.appendChild(card);

        });

    } catch (error) {

        console.error("Error loading bookings:", error);

        loading.style.display = "none";

        message.textContent =
            "Cannot connect to the server.";

        message.className =
            "error-message";
    }
}


// =====================================================
// CANCEL BOOKING
// =====================================================

async function cancelBooking(bookingId, quantity) {

    const confirmCancel = confirm(
        "Are you sure you want to cancel this booking?"
    );

    if (!confirmCancel) {
        return;
    }

    try {

        const response = await fetch(
            `http://localhost/event_booking_api/api/bookings.php?id=${bookingId}`,
            {
                method: "PUT",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({
                    quantity: Number(quantity),
                    booking_status: "cancelled"
                })
            }
        );

        const result = await response.json();

        console.log("Cancel Booking:", result);

        if (!response.ok || !result.success) {

            message.textContent =
                result.message ||
                "Failed to cancel booking.";

            message.className =
                "error-message";

            return;
        }


        // SUCCESS MESSAGE

        message.textContent =
            "Booking cancelled successfully.";

        message.className =
            "success-message";


        // Reload bookings
        await loadBookings();


    } catch (error) {

        console.error(
            "Cancel booking error:",
            error
        );

        message.textContent =
            "Cannot connect to the server.";

        message.className =
            "error-message";
    }
}


// =====================================================
// LOGOUT
// =====================================================

function logout() {

    localStorage.removeItem("user");

    localStorage.removeItem(
        "selected_event_id"
    );

    window.location.href =
        "login.html";
}


// =====================================================
// START
// =====================================================

loadBookings();