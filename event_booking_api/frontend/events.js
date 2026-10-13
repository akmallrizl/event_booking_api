// =====================================================
// GET LOGGED-IN USER
// =====================================================

const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
}


// =====================================================
// DISPLAY USER
// =====================================================

const userInfo = document.getElementById("userInfo");

if (userInfo) {
    userInfo.innerHTML = `
        <strong>${user.name}</strong>
        <span>${user.role}</span>
    `;
}


// =====================================================
// LOAD EVENTS
// =====================================================

async function loadEvents() {

    const container =
        document.getElementById("eventsContainer");

    const loading =
        document.getElementById("loading");

    const message =
        document.getElementById("eventMessage");

    try {

        const response = await fetch(
            "http://localhost/event_booking_api/api/events.php?status=upcoming"
        );

        const result = await response.json();

        console.log("Events API:", result);

        loading.style.display = "none";


        if (!response.ok || !result.success) {

            message.textContent =
                result.message || "Failed to load events.";

            return;
        }


        const events = result.data || [];


        if (events.length === 0) {

            container.innerHTML = `
                <div class="no-events">
                    No upcoming events available.
                </div>
            `;

            return;
        }


        container.innerHTML = "";


        events.forEach(function (event) {

            const card =
                document.createElement("div");

            card.className = "event-card";


            const price =
                Number(event.ticket_price || 0)
                    .toFixed(2);


            card.innerHTML = `

                <div class="event-status">
                    ${event.status || "upcoming"}
                </div>

                <h2>
                    ${event.event_name}
                </h2>

                <p class="event-description">
                    ${event.description || "No description available."}
                </p>

                <div class="event-details">

                    <p>
                        <strong>Date:</strong>
                        ${event.event_date}
                    </p>

                    <p>
                        <strong>Time:</strong>
                        ${event.event_time}
                    </p>

                    <p>
                        <strong>Venue:</strong>
                        ${event.venue_name || "-"}
                    </p>

                    <p>
                        <strong>Location:</strong>
                        ${event.location || "-"}
                    </p>

                    <p>
                        <strong>Available Tickets:</strong>
                        ${event.available_tickets}
                    </p>

                </div>

                <div class="event-footer">

                    <div class="event-price">
                        RM ${price}
                    </div>

                    <button
                        class="book-btn"
                        onclick="bookEvent(${event.event_id})"
                    >
                        Book Event
                    </button>

                </div>
            `;


            container.appendChild(card);
        });


    } catch (error) {

        console.error(
            "Error loading events:",
            error
        );

        if (loading) {
            loading.style.display = "none";
        }

        if (message) {
            message.textContent =
                "Cannot load events. Please check the API connection.";
        }
    }
}


// =====================================================
// BOOK EVENT
// =====================================================

function bookEvent(eventId) {

    localStorage.setItem(
        "selected_event_id",
        eventId
    );

    window.location.href =
        "book-event.html";
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
// START PAGE
// =====================================================

document.addEventListener(
    "DOMContentLoaded",
    function () {

        loadEvents();

    }
);