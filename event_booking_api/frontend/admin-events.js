// =====================================================
// ADMIN EVENTS
// =====================================================

const API_URL = "http://localhost/event_booking_api/api/events.php";

// =====================================================
// GET LOGGED-IN USER
// =====================================================

const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
} else if (user.role !== "admin") {
    window.location.href = "events.html";
}

// =====================================================
// ELEMENTS
// =====================================================

const userInfo = document.getElementById("userInfo");
const eventsContainer = document.getElementById("eventsContainer");
const loading = document.getElementById("loading");
const message = document.getElementById("message");

const addEventBtn = document.getElementById("addEventBtn");
const eventFormContainer = document.getElementById("eventFormContainer");
const eventForm = document.getElementById("eventForm");

const closeFormBtn = document.getElementById("closeFormBtn");
const cancelFormBtn = document.getElementById("cancelFormBtn");

const formTitle = document.getElementById("formTitle");

// Store current available tickets when editing
let currentAvailableTickets = null;

// Store original total tickets when editing
let originalTotalTickets = null;


// =====================================================
// DISPLAY ADMIN INFO
// =====================================================

if (userInfo && user) {
    userInfo.innerHTML = `
        <strong>${user.name}</strong>
        <span>${capitalize(user.role)}</span>
    `;
}


// =====================================================
// LOAD EVENTS
// =====================================================

async function loadEvents() {

    loading.style.display = "block";
    eventsContainer.innerHTML = "";

    try {

        const response = await fetch(API_URL);
        const result = await response.json();

        loading.style.display = "none";

        if (!response.ok || !result.success) {

            showMessage(
                result.message || "Failed to load events.",
                "error"
            );

            return;
        }

        const events = result.data || [];

        if (events.length === 0) {

            eventsContainer.innerHTML = `
                <div class="empty-state">
                    No events found.
                </div>
            `;

            return;
        }

        events.forEach(event => {

            const card = document.createElement("div");
            card.className = "admin-event-card";

            card.innerHTML = `
                <div class="admin-event-top">

                    <span class="status-badge ${event.status}">
                        ${event.status.toUpperCase()}
                    </span>

                    <span class="event-id">
                        Event #${event.event_id}
                    </span>

                </div>

                <h3>${event.event_name}</h3>

                <p class="event-description">
                    ${event.description || "No description"}
                </p>

                <div class="admin-event-details">

                    <div>
                        <span>Date</span>
                        <strong>${event.event_date}</strong>
                    </div>

                    <div>
                        <span>Time</span>
                        <strong>${event.event_time}</strong>
                    </div>

                    <div>
                        <span>Venue</span>
                        <strong>${event.venue_name || "N/A"}</strong>
                    </div>

                    <div>
                        <span>Location</span>
                        <strong>${event.location || "N/A"}</strong>
                    </div>

                    <div>
                        <span>Ticket Price</span>
                        <strong>
                            RM ${Number(event.ticket_price).toFixed(2)}
                        </strong>
                    </div>

                    <div>
                        <span>Available Tickets</span>
                        <strong>
                            ${event.available_tickets}
                            / ${event.total_tickets}
                        </strong>
                    </div>

                    <div>
                        <span>Organiser</span>
                        <strong>
                            ${event.organiser_name || "N/A"}
                        </strong>
                    </div>

                </div>

                <div class="admin-event-actions">

                    <button
                        class="edit-btn"
                        onclick="editEvent(${event.event_id})"
                    >
                        Edit
                    </button>

                    <button
                        class="delete-btn"
                        onclick="deleteEvent(${event.event_id})"
                    >
                        Delete
                    </button>

                </div>
            `;

            eventsContainer.appendChild(card);
        });

    } catch (error) {

        loading.style.display = "none";

        console.error(error);

        showMessage(
            "Unable to connect to the server.",
            "error"
        );
    }
}


// =====================================================
// OPEN ADD EVENT FORM
// =====================================================

addEventBtn.addEventListener("click", () => {

    eventForm.reset();

    document.getElementById("eventId").value = "";

    currentAvailableTickets = null;
    originalTotalTickets = null;

    formTitle.textContent = "Add New Event";

    eventFormContainer.style.display = "block";

    eventFormContainer.scrollIntoView({
        behavior: "smooth"
    });
});


// =====================================================
// CLOSE FORM
// =====================================================

function closeForm() {

    eventForm.reset();

    document.getElementById("eventId").value = "";

    currentAvailableTickets = null;
    originalTotalTickets = null;

    eventFormContainer.style.display = "none";
}

closeFormBtn.addEventListener("click", closeForm);
cancelFormBtn.addEventListener("click", closeForm);


// =====================================================
// SAVE EVENT
// =====================================================

