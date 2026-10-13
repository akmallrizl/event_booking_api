const loginForm = document.getElementById("loginForm");
const message = document.getElementById("message");


// ==========================================
// LOGIN FORM
// ==========================================
loginForm.addEventListener("submit", async function (event) {

    event.preventDefault();

    const email = document
        .getElementById("email")
        .value
        .trim();

    const password = document
        .getElementById("password")
        .value;


    // ======================================
    // BASIC VALIDATION
    // ======================================
    if (!email || !password) {

        message.textContent =
            "Please enter email and password.";

        message.className =
            "error-message";

        return;
    }


    // ======================================
    // LOGIN MESSAGE
    // ======================================
    message.textContent = "Logging in...";
    message.className = "";


    try {

        // ==================================
        // SEND LOGIN REQUEST
        // ==================================
        const response = await fetch(
            "http://localhost/event_booking_api/api/login.php",
            {
                method: "POST",

                headers: {
                    "Content-Type": "application/json"
                },

                body: JSON.stringify({
                    email: email,
                    password: password
                })
            }
        );


        const result = await response.json();


        // ==================================
        // LOGIN SUCCESS
        // ==================================
        if (response.ok && result.success) {

            const user = result.data;


            // Save logged-in user
            localStorage.setItem(
                "user",
                JSON.stringify(user)
            );


            message.textContent =
                "Login successful!";

            message.className =
                "success-message";


            // ==================================
            // REDIRECT BASED ON ROLE
            // ==================================
            setTimeout(function () {

                if (user.role === "admin") {

                    window.location.href =
                        "admin-dashboard.html";

                }

                else if (user.role === "organiser") {

                    window.location.href =
                        "organiser-dashboard.html";

                }

                else if (user.role === "customer") {

                    window.location.href =
                        "events.html";

                }

                else {

                    // Unknown role
                    localStorage.removeItem("user");

                    message.textContent =
                        "Invalid user role.";

                    message.className =
                        "error-message";
                }

            }, 800);

        }

        // ==================================
        // LOGIN FAILED
        // ==================================
        else {

            message.textContent =
                result.message ||
                "Invalid email or password.";

            message.className =
                "error-message";
        }


    }

    // ======================================
    // SERVER / CONNECTION ERROR
    // ======================================
    catch (error) {

        console.error(
            "Login Error:",
            error
        );

        message.textContent =
            "Cannot connect to the server.";

        message.className =
            "error-message";
    }

});