// =====================================================
// GET LOGGED-IN USER
// =====================================================

const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
}


// =====================================================
// GET SELECTED EVENT ID
// =====================================================

const eventId = localStorage.getItem("selected_event_id");

if (!eventId) {
    alert("No event selected.");
    window.location.href = "events.html";
}


// =====================================================
// ELEMENTS
// =====================================================

const loadingEvent = document.getElementById("loadingEvent");
const eventDetails = document.getElementById("eventDetails");
const bookingForm = document.getElementById("bookingForm");

const quantityInput = document.getElementById("quantity");
const ticketPriceText = document.getElementById("ticketPrice");
const summaryQuantity = document.getElementById("summaryQuantity");
const totalPrice = document.getElementById("totalPrice");
const bookingMessage = document.getElementById("bookingMessage");

let selectedEvent = null;


// =====================================================
// LOAD EVENT DETAILS
// =====================================================

async function loadEvent() {

    try {

        const response = await fetch(
            `http://localhost/event_booking_api/api/events.php?id=${eventId}`
        );

        const result = await response.json();

        loadingEvent.style.display = "none";

        if (!response.ok || !result.success) {

            bookingMessage.textContent =
                result.message || "Failed to load event.";

            bookingMessage.className = "error-message";

            return;
        }


        // Support API returning object OR array
        if (Array.isArray(result.data)) {
            selectedEvent = result.data[0];
        } else {
            selectedEvent = result.data;
        }


        if (!selectedEvent) {

            bookingMessage.textContent =
                "Event not found.";

            bookingMessage.className = "error-message";

            return;
        }


        // =================================================
        // DISPLAY EVENT
        // =================================================

        eventDetails.innerHTML = `

            <div class="selected-event">

                <div class="event-status">
                    ${selectedEvent.status}
                </div>

                <h2>
                    ${selectedEvent.event_name}
                </h2>

                <p>
                    ${selectedEvent.description || ""}
                </p>

                <div class="selected-event-info">

                    <p>
                        <strong>Date:</strong>
                        ${selectedEvent.event_date}
                    </p>

                    <p>
                        <strong>Time:</strong>
                        ${selectedEvent.event_time}
                    </p>

                    <p>
                        <strong>Venue:</strong>
                        ${selectedEvent.venue_name || "-"}
                    </p>

                    <p>
                        <strong>Location:</strong>
                        ${selectedEvent.location || "-"}
                    </p>

                    <p>
                        <strong>Available Tickets:</strong>
                        ${selectedEvent.available_tickets}
                    </p>

                </div>

            </div>
        `;


        // =================================================
        // QUANTITY SETTINGS
        // =================================================

        quantityInput.max =
            selectedEvent.available_tickets;

        quantityInput.value = 1;


        // =================================================
        // SHOW PRICE
        // =================================================

        const price =
            Number(selectedEvent.ticket_price);

        ticketPriceText.textContent =
            `RM ${price.toFixed(2)}`;

        summaryQuantity.textContent = "1";

        totalPrice.textContent =
            `RM ${price.toFixed(2)}`;


        bookingForm.style.display = "block";


    } catch (error) {

        console.error(error);

        loadingEvent.style.display = "none";

        bookingMessage.textContent =
            "Cannot connect to the server.";

        bookingMessage.className =
            "error-message";
    }
}


// =====================================================
// UPDATE TOTAL PRICE
// =====================================================

quantityInput.addEventListener(
    "input",
    function () {

        if (!selectedEvent) {
            return;
        }

        let quantity =
            parseInt(quantityInput.value);

        if (
            isNaN(quantity) ||
            quantity < 1
        ) {
            quantity = 1;
        }


        summaryQuantity.textContent =
            quantity;


        const price =
            Number(selectedEvent.ticket_price);

        const total =
            price * quantity;


        totalPrice.textContent =
            `RM ${total.toFixed(2)}`;
    }
);


// =====================================================
// SUBMIT BOOKING
// =====================================================

bookingForm.addEventListener(
    "submit",
    async function (event) {

        event.preventDefault();


        if (!selectedEvent) {
            return;
        }


        const quantity =
            parseInt(quantityInput.value);


        // =================================================
        // VALIDATE QUANTITY
        // =================================================

        if (
            isNaN(quantity) ||
            quantity < 1
        ) {

            bookingMessage.textContent =
                "Quantity must be at least 1.";

            bookingMessage.className =
                "error-message";

            return;
        }


        if (
            quantity >
            Number(selectedEvent.available_tickets)
        ) {

            bookingMessage.textContent =
                "Not enough tickets available.";

            bookingMessage.className =
                "error-message";

            return;
        }


        bookingMessage.textContent =
            "Processing booking...";

        bookingMessage.className = "";


        try {

            const response = await fetch(
                "http://localhost/event_booking_api/api/bookings.php",
                {
                    method: "POST",

                    headers: {
                        "Content-Type":
                            "application/json"
                    },

                    body: JSON.stringify({

                        customer_id:
                            Number(user.user_id),

                        event_id:
                            Number(eventId),

                        quantity:
                            quantity
                    })
                }
            );


            const result =
                await response.json();


            console.log(
                "Booking API:",
                result
            );


            if (
                response.ok &&
                result.success
            ) {

                bookingMessage.textContent =
                    "Booking successful!";

                bookingMessage.className =
                    "success-message";


                // Prevent duplicate click
                const button =
                    bookingForm.querySelector(
                        "button[type='submit']"
                    );

                button.disabled = true;

                button.textContent =
                    "Booking Successful";


                // Go to My Bookings
                setTimeout(function () {

                    window.location.href =
                        "my-bookings.html";

                }, 1200);


            } else {

                bookingMessage.textContent =
                    result.message ||
                    "Booking failed.";

                bookingMessage.className =
                    "error-message";
            }


        } catch (error) {

            console.error(error);

            bookingMessage.textContent =
                "Cannot connect to the server.";

            bookingMessage.className =
                "error-message";
        }
    }
);


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

loadEvent();