// =====================================================
// EVENT BOOKING SYSTEM - PAGE AUTHENTICATION
// =====================================================

// Get logged-in user
function getLoggedInUser() {

    const userData = localStorage.getItem("user");

    if (!userData) {
        return null;
    }

    try {
        return JSON.parse(userData);
    } catch (error) {

        localStorage.removeItem("user");
        return null;
    }
}


// =====================================================
// REQUIRE LOGIN
// =====================================================

function requireLogin() {

    const user = getLoggedInUser();

    if (!user) {

        alert("Please login first.");

        window.location.href = "login.html";

        return null;
    }

    return user;
}


// =====================================================
// REQUIRE SPECIFIC ROLE
// =====================================================

function requireRole(requiredRole) {

    const user = requireLogin();

    if (!user) {
        return null;
    }


    if (user.role !== requiredRole) {

        alert("Access denied. You are not allowed to access this page.");


        // Redirect user to their correct page
        redirectToDashboard(user.role);

        return null;
    }


    return user;
}


// =====================================================
// REDIRECT BASED ON ROLE
// =====================================================

function redirectToDashboard(role) {

    if (role === "admin") {

        window.location.href =
            "admin-dashboard.html";

    }

    else if (role === "organiser") {

        window.location.href =
            "organiser-dashboard.html";

    }

    else if (role === "customer") {

        window.location.href =
            "events.html";

    }

    else {

        localStorage.removeItem("user");

        window.location.href =
            "login.html";
    }
}


// =====================================================
// LOGOUT
// =====================================================

function logoutUser() {

    localStorage.removeItem("user");

    window.location.href =
        "login.html";
}