eventForm.addEventListener("submit", async function (e) {

    e.preventDefault();

    const eventId =
        document.getElementById("eventId").value;

    const newTotalTickets =
        Number(document.getElementById("totalTickets").value);

    let availableTickets = newTotalTickets;

    // If editing, preserve sold-ticket count.
    // Example:
    // Original = 150 total, 140 available -> 10 sold
    // New total = 200 -> available becomes 190
    if (
        eventId &&
        currentAvailableTickets !== null &&
        originalTotalTickets !== null
    ) {

        const soldTickets =
            originalTotalTickets - currentAvailableTickets;

        availableTickets =
            newTotalTickets - soldTickets;

        if (availableTickets < 0) {

            showMessage(
                `Total tickets cannot be lower than tickets already sold (${soldTickets}).`,
                "error"
            );

            return;
        }
    }

    const eventData = {

        event_name:
            document.getElementById("eventName").value.trim(),

        description:
            document.getElementById("description").value.trim(),

        event_date:
            document.getElementById("eventDate").value,

        event_time:
            document.getElementById("eventTime").value,

        ticket_price:
            Number(document.getElementById("ticketPrice").value),

        total_tickets:
            newTotalTickets,

        available_tickets:
            availableTickets,

        venue_id:
            Number(document.getElementById("venueId").value),

        organiser_id:
            Number(document.getElementById("organiserId").value),

        status:
            document.getElementById("status").value
    };

    try {

        let url = API_URL;
        let method = "POST";

        if (eventId) {
            url += `?id=${eventId}`;
            method = "PUT";
        }

        const response = await fetch(url, {

            method: method,

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify(eventData)
        });

        const result = await response.json();

        if (!response.ok || !result.success) {

            showMessage(
                result.message || "Failed to save event.",
                "error"
            );

            return;
        }

        showMessage(
            eventId
                ? "Event updated successfully."
                : "Event created successfully.",
            "success"
        );

        closeForm();

        loadEvents();

    } catch (error) {

        console.error(error);

        showMessage(
            "Unable to connect to the server.",
            "error"
        );
    }
});


// =====================================================
// EDIT EVENT
// =====================================================

async function editEvent(id) {

    try {

        const response =
            await fetch(`${API_URL}?id=${id}`);

        const result = await response.json();

        if (!response.ok || !result.success) {

            showMessage(
                result.message || "Event not found.",
                "error"
            );

            return;
        }

        const event = result.data;

        // Save ticket information before editing
        currentAvailableTickets =
            Number(event.available_tickets);

        originalTotalTickets =
            Number(event.total_tickets);

        document.getElementById("eventId").value =
            event.event_id;

        document.getElementById("eventName").value =
            event.event_name;

        document.getElementById("description").value =
            event.description || "";

        document.getElementById("eventDate").value =
            event.event_date;

        document.getElementById("eventTime").value =
            event.event_time.substring(0, 5);

        document.getElementById("ticketPrice").value =
            event.ticket_price;

        document.getElementById("totalTickets").value =
            event.total_tickets;

        document.getElementById("venueId").value =
            event.venue_id;

        document.getElementById("organiserId").value =
            event.organiser_id;

        document.getElementById("status").value =
            event.status;

        formTitle.textContent = "Edit Event";

        eventFormContainer.style.display = "block";

        eventFormContainer.scrollIntoView({
            behavior: "smooth"
        });

    } catch (error) {

        console.error(error);

        showMessage(
            "Unable to load event information.",
            "error"
        );
    }
}


// =====================================================
// DELETE EVENT
// =====================================================

async function deleteEvent(id) {

    const confirmed = confirm(
        "Are you sure you want to delete this event?"
    );

    if (!confirmed) {
        return;
    }

    try {

        const response = await fetch(
            `${API_URL}?id=${id}`,
            {
                method: "DELETE"
            }
        );

        const result = await response.json();

        if (!response.ok || !result.success) {

            showMessage(
                result.message || "Failed to delete event.",
                "error"
            );

            return;
        }

        showMessage(
            "Event deleted successfully.",
            "success"
        );

        loadEvents();

    } catch (error) {

        console.error(error);

        showMessage(
            "Unable to connect to the server.",
            "error"
        );
    }
}


// =====================================================
// MESSAGE
// =====================================================

function showMessage(text, type) {

    message.innerHTML = `
        <div class="message ${type}">
            ${text}
        </div>
    `;

    setTimeout(() => {
        message.innerHTML = "";
    }, 4000);
}


// =====================================================
// CAPITALIZE
// =====================================================

function capitalize(text) {

    if (!text) return "";

    return (
        text.charAt(0).toUpperCase() +
        text.slice(1)
    );
}


// =====================================================
// LOGOUT
// =====================================================

document
    .getElementById("logoutBtn")
    .addEventListener("click", () => {

        localStorage.removeItem("user");

        window.location.href = "login.html";
    });


// =====================================================
// START
// =====================================================

loadEvents();