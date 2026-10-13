const API_BASE = "http://localhost/event_booking_api/api";

// ========================================
// LOGIN CHECK
// ========================================

const user = JSON.parse(localStorage.getItem("user"));

if (!user) {
    window.location.href = "login.html";
}

if (user && user.role !== "organiser") {
    alert("Access denied. Organiser only.");
    window.location.href = "login.html";
}

document.getElementById("organiserName").textContent = user.name;


// ========================================
// ELEMENTS
// ========================================

const eventsContainer = document.getElementById("eventsContainer");
const eventFormSection = document.getElementById("eventFormSection");
const eventForm = document.getElementById("eventForm");
const formTitle = document.getElementById("formTitle");
const messageBox = document.getElementById("messageBox");

const eventIdInput = document.getElementById("eventId");
const eventNameInput = document.getElementById("eventName");
const descriptionInput = document.getElementById("description");
const eventDateInput = document.getElementById("eventDate");
const eventTimeInput = document.getElementById("eventTime");
const ticketPriceInput = document.getElementById("ticketPrice");
const totalTicketsInput = document.getElementById("totalTickets");
const venueIdInput = document.getElementById("venueId");
const statusInput = document.getElementById("status");

let organiserEvents = [];


// ========================================
// LOGOUT
// ========================================

document.getElementById("logoutBtn").addEventListener("click", () => {

    if (confirm("Are you sure you want to logout?")) {
        localStorage.removeItem("user");
        window.location.href = "login.html";
    }

});


// ========================================
// MESSAGE
// ========================================

function showMessage(message, type = "success") {

    messageBox.innerHTML = `
        <div class="${type === "success" ? "success-message" : "error-message"}">
            ${message}
        </div>
    `;

    setTimeout(() => {
        messageBox.innerHTML = "";
    }, 3500);
}


// ========================================
// LOAD VENUES
// ========================================

async function loadVenues() {

    try {

        const response = await fetch(`${API_BASE}/venues.php`);
        const result = await response.json();

        if (!result.success || !Array.isArray(result.data)) {
            return;
        }

        venueIdInput.innerHTML = `
            <option value="">Select Venue</option>
        `;

        result.data.forEach(venue => {

            const option = document.createElement("option");

            option.value = venue.venue_id;

            option.textContent =
                `${venue.venue_name} - ${venue.location}`;

            venueIdInput.appendChild(option);

        });

    } catch (error) {

        console.error("Venue error:", error);

    }

}


// ========================================
// LOAD ORGANISER EVENTS
// ========================================

async function loadEvents() {

    try {

        eventsContainer.innerHTML = "<p>Loading events...</p>";

        const response = await fetch(
            `${API_BASE}/events.php?organiser_id=${user.user_id}`
        );

        const result = await response.json();

        if (!result.success || !Array.isArray(result.data)) {

            organiserEvents = [];

            eventsContainer.innerHTML = "<p>No events found.</p>";

            return;
        }

        organiserEvents = result.data;

        displayEvents();

    } catch (error) {

        console.error("Event error:", error);

        eventsContainer.innerHTML =
            "<p>Unable to load events.</p>";

    }

}


// ========================================
// DISPLAY EVENTS
// ========================================

function displayEvents() {

    if (organiserEvents.length === 0) {

        eventsContainer.innerHTML = `
            <p>No events created yet.</p>
        `;

        return;
    }

    eventsContainer.innerHTML = `
        <div class="organiser-events-grid">

            ${organiserEvents.map(event => `

                <div class="organiser-event-card">

                    <div class="event-card-top">

                        <span class="status-badge status-${event.status}">
                            ${event.status.toUpperCase()}
                        </span>

                        <span class="event-number">
                            Event #${event.event_id}
                        </span>

                    </div>

                    <h3>${event.event_name}</h3>

                    <p class="event-description">
                        ${event.description || "No description"}
                    </p>

                    <div class="event-info-grid">

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
                            <strong>${event.venue_name || "-"}</strong>
                        </div>

                        <div>
                            <span>Location</span>
                            <strong>${event.location || "-"}</strong>
                        </div>

                        <div>
                            <span>Ticket Price</span>
                            <strong>
                                RM ${Number(event.ticket_price).toFixed(2)}
                            </strong>
                        </div>

                        <div>
                            <span>Available Tickets</span>
                            <strong>${event.available_tickets}</strong>
                        </div>

                    </div>

                    <button
                        class="primary-btn event-edit-btn"
                        onclick="editEvent(${event.event_id})"
                    >
                        Edit Event
                    </button>

                </div>

            `).join("")}

        </div>
    `;

}


// ========================================
// ADD EVENT BUTTON
// ========================================

document.getElementById("addEventBtn").addEventListener("click", () => {

    resetForm();

    formTitle.textContent = "Add New Event";

    eventFormSection.style.display = "block";

    eventFormSection.scrollIntoView({
        behavior: "smooth"
    });

});


// ========================================
// CANCEL FORM
// ========================================

document.getElementById("cancelFormBtn").addEventListener("click", () => {

    resetForm();

    eventFormSection.style.display = "none";

});


// ========================================
// RESET FORM
// ========================================

function resetForm() {

    eventForm.reset();

    eventIdInput.value = "";

    statusInput.value = "upcoming";

}


// ========================================
// EDIT EVENT
// ========================================

function editEvent(eventId) {

    const event = organiserEvents.find(
        item => Number(item.event_id) === Number(eventId)
    );

    if (!event) {
        return;
    }

    eventIdInput.value = event.event_id;

    eventNameInput.value = event.event_name;

    descriptionInput.value = event.description || "";

    eventDateInput.value = event.event_date;

    eventTimeInput.value = event.event_time.substring(0, 5);

    ticketPriceInput.value = event.ticket_price;

    totalTicketsInput.value = event.total_tickets;

    venueIdInput.value = event.venue_id;

    statusInput.value = event.status;

    formTitle.textContent = "Edit Event";

    eventFormSection.style.display = "block";

    eventFormSection.scrollIntoView({
        behavior: "smooth"
    });

}


// ========================================
// SAVE EVENT
// ========================================

eventForm.addEventListener("submit", async event => {

    event.preventDefault();

    const eventId = eventIdInput.value;

    const totalTickets = Number(totalTicketsInput.value);

    // Basic validation
    if (totalTickets <= 0) {

        showMessage(
            "Total tickets must be greater than 0.",
            "error"
        );

        return;
    }

    const eventData = {

        event_name: eventNameInput.value.trim(),

        description: descriptionInput.value.trim(),

        event_date: eventDateInput.value,

        event_time: eventTimeInput.value,

        ticket_price: Number(ticketPriceInput.value),

        total_tickets: totalTickets,

        organiser_id: Number(user.user_id),

        venue_id: Number(venueIdInput.value),

        status: statusInput.value

    };


    // ========================================
    // AVAILABLE TICKETS CALCULATION
    // ========================================

    if (eventId) {

        // Editing existing event
        const currentEvent = organiserEvents.find(
            item => Number(item.event_id) === Number(eventId)
        );

        if (!currentEvent) {

            showMessage(
                "Event not found.",
                "error"
            );

            return;
        }

        const oldTotal =
            Number(currentEvent.total_tickets);

        const oldAvailable =
            Number(currentEvent.available_tickets);

        // Calculate how many tickets already booked
        const bookedTickets =
            oldTotal - oldAvailable;

        // New available ticket amount
        const newAvailable =
            totalTickets - bookedTickets;

        if (newAvailable < 0) {

            showMessage(
                `Total tickets cannot be lower than ${bookedTickets} because ${bookedTickets} ticket(s) are already booked.`,
                "error"
            );

            return;
        }

        eventData.available_tickets =
            newAvailable;

    } else {

        // Creating new event
        eventData.available_tickets =
            totalTickets;

    }


    // ========================================
    // SEND TO API
    // ========================================

    try {

        let url = `${API_BASE}/events.php`;

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
                result.message || "Unable to save event.",
                "error"
            );

            return;
        }


        // ========================================
        // SUCCESS
        // ========================================

        if (eventId) {

            showMessage(
                "Event updated successfully."
            );

        } else {

            showMessage(
                "Event created successfully."
            );

        }

        resetForm();

        eventFormSection.style.display =
            "none";

        await loadEvents();

    } catch (error) {

        console.error(
            "Save event error:",
            error
        );

        showMessage(
            "Unable to connect to the server.",
            "error"
        );

    }

});


// ========================================
// START PAGE
// ========================================

async function startPage() {

    await loadVenues();

    await loadEvents();

}

startPage